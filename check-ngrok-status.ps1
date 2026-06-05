# ROTC QR System - Ngrok Status Checker
# Checks the current status of ngrok tunnels

Write-Host "=== ROTC QR System - Ngrok Status ===" -ForegroundColor Green

$PROJECT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$NGROK_EXE = Join-Path $PROJECT_DIR "ngrok\ngrok.exe"

if (-not (Test-Path $NGROK_EXE)) {
    Write-Host "Error: Ngrok not found. Please run setup-ngrok.ps1 first." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

try {
    Write-Host "Checking ngrok status..." -ForegroundColor Cyan
    $status = & $NGROK_EXE api tunnels 2>&1
    
    if ($status -match "no tunnels") {
        Write-Host "No active tunnels found." -ForegroundColor Yellow
        Write-Host "Run start-ngrok.bat to start the tunnel." -ForegroundColor Cyan
    }
    else {
        Write-Host "Active tunnels:" -ForegroundColor Green
        Write-Host $status -ForegroundColor White
    }
}
catch {
    Write-Host "Error checking status: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Ngrok may not be running." -ForegroundColor Yellow
}

Read-Host "Press Enter to exit"
