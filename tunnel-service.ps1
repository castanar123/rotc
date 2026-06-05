# ROTC QR System - Tunnel Service
# Maintains persistent ngrok tunnel with auto-restart

param(
    [switch]$Install,
    [switch]$Uninstall,
    [switch]$Start,
    [switch]$Stop,
    [switch]$Status,
    [switch]$GetUrl
)

$PROJECT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$NGROK_EXE = Join-Path $PROJECT_DIR "ngrok\ngrok.exe"
$CONFIG_FILE = Join-Path $PROJECT_DIR "ngrok-config.yml"
$URL_CACHE = Join-Path $PROJECT_DIR "tunnel-url.txt"
$PID_FILE = Join-Path $PROJECT_DIR "tunnel.pid"
$LOG_FILE = Join-Path $PROJECT_DIR "tunnel-service.log"

function Write-ServiceLog {
    param($Message, $Color = "White")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LOG_FILE -Value $logMessage
}

function Get-TunnelUrl {
    try {
        $response = Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels" -ErrorAction SilentlyContinue
        $tunnel = $response.tunnels | Where-Object { $_.name -eq "qr-project" }
        if ($tunnel) {
            $url = $tunnel.public_url
            Set-Content -Path $URL_CACHE -Value $url
            return $url
        }
    }
    catch {
        Write-ServiceLog "Could not retrieve tunnel URL: $($_.Exception.Message)" "Yellow"
    }
    return $null
}

function Start-TunnelService {
    Write-ServiceLog "Starting ROTC QR Tunnel Service..." "Green"
    
    # Check if already running
    if (Test-Path $PID_FILE) {
        $pid = Get-Content $PID_FILE
        if (Get-Process -Id $pid -ErrorAction SilentlyContinue) {
            Write-ServiceLog "Tunnel service already running (PID: $pid)" "Yellow"
            return
        }
    }
    
    # Start ngrok in background
    $process = Start-Process -FilePath $NGROK_EXE -ArgumentList "start", "--config=$CONFIG_FILE", "qr-project" -PassThru -WindowStyle Hidden
    Set-Content -Path $PID_FILE -Value $process.Id
    
    Write-ServiceLog "Tunnel started with PID: $($process.Id)" "Green"
    
    # Wait for tunnel to be ready and get URL
    Start-Sleep -Seconds 5
    $url = Get-TunnelUrl
    if ($url) {
        Write-ServiceLog "Tunnel URL: $url" "Cyan"
        Write-Host "\n=== ROTC QR System URLs ===" -ForegroundColor Green
        Write-Host "Public URL: $url" -ForegroundColor Cyan
        Write-Host "Admin Dashboard: $url/admin_dashboard.php" -ForegroundColor Yellow
        Write-Host "QR Scanner: $url/scanner.php" -ForegroundColor Yellow
        Write-Host "Login Page: $url/login.php" -ForegroundColor Yellow
        Write-Host "Ngrok Interface: http://localhost:4040" -ForegroundColor Gray
    }
}

function Stop-TunnelService {
    Write-ServiceLog "Stopping ROTC QR Tunnel Service..." "Yellow"
    
    if (Test-Path $PID_FILE) {
        $pid = Get-Content $PID_FILE
        try {
            Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
            Remove-Item $PID_FILE -Force
            Write-ServiceLog "Tunnel service stopped" "Green"
        }
        catch {
            Write-ServiceLog "Error stopping service: $($_.Exception.Message)" "Red"
        }
    }
    else {
        Write-ServiceLog "No running tunnel service found" "Yellow"
    }
}

function Get-TunnelStatus {
    Write-Host "=== ROTC QR Tunnel Status ===" -ForegroundColor Green
    
    if (Test-Path $PID_FILE) {
        $processId = Get-Content $PID_FILE
        $process = Get-Process -Id $processId -ErrorAction SilentlyContinue
        if ($process) {
            Write-Host "Status: RUNNING (PID: $processId)" -ForegroundColor Green
            $url = Get-TunnelUrl
            if ($url) {
                Write-Host "Public URL: $url" -ForegroundColor Cyan
                if (Test-Path $URL_CACHE) {
                    $cachedUrl = Get-Content $URL_CACHE
                    Write-Host "Cached URL: $cachedUrl" -ForegroundColor Gray
                }
            }
        }
        else {
            Write-Host "Status: STOPPED (stale PID file)" -ForegroundColor Red
            Remove-Item $PID_FILE -Force -ErrorAction SilentlyContinue
        }
    }
    else {
        Write-Host "Status: STOPPED" -ForegroundColor Red
    }
}

# Main execution
if ($Install) {
    Write-Host "Installing ROTC QR Tunnel Service..." -ForegroundColor Green
    # Create startup entry
    $startupScript = Join-Path ([Environment]::GetFolderPath("Startup")) "ROTC-QR-Tunnel.bat"
    $batchContent = "@echo off`ncd /d `"$PROJECT_DIR`"`npowershell.exe -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$($MyInvocation.MyCommand.Path)`" -Start"
    Set-Content -Path $startupScript -Value $batchContent
    Write-Host "Startup entry created: $startupScript" -ForegroundColor Green
}
elseif ($Uninstall) {
    Write-Host "Uninstalling ROTC QR Tunnel Service..." -ForegroundColor Yellow
    Stop-TunnelService
    $startupScript = Join-Path ([Environment]::GetFolderPath("Startup")) "ROTC-QR-Tunnel.bat"
    if (Test-Path $startupScript) {
        Remove-Item $startupScript -Force
        Write-Host "Startup entry removed" -ForegroundColor Green
    }
}
elseif ($Start) {
    Start-TunnelService
}
elseif ($Stop) {
    Stop-TunnelService
}
elseif ($Status) {
    Get-TunnelStatus
}
elseif ($GetUrl) {
    $url = Get-TunnelUrl
    if ($url) {
        Write-Host $url
    }
    else {
        Write-Host "No active tunnel found" -ForegroundColor Red
    }
}
else {
    Write-Host "ROTC QR System - Tunnel Service Manager" -ForegroundColor Green
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  -Install    Install as startup service" -ForegroundColor Cyan
    Write-Host "  -Uninstall  Remove startup service" -ForegroundColor Cyan
    Write-Host "  -Start      Start tunnel service" -ForegroundColor Cyan
    Write-Host "  -Stop       Stop tunnel service" -ForegroundColor Cyan
    Write-Host "  -Status     Show tunnel status" -ForegroundColor Cyan
    Write-Host "  -GetUrl     Get current public URL" -ForegroundColor Cyan
}
