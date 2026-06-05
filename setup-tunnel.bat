@echo off
echo ========================================
echo   ROTC QR System - Cloudflare Tunnel
echo   Automated Setup (No Re-authentication)
echo ========================================
echo.
echo This script will set up a PERMANENT tunnel with URLs that NEVER change!
echo.
echo You will need:
echo 1. Cloudflare API Token
echo 2. Account ID
echo 3. Zone ID  
echo 4. Your domain name
echo.
echo See CLOUDFLARE_SETUP_GUIDE.md for detailed instructions.
echo.
pause
echo.

REM Get user input
set /p API_TOKEN=Enter your Cloudflare API Token: 
set /p ACCOUNT_ID=Enter your Account ID: 
set /p ZONE_ID=Enter your Zone ID: 
set /p DOMAIN=Enter your domain (e.g., myapp.com): 

echo.
echo Setting up tunnel with:
echo Domain: %DOMAIN%
echo Account: %ACCOUNT_ID%
echo Zone: %ZONE_ID%
echo.
echo Starting setup...
echo.

REM Run the PowerShell script
powershell.exe -ExecutionPolicy Bypass -File "setup-automated-cloudflare-tunnel.ps1" -CloudflareApiToken "%API_TOKEN%" -AccountId "%ACCOUNT_ID%" -ZoneId "%ZONE_ID%" -Domain "%DOMAIN%"

echo.
echo Setup completed! Check the output above for your permanent URLs.
pause