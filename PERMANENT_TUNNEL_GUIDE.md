# ROTC QR System - Permanent Tunnel Guide

## 🚀 Enhanced Ngrok Setup Complete!

Your ROTC QR System now has a **permanent tunnel solution** with auto-startup capabilities!

## 📋 What's New?

### ✅ Auto-Startup Service
- **Automatically starts** when Windows boots up
- **No manual intervention** required
- **Persistent tunnel** that maintains connection
- **Auto-restart** capabilities if tunnel fails

### ✅ Quick Access Commands
Four simple batch files for easy tunnel management:

| Command | Purpose | Usage |
|---------|---------|-------|
| `start-tunnel.bat` | Start the tunnel service | Double-click or run from terminal |
| `stop-tunnel.bat` | Stop the tunnel service | Double-click or run from terminal |
| `tunnel-status.bat` | Check tunnel status | Shows if running + current URL |
| `get-url.bat` | Get current public URL | Quickly retrieve the active URL |

### ✅ Enhanced Auto-Start
- **Detects** if enhanced service is available
- **Prompts** to use enhanced features
- **Falls back** to original method if needed
- **Shows** quick commands after execution

## 🌐 Current Tunnel Information

**Public URL:** `https://b2b475ce2dfe.ngrok-free.app`

### Direct Access Links:
- **Main System:** https://b2b475ce2dfe.ngrok-free.app
- **Admin Dashboard:** https://b2b475ce2dfe.ngrok-free.app/admin_dashboard.php
- **QR Scanner:** https://b2b475ce2dfe.ngrok-free.app/scanner.php
- **Login Page:** https://b2b475ce2dfe.ngrok-free.app/login.php
- **Ngrok Interface:** http://localhost:4040

## 🔧 Service Management

### PowerShell Commands:
```powershell
# Install auto-startup (already done)
powershell -File tunnel-service.ps1 -Install

# Remove auto-startup
powershell -File tunnel-service.ps1 -Uninstall

# Start tunnel service
powershell -File tunnel-service.ps1 -Start

# Stop tunnel service
powershell -File tunnel-service.ps1 -Stop

# Check tunnel status
powershell -File tunnel-service.ps1 -Status

# Get current URL
powershell -File tunnel-service.ps1 -GetUrl
```

## 🎯 Key Benefits

### 1. **One-Click Access**
- Just double-click `start-tunnel.bat` to start
- No need to remember complex commands
- Instant access to current URL

### 2. **Permanent Solution**
- **Auto-starts** with Windows
- **Maintains** connection automatically
- **Restarts** if connection drops
- **Caches** URL for quick retrieval

### 3. **Easy Management**
- Simple batch files for all operations
- Clear status information
- Quick URL retrieval
- Professional logging

### 4. **Reliability**
- **Background service** runs independently
- **Process monitoring** ensures uptime
- **Error handling** with auto-recovery
- **Detailed logging** for troubleshooting

## 📱 Mobile Access

Your ROTC QR System is now **permanently accessible** from any device:

1. **Smartphones** - Scan QR codes directly
2. **Tablets** - Full admin interface
3. **Laptops** - Complete system access
4. **Desktop** - Local and remote access

## 🔄 Auto-Startup Details

### Installation Location:
```
C:\Users\[Username]\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup\ROTC-QR-Tunnel.bat
```

### What Happens on Boot:
1. Windows starts the tunnel service automatically
2. Ngrok connects and establishes tunnel
3. Public URL becomes available
4. System is ready for use

## 🛠️ Troubleshooting

### If Tunnel Doesn't Start:
1. Run `tunnel-status.bat` to check status
2. Run `start-tunnel.bat` to manually start
3. Check `tunnel-service.log` for errors
4. Restart XAMPP if needed

### If URL Changes:
- Run `get-url.bat` to get new URL
- URL is automatically cached
- Enhanced service detects changes

### If Auto-Startup Fails:
- Check Windows Startup folder
- Reinstall with: `powershell -File tunnel-service.ps1 -Install`
- Verify XAMPP is running

## 📊 File Structure

```
generate qr/
├── tunnel-service.ps1          # Main service script
├── permanent-tunnel-setup.ps1   # Setup script
├── start-tunnel.bat            # Quick start
├── stop-tunnel.bat             # Quick stop
├── tunnel-status.bat           # Quick status
├── get-url.bat                 # Quick URL
├── tunnel-url.txt              # Cached URL
├── tunnel.pid                  # Process ID
├── tunnel-service.log          # Service logs
└── permanent-tunnel.log        # Setup logs
```

## 🎉 Success!

Your ROTC QR System now has:
- ✅ **Permanent tunnel** with auto-startup
- ✅ **One-click management** commands
- ✅ **Reliable connection** with auto-restart
- ✅ **Easy URL access** anytime
- ✅ **Professional logging** and monitoring

## 🚀 Next Steps

1. **Test the system** - Try accessing from mobile device
2. **Bookmark URLs** - Save direct links for quick access
3. **Share with team** - Distribute the public URL
4. **Monitor logs** - Check `tunnel-service.log` periodically

---

**Note:** The tunnel URL may change if ngrok restarts. Use `get-url.bat` to always get the current URL.

**Support:** Check the log files for detailed information about tunnel operations and any issues.