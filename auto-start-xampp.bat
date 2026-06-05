@echo off
REM ROTC QR System - Manual XAMPP Startup Script
REM This script manually starts XAMPP services (Apache and MySQL)
REM No auto-startup functionality - manual execution only

setlocal enabledelayedexpansion

echo ========================================
echo ROTC QR System - Manual XAMPP Startup
echo ========================================
echo Starting XAMPP services manually...
echo.

REM Change to script directory
cd /d "%~dp0"

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
echo Waiting for XAMPP to initialize...
timeout /t 5 /nobreak >nul

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
echo ========================================
echo Manual XAMPP Startup Complete!
echo ========================================
echo.
echo Your ROTC QR System is accessible at:
echo • Local: http://localhost
echo.
echo Note: No auto-startup configured
echo • Services must be started manually each time
echo • Use XAMPP Control Panel for service management
echo.
echo XAMPP Control Panel: C:\xampp\xampp-control.exe
echo.
echo Press any key to exit...
pause >nul

exit /b 0