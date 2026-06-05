@echo off
title ROTC QR System - Start Tunnel
cd /d "C:\xampp\htdocs\generate qr"
powershell.exe -ExecutionPolicy Bypass -File "tunnel-service.ps1" -Start
pause
