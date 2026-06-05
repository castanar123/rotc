# Cloudflare Tunnel Windows Service Manager
# This script creates and manages Cloudflare tunnel as a Windows service
# Ensures tunnel runs completely detached without any visible windows

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet("Install", "Uninstall", "Start", "Stop", "Status", "Restart")]
    [string]$Action = "Status"
)

# Configuration
$ServiceName = "CloudflareTunnel-ROTC"
$ServiceDisplayName = "Cloudflare Tunnel - ROTC QR System"
$ServiceDescription = "Cloudflare Tunnel service for ROTC QR System providing secure access to https://rotc.lspulbrotcunit.online"
$WorkingDirectory = "c:\xampp\htdocs\generate qr"
$CloudflaredPath = "$WorkingDirectory\cloudflare\cloudflared.exe"
$ConfigPath = "$WorkingDirectory\cloudflare-tunnel.yml"
$LogPath = "$WorkingDirectory\logs\cloudflare-service.log"

# Ensure logs directory exists
if (-not (Test-Path "$WorkingDirectory\logs")) {
    New-Item -ItemType Directory -Path "$WorkingDirectory\logs" -Force | Out-Null
}

function Write-Log {
    param([string]$Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage
    Add-Content -Path $LogPath -Value $logMessage
}

function Test-Prerequisites {
    Write-Log "Checking prerequisites..."
    
    if (-not (Test-Path $CloudflaredPath)) {
        Write-Log "ERROR: cloudflared.exe not found at $CloudflaredPath"
        return $false
    }
    
    if (-not (Test-Path $ConfigPath)) {
        Write-Log "ERROR: cloudflare-tunnel.yml not found at $ConfigPath"
        return $false
    }
    
    Write-Log "Prerequisites check passed"
    return $true
}

function Install-Service {
    Write-Log "Installing Cloudflare Tunnel as Windows Service..."
    
    if (-not (Test-Prerequisites)) {
        return $false
    }
    
    # Check if service already exists
    $existingService = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($existingService) {
        Write-Log "Service already exists. Uninstalling first..."
        Uninstall-Service
    }
    
    try {
        # Create the service using sc.exe for better control
        $serviceArgs = "tunnel --config `"$ConfigPath`" run"
        $binPath = "`"$CloudflaredPath`" $serviceArgs"
        
        Write-Log "Creating service with command: sc.exe create $ServiceName binPath= `"$binPath`" DisplayName= `"$ServiceDisplayName`" start= auto"
        $createResult = & sc.exe create $ServiceName binPath= $binPath DisplayName= $ServiceDisplayName start= auto
        
        if ($LASTEXITCODE -eq 0) {
            # Set service description
            & sc.exe description $ServiceName "$ServiceDescription"
            
            # Configure service to restart on failure
            & sc.exe failure $ServiceName reset= 86400 actions= restart/5000/restart/10000/restart/30000
            
            Write-Log "Service installed successfully"
            Write-Host "SUCCESS: Service installed successfully!" -ForegroundColor Green
            return $true
        } else {
            Write-Log "ERROR: Failed to create service. Exit code: $LASTEXITCODE"
            Write-Log "Output: $createResult"
            Write-Host "ERROR: Failed to create service. Exit code: $LASTEXITCODE" -ForegroundColor Red
            
            # Provide specific error explanations
            switch ($LASTEXITCODE) {
                1639 { Write-Host "  Reason: Invalid command line argument. Check cloudflared.exe path." -ForegroundColor Yellow }
                1073 { Write-Host "  Reason: Service already exists with this name." -ForegroundColor Yellow }
                5 { Write-Host "  Reason: Access denied. Run as Administrator." -ForegroundColor Yellow }
                default { Write-Host "  Reason: Unknown error. Check logs for details." -ForegroundColor Yellow }
            }
            
            return $false
        }
    } catch {
        Write-Log "ERROR: Exception during service installation: $($_.Exception.Message)"
        return $false
    }
}

function Uninstall-Service {
    Write-Log "Uninstalling Cloudflare Tunnel service..."
    
    try {
        # Stop service if running
        $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
        if ($service -and $service.Status -eq "Running") {
            Write-Log "Stopping service..."
            Stop-Service -Name $ServiceName -Force
            Start-Sleep -Seconds 3
        }
        
        # Delete the service
        $deleteResult = & sc.exe delete $ServiceName
        
        if ($LASTEXITCODE -eq 0) {
            Write-Log "Service uninstalled successfully"
            return $true
        } else {
            Write-Log "ERROR: Failed to delete service. Exit code: $LASTEXITCODE"
            return $false
        }
    } catch {
        Write-Log "ERROR: Exception during service uninstallation: $($_.Exception.Message)"
        return $false
    }
}

function Start-TunnelService {
    Write-Log "Starting Cloudflare Tunnel service..."
    
    try {
        $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
        if (-not $service) {
            Write-Log "ERROR: Service not found. Please install first."
            return $false
        }
        
        if ($service.Status -eq "Running") {
            Write-Log "Service is already running"
            return $true
        }
        
        Start-Service -Name $ServiceName
        Start-Sleep -Seconds 5
        
        $service = Get-Service -Name $ServiceName
        if ($service.Status -eq "Running") {
            Write-Log "Service started successfully"
            return $true
        } else {
            Write-Log "ERROR: Service failed to start. Status: $($service.Status)"
            return $false
        }
    } catch {
        Write-Log "ERROR: Exception during service start: $($_.Exception.Message)"
        return $false
    }
}

function Stop-TunnelService {
    Write-Log "Stopping Cloudflare Tunnel service..."
    
    try {
        $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
        if (-not $service) {
            Write-Log "ERROR: Service not found"
            return $false
        }
        
        if ($service.Status -eq "Stopped") {
            Write-Log "Service is already stopped"
            return $true
        }
        
        Stop-Service -Name $ServiceName -Force
        Start-Sleep -Seconds 3
        
        $service = Get-Service -Name $ServiceName
        if ($service.Status -eq "Stopped") {
            Write-Log "Service stopped successfully"
            return $true
        } else {
            Write-Log "ERROR: Service failed to stop. Status: $($service.Status)"
            return $false
        }
    } catch {
        Write-Log "ERROR: Exception during service stop: $($_.Exception.Message)"
        return $false
    }
}

function Get-ServiceStatus {
    Write-Log "Checking Cloudflare Tunnel service status..."
    
    try {
        $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
        if (-not $service) {
            Write-Log "Service is not installed"
            return $false
        }
        
        Write-Log "Service Status: $($service.Status)"
        Write-Log "Service Start Type: $($service.StartType)"
        
        # Check if cloudflared process is actually running
        $processes = Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue
        if ($processes) {
            Write-Log "Cloudflared processes found: $($processes.Count)"
            foreach ($proc in $processes) {
                Write-Log "  PID: $($proc.Id), CPU: $($proc.CPU), Memory: $([math]::Round($proc.WorkingSet64/1MB, 2)) MB"
            }
        } else {
            Write-Log "No cloudflared processes found"
        }
        
        return $true
    } catch {
        Write-Log "ERROR: Exception during status check: $($_.Exception.Message)"
        return $false
    }
}

function Restart-TunnelService {
    Write-Log "Restarting Cloudflare Tunnel service..."
    
    if (Stop-TunnelService) {
        Start-Sleep -Seconds 2
        return Start-TunnelService
    }
    
    return $false
}

# Main execution
Write-Log "=== Cloudflare Tunnel Service Manager ==="
Write-Log "Action: $Action"

# Check if running as administrator
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Log "ERROR: This script must be run as Administrator"
    Write-Host "Please run PowerShell as Administrator and try again."
    exit 1
}

