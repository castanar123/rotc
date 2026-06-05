# ROTC QR System - Get Ngrok Public URL
# This script helps you find your current ngrok public URL

Write-Host "=== ROTC QR System - Ngrok URL Finder ===" -ForegroundColor Green
Write-Host ""

# Method 1: Check ngrok web interface
Write-Host "Method 1: Ngrok Web Interface" -ForegroundColor Cyan
Write-Host "1. Open your browser and go to: http://localhost:4040" -ForegroundColor Yellow
Write-Host "2. You'll see your public URL(s) listed there" -ForegroundColor Yellow
Write-Host ""

# Method 2: Try to get URL from ngrok process
Write-Host "Method 2: Automatic Detection" -ForegroundColor Cyan
try {
    # Try to get the URL from the web interface API
    $response = Invoke-WebRequest -Uri "http://localhost:4040/api/tunnels" -UseBasicParsing -ErrorAction SilentlyContinue
    if ($response.StatusCode -eq 200) {
        $tunnels = $response.Content | ConvertFrom-Json
        if ($tunnels.tunnels.Count -gt 0) {
            Write-Host "Found active tunnels:" -ForegroundColor Green
            foreach ($tunnel in $tunnels.tunnels) {
                Write-Host "  Name: $($tunnel.name)" -ForegroundColor White
                Write-Host "  Public URL: $($tunnel.public_url)" -ForegroundColor Yellow -BackgroundColor DarkBlue
                Write-Host "  Local URL: $($tunnel.config.addr)" -ForegroundColor Gray
                Write-Host ""
            }
        }
        else {
            Write-Host "No active tunnels found." -ForegroundColor Yellow
        }
    }
}
catch {
    Write-Host "Could not connect to ngrok web interface." -ForegroundColor Red
    Write-Host "Make sure ngrok is running first." -ForegroundColor Yellow
}

# Method 3: Manual instructions
Write-Host "Method 3: Manual Check" -ForegroundColor Cyan
Write-Host "If ngrok is running, look at the terminal window where you started it." -ForegroundColor Yellow
Write-Host "The public URL will be displayed in the format:" -ForegroundColor Yellow
Write-Host "  https://xxxx-xxx-xxx-xxx.ngrok-free.app" -ForegroundColor White -BackgroundColor DarkBlue
Write-Host ""

# Quick start reminder
Write-Host "Quick Start Reminder:" -ForegroundColor Magenta
Write-Host "- To start ngrok: Double-click 'auto-start-ngrok.bat'" -ForegroundColor White
Write-Host "- To view web interface: Open http://localhost:4040" -ForegroundColor White
Write-Host "- To stop ngrok: Run 'stop-ngrok.ps1'" -ForegroundColor White
Write-Host ""

# Try to open web interface
$openWeb = Read-Host "Would you like to open the ngrok web interface now? (y/n)"
if ($openWeb -eq "y" -or $openWeb -eq "Y") {
    try {
        Start-Process "http://localhost:4040"
        Write-Host "Opening ngrok web interface..." -ForegroundColor Green
    }
    catch {
        Write-Host "Could not open web interface. Please open http://localhost:4040 manually." -ForegroundColor Red
    }
}

Read-Host "Press Enter to exit"