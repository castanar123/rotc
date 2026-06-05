@echo off
color 0A
echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║                    XAMPP AUTO-STARTUP MANAGER               ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.
echo  Current XAMPP Status:
echo  ────────────────────

REM Quick status check
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if %ERRORLEVEL% EQU 0 (
    echo  [✓] Apache: RUNNING
) else (
    echo  [✗] Apache: STOPPED
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul
if %ERRORLEVEL% EQU 0 (
    echo  [✓] MySQL: RUNNING
) else (
    echo  [✗] MySQL: STOPPED
)

echo.
echo  Available Actions:
echo  ─────────────────
echo.
echo  [1] Install Auto-Startup Solution (Recommended)
echo      └─ Makes XAMPP start automatically with Windows
echo      └─ Monitors and restarts services if they crash
echo      └─ Requires Administrator privileges
echo.
echo  [2] Quick Status Check
echo      └─ Check current XAMPP status without admin rights
echo.
echo  [3] Start XAMPP Manually (if stopped)
echo      └─ Start Apache and MySQL processes
echo.
echo  [4] Open XAMPP Control Panel
echo      └─ Traditional XAMPP management interface
echo.
echo  [5] View Setup Guide
echo      └─ Complete documentation and troubleshooting
echo.
echo  [0] Exit
echo.
set /p choice="Enter your choice (0-5): "

if "%choice%"=="1" goto install
if "%choice%"=="2" goto status
if "%choice%"=="3" goto start
if "%choice%"=="4" goto control
if "%choice%"=="5" goto guide
if "%choice%"=="0" goto exit
goto invalid

:install
echo.
echo Installing XAMPP Auto-Startup Solution...
echo ==========================================
echo.
echo This will:
echo - Install Apache and MySQL as Windows Services
echo - Configure automatic startup with Windows
echo - Create monitoring system to prevent crashes
echo - Add backup startup methods
echo.
echo NOTE: This requires Administrator privileges!
echo.
pause
echo.
echo Starting installation...
powershell -Command "Start-Process PowerShell -ArgumentList '-ExecutionPolicy Bypass -File \"%~dp0xampp-auto-startup-solution.ps1\" -Action install' -Verb RunAs"
echo.
echo Installation initiated! Check the PowerShell window for progress.
goto end

:status
echo.
echo Running detailed status check...
echo.
call "%~dp0quick-xampp-check.bat"
goto end

:start
echo.
echo Starting XAMPP services manually...
echo.
echo Starting Apache...
start "" "C:\xampp\apache\bin\httpd.exe"
echo.
echo Starting MySQL...
start "" "C:\xampp\mysql\bin\mysqld.exe" --console
echo.
echo Services started! Use option 2 to check status.
goto end

:control
echo.
echo Opening XAMPP Control Panel...
start "" "C:\xampp\xampp-control.exe"
echo.
echo XAMPP Control Panel opened.
goto end

:guide
echo.
echo Opening setup guide...
start "" notepad "%~dp0XAMPP_AUTO_STARTUP_GUIDE.md"
echo.
echo Guide opened in Notepad.
goto end

:invalid
echo.
echo Invalid choice! Please enter a number between 0-5.
echo.
pause
cls
goto start

:end
echo.
echo ────────────────────────────────────────────────────────────────
echo.
set /p again="Would you like to return to the main menu? (Y/N): "
if /i "%again%"=="Y" (
    cls
    goto start
)

:exit
echo.
echo Thank you for using XAMPP Auto-Startup Manager!
echo.
echo Remember:
echo - For permanent solution: Use option 1 (Install Auto-Startup)
echo - For quick checks: Use option 2 (Status Check)
echo - For help: Use option 5 (Setup Guide)
echo.
echo Your Cloudflare tunnel should now work reliably!
echo.
pause
exit