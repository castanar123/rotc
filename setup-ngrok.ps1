# ROTC QR System - Automated Ngrok Setup Script
# This script provides fully autonomous ngrok setup and management

Write-Host "=== ROTC QR System - Ngrok Auto Setup ===" -ForegroundColor Green
Write-Host "Starting automated ngrok configuration..." -ForegroundColor Yellow

# Configuration
$NGROK_VERSION = "3.15.0"
$NGROK_URL = "https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip"
$PROJECT_DIR = Get-Location
$NGROK_DIR = Join-Path $PROJECT_DIR "ngrok"
$NGROK_EXE = Join-Path $NGROK_DIR "ngrok.exe"
$CONFIG_FILE = Join-Path $PROJECT_DIR "ngrok-config.yml"
$STARTUP_SCRIPT = Join-Path $PROJECT_DIR "start-ngrok.ps1"
$LOG_FILE = Join-Path $PROJECT_DIR "ngrok-setup.log"

# Function to log messages
function Write-Log {
    param($Message, $Color = "White")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LOG_FILE -Value $logMessage
}

# Function to check if running as administrator
function Test-Administrator {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# Check administrator privileges
if (-not (Test-Administrator)) {
    Write-Log "Warning: Not running as administrator. Some features may not work properly." "Yellow"
}

# Create ngrok directory
Write-Log "Creating ngrok directory..." "Cyan"
if (-not (Test-Path $NGROK_DIR)) {
    New-Item -ItemType Directory -Path $NGROK_DIR -Force | Out-Null
    Write-Log "Created directory: $NGROK_DIR" "Green"
}

# Download ngrok if not exists
if (-not (Test-Path $NGROK_EXE)) {
    Write-Log "Downloading ngrok v$NGROK_VERSION..." "Cyan"
    try {
        $zipPath = Join-Path $NGROK_DIR "ngrok.zip"
        Invoke-WebRequest -Uri $NGROK_URL -OutFile $zipPath -UseBasicParsing
        Write-Log "Download completed successfully" "Green"
        
        # Extract ngrok
        Write-Log "Extracting ngrok..." "Cyan"
        Expand-Archive -Path $zipPath -DestinationPath $NGROK_DIR -Force
        Remove-Item $zipPath -Force
        Write-Log "Extraction completed" "Green"
    }
    catch {
        Write-Log "Error downloading ngrok: $($_.Exception.Message)" "Red"
        exit 1
    }
}
else {
    Write-Log "Ngrok already exists at: $NGROK_EXE" "Green"
}

# Verify ngrok installation
Write-Log "Verifying ngrok installation..." "Cyan"
try {
    $version = & $NGROK_EXE version 2>&1
    Write-Log "Ngrok version: $version" "Green"
}
catch {
    Write-Log "Error verifying ngrok installation: $($_.Exception.Message)" "Red"
    exit 1
}

# Configure ngrok with authtoken
Write-Log "Configuring ngrok authentication..." "Cyan"
if (Test-Path $CONFIG_FILE) {
    $configContent = Get-Content $CONFIG_FILE -Raw
    if ($configContent -match 'authtoken:\s*([\w\-_]+)') {
        $authtoken = $matches[1]
        Write-Log "Found authtoken in config file" "Green"
        
        try {
            & $NGROK_EXE config add-authtoken $authtoken
            Write-Log "Authtoken configured successfully" "Green"
        }
        catch {
            Write-Log "Error configuring authtoken: $($_.Exception.Message)" "Red"
        }
    }
    else {
        Write-Log "No authtoken found in config file" "Yellow"
    }
}
else {
    Write-Log "Config file not found: $CONFIG_FILE" "Yellow"
}

# Create startup script
Write-Log "Creating startup script..." "Cyan"
$startupContent = @'
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
'@

Set-Content -Path $STARTUP_SCRIPT -Value $startupContent -Encoding UTF8
Write-Log "Startup script created: $STARTUP_SCRIPT" "Green"

# Create quick start batch file
Write-Log "Creating quick start batch file..." "Cyan"
$batchContent = @"
@echo off
title ROTC QR System - Ngrok Tunnel
echo Starting ROTC QR System Ngrok Tunnel...
powershell.exe -ExecutionPolicy Bypass -File "%~dp0start-ngrok.ps1"
pause
"@

$batchFile = Join-Path $PROJECT_DIR "start-ngrok.bat"
Set-Content -Path $batchFile -Value $batchContent -Encoding ASCII
Write-Log "Batch file created: $batchFile" "Green"

# Create status checker script
Write-Log "Creating status checker script..." "Cyan"
$statusContent = @'
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
'@

$statusScript = Join-Path $PROJECT_DIR "check-ngrok-status.ps1"
Set-Content -Path $statusScript -Value $statusContent -Encoding UTF8
Write-Log "Status checker created: $statusScript" "Green"

# Create stop script
Write-Log "Creating stop script..." "Cyan"
$stopContent = @'
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
'@

$stopScript = Join-Path $PROJECT_DIR "stop-ngrok.ps1"
Set-Content -Path $stopScript -Value $stopContent -Encoding UTF8
Write-Log "Stop script created: $stopScript" "Green"

# Create desktop shortcuts (if possible)
Write-Log "Creating desktop shortcuts..." "Cyan"
try {
    $WshShell = New-Object -comObject WScript.Shell
    $desktopPath = [Environment]::GetFolderPath("Desktop")
    
    # Start shortcut
    $startShortcut = $WshShell.CreateShortcut((Join-Path $desktopPath "Start ROTC Ngrok.lnk"))
    $startShortcut.TargetPath = $batchFile
    $startShortcut.WorkingDirectory = $PROJECT_DIR
    $startShortcut.Description = "Start ROTC QR System Ngrok Tunnel"
    $startShortcut.Save()
    
    # Status shortcut
    $statusShortcut = $WshShell.CreateShortcut((Join-Path $desktopPath "Check ROTC Ngrok Status.lnk"))
    $statusShortcut.TargetPath = "powershell.exe"
    $statusShortcut.Arguments = "-ExecutionPolicy Bypass -File `"$statusScript`""
    $statusShortcut.WorkingDirectory = $PROJECT_DIR
    $statusShortcut.Description = "Check ROTC QR System Ngrok Status"
    $statusShortcut.Save()
    
    Write-Log "Desktop shortcuts created successfully" "Green"
}
catch {
    Write-Log "Could not create desktop shortcuts: $($_.Exception.Message)" "Yellow"
}

# Final setup summary
Write-Log "" "White"
Write-Log "=== SETUP COMPLETE ===" "Green"
Write-Log "" "White"
Write-Log "Files created:" "Cyan"
Write-Log "  - $NGROK_EXE (ngrok executable)" "White"
Write-Log "  - $STARTUP_SCRIPT (PowerShell startup script)" "White"
Write-Log "  - $batchFile (Quick start batch file)" "White"
Write-Log "  - $statusScript (Status checker)" "White"
Write-Log "  - $stopScript (Stop script)" "White"
Write-Log "" "White"
Write-Log "Usage:" "Cyan"
Write-Log "  1. Double-click 'start-ngrok.bat' to start the tunnel" "White"
Write-Log "  2. Use 'check-ngrok-status.ps1' to check tunnel status" "White"
Write-Log "  3. Use 'stop-ngrok.ps1' to stop all tunnels" "White"
Write-Log "" "White"
Write-Log "The system is now ready for autonomous operation!" "Green"
Write-Log "" "White"

# Auto-start option
$autoStart = Read-Host "Would you like to start the ngrok tunnel now? (y/n)"
if ($autoStart -eq "y" -or $autoStart -eq "Y") {
    Write-Log "Starting ngrok tunnel..." "Cyan"
    Start-Process -FilePath $batchFile -Wait
}
else {
    Write-Log "Setup complete. Run start-ngrok.bat when ready." "Green"
}

Read-Host "Press Enter to exit"