switch ($Action.ToLower()) {
    "install" {
        if (Install-Service) {
            Write-Log "Installation completed successfully"
            Write-Host "\nCloudflare Tunnel service installed successfully!"
            Write-Host "Use 'Start' action to start the service."
            exit 0
        } else {
            Write-Log "Installation failed"
            exit 1
        }
    }
    
    "uninstall" {
        if (Uninstall-Service) {
            Write-Log "Uninstallation completed successfully"
            Write-Host "\nCloudflare Tunnel service uninstalled successfully!"
            exit 0
        } else {
            Write-Log "Uninstallation failed"
            exit 1
        }
    }
    
    "start" {
        if (Start-TunnelService) {
            Write-Log "Service started successfully"
            Write-Host "\nCloudflare Tunnel service is now running!"
            Write-Host "Your ROTC system is accessible at: https://rotc.lspulbrotcunit.online"
            exit 0
        } else {
            Write-Log "Failed to start service"
            exit 1
        }
    }
    
    "stop" {
        if (Stop-TunnelService) {
            Write-Log "Service stopped successfully"
            Write-Host "\nCloudflare Tunnel service stopped."
            exit 0
        } else {
            Write-Log "Failed to stop service"
            exit 1
        }
    }
    
    "restart" {
        if (Restart-TunnelService) {
            Write-Log "Service restarted successfully"
            Write-Host "\nCloudflare Tunnel service restarted successfully!"
            exit 0
        } else {
            Write-Log "Failed to restart service"
            exit 1
        }
    }
    
    "status" {
        Get-ServiceStatus
        Write-Host "\nCheck the log file for detailed information: $LogPath"
        exit 0
    }
    
    default {
        Write-Host "Invalid action. Use: Install, Uninstall, Start, Stop, Status, or Restart"
        exit 1
    }
}

Write-Log "=== Operation completed ==="