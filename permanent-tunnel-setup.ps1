# ROTC QR System - Permanent Tunnel Setup
# Creates a persistent ngrok tunnel with auto-startup capabilities

Write-Host "=== ROTC QR System - Permanent Tunnel Setup ===" -ForegroundColor Green
Write-Host "Setting up persistent ngrok tunnel with auto-startup..." -ForegroundColor Yellow

# Configuration
$PROJECT_DIR = Get-Location
$NGROK_DIR = Join-Path $PROJECT_DIR "ngrok"
$NGROK_EXE = Join-Path $NGROK_DIR "ngrok.exe"
$CONFIG_FILE = Join-Path $PROJECT_DIR "ngrok-config.yml"
$STARTUP_DIR = [Environment]::GetFolderPath("Startup")
$STARTUP_SCRIPT = Join-Path $STARTUP_DIR "ROTC-QR-Tunnel.bat"
$SERVICE_SCRIPT = Join-Path $PROJECT_DIR "tunnel-service.ps1"
$URL_CACHE = Join-Path $PROJECT_DIR "tunnel-url.txt"
$LOG_FILE = Join-Path $PROJECT_DIR "permanent-tunnel.log"

# Function to log messages
function Write-Log {
    param($Message, $Color = "White")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LOG_FILE -Value $logMessage
}

# Check if ngrok is installed
if (-not (Test-Path $NGROK_EXE)) {
    Write-Log "Ngrok not found. Running setup first..." "Yellow"
    & "$PROJECT_DIR\setup-ngrok.ps1"
    if (-not (Test-Path $NGROK_EXE)) {
        Write-Log "Setup failed. Cannot continue." "Red"
        exit 1
    }
}

Write-Log "Creating permanent tunnel service script..." "Cyan"

# Create the tunnel service script
$serviceContent = @'
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
        $pid = Get-Content $PID_FILE
        $process = Get-Process -Id $pid -ErrorAction SilentlyContinue
        if ($process) {
            Write-Host "Status: RUNNING (PID: $pid)" -ForegroundColor Green
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
'@

Set-Content -Path $SERVICE_SCRIPT -Value $serviceContent -Encoding UTF8
Write-Log "Service script created: $SERVICE_SCRIPT" "Green"

# Create quick access scripts
Write-Log "Creating quick access scripts..." "Cyan"

# Start tunnel script
$startScript = Join-Path $PROJECT_DIR "start-tunnel.bat"
$startContent = "@echo off`ntitle ROTC QR System - Start Tunnel`ncd /d `"$PROJECT_DIR`"`npowershell.exe -ExecutionPolicy Bypass -File `"tunnel-service.ps1`" -Start`npause"
Set-Content -Path $startScript -Value $startContent

# Stop tunnel script
$stopScript = Join-Path $PROJECT_DIR "stop-tunnel.bat"
$stopContent = "@echo off`ntitle ROTC QR System - Stop Tunnel`ncd /d `"$PROJECT_DIR`"`npowershell.exe -ExecutionPolicy Bypass -File `"tunnel-service.ps1`" -Stop`npause"
Set-Content -Path $stopScript -Value $stopContent

# Status script
$statusScript = Join-Path $PROJECT_DIR "tunnel-status.bat"
$statusContent = "@echo off`ntitle ROTC QR System - Tunnel Status`ncd /d `"$PROJECT_DIR`"`npowershell.exe -ExecutionPolicy Bypass -File `"tunnel-service.ps1`" -Status`npause"
Set-Content -Path $statusScript -Value $statusContent

# Get URL script
$urlScript = Join-Path $PROJECT_DIR "get-url.bat"
$urlContent = "@echo off`ntitle ROTC QR System - Get URL`ncd /d `"$PROJECT_DIR`"`necho Getting current tunnel URL...`npowershell.exe -ExecutionPolicy Bypass -File `"tunnel-service.ps1`" -GetUrl`npause"
Set-Content -Path $urlScript -Value $urlContent

Write-Log "Quick access scripts created:" "Green"
Write-Log "  - start-tunnel.bat (Start tunnel)" "Cyan"
Write-Log "  - stop-tunnel.bat (Stop tunnel)" "Cyan"
Write-Log "  - tunnel-status.bat (Check status)" "Cyan"
Write-Log "  - get-url.bat (Get current URL)" "Cyan"

# Ask user about auto-startup installation
Write-Host "`n=== Auto-Startup Configuration ===" -ForegroundColor Green
Write-Host "Would you like to install the tunnel as an auto-startup service?" -ForegroundColor Yellow
Write-Host "This will automatically start the tunnel when Windows starts." -ForegroundColor Gray
$response = Read-Host "Install auto-startup? (y/n)"

if ($response -eq 'y' -or $response -eq 'Y') {
    Write-Log "Installing auto-startup service..." "Cyan"
    & $SERVICE_SCRIPT -Install
    Write-Log "Auto-startup service installed successfully!" "Green"
    
    Write-Host "`nWould you like to start the tunnel now? (y/n)" -ForegroundColor Yellow
    $startNow = Read-Host
    if ($startNow -eq 'y' -or $startNow -eq 'Y') {
        & $SERVICE_SCRIPT -Start
    }
}
else {
    Write-Log "Auto-startup not installed. You can install it later using:" "Yellow"
    Write-Log "  powershell -File tunnel-service.ps1 -Install" "Cyan"
}

Write-Host "`n=== Setup Complete! ===" -ForegroundColor Green
Write-Host "Permanent tunnel setup completed successfully." -ForegroundColor Cyan
Write-Host "`nQuick Commands:" -ForegroundColor Yellow
Write-Host "  start-tunnel.bat    - Start the tunnel" -ForegroundColor Cyan
Write-Host "  stop-tunnel.bat     - Stop the tunnel" -ForegroundColor Cyan
Write-Host "  tunnel-status.bat   - Check tunnel status" -ForegroundColor Cyan
Write-Host "  get-url.bat         - Get current public URL" -ForegroundColor Cyan
Write-Host "`nService Management:" -ForegroundColor Yellow
Write-Host "  powershell -File tunnel-service.ps1 -Install    - Install auto-startup" -ForegroundColor Cyan
Write-Host "  powershell -File tunnel-service.ps1 -Uninstall  - Remove auto-startup" -ForegroundColor Cyan

Write-Log "Setup completed at $(Get-Date)" "Green"
Read-Host "`nPress Enter to exit"