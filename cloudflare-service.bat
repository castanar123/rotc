@echo off
setlocal enabledelayedexpansion
REM Cloudflare Tunnel Windows Service Manager
REM Simple interface for managing Cloudflare tunnel as a Windows service
REM This eliminates the terminal window issue by running as a proper service

cd /d "%~dp0"

echo ========================================
echo Cloudflare Tunnel Service Manager
echo ========================================
echo.
echo This tool manages Cloudflare tunnel as a Windows service
echo No more terminal windows that need to stay open!
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Administrator privileges required!
    echo.
    echo Please right-click this file and select "Run as administrator"
    echo.
    pause
    exit /b 1
)

echo Choose an action:
echo.
echo 1. Install service (one-time setup)
echo 2. Start tunnel service
echo 3. Stop tunnel service
echo 4. Restart tunnel service
echo 5. Check service status
echo 6. Uninstall service
echo 7. View service logs
echo 8. Exit
echo.
set /p choice="Enter your choice (1-8): "

if "%choice%"=="1" (
    echo.
    echo Installing Cloudflare Tunnel as Windows Service...
    echo This is a one-time setup process.
    echo.
    powershell -ExecutionPolicy Bypass -File "cloudflare-service-manager.ps1" -Action Install
    set install_result=%errorlevel%
    echo.
    if !install_result! equ 0 (
        echo ========================================
        echo SUCCESS: Service installed successfully!
        echo ========================================
        echo.
        echo You can now use option 2 to start the service.
        echo The service will automatically start on system boot.
        echo No more terminal windows will stay open!
    ) else (
        echo ========================================
        echo ERROR: Service installation failed!
        echo ========================================
        echo.
        echo Exit code: !install_result!
        echo Please check the logs\cloudflare-service.log for details.
        echo.
        echo Common issues:
        echo - Invalid cloudflared.exe path
        echo - Missing cloudflare-tunnel.yml config
        echo - Insufficient administrator privileges
    )
)

if "%choice%"=="2" (
    echo.
    echo Starting Cloudflare Tunnel service...
    powershell -ExecutionPolicy Bypass -File "cloudflare-service-manager.ps1" -Action Start
    if %errorlevel% equ 0 (
        echo.
        echo SUCCESS: Cloudflare Tunnel is now running as a service!
        echo No terminal windows will remain open.
        echo Your ROTC system is accessible at:
        echo   https://rotc.lspulbrotcunit.online
        echo   https://admin.lspulbrotcunit.online
    )
)

if "%choice%"=="3" (
    echo.
    echo Stopping Cloudflare Tunnel service...
    powershell -ExecutionPolicy Bypass -File "cloudflare-service-manager.ps1" -Action Stop
)

if "%choice%"=="4" (
    echo.
    echo Restarting Cloudflare Tunnel service...
    powershell -ExecutionPolicy Bypass -File "cloudflare-service-manager.ps1" -Action Restart
)

if "%choice%"=="5" (
    echo.
    echo Checking service status...
    powershell -ExecutionPolicy Bypass -File "cloudflare-service-manager.ps1" -Action Status
)

if "%choice%"=="6" (
    echo.
    echo WARNING: This will remove the Cloudflare Tunnel service.
    echo You will need to reinstall it to use the service again.
    echo.
    set /p confirm="Are you sure? (Y/N): "
    if /i "%confirm%"=="Y" (
        powershell -ExecutionPolicy Bypass -File "cloudflare-service-manager.ps1" -Action Uninstall
    ) else (
        echo Operation cancelled.
    )
)

if "%choice%"=="7" (
    echo.
    echo Opening service logs...
    if exist "logs\cloudflare-service.log" (
        notepad "logs\cloudflare-service.log"
    ) else (
        echo No log file found yet.
        echo Logs will be created after the service runs.
    )
)

if "%choice%"=="8" (
    exit /b 0
)

echo.
echo ========================================
echo Operation completed!
echo ========================================
echo.
echo Quick reference:
echo - Service runs completely in background
echo - No terminal windows remain open
echo - Automatically starts on system boot
echo - Survives user logoff and system restart
echo.
pause