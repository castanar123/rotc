# ROTC QR System - Cloudflare Tunnel Manager
# Easy management of Cloudflare Tunnel operations

param(
    [string]$Action = "status",
    [string]$TunnelName = "rotc-qr-system",
    [switch]$Help
)

$PROJECT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$CLOUDFLARED_EXE = Join-Path $PROJECT_DIR "cloudflare\cloudflared.exe"
$CONFIG_FILE = Join-Path $PROJECT_DIR "cloudflare-tunnel.yml"
$PID_FILE = Join-Path $PROJECT_DIR "cloudflare-tunnel.pid"
$LOG_FILE = Join-Path $PROJECT_DIR "cloudflare-tunnel.log"
$URL_FILE = Join-Path $PROJECT_DIR "cloudflare-url.txt"

function Write-Log {
    param($Message, $Color = "White")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LOG_FILE -Value $logMessage -ErrorAction SilentlyContinue
}

function Show-Help {
    Write-Host "=== ROTC QR System - Cloudflare Tunnel Manager ===" -ForegroundColor Green
    Write-Host ""
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  .\cloudflare-tunnel-manager.ps1 [action]" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Actions:" -ForegroundColor Yellow
    Write-Host "  status    Show tunnel status (default)" -ForegroundColor Cyan
    Write-Host "  start     Start the tunnel" -ForegroundColor Cyan
    Write-Host "  stop      Stop the tunnel" -ForegroundColor Cyan
    Write-Host "  restart   Restart the tunnel" -ForegroundColor Cyan
    Write-Host "  urls      Show tunnel URLs" -ForegroundColor Cyan
    Write-Host "  logs      Show recent logs" -ForegroundColor Cyan
    Write-Host "  install   Install cloudflared" -ForegroundColor Cyan
    Write-Host "  setup     Complete setup (install + configure)" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Examples:" -ForegroundColor Yellow
    Write-Host "  .\cloudflare-tunnel-manager.ps1 setup" -ForegroundColor Gray
    Write-Host "  .\cloudflare-tunnel-manager.ps1 start" -ForegroundColor Gray
    Write-Host "  .\cloudflare-tunnel-manager.ps1 status" -ForegroundColor Gray
}

function Get-TunnelStatus {
    Write-Host "=== Cloudflare Tunnel Status ===" -ForegroundColor Green
    Write-Host ""
    
    # Check if cloudflared is installed
    if (-not (Test-Path $CLOUDFLARED_EXE)) {
        Write-Host "❌ Cloudflared not installed" -ForegroundColor Red
        Write-Host "   Run: .\cloudflare-tunnel-manager.ps1 install" -ForegroundColor Gray
        return
    }
    else {
        Write-Host "✅ Cloudflared installed" -ForegroundColor Green
    }
    
    # Check configuration
    if (-not (Test-Path $CONFIG_FILE)) {
        Write-Host "❌ Tunnel not configured" -ForegroundColor Red
        Write-Host "   Run: .\setup-cloudflare-tunnel.ps1 -Configure" -ForegroundColor Gray
        return
    }
    else {
        Write-Host "✅ Tunnel configured" -ForegroundColor Green
    }
    
    # Check if tunnel is running
    $isRunning = $false
    $processId = $null
    
    if (Test-Path $PID_FILE) {
        $processId = Get-Content $PID_FILE -ErrorAction SilentlyContinue
        if ($processId) {
            try {
                $process = Get-Process -Id $processId -ErrorAction Stop
                if ($process.ProcessName -eq "cloudflared") {
                    $isRunning = $true
                    Write-Host "✅ Tunnel running (PID: $processId)" -ForegroundColor Green
                }
            }
            catch {
                # Process not found
            }
        }
    }
    
    if (-not $isRunning) {
        Write-Host "❌ Tunnel not running" -ForegroundColor Red
        Write-Host "   Run: .\cloudflare-tunnel-manager.ps1 start" -ForegroundColor Gray
    }
    
    # Show URLs if available
    if ($isRunning -and (Test-Path $URL_FILE)) {
        Write-Host ""
        Write-Host "🌐 Tunnel URLs:" -ForegroundColor Yellow
        $urls = Get-Content $URL_FILE
        foreach ($url in $urls) {
            Write-Host "   $url" -ForegroundColor Cyan
        }
    }
    
    # Show recent activity
    if (Test-Path $LOG_FILE) {
        Write-Host ""
        Write-Host "📋 Recent Activity:" -ForegroundColor Yellow
        $recentLogs = Get-Content $LOG_FILE -Tail 3 -ErrorAction SilentlyContinue
        foreach ($log in $recentLogs) {
            Write-Host "   $log" -ForegroundColor Gray
        }
    }
}

