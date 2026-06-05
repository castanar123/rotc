# Completely Detached Cloudflare Tunnel Starter
# This script starts Cloudflare tunnel as a truly detached background process
# No visible windows, survives terminal closure, runs independently

param(
    [switch]$Stop,
    [switch]$Status,
    [switch]$Restart
)

# Configuration
$WorkingDirectory = "c:\xampp\htdocs\generate qr"
$CloudflaredPath = "$WorkingDirectory\cloudflare\cloudflared.exe"
$ConfigPath = "$WorkingDirectory\cloudflare-tunnel.yml"
$LogPath = "$WorkingDirectory\logs\cloudflare-detached.log"
$PidFile = "$WorkingDirectory\cloudflare-tunnel.pid"

# Ensure logs directory exists
if (-not (Test-Path "$WorkingDirectory\logs")) {
    New-Item -ItemType Directory -Path "$WorkingDirectory\logs" -Force | Out-Null
}

function Write-Log {
    param([string]$Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage
    try {
        Add-Content -Path $LogPath -Value $logMessage -ErrorAction SilentlyContinue
    } catch {
        # Ignore log write errors
    }
}

function Test-Prerequisites {
    if (-not (Test-Path $CloudflaredPath)) {
        Write-Log "ERROR: cloudflared.exe not found at $CloudflaredPath"
        return $false
    }
    
    if (-not (Test-Path $ConfigPath)) {
        Write-Log "ERROR: cloudflare-tunnel.yml not found at $ConfigPath"
        return $false
    }
    
    return $true
}

function Get-TunnelProcess {
    # Get cloudflared processes that match our working directory
    $processes = Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue
    if ($processes) {
        foreach ($proc in $processes) {
            try {
                # Check if process command line contains our config path
                $commandLine = (Get-WmiObject Win32_Process -Filter "ProcessId = $($proc.Id)").CommandLine
                if ($commandLine -and $commandLine.Contains($ConfigPath)) {
                    return $proc
                }
            } catch {
                # Continue checking other processes
            }
        }
    }
    return $null
}

function Stop-TunnelProcess {
    Write-Log "Stopping Cloudflare tunnel..."
    
    # Try to get process from PID file first
    if (Test-Path $PidFile) {
        try {
            $pid = Get-Content $PidFile -ErrorAction SilentlyContinue
            if ($pid) {
                $process = Get-Process -Id $pid -ErrorAction SilentlyContinue
                if ($process -and $process.ProcessName -eq "cloudflared") {
                    Write-Log "Stopping process with PID: $pid"
                    $process.Kill()
                    Start-Sleep -Seconds 2
                }
            }
        } catch {
            Write-Log "Could not stop process using PID file"
        }
        Remove-Item $PidFile -Force -ErrorAction SilentlyContinue
    }
    
    # Kill any remaining cloudflared processes
    $tunnelProcess = Get-TunnelProcess
    if ($tunnelProcess) {
        Write-Log "Stopping remaining cloudflared process: $($tunnelProcess.Id)"
        try {
            $tunnelProcess.Kill()
            Start-Sleep -Seconds 2
        } catch {
            Write-Log "Failed to stop process: $($_.Exception.Message)"
        }
    }
    
    # Verify all processes are stopped
    $remainingProcesses = Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue
    if ($remainingProcesses) {
        Write-Log "Force killing remaining cloudflared processes..."
        $remainingProcesses | ForEach-Object { 
            try { $_.Kill() } catch { }
        }
    }
    
    Write-Log "Cloudflare tunnel stopped"
    return $true
}

function Get-TunnelStatus {
    Write-Log "Checking Cloudflare tunnel status..."
    
    $tunnelProcess = Get-TunnelProcess
    if ($tunnelProcess) {
        Write-Log "Cloudflare tunnel is RUNNING"
        Write-Log "  Process ID: $($tunnelProcess.Id)"
        Write-Log "  CPU Time: $($tunnelProcess.TotalProcessorTime)"
        Write-Log "  Memory: $([math]::Round($tunnelProcess.WorkingSet64/1MB, 2)) MB"
        Write-Log "  Start Time: $($tunnelProcess.StartTime)"
        
        # Check PID file
        if (Test-Path $PidFile) {
            $storedPid = Get-Content $PidFile -ErrorAction SilentlyContinue
            if ($storedPid -eq $tunnelProcess.Id) {
                Write-Log "  PID file matches running process"
            } else {
                Write-Log "  WARNING: PID file mismatch (stored: $storedPid, actual: $($tunnelProcess.Id))"
            }
        } else {
            Write-Log "  WARNING: PID file not found"
        }
        
        return $true
    } else {
        Write-Log "Cloudflare tunnel is NOT RUNNING"
        
        # Clean up stale PID file
        if (Test-Path $PidFile) {
            Write-Log "Removing stale PID file"
            Remove-Item $PidFile -Force -ErrorAction SilentlyContinue
        }
        
        return $false
    }
}

function Start-TunnelDetached {
    Write-Log "Starting Cloudflare tunnel as detached process..."
    
    if (-not (Test-Prerequisites)) {
        return $false
    }
    
    # Check if already running
    if (Get-TunnelProcess) {
        Write-Log "Cloudflare tunnel is already running"
        return $true
    }
    
    try {
        # Create a completely detached process using Start-Process with specific parameters
        # This creates a process that runs independently of the PowerShell session
        $processInfo = New-Object System.Diagnostics.ProcessStartInfo
        $processInfo.FileName = $CloudflaredPath
        $processInfo.Arguments = "tunnel --config `"$ConfigPath`" run"
        $processInfo.WorkingDirectory = $WorkingDirectory
        $processInfo.UseShellExecute = $false
        $processInfo.CreateNoWindow = $true
        $processInfo.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Hidden
        $processInfo.RedirectStandardOutput = $false
        $processInfo.RedirectStandardError = $false
        $processInfo.RedirectStandardInput = $false
        
        # Start the process
        $process = [System.Diagnostics.Process]::Start($processInfo)
        
        if ($process) {
            # Save PID for later management
            $process.Id | Out-File -FilePath $PidFile -Encoding ASCII
            
            Write-Log "Cloudflare tunnel started successfully"
            Write-Log "  Process ID: $($process.Id)"
            Write-Log "  Process is completely detached and will survive terminal closure"
            
            # Wait a moment to ensure process is stable
            Start-Sleep -Seconds 3
            
            # Verify process is still running
            $runningProcess = Get-Process -Id $process.Id -ErrorAction SilentlyContinue
            if ($runningProcess) {
                Write-Log "Process verification successful - tunnel is running independently"
                return $true
            } else {
                Write-Log "ERROR: Process exited immediately after start"
                Remove-Item $PidFile -Force -ErrorAction SilentlyContinue
                return $false
            }
        } else {
            Write-Log "ERROR: Failed to start process"
            return $false
        }
    } catch {
        Write-Log "ERROR: Exception during process start: $($_.Exception.Message)"
        return $false
    }
}

# Main execution
Write-Log "=== Cloudflare Tunnel Detached Manager ==="

if ($Stop) {
    Stop-TunnelProcess
} elseif ($Status) {
    Get-TunnelStatus
} elseif ($Restart) {
    Write-Log "Restarting Cloudflare tunnel..."
    Stop-TunnelProcess
    Start-Sleep -Seconds 2
    Start-TunnelDetached
} else {
    # Default action: start
    if (Start-TunnelDetached) {
        Write-Host ""
        Write-Host "SUCCESS: Cloudflare tunnel started as detached background process!"
        Write-Host ""
        Write-Host "Key benefits:"
        Write-Host "  ✓ No visible windows"
        Write-Host "  ✓ Survives terminal closure"
        Write-Host "  ✓ Runs completely independently"
        Write-Host "  ✓ No dependency on parent process"
        Write-Host ""
        Write-Host "Your ROTC system is now accessible at:"
        Write-Host "  https://rotc.lspulbrotcunit.online"
        Write-Host "  https://admin.lspulbrotcunit.online"
        Write-Host ""
        Write-Host "To manage the tunnel:"
        Write-Host "  Stop:    .\start-cloudflare-detached.ps1 -Stop"
        Write-Host "  Status:  .\start-cloudflare-detached.ps1 -Status"
        Write-Host "  Restart: .\start-cloudflare-detached.ps1 -Restart"
    } else {
        Write-Host ""
        Write-Host "ERROR: Failed to start Cloudflare tunnel"
        Write-Host "Please check the log file: $LogPath"
        exit 1
    }
}

Write-Log "=== Operation completed ==="