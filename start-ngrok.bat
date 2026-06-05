@echo off
title ROTC QR System - Ngrok Tunnel
echo Starting ROTC QR System Ngrok Tunnel...
powershell.exe -ExecutionPolicy Bypass -File "%~dp0start-ngrok.ps1"
pause
