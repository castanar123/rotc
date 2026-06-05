@echo off
REM Cloudflare Tunnel - Invisible Background Starter
REM This script starts Cloudflare tunnel completely invisibly
REM No windows remain open, tunnel runs independently

cd /d "%~dp0"

echo ========================================
echo Cloudflare Tunnel - Invisible Starter
echo ========================================
echo.
echo This tool starts Cloudflare tunnel invisibly in the background.
echo No terminal windows will remain open!
echo.

echo Choose an action:
echo.
echo 1. Start tunnel (invisible background)
echo 2. Stop tunnel
echo 3. Check tunnel status
echo 4. Restart tunnel
echo 5. View logs
echo 6. Exit
echo.
set /p choice="Enter your choice (1-6): "

if "%choice%"=="1" (
    echo.
    echo Starting Cloudflare tunnel invisibly...
    echo This will run completely in the background.
    echo.
    powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File "start-cloudflare-detached.ps1"
    if %errorlevel% equ 0 (
        echo.
        echo SUCCESS: Cloudflare tunnel started invisibly!
        echo.
        echo Key benefits:
        echo   ✓ No visible windows
        echo   ✓ Survives terminal closure
        echo   ✓ Runs completely independently
        echo.
        echo Your ROTC system is accessible at:
        echo   https://rotc.lspulbrotcunit.online
        echo   https://admin.lspulbrotcunit.online
        echo.
        echo This window will close in 5 seconds...
        timeout /t 5 /nobreak >nul
        exit /b 0
    ) else (
        echo.
        echo ERROR: Failed to start tunnel!
        echo Check the logs for details.
    )
)

if "%choice%"=="2" (
    echo.
    echo Stopping Cloudflare tunnel...
    powershell -ExecutionPolicy Bypass -File "start-cloudflare-detached.ps1" -Stop
    echo.
    echo Tunnel stopped.
)

if "%choice%"=="3" (
    echo.
    echo Checking tunnel status...
    powershell -ExecutionPolicy Bypass -File "start-cloudflare-detached.ps1" -Status
)

if "%choice%"=="4" (
    echo.
    echo Restarting Cloudflare tunnel...
    powershell -ExecutionPolicy Bypass -File "start-cloudflare-detached.ps1" -Restart
    if %errorlevel% equ 0 (
        echo.
        echo SUCCESS: Tunnel restarted invisibly!
        echo Your ROTC system is accessible at:
        echo   https://rotc.lspulbrotcunit.online
    )
)

if "%choice%"=="5" (
    echo.
    echo Opening tunnel logs...
    if exist "logs\cloudflare-detached.log" (
        notepad "logs\cloudflare-detached.log"
    ) else (
        echo No log file found yet.
        echo Logs will be created after the tunnel runs.
    )
)

if "%choice%"=="6" (
    exit /b 0
)

echo.
echo ========================================
echo.
echo Quick reference for command line usage:
echo   Start:   powershell -File start-cloudflare-detached.ps1
echo   Stop:    powershell -File start-cloudflare-detached.ps1 -Stop
echo   Status:  powershell -File start-cloudflare-detached.ps1 -Status
echo   Restart: powershell -File start-cloudflare-detached.ps1 -Restart
echo.
pause