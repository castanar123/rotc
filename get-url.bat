@echo off
title ROTC QR System - Get URL
cd /d "C:\xampp\htdocs\generate qr"
echo Getting current tunnel URL...
powershell.exe -ExecutionPolicy Bypass -File "tunnel-service.ps1" -GetUrl
pause
