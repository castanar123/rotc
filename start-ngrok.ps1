# ROTC QR System - Ngrok Startup Script
# Automatically starts ngrok tunnel for the QR system

Write-Host "=== Starting ROTC QR System Ngrok Tunnel ===" -ForegroundColor Green

$PROJECT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$NGROK_EXE = Join-Path $PROJECT_DIR "ngrok\ngrok.exe"
$CONFIG_FILE = Join-Path $PROJECT_DIR "ngrok-config.yml"
$LOG_FILE = Join-Path $PROJECT_DIR "ngrok-tunnel.log"

# Function to log messages
function Write-TunnelLog {
    param($Message, $Color = "White")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LOG_FILE -Value $logMessage
}

# Check if ngrok exists
if (-not (Test-Path $NGROK_EXE)) {
    Write-TunnelLog "Error: Ngrok not found. Please run setup-ngrok.ps1 first." "Red"
    Read-Host "Press Enter to exit"
    exit 1
}

# Check if config file exists
if (-not (Test-Path $CONFIG_FILE)) {
    Write-TunnelLog "Error: Config file not found: $CONFIG_FILE" "Red"
    Read-Host "Press Enter to exit"
    exit 1
}

# Start ngrok tunnel
Write-TunnelLog "Starting ngrok tunnel..." "Cyan"
Write-TunnelLog "Config file: $CONFIG_FILE" "Gray"
Write-TunnelLog "Tunnel name: qr-project" "Gray"

try {
    # Start ngrok with config file
    Write-TunnelLog "Executing: $NGROK_EXE start --config=$CONFIG_FILE qr-project" "Gray"
    & $NGROK_EXE start --config="$CONFIG_FILE" qr-project
}
catch {
    Write-TunnelLog "Error starting ngrok: $($_.Exception.Message)" "Red"
    Read-Host "Press Enter to exit"
    exit 1
}
