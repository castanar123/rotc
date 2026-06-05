# ROTC QR System - Ngrok Stop Script
# Stops all ngrok tunnels

Write-Host "=== Stopping ROTC QR System Ngrok Tunnels ===" -ForegroundColor Red

try {
    # Kill all ngrok processes
    Get-Process -Name "ngrok" -ErrorAction SilentlyContinue | Stop-Process -Force
    Write-Host "All ngrok processes stopped." -ForegroundColor Green
}
catch {
    Write-Host "No ngrok processes found or error stopping: $($_.Exception.Message)" -ForegroundColor Yellow
}

Read-Host "Press Enter to exit"
