# ROTC QR System - Auto Startup Guide

This guide explains how to make your ROTC QR System start automatically when your computer boots up, ensuring it's always available without manual intervention.

## 🎯 Quick Setup (Recommended)

### Option 1: Windows Startup Folder (Easiest)

1. **Copy the startup script to Windows Startup folder:**
   ```batch
   # Press Win+R, type: shell:startup
   # Copy auto-startup-complete.bat to the opened folder
   ```

2. **That's it!** Your system will now start automatically on boot.

### Option 2: Windows Services (Most Reliable)

1. **Install XAMPP as Windows Services:**
   ```powershell
   # Run PowerShell as Administrator
   .\setup-xampp-service.ps1 -Install
   ```

2. **Install Cloudflare as Windows Service:**
   ```powershell
   # Run PowerShell as Administrator
   .\cloudflare-service.ps1 -Install
   ```

## 📋 Detailed Setup Options

### XAMPP Auto-Start Options

#### Method 1: Windows Services (Recommended)
```powershell
# Run as Administrator
.\setup-xampp-service.ps1 -Install
```

**Benefits:**
- ✅ Starts before user login
- ✅ Most reliable
- ✅ Automatic restart on failure
- ✅ No user interaction needed

#### Method 2: Startup Folder
```batch
# Copy auto-startup-complete.bat to:
# C:\Users\[Username]\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup
```

**Benefits:**
- ✅ Easy to set up
- ✅ User-friendly
- ❌ Requires user login

#### Method 3: Task Scheduler
1. Open Task Scheduler
2. Create Basic Task
3. Trigger: "When the computer starts"
4. Action: Start `auto-startup-complete.bat`

### Cloudflare Tunnel Auto-Start Options

#### Method 1: Persistent Named Tunnel (Recommended)
```powershell
# One-time setup
.\setup-persistent-tunnel.ps1 -Setup

# Install as service
.\cloudflare-service.ps1 -Install
```

**Benefits:**
- ✅ Same URL every time
- ✅ No reconfiguration needed
- ✅ Automatic restart
- ✅ Professional setup

#### Method 2: Quick Tunnel (Fallback)
```batch
# Included in auto-startup-complete.bat
# Different URL each restart
```

## 🔧 Complete Setup Process

### Step 1: Install XAMPP as Service
```powershell
# Run PowerShell as Administrator
cd "C:\xampp\htdocs\generate qr"
.\setup-xampp-service.ps1 -Install
```

### Step 2: Setup Persistent Cloudflare Tunnel
```powershell
# One-time authentication and setup
.\setup-persistent-tunnel.ps1 -Setup
```

### Step 3: Install Cloudflare as Service
```powershell
# Run PowerShell as Administrator
.\cloudflare-service.ps1 -Install
```

### Step 4: Test the Setup
```powershell
# Check all services
.\setup-xampp-service.ps1 -Status
.\setup-persistent-tunnel.ps1 -Status
.\cloudflare-service.ps1 -Status
```

## 🚀 Alternative: Simple Startup Method

If you prefer a simpler approach:

1. **Copy to Startup Folder:**
   ```batch
   # Press Win+R, type: shell:startup
   # Copy auto-startup-complete.bat to the folder
   ```

2. **Done!** Everything starts when you log in.

## 📱 Managing Your System

### Check Status
```batch
get-cloudflare-status.bat
```

### Start/Stop Services
```powershell
# XAMPP
.\setup-xampp-service.ps1 -Start
.\setup-xampp-service.ps1 -Stop

# Cloudflare
.\setup-persistent-tunnel.ps1 -Start
.\setup-persistent-tunnel.ps1 -Stop
```

### View URLs
```batch
type cloudflare-url.txt
```

## 🔒 Security Considerations

### Firewall Settings
- Windows Firewall may prompt for Apache/MySQL
- Allow access for local network if needed
- Cloudflare tunnel is secure by default

### Access Control
- Your system is accessible via Cloudflare URL
- Ensure strong passwords for admin accounts
- Monitor access logs regularly

## 🛠️ Troubleshooting

### XAMPP Won't Start
```powershell
# Check service status
.\setup-xampp-service.ps1 -Status

# Try manual start
.\setup-xampp-service.ps1 -Start

# Check for port conflicts
netstat -an | findstr :80
netstat -an | findstr :3306
```

### Cloudflare Tunnel Issues
```powershell
# Check tunnel status
.\setup-persistent-tunnel.ps1 -Status

# Restart tunnel
.\setup-persistent-tunnel.ps1 -Stop
.\setup-persistent-tunnel.ps1 -Start

# Check logs
type cloudflare-tunnel.log
```

### URL Changes
- **Persistent tunnels:** Same URL always
- **Quick tunnels:** New URL each restart
- Check `cloudflare-url.txt` for current URLs

## 📊 Monitoring

### Health Check Script
Create a scheduled task to run every 5 minutes:
```batch
get-cloudflare-status.bat
```

### Log Files
- XAMPP: `C:\xampp\apache\logs\error.log`
- Cloudflare: `cloudflare-tunnel.log`
- System: `logs\system.log`

## 🔄 Maintenance

### Regular Tasks
1. **Weekly:** Check service status
2. **Monthly:** Review logs
3. **Quarterly:** Update cloudflared

### Updates
```powershell
# Update cloudflared
.\setup-persistent-tunnel.ps1 -Install

# Restart services after updates
.\setup-xampp-service.ps1 -Stop
.\setup-xampp-service.ps1 -Start
```

## 📞 Support

### Quick Commands Reference
```batch
# Status check
get-cloudflare-status.bat

# Full restart
auto-startup-complete.bat

# Service management
setup-xampp-service.ps1 -Status
setup-persistent-tunnel.ps1 -Status
```

### Common Issues
1. **Port 80 in use:** Stop IIS or other web servers
2. **MySQL won't start:** Check port 3306 availability
3. **Tunnel authentication:** Re-run setup with `-Setup`
4. **Permission denied:** Run PowerShell as Administrator

## 🎉 Benefits of This Setup

✅ **Zero Configuration:** Starts automatically on boot  
✅ **Persistent URLs:** Same Cloudflare URL every time  
✅ **Reliable:** Windows services auto-restart on failure  
✅ **Professional:** Enterprise-grade tunnel solution  
✅ **Maintenance-Free:** No daily intervention needed  
✅ **Secure:** Cloudflare provides DDoS protection  
✅ **Fast:** Global CDN for better performance  

---

**Your ROTC QR System will now be available 24/7 without any manual intervention!**