@echo off
REM ROTC QR System - Simple Cloudflare Tunnel Starter
REM This script starts Cloudflare tunnel without auto-close issues

cd /d "%~dp0"

echo ========================================
echo ROTC QR System - Simple Cloudflare Start
echo ========================================
echo.

REM Check if cloudflared.exe exists
if not exist "cloudflare\cloudflared.exe" (
    echo [ERROR] cloudflared.exe not found!
    echo Please run setup first: setup-cloudflare-tunnel.ps1
    echo.
    pause
    exit /b 1
)

REM Check if config file exists
if not exist "cloudflare-tunnel.yml" (
    echo [ERROR] cloudflare-tunnel.yml not found!
    echo Please run setup first: setup-cloudflare-tunnel.ps1
    echo.
    pause
    exit /b 1
)

REM Check if tunnel is already running
echo [INFO] Checking if Cloudflare tunnel is already running...
tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL | find /I /N "cloudflared.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] Cloudflare tunnel is already running!
    echo.
    echo Your URLs:
    echo - Online: https://rotc.lspulbrotcunit.online
    echo - Admin:  https://admin.lspulbrotcunit.online
    echo.
    echo Press any key to exit...
    pause >nul
    exit /b 0
)

echo [INFO] Starting Cloudflare tunnel...
echo.

REM Start tunnel in background
start "Cloudflare Tunnel" /MIN cloudflare\cloudflared.exe tunnel --config cloudflare-tunnel.yml run

REM Wait for tunnel to initialize
echo [INFO] Waiting for tunnel to start...
timeout /t 5 /nobreak >nul

REM Check if tunnel started successfully
tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL | find /I /N "cloudflared.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] Cloudflare tunnel started successfully!
    echo.
    echo Your URLs:
    echo - Online: https://rotc.lspulbrotcunit.online
    echo - Admin:  https://admin.lspulbrotcunit.online
    echo.
    echo [INFO] Tunnel is now running in the background.
    echo [INFO] You can close this window safely.
) else (
    echo [ERROR] Failed to start Cloudflare tunnel!
    echo Please check the configuration and try again.
)

echo.
echo Press any key to exit...
pause >nul
exit /b 0