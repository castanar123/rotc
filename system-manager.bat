@echo off
REM ROTC QR System - Complete System Manager
REM This script provides a unified interface for managing the entire system

setlocal enabledelayedexpansion

echo ========================================
echo ROTC QR System - System Manager
echo ========================================
echo.

:main_menu
echo Select an option:
echo.
echo [1] Quick Status Check
echo [2] Start All Services
echo [3] Stop All Services
echo [4] Restart All Services
echo [5] Setup Auto-Startup (One-time)
echo [6] Install as Windows Services
echo [7] View Current URLs
echo [8] Advanced Management
echo [9] Troubleshooting
echo [0] Exit
echo.
set /p choice="Enter your choice (0-9): "

if "%choice%"=="1" goto :status_check
if "%choice%"=="2" goto :start_all
if "%choice%"=="3" goto :stop_all
if "%choice%"=="4" goto :restart_all
if "%choice%"=="5" goto :setup_startup
if "%choice%"=="6" goto :install_services
if "%choice%"=="7" goto :view_urls
if "%choice%"=="8" goto :advanced_menu
if "%choice%"=="9" goto :troubleshooting
if "%choice%"=="0" goto :exit

echo Invalid choice. Please try again.
echo.
goto :main_menu

:status_check
echo.
echo ========================================
echo System Status Check
echo ========================================
echo.

echo Checking XAMPP Services...
echo --------------------------
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Apache: Running
) else (
    echo ✗ Apache: Not Running
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ MySQL: Running
) else (
    echo ✗ MySQL: Not Running
)

echo.
echo Checking Cloudflare Tunnel...
echo -----------------------------
tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL | find /I /N "cloudflared.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Cloudflare Tunnel: Running
) else (
    echo ✗ Cloudflare Tunnel: Not Running
)

echo.
echo Testing Localhost Connection...
echo ------------------------------
powershell -Command "try { Invoke-WebRequest -Uri 'http://localhost' -TimeoutSec 5 -UseBasicParsing | Out-Null; Write-Host '✓ Localhost: Accessible' } catch { Write-Host '✗ Localhost: Not Accessible' }"

echo.
if exist "cloudflare-url.txt" (
    echo Current Public URLs:
    echo -------------------
    findstr /C:"Main URL:" cloudflare-url.txt 2>nul
    if errorlevel 1 echo No URLs found in cloudflare-url.txt
) else (
    echo No public URLs available yet
)

echo.
echo Press any key to return to main menu...
pause >nul
goto :main_menu

:start_all
echo.
echo ========================================
echo Starting All Services
echo ========================================
echo.

echo [1/3] Starting XAMPP...
echo ------------------------
call auto-startup-complete.bat

echo.
echo [2/3] Starting Cloudflare Tunnel...
echo -----------------------------------
if exist "setup-persistent-tunnel.ps1" (
    powershell -ExecutionPolicy Bypass -File "setup-persistent-tunnel.ps1" -Start
) else (
    echo Persistent tunnel script not found, using quick tunnel...
    if exist "cloudflare\cloudflared.exe" (
        start "Cloudflare Tunnel" /MIN "cloudflare\cloudflared.exe" tunnel --url http://localhost:80
    ) else (
        echo Cloudflare not installed. Run option 5 first.
    )
)

echo.
echo [3/3] Final Status Check...
echo ----------------------------
timeout /t 5 /nobreak >nul
goto :status_check

:stop_all
echo.
echo ========================================
echo Stopping All Services
echo ========================================
echo.

echo Stopping Cloudflare Tunnel...
echo -----------------------------
tasklist /FI "IMAGENAME eq cloudflared.exe" >nul 2>&1
if not errorlevel 1 (
    taskkill /F /IM cloudflared.exe >nul 2>&1
    echo ✓ Cloudflare tunnel stopped
) else (
    echo Cloudflare tunnel was not running
)

echo.
echo Stopping XAMPP Services...
echo -------------------------
if exist "setup-xampp-service.ps1" (
    powershell -ExecutionPolicy Bypass -File "setup-xampp-service.ps1" -Stop
) else (
    echo Stopping Apache...
    taskkill /F /IM httpd.exe >nul 2>&1
    echo Stopping MySQL...
    taskkill /F /IM mysqld.exe >nul 2>&1
)

echo.
echo All services stopped.
echo.
echo Press any key to return to main menu...
pause >nul
goto :main_menu

:restart_all
echo.
echo ========================================
echo Restarting All Services
echo ========================================
echo.

echo Stopping services first...
call :stop_all_silent

echo.
echo Waiting 5 seconds...
timeout /t 5 /nobreak >nul

echo.
echo Starting services...
goto :start_all

:stop_all_silent
taskkill /F /IM cloudflared.exe >nul 2>&1
taskkill /F /IM httpd.exe >nul 2>&1
taskkill /F /IM mysqld.exe >nul 2>&1
return

