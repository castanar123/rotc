@echo off
echo XAMPP Auto-Startup Setup
echo ========================
echo.
echo This will configure XAMPP to automatically start with Windows
echo and monitor services to prevent them from stopping.
echo.
echo Requirements:
 echo - Administrator privileges
 echo - XAMPP installed in C:\xampp (default location)
echo.
pause

echo.
echo Running XAMPP auto-startup installation...
echo.

REM Run PowerShell script as administrator
powershell -Command "Start-Process PowerShell -ArgumentList '-ExecutionPolicy Bypass -File "%~dp0xampp-auto-startup-solution.ps1" -Action install' -Verb RunAs"

echo.
echo Setup completed! Check the PowerShell window for results.
echo.
echo To check status later, run: setup-xampp-autostart.bat status
echo To uninstall, run: setup-xampp-autostart.bat uninstall
echo.
pause