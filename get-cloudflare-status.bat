@echo off
REM ROTC QR System - Quick Cloudflare Status Check

cd /d "%~dp0"

echo ========================================
echo ROTC QR System - Cloudflare Status
echo ========================================
echo.

if exist "cloudflare\cloudflared.exe" (
    powershell -ExecutionPolicy Bypass -File "cloudflare-tunnel-manager.ps1" status
) else (
    echo Cloudflare Tunnel not installed.
    echo Run start-cloudflare.bat to set up.
)

echo.
if exist "cloudflare-url.txt" (
    echo Current URLs:
    type cloudflare-url.txt
    echo.
)

pause