:setup_startup
echo.
echo ========================================
echo Auto-Startup Setup
echo ========================================
echo.
echo This will set up your system to start automatically on boot.
echo.
echo Choose setup method:
echo [1] Windows Startup Folder (Easy)
echo [2] Windows Services (Recommended)
echo [3] Both (Maximum Reliability)
echo [0] Back to main menu
echo.
set /p startup_choice="Enter your choice (0-3): "

if "%startup_choice%"=="1" goto :setup_startup_folder
if "%startup_choice%"=="2" goto :setup_services
if "%startup_choice%"=="3" goto :setup_both
if "%startup_choice%"=="0" goto :main_menu

echo Invalid choice.
goto :setup_startup

:setup_startup_folder
echo.
echo Setting up Windows Startup Folder...
echo.
echo Opening Windows Startup folder...
echo Copy 'auto-startup-complete.bat' to the folder that opens.
echo.
start shell:startup
echo.
echo Instructions:
echo 1. A folder window will open
echo 2. Copy 'auto-startup-complete.bat' from this directory
echo 3. Paste it into the Startup folder
echo 4. Your system will start automatically when you log in
echo.
echo Press any key when done...
pause >nul
goto :main_menu

:setup_services
echo.
echo Setting up Windows Services...
echo.
echo This requires Administrator privileges.
echo.
powershell -Command "Start-Process PowerShell -ArgumentList '-ExecutionPolicy Bypass -File setup-xampp-service.ps1 -Install' -Verb RunAs"
echo.
echo XAMPP service installation initiated...
echo.
powershell -Command "Start-Process PowerShell -ArgumentList '-ExecutionPolicy Bypass -File cloudflare-service.ps1 -Install' -Verb RunAs"
echo.
echo Cloudflare service installation initiated...
echo.
echo Services are being installed. Check the PowerShell windows for progress.
echo.
echo Press any key to continue...
pause >nul
goto :main_menu

:setup_both
echo.
echo Setting up both methods for maximum reliability...
echo.
call :setup_startup_folder
call :setup_services
goto :main_menu

:install_services
echo.
echo ========================================
echo Install as Windows Services
echo ========================================
echo.
echo This will install XAMPP and Cloudflare as Windows services.
echo Services start automatically and are more reliable.
echo.
echo Requires Administrator privileges.
echo.
echo Continue? (Y/N)
set /p confirm=""
if /i not "%confirm%"=="Y" goto :main_menu

echo.
echo Installing XAMPP as service...
powershell -Command "Start-Process PowerShell -ArgumentList '-ExecutionPolicy Bypass -File setup-xampp-service.ps1 -Install' -Verb RunAs -Wait"

echo.
echo Setting up persistent Cloudflare tunnel...
powershell -ExecutionPolicy Bypass -File "setup-persistent-tunnel.ps1" -Setup

echo.
echo Installing Cloudflare as service...
powershell -Command "Start-Process PowerShell -ArgumentList '-ExecutionPolicy Bypass -File cloudflare-service.ps1 -Install' -Verb RunAs -Wait"

echo.
echo Installation complete!
echo.
echo Press any key to return to main menu...
pause >nul
goto :main_menu

:view_urls
echo.
echo ========================================
echo Current System URLs
echo ========================================
echo.

if exist "cloudflare-url.txt" (
    type cloudflare-url.txt
) else (
    echo No Cloudflare URLs file found.
    echo.
    echo Local access: http://localhost
    echo.
    echo To get public URLs:
    echo 1. Run option 2 (Start All Services)
    echo 2. Wait for tunnel to establish
    echo 3. Check this option again
)

echo.
echo Press any key to return to main menu...
pause >nul
goto :main_menu

:advanced_menu
echo.
echo ========================================
echo Advanced Management
echo ========================================
echo.
echo [1] XAMPP Service Management
echo [2] Cloudflare Tunnel Management
echo [3] View System Logs
echo [4] Network Diagnostics
echo [5] Reset Configuration
echo [0] Back to main menu
echo.
set /p adv_choice="Enter your choice (0-5): "

if "%adv_choice%"=="1" goto :xampp_management
if "%adv_choice%"=="2" goto :cloudflare_management
if "%adv_choice%"=="3" goto :view_logs
if "%adv_choice%"=="4" goto :network_diagnostics
if "%adv_choice%"=="5" goto :reset_config
if "%adv_choice%"=="0" goto :main_menu

echo Invalid choice.
goto :advanced_menu

