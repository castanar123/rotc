# ROTC QR System - PowerShell Cloudflare Tunnel Starter
# This script provides reliable Cloudflare tunnel startup with better error handling

param(
    [switch]$Silent,
    [switch]$Background
)

# Set working directory
Set-Location "c:\xampp\htdocs\generate qr"

# Function to write colored output
function Write-Status {
    param(
        [string]$Message,
        [string]$Type = "Info"
    )
    
    if (-not $Silent) {
        switch ($Type) {
            "Success" { Write-Host "[SUCCESS] $Message" -ForegroundColor Green }
            "Error"   { Write-Host "[ERROR] $Message" -ForegroundColor Red }
            "Warning" { Write-Host "[WARNING] $Message" -ForegroundColor Yellow }
            default   { Write-Host "[INFO] $Message" -ForegroundColor Cyan }
        }
    }
}

# Function to check if tunnel is running
function Test-TunnelRunning {
    return (Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue) -ne $null
}

if (-not $Silent) {
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host "ROTC QR System - PowerShell Cloudflare Starter" -ForegroundColor Magenta
    Write-Host "========================================" -ForegroundColor Magenta
    Write-Host ""
}

# Check if cloudflared exists
if (-not (Test-Path "cloudflare\cloudflared.exe")) {
    Write-Status "cloudflared.exe not found!" "Error"
    Write-Status "Please run setup first: .\setup-cloudflare-tunnel.ps1" "Error"
    if (-not $Silent) {
        Read-Host "Press Enter to exit"
    }
    exit 1
}

# Check if config exists
if (-not (Test-Path "cloudflare-tunnel.yml")) {
    Write-Status "cloudflare-tunnel.yml not found!" "Error"
    Write-Status "Please run setup first: .\setup-cloudflare-tunnel.ps1" "Error"
    if (-not $Silent) {
        Read-Host "Press Enter to exit"
    }
    exit 1
}

# Check if tunnel is already running
Write-Status "Checking if Cloudflare tunnel is already running..."
if (Test-TunnelRunning) {
    Write-Status "Cloudflare tunnel is already running!" "Success"
    Write-Host ""
    Write-Host "Your URLs:" -ForegroundColor Yellow
    Write-Host "- Online: https://rotc.lspulbrotcunit.online" -ForegroundColor Green
    Write-Host "- Admin:  https://admin.lspulbrotcunit.online" -ForegroundColor Green
    Write-Host ""
    if (-not $Silent) {
        Read-Host "Press Enter to exit"
    }
    exit 0
}

Write-Status "Starting Cloudflare tunnel..."

try {
    # Start tunnel process
    if ($Background) {
        # Start as background process
        $process = Start-Process -FilePath "cloudflare\cloudflared.exe" `
                                -ArgumentList "tunnel", "--config", "cloudflare-tunnel.yml", "run" `
                                -WindowStyle Hidden `
                                -PassThru
    } else {
        # Start with visible window
        $process = Start-Process -FilePath "cloudflare\cloudflared.exe" `
                                -ArgumentList "tunnel", "--config", "cloudflare-tunnel.yml", "run" `
                                -WindowStyle Minimized `
                                -PassThru
    }
    
    # Wait for tunnel to initialize
    Write-Status "Waiting for tunnel to initialize..."
    Start-Sleep -Seconds 5
    
    # Check if tunnel started successfully
    if (Test-TunnelRunning) {
        Write-Status "Cloudflare tunnel started successfully!" "Success"
        Write-Host ""
        Write-Host "Your URLs:" -ForegroundColor Yellow
        Write-Host "- Online: https://rotc.lspulbrotcunit.online" -ForegroundColor Green
        Write-Host "- Admin:  https://admin.lspulbrotcunit.online" -ForegroundColor Green
        Write-Host ""
        Write-Status "Tunnel is now running with PID: $($process.Id)" "Success"
        Write-Status "You can close this window safely." "Info"
    } else {
        Write-Status "Failed to start Cloudflare tunnel!" "Error"
        Write-Status "Please check the configuration and try again." "Error"
        exit 1
    }
    
} catch {
    Write-Status "Error starting Cloudflare tunnel: $($_.Exception.Message)" "Error"
    exit 1
}

if (-not $Silent) {
    Write-Host ""
    Read-Host "Press Enter to exit"
}

exit 0