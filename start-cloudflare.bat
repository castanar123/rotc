@echo off
REM ROTC QR System - Quick Start Cloudflare Tunnel
REM This batch file provides easy access to Cloudflare Tunnel operations

cd /d "%~dp0"

echo ========================================
echo ROTC QR System - Cloudflare Tunnel
echo ========================================
echo.

REM Check if setup is needed
if not exist "cloudflare\cloudflared.exe" (
    echo Cloudflare Tunnel not installed yet.
    echo.
    echo Choose an option:
    echo 1. Complete setup (install + configure + start)
    echo 2. Install only
    echo 3. Exit
    echo.
    set /p choice="Enter your choice (1-3): "
    
    if "!choice!"=="1" (
        echo.
        echo Running complete setup...
        powershell -ExecutionPolicy Bypass -File "setup-cloudflare-tunnel.ps1" -Install -Configure -Start
        pause
        exit /b
    )
    
    if "!choice!"=="2" (
        echo.
        echo Installing cloudflared...
        powershell -ExecutionPolicy Bypass -File "setup-cloudflare-tunnel.ps1" -Install
        pause
        exit /b
    )
    
    exit /b
)

REM Show current status
echo Current Status:
powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" status
echo.

REM Show menu
echo Choose an action:
echo 1. Start tunnel
echo 2. Stop tunnel
echo 3. Restart tunnel
echo 4. Show URLs
echo 5. Show logs
echo 6. Status check
echo 7. Complete setup
echo 8. Exit
echo.
set /p action="Enter your choice (1-8): "

if "%action%"=="1" (
    echo.
    echo Starting Cloudflare Tunnel...
    powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" start
    echo.
    echo Tunnel started! Your URLs:
    powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" urls
)

if "%action%"=="2" (
    echo.
    echo Stopping Cloudflare Tunnel...
    powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" stop
)

if "%action%"=="3" (
    echo.
    echo Restarting Cloudflare Tunnel...
    powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" restart
)

if "%action%"=="4" (
    echo.
    powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" urls
)

if "%action%"=="5" (
    echo.
    powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" logs
)

if "%action%"=="6" (
    echo.
    powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" status
)

if "%action%"=="7" (
    echo.
    echo Running complete setup...
    powershell -ExecutionPolicy Bypass -File "setup-cloudflare-tunnel.ps1" -Install -Configure -Start
)

if "%action%"=="8" (
    exit /b
)

echo.
pause