:xampp_management
echo.
echo XAMPP Service Management
echo ========================
echo.
if exist "setup-xampp-service.ps1" (
    powershell -ExecutionPolicy Bypass -File "setup-xampp-service.ps1" -Status
    echo.
    echo Available commands:
    echo - setup-xampp-service.ps1 -Start
    echo - setup-xampp-service.ps1 -Stop
    echo - setup-xampp-service.ps1 -Install
    echo - setup-xampp-service.ps1 -Uninstall
) else (
    echo XAMPP service script not found.
)
echo.
echo Press any key to return...
pause >nul
goto :advanced_menu

:cloudflare_management
echo.
echo Cloudflare Tunnel Management
echo ============================
echo.
if exist "setup-persistent-tunnel.ps1" (
    powershell -ExecutionPolicy Bypass -File "setup-persistent-tunnel.ps1" -Status
    echo.
    echo Available commands:
    echo - setup-persistent-tunnel.ps1 -Start
    echo - setup-persistent-tunnel.ps1 -Stop
    echo - setup-persistent-tunnel.ps1 -Setup
) else (
    echo Persistent tunnel script not found.
)
echo.
echo Press any key to return...
pause >nul
goto :advanced_menu

:view_logs
echo.
echo System Logs
echo ===========
echo.
echo [1] XAMPP Error Log
echo [2] Cloudflare Tunnel Log
echo [3] System Log
echo [0] Back
echo.
set /p log_choice="Enter your choice (0-3): "

if "%log_choice%"=="1" (
    if exist "C:\xampp\apache\logs\error.log" (
        echo.
        echo Latest XAMPP errors:
        echo --------------------
        powershell -Command "Get-Content 'C:\xampp\apache\logs\error.log' -Tail 20"
    ) else (
        echo XAMPP error log not found.
    )
)

if "%log_choice%"=="2" (
    if exist "cloudflare-tunnel.log" (
        echo.
        echo Latest Cloudflare tunnel log:
        echo -----------------------------
        type cloudflare-tunnel.log
    ) else (
        echo Cloudflare tunnel log not found.
    )
)

if "%log_choice%"=="3" (
    if exist "logs\system.log" (
        echo.
        echo Latest system log:
        echo ------------------
        type logs\system.log
    ) else (
        echo System log not found.
    )
)

if "%log_choice%"=="0" goto :advanced_menu

echo.
echo Press any key to return...
pause >nul
goto :advanced_menu

:network_diagnostics
echo.
echo Network Diagnostics
echo ===================
echo.
echo Testing network connectivity...
echo.
echo Localhost test:
ping -n 1 127.0.0.1 >nul && echo ✓ Localhost OK || echo ✗ Localhost failed
echo.
echo Internet connectivity:
ping -n 1 8.8.8.8 >nul && echo ✓ Internet OK || echo ✗ Internet failed
echo.
echo Port availability:
netstat -an | findstr :80 >nul && echo ✓ Port 80 in use || echo ✗ Port 80 available
netstat -an | findstr :3306 >nul && echo ✓ Port 3306 in use || echo ✗ Port 3306 available
echo.
echo Press any key to return...
pause >nul
goto :advanced_menu

:reset_config
echo.
echo Reset Configuration
echo ==================
echo.
echo WARNING: This will reset tunnel configuration.
echo You will need to re-authenticate with Cloudflare.
echo.
echo Continue? (Y/N)
set /p reset_confirm=""
if /i not "%reset_confirm%"=="Y" goto :advanced_menu

echo.
echo Stopping services...
call :stop_all_silent

echo Removing configuration files...
if exist "cloudflare-tunnel.yml" del "cloudflare-tunnel.yml"
if exist "cloudflare-url.txt" del "cloudflare-url.txt"
if exist "%USERPROFILE%\.cloudflared" rmdir /s /q "%USERPROFILE%\.cloudflared"

echo.
echo Configuration reset complete.
echo Run option 5 to set up again.
echo.
echo Press any key to return...
pause >nul
goto :advanced_menu

:troubleshooting
echo.
echo ========================================
echo Troubleshooting Guide
echo ========================================
echo.
echo Common Issues and Solutions:
echo.
echo 1. Apache won't start:
echo    - Check if port 80 is in use
echo    - Stop IIS: net stop iisadmin
echo    - Check Windows features
echo.
echo 2. MySQL won't start:
echo    - Check if port 3306 is in use
echo    - Verify MySQL service status
echo.
echo 3. Cloudflare tunnel fails:
echo    - Check internet connection
echo    - Re-authenticate: setup-persistent-tunnel.ps1 -Setup
echo    - Try quick tunnel as fallback
echo.
echo 4. Permission errors:
echo    - Run PowerShell as Administrator
echo    - Check file permissions
echo.
echo 5. Services don't auto-start:
echo    - Verify Windows services are installed
echo    - Check startup folder
echo.
echo For detailed diagnostics, use Advanced Management menu.
echo.
echo Press any key to return to main menu...
pause >nul
goto :main_menu

:exit
echo.
echo Thank you for using ROTC QR System Manager!
echo.
exit /b 0