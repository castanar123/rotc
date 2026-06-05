@echo off
REM ROTC QR System - Silent Startup Launcher
REM This batch file launches the PowerShell script completely hidden
REM Place this file in: C:\Users\User\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup

REM Change to the script directory
cd /d "c:\xampp\htdocs\generate qr"

REM Launch PowerShell script completely hidden
powershell.exe -WindowStyle Hidden -ExecutionPolicy Bypass -File "ROTC-Silent-Startup.ps1"

REM Exit silently
exit /b 0