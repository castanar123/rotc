@echo off
setlocal enabledelayedexpansion
REM ========================================
REM ROTC QR System - Complete Auto-Startup
REM ========================================
REM This script starts XAMPP services first, then Cloudflare tunnel
REM Created for permanent domain access: https://rotc.lspulbrotcunit.online

echo.
echo ========================================
echo ROTC QR System - Complete Auto-Startup
echo ========================================
echo.

REM Change to the correct directory
cd /d "c:\xampp\htdocs\generate qr"

REM Step 1: Start XAMPP Services
echo [INFO] Step 1: Starting XAMPP services...
echo.

REM Check if Apache is running
echo [INFO] Checking Apache status...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] Apache is already running!
    goto :check_mysql
)

echo [INFO] Starting Apache...
if not exist "c:\xampp\apache\bin\httpd.exe" (
    echo [ERROR] Apache executable not found!
    goto :error
)

start /B "Apache" "c:\xampp\apache\bin\httpd.exe"
timeout /t 2 /nobreak >nul

REM Wait and check multiple times for Apache to start
set APACHE_ATTEMPTS=0
:check_apache_loop
set /a APACHE_ATTEMPTS+=1
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] Apache started successfully!
    goto :check_mysql
)
if %APACHE_ATTEMPTS% LSS 3 (
    timeout /t 2 /nobreak >nul
    goto :check_apache_loop
)
echo [ERROR] Failed to start Apache after multiple attempts!
goto :error

:check_mysql

REM Check if MySQL is running
echo [INFO] Checking MySQL status...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] MySQL is already running!
    goto :start_cloudflare
)

echo [INFO] Starting MySQL...
if not exist "c:\xampp\mysql\bin\mysqld.exe" (
    echo [ERROR] MySQL executable not found!
    goto :error
)

start /B "MySQL" "c:\xampp\mysql\bin\mysqld.exe" --defaults-file="c:\xampp\mysql\bin\my.ini" --standalone
timeout /t 3 /nobreak >nul

REM Wait and check multiple times for MySQL to start
set MYSQL_ATTEMPTS=0
:check_mysql_loop
set /a MYSQL_ATTEMPTS+=1
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] MySQL started successfully!
    goto :start_cloudflare
)
if %MYSQL_ATTEMPTS% LSS 5 (
    timeout /t 2 /nobreak >nul
    goto :check_mysql_loop
)
echo [ERROR] Failed to start MySQL after multiple attempts!
goto :error

:start_cloudflare

REM Wait for services to fully initialize
echo [INFO] Waiting for services to initialize...
timeout /t 5 /nobreak >nul

REM Test localhost connectivity
echo [INFO] Testing localhost connectivity...
powershell -Command "try { $response = Invoke-WebRequest -Uri 'http://localhost' -Method Head -TimeoutSec 10; if ($response.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }"
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] Localhost is accessible!
) else (
    echo [ERROR] Localhost is not accessible!
    echo [ERROR] XAMPP services may not be working properly.
    goto :error
)

echo.
echo [INFO] Step 2: Starting Cloudflare tunnel...
echo.

REM Check if Cloudflare tunnel is already running
echo [INFO] Checking if Cloudflare tunnel is already running...
tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL | find /I /N "cloudflared.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] Cloudflare tunnel is already running!
    goto :success
)

REM Check if cloudflared.exe exists
if not exist "cloudflare\cloudflared.exe" (
    echo [ERROR] cloudflared.exe not found in cloudflare\ directory!
    echo [ERROR] Please ensure Cloudflare tunnel is properly installed.
    goto :error
)

REM Check if config file exists
if not exist "cloudflare-tunnel.yml" (
    echo [ERROR] cloudflare-tunnel.yml configuration file not found!
    echo [ERROR] Please ensure tunnel configuration is properly set up.
    goto :error
)

echo [INFO] Starting Cloudflare tunnel...
echo [INFO] This may take a few seconds to establish connections...
echo.

REM Start Cloudflare tunnel using PowerShell persistent launcher
echo [INFO] Starting Cloudflare tunnel as detached background process...
start "Cloudflare Tunnel" /MIN /D "%CD%" cloudflare\cloudflared.exe tunnel --config cloudflare-tunnel.yml run

REM Wait for tunnel to initialize
echo [INFO] Waiting for tunnel to initialize...
timeout /t 10 /nobreak >nul

REM Check if tunnel started successfully
tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL | find /I /N "cloudflared.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] Cloudflare tunnel started successfully!
    goto :success
) else (
    echo [ERROR] Failed to start Cloudflare tunnel!
    echo [ERROR] Please check the configuration and try again.
    goto :error
)

:success
echo.
echo ========================================
echo AUTO-STARTUP COMPLETE - ALL SERVICES RUNNING
echo ========================================
echo.
echo XAMPP Services:
echo - Apache: RUNNING
echo - MySQL: RUNNING
echo - Localhost: http://localhost
echo.
echo Cloudflare Tunnel:
echo - Status: RUNNING
echo - Online: https://rotc.lspulbrotcunit.online
echo - Admin:  https://admin.lspulbrotcunit.online
echo.
echo [SUCCESS] ROTC QR System is now fully operational!
echo [INFO] All services started automatically on boot.
echo [INFO] System is accessible both locally and worldwide.
echo.
echo [INFO] Window will close automatically in 3 seconds...
echo.
for /l %%i in (3,-1,1) do (
    echo Closing in %%i seconds...
    timeout /t 1 /nobreak >nul
)
echo.
echo [INFO] Auto-closing now...
taskkill /f /im cmd.exe /fi "windowtitle eq auto-start-cloudflare.bat*" >nul 2>&1
exit

:error
echo.
echo ========================================
echo AUTO-STARTUP FAILED
echo ========================================
echo.
echo Please check:
echo 1. XAMPP installation (Apache and MySQL)
echo 2. Cloudflare tunnel installation
echo 3. Configuration files
echo 4. Network connectivity
echo.
echo Manual commands:
echo - Start XAMPP: Use XAMPP Control Panel
echo - Start tunnel: cloudflare\cloudflared.exe tunnel --config cloudflare-tunnel.yml run
echo.
echo [ERROR] Auto-startup failed! Please check the errors above.
echo.
pause
exit /b 1