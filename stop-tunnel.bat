@echo off
title ROTC QR System - Stop Tunnel
cd /d "C:\xampp\htdocs\generate qr"
powershell.exe -ExecutionPolicy Bypass -File "tunnel-service.ps1" -Stop
pause
