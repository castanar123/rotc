# Cloudflare Tunnel Startup Guide

## Overview
This guide provides multiple ways to start your Cloudflare tunnel for the ROTC QR System, including solutions for the auto-close issue.

## Available Startup Methods

### 1. Fixed Auto-Startup Script (Recommended for Auto-Boot)
**File:** `auto-start-cloudflare.bat`
- **Fixed Issue:** Now properly closes after 3 seconds
- **Use Case:** Automatic startup on system boot
- **Features:** Starts XAMPP + Cloudflare tunnel together

```batch
# Double-click to run
auto-start-cloudflare.bat
```

### 2. Simple Cloudflare Starter (Recommended for Manual Use)
**File:** `start-cloudflare-simple.bat`
- **No Auto-Close Issues:** Uses manual pause instead
- **Use Case:** Quick manual startup
- **Features:** Clean interface, safe window closing

```batch
# Double-click to run
start-cloudflare-simple.bat
```

### 3. PowerShell Starter (Most Reliable)
**File:** `Start-CloudflareTunnel.ps1`
- **Best Error Handling:** Advanced PowerShell features
- **Use Case:** Advanced users, scripting
- **Features:** Colored output, background mode, silent mode

```powershell
# Basic usage
.\Start-CloudflareTunnel.ps1

# Background mode (hidden window)
.\Start-CloudflareTunnel.ps1 -Background

# Silent mode (no prompts)
.\Start-CloudflareTunnel.ps1 -Silent
```

### 4. Interactive Menu (Full Control)
**File:** `start-cloudflare.bat`
- **Full Control:** Start, stop, restart, status
- **Use Case:** Management and troubleshooting
- **Features:** Menu-driven interface

```batch
# Double-click to run
start-cloudflare.bat
```

### 5. Persistent Background (Advanced)
**File:** `start-cloudflare-persistent.ps1`
- **Survives Terminal Closure:** Detached process
- **Use Case:** Server environments
- **Features:** Independent background process

```powershell
.\start-cloudflare-persistent.ps1
```

## Quick Start Recommendations

### For Daily Use
1. **Simple Startup:** Use `start-cloudflare-simple.bat`
2. **No auto-close issues**
3. **Clean and reliable**

### For Auto-Boot Setup
1. **Fixed Auto-Startup:** Use `auto-start-cloudflare.bat`
2. **Now properly closes after 3 seconds**
3. **Starts both XAMPP and Cloudflare**

### For Advanced Users
1. **PowerShell Starter:** Use `Start-CloudflareTunnel.ps1`
2. **Best error handling and features**
3. **Multiple modes available**

## Troubleshooting

### If Tunnel Won't Start
1. Check if `cloudflare\cloudflared.exe` exists
2. Check if `cloudflare-tunnel.yml` exists
3. Run setup: `setup-cloudflare-tunnel.ps1`

### If Window Won't Close
1. Use `start-cloudflare-simple.bat` instead
2. The auto-startup script is now fixed
3. PowerShell version has better control

### Check Tunnel Status
```batch
# Check if running
tasklist | findstr cloudflared

# Or use the menu
start-cloudflare.bat
```

## Your URLs
Once started, your tunnel provides:
- **Main Site:** https://rotc.lspulbrotcunit.online
- **Admin Panel:** https://admin.lspulbrotcunit.online
- **Local Access:** http://localhost

## Notes
- All methods check if tunnel is already running
- Background processes survive window closure
- Use Task Manager to stop if needed: End `cloudflared.exe`
- The original auto-startup issue has been resolved