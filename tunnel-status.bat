@echo off
title ROTC QR System - Tunnel Status
cd /d "C:\xampp\htdocs\generate qr"
powershell.exe -ExecutionPolicy Bypass -File "tunnel-service.ps1" -Status
pause
