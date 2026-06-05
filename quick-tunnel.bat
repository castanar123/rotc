@echo off
echo ========================================
echo   ROTC QR System - Quick Tunnel
echo   Temporary URLs (No Domain Required)
echo ========================================
echo.
echo This will create TEMPORARY tunnel URLs for immediate testing.
echo No domain or authentication required!
echo.
echo Note: URLs will change every time you restart the tunnel.
echo For permanent URLs, use setup-tunnel.bat instead.
echo.
pause
echo.
echo Starting quick tunnel...
echo.

REM Run the PowerShell script
powershell.exe -ExecutionPolicy Bypass -File "quick-tunnel.ps1"

echo.
echo Quick tunnel setup completed!
echo.
echo To stop the tunnel later, run:
echo powershell.exe -ExecutionPolicy Bypass -Command "Import-Module .\quick-tunnel.ps1; Stop-QuickTunnel"
echo.
pause