function Start-TunnelService {
    Write-Host "Starting Cloudflare Tunnel..." -ForegroundColor Green
    
    # Check if already running
    if (Test-Path $PID_FILE) {
        $processId = Get-Content $PID_FILE -ErrorAction SilentlyContinue
        if ($processId) {
            try {
                $process = Get-Process -Id $processId -ErrorAction Stop
                if ($process.ProcessName -eq "cloudflared") {
                    Write-Host "Tunnel is already running (PID: $processId)" -ForegroundColor Yellow
                    return $true
                }
            }
            catch {
                # Process not found, continue with start
            }
        }
    }
    
    if (-not (Test-Path $CLOUDFLARED_EXE)) {
        Write-Host "Cloudflared not found. Run setup first." -ForegroundColor Red
        return $false
    }
    
    if (-not (Test-Path $CONFIG_FILE)) {
        Write-Host "Configuration not found. Run setup first." -ForegroundColor Red
        return $false
    }
    
    try {
        # Start tunnel
        $process = Start-Process -FilePath $CLOUDFLARED_EXE -ArgumentList "tunnel", "--config", $CONFIG_FILE, "run", $TunnelName -PassThru -WindowStyle Hidden
        
        # Save PID
        Set-Content -Path $PID_FILE -Value $process.Id
        
        Write-Log "Tunnel started with PID: $($process.Id)" "Green"
        
        # Wait for tunnel to establish
        Write-Host "Waiting for tunnel to establish..." -ForegroundColor Cyan
        Start-Sleep -Seconds 8
        
        # Update URLs
        $urls = @(
            "https://$TunnelName.trycloudflare.com",
            "https://admin-$TunnelName.trycloudflare.com",
            "https://scanner-$TunnelName.trycloudflare.com",
            "https://api-$TunnelName.trycloudflare.com"
        )
        Set-Content -Path $URL_FILE -Value ($urls -join "`n")
        
        Write-Host "✅ Tunnel started successfully!" -ForegroundColor Green
        Write-Host ""
        Write-Host "Your ROTC QR System is accessible at:" -ForegroundColor Yellow
        foreach ($url in $urls) {
            Write-Host "  $url" -ForegroundColor Cyan
        }
        
        return $true
    }
    catch {
        Write-Log "Error starting tunnel: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Stop-TunnelService {
    Write-Host "Stopping Cloudflare Tunnel..." -ForegroundColor Yellow
    
    if (-not (Test-Path $PID_FILE)) {
        Write-Host "No PID file found. Tunnel may not be running." -ForegroundColor Gray
        return $true
    }
    
    $processId = Get-Content $PID_FILE -ErrorAction SilentlyContinue
    if (-not $processId) {
        Write-Host "Invalid PID file. Tunnel may not be running." -ForegroundColor Gray
        return $true
    }
    
    try {
        $process = Get-Process -Id $processId -ErrorAction Stop
        if ($process.ProcessName -eq "cloudflared") {
            $process.Kill()
            $process.WaitForExit(5000)
            Write-Log "Tunnel stopped (PID: $processId)" "Yellow"
            Write-Host "✅ Tunnel stopped successfully" -ForegroundColor Green
        }
    }
    catch {
        Write-Host "Process not found or already stopped" -ForegroundColor Gray
    }
    
    # Clean up PID file
    if (Test-Path $PID_FILE) {
        Remove-Item $PID_FILE -Force -ErrorAction SilentlyContinue
    }
    
    return $true
}

function Restart-TunnelService {
    Write-Host "Restarting Cloudflare Tunnel..." -ForegroundColor Yellow
    Stop-TunnelService
    Start-Sleep -Seconds 2
    Start-TunnelService
}

function Show-TunnelUrls {
    Write-Host "=== Tunnel URLs ===" -ForegroundColor Green
    Write-Host ""
    
    if (Test-Path $URL_FILE) {
        $urls = Get-Content $URL_FILE
        Write-Host "Your ROTC QR System URLs:" -ForegroundColor Yellow
        foreach ($url in $urls) {
            Write-Host "  $url" -ForegroundColor Cyan
        }
    }
    else {
        Write-Host "No URLs found. Start the tunnel first." -ForegroundColor Red
    }
}

function Show-TunnelLogs {
    Write-Host "=== Recent Tunnel Logs ===" -ForegroundColor Green
    Write-Host ""
    
    if (Test-Path $LOG_FILE) {
        $logs = Get-Content $LOG_FILE -Tail 20
        foreach ($log in $logs) {
            Write-Host $log -ForegroundColor Gray
        }
    }
    else {
        Write-Host "No logs found." -ForegroundColor Gray
    }
}

function Install-CloudflaredOnly {
    Write-Host "Installing Cloudflared..." -ForegroundColor Green
    & "$PROJECT_DIR\setup-cloudflare-tunnel.ps1" -Install
}

function Complete-Setup {
    Write-Host "Running complete Cloudflare Tunnel setup..." -ForegroundColor Green
    & "$PROJECT_DIR\setup-cloudflare-tunnel.ps1" -Install -Configure -Start
}

# Main execution
if ($Help) {
    Show-Help
    exit 0
}

switch ($Action.ToLower()) {
    "status" { Get-TunnelStatus }
    "start" { Start-TunnelService }
    "stop" { Stop-TunnelService }
    "restart" { Restart-TunnelService }
    "urls" { Show-TunnelUrls }
    "logs" { Show-TunnelLogs }
    "install" { Install-CloudflaredOnly }
    "setup" { Complete-Setup }
    default {
        Write-Host "Unknown action: $Action" -ForegroundColor Red
        Write-Host "Use -Help for available actions" -ForegroundColor Gray
    }
}