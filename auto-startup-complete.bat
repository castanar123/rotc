@echo off
REM ROTC QR System - Manual Complete Startup Script
REM This script manually starts XAMPP and Cloudflare Tunnel
REM No auto-startup functionality - manual execution only

setlocal enabledelayedexpansion

echo ========================================
echo ROTC QR System - Manual Startup
echo ========================================
echo Starting services manually...
echo.

REM Change to script directory
cd /d "%~dp0"

REM Step 1: Start XAMPP Services
echo [1/3] Starting XAMPP Services...
echo =====================================

REM Check if Apache is already running
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Apache is already running
) else (
    echo Starting Apache...
    start "" "C:\xampp\apache\bin\httpd.exe" -k start
    timeout /t 3 /nobreak >nul
)

REM Check if MySQL is already running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ MySQL is already running
) else (
    echo Starting MySQL...
    start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    timeout /t 5 /nobreak >nul
)

echo.
echo [2/3] Waiting for XAMPP to initialize...
timeout /t 10 /nobreak >nul

REM Verify XAMPP is running
echo Checking XAMPP status...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Apache is running
) else (
    echo ✗ Apache failed to start
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ MySQL is running
) else (
    echo ✗ MySQL failed to start
)

REM Test localhost
echo Testing localhost connection...
powershell -Command "try { Invoke-WebRequest -Uri 'http://localhost' -TimeoutSec 5 -UseBasicParsing | Out-Null; Write-Host '✓ Localhost is accessible' } catch { Write-Host '✗ Localhost test failed' }"

echo.
echo [3/3] Starting Cloudflare Tunnel...
echo =====================================

REM Check if tunnel is already running
tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL | find /I /N "cloudflared.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Cloudflare tunnel is already running
    goto :show_urls
)

REM Start the persistent tunnel
if exist "cloudflare\cloudflared.exe" (
    if exist "cloudflare-tunnel.yml" (
        echo Starting persistent tunnel...
        start "Cloudflare Tunnel" /min "cloudflare\cloudflared.exe" tunnel --config cloudflare-tunnel.yml run
        timeout /t 8 /nobreak >nul
        
        REM Check if it started successfully
        tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL | find /I /N "cloudflared.exe">NUL
        if "%ERRORLEVEL%"=="0" (
            echo ✓ Persistent tunnel started successfully
        ) else (
            echo ✗ Tunnel failed to start
        )
    ) else (
        echo ✗ cloudflare-tunnel.yml not found
        echo Please run tunnel setup first
    )
) else (
    echo ✗ Cloudflare not installed
    echo Run setup-cloudflare-tunnel.ps1 first
)

:show_urls
echo.
echo ========================================
echo Manual Startup Complete!
echo ========================================
echo.
echo Your ROTC QR System is now accessible at:
echo • Local: http://localhost
echo • Online: https://rotc.lspulbrotcunit.online (if tunnel is running)
echo • Admin: https://admin.lspulbrotcunit.online (if tunnel is running)
echo.
echo Services Status:
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Apache: Running
) else (
    echo ✗ Apache: Not running
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ MySQL: Running
) else (
    echo ✗ MySQL: Not running
)

tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL | find /I /N "cloudflared.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Cloudflare Tunnel: Running
) else (
    echo ✗ Cloudflare Tunnel: Not running
)

echo.
echo Note: No auto-startup configured
echo • All services must be started manually each time
echo • No Windows startup scripts are active
echo.
echo Management Commands:
echo -------------------
echo • XAMPP Control: C:\xampp\xampp-control.exe
echo • System Manager: system-manager.bat
echo.
echo Press any key to exit...
pause >nul

exit /b 0