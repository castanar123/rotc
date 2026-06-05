@echo off
color 0B
echo.
echo STEP 2: Update IONOS Nameservers
echo =====================================
echo 1. Log into your IONOS account
echo 2. Go to Domain ^& SSL -^> Domains
echo 3. Find your domain: lspulbrotcunit.online
echo 4. Click on the domain name
echo 5. Look for "Nameserver" or "DNS" settings
echo 6. IMPORTANT: Turn off DNSSEC if it's enabled
echo 7. Replace current nameservers:
echo.
echo    REMOVE these IONOS nameservers:
echo    - ns1091.ui-dns.org
echo    - ns1105.ui-dns.com
echo    - ns1124.ui-dns.de
echo    - ns1073.ui-dns.biz
echo.
echo    ADD these Cloudflare nameservers:
echo    - cheryl.ns.cloudflare.com
echo    - sevki.ns.cloudflare.com
echo.
echo 8. Save the changes
echo.
echo NOTE: DNS propagation can take up to 24 hours
echo.
pause
echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║              IONOS Domain → Cloudflare Setup                ║
echo  ║                lspulbrotcunit.online                         ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.
echo  This script will set up your IONOS domain with Cloudflare Tunnel
echo  for permanent, professional URLs that never change!
echo.
echo  📋 Prerequisites Checklist:
echo  ────────────────────────────
echo  [ ] Domain added to Cloudflare dashboard
echo  [ ] Nameservers updated in IONOS
echo  [ ] Domain shows "Active" in Cloudflare
echo  [ ] API Token created with proper permissions
echo.
echo  ❓ Need help with prerequisites? 
echo     Open: IONOS_TO_CLOUDFLARE_SETUP.md
echo.
set /p ready="Are all prerequisites completed? (Y/N): "
if /i not "%ready%"=="Y" (
    echo.
    echo Opening setup guide...
    start "" notepad "IONOS_TO_CLOUDFLARE_SETUP.md"
    echo.
    echo Please complete the prerequisites and run this script again.
    pause
    exit
)

echo.
echo  🔑 Cloudflare Credentials Required:
echo  ──────────────────────────────────
echo.
set /p API_TOKEN=Enter your Cloudflare API Token: 
echo.
set /p ACCOUNT_ID=Enter your Account ID: 
echo.
set /p ZONE_ID=Enter your Zone ID: 
echo.

REM Validate inputs
if "%API_TOKEN%"=="" (
    echo ❌ API Token is required!
    pause
    exit
)
if "%ACCOUNT_ID%"=="" (
    echo ❌ Account ID is required!
    pause
    exit
)
if "%ZONE_ID%"=="" (
    echo ❌ Zone ID is required!
    pause
    exit
)

echo.
echo  🚀 Setting up tunnel for: lspulbrotcunit.online
echo  ═══════════════════════════════════════════════
echo.
echo  This will create these permanent URLs:
echo  • https://rotc.lspulbrotcunit.online      (Main System)
echo  • https://admin.lspulbrotcunit.online     (Admin Dashboard)
echo  • https://scanner.lspulbrotcunit.online   (QR Scanner)
echo  • https://api.lspulbrotcunit.online       (API Endpoint)
echo.
set /p confirm="Proceed with setup? (Y/N): "
if /i not "%confirm%"=="Y" (
    echo Setup cancelled.
    pause
    exit
)

echo.
echo  ⚙️  Starting automated setup...
echo  ═══════════════════════════════
echo.

REM Check if XAMPP is running
echo [1/6] Checking XAMPP status...
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ XAMPP Apache is not running!
    echo.
    echo Starting XAMPP...
    call quick-xampp-check.bat
    echo.
    echo Please ensure XAMPP is running and try again.
    pause
    exit
)
echo ✅ XAMPP is running

echo.
echo [2/6] Running Cloudflare tunnel setup...
echo.

REM Run the PowerShell script with IONOS domain
powershell.exe -ExecutionPolicy Bypass -File "setup-automated-cloudflare-tunnel.ps1" -CloudflareApiToken "%API_TOKEN%" -AccountId "%ACCOUNT_ID%" -ZoneId "%ZONE_ID%" -Domain "lspulbrotcunit.online"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo  🎉 SUCCESS! Your IONOS domain is now connected!
    echo  ═══════════════════════════════════════════════
    echo.
    echo  📱 Your Permanent URLs:
    echo  ────────────────────────
    echo  Main System:     https://rotc.lspulbrotcunit.online
    echo  Admin Dashboard: https://admin.lspulbrotcunit.online
    echo  QR Scanner:      https://scanner.lspulbrotcunit.online
    echo  API Endpoint:    https://api.lspulbrotcunit.online
    echo.
    echo  ✅ Benefits:
    echo  • URLs never change (permanent)
    echo  • No re-authentication needed
    echo  • Professional domain name
    echo  • Automatic startup with Windows
    echo  • SSL/HTTPS enabled
    echo.
    echo  📋 Next Steps:
    echo  1. Test all URLs in your browser
    echo  2. Share the main URL: https://rotc.lspulbrotcunit.online
    echo  3. Bookmark admin panel: https://admin.lspulbrotcunit.online
    echo.
    echo  🔧 Management Commands:
    echo  • Check status: tunnel-status.bat
    echo  • XAMPP status: quick-xampp-check.bat
    echo  • Full management: xampp-manager.bat
    echo.
) else (
    echo.
    echo  ❌ Setup encountered an error!
    echo  ═══════════════════════════════
    echo.
    echo  🔍 Troubleshooting:
    echo  1. Verify your API token has correct permissions
    echo  2. Check that domain is "Active" in Cloudflare
    echo  3. Ensure nameservers are updated in IONOS
    echo  4. Review IONOS_TO_CLOUDFLARE_SETUP.md
    echo.
    echo  💡 Common Issues:
    echo  • Domain not yet active in Cloudflare (wait 2-48 hours)
    echo  • API token missing permissions
    echo  • Incorrect Account/Zone ID
    echo.
)

echo.
echo Press any key to exit...
pause > nul