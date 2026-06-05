@echo off
title ROTC QR System - Enhanced Auto Ngrok Setup & Start
color 0A

echo ===============================================
echo    ROTC QR System - Enhanced Auto Launcher
echo ===============================================
echo.
echo Starting enhanced automatic ngrok setup...
echo.

REM Check if permanent tunnel service exists
if exist "%~dp0tunnel-service.ps1" (
    echo Enhanced tunnel service detected!
    echo Checking tunnel status...
    echo.
    
    REM Check if tunnel is already running
    powershell.exe -ExecutionPolicy Bypass -File "%~dp0tunnel-service.ps1" -Status
    
    echo.
    echo Would you like to use the enhanced tunnel service? (y/n)
    set /p useEnhanced=
    
    if /i "%useEnhanced%"=="y" (
        echo Starting enhanced tunnel service...
        powershell.exe -ExecutionPolicy Bypass -File "%~dp0tunnel-service.ps1" -Start
        goto :end
    )
)

REM Original auto-setup logic
REM Check if setup has been run
if not exist "ngrok\ngrok.exe" (
    echo Ngrok not found. Running initial setup...
    echo.
    powershell.exe -ExecutionPolicy Bypass -File "%~dp0setup-ngrok.ps1"
    if errorlevel 1 (
        echo.
        echo Setup failed. Please check the error messages above.
        pause
        exit /b 1
    )
)

echo.
echo Starting ngrok tunnel...
echo.
echo Tunnel will be available at: http://localhost:4040 (ngrok web interface)
echo Your public URL will be displayed below:
echo.

REM Start ngrok with the main QR project tunnel
"%~dp0ngrok\ngrok.exe" start --config="%~dp0ngrok-config.yml" qr-project

:end
REM If ngrok exits, show message
echo.
echo Ngrok tunnel has stopped.
echo.
echo Quick Commands Available:
echo   start-tunnel.bat    - Start enhanced tunnel
echo   stop-tunnel.bat     - Stop tunnel
echo   tunnel-status.bat   - Check status
echo   get-url.bat         - Get current URL
echo.
pause