# ROTC QR System - Automated Ngrok Setup

## 🚀 Quick Start (Fully Autonomous)

**For immediate use with zero configuration:**

1. **Double-click** `auto-start-ngrok.bat`
2. **That's it!** The system will:
   - Automatically download and install ngrok
   - Configure authentication
   - Start the tunnel
   - Display your public URL

## 📁 Files Overview

### Core Files
- `auto-start-ngrok.bat` - **One-click launcher** (recommended for most users)
- `setup-ngrok.ps1` - Complete automated setup script
- `ngrok-config.yml` - Enhanced tunnel configuration
- `ngrok-manager.ps1` - GUI management dashboard

### Generated Files (after setup)
- `ngrok/ngrok.exe` - Ngrok executable
- `start-ngrok.ps1` - PowerShell startup script
- `start-ngrok.bat` - Batch file launcher
- `check-ngrok-status.ps1` - Status checker
- `stop-ngrok.ps1` - Stop all tunnels
- `ngrok-setup.log` - Setup log file
- `ngrok-tunnel.log` - Tunnel operation log

## 🎯 Usage Options

### Option 1: Fully Automatic (Recommended)
```bash
# Just double-click this file:
auto-start-ngrok.bat
```

### Option 2: GUI Management
```powershell
# Run the graphical manager:
powershell -ExecutionPolicy Bypass -File ngrok-manager.ps1
```

### Option 3: Manual Setup
```powershell
# Run setup first:
powershell -ExecutionPolicy Bypass -File setup-ngrok.ps1

# Then start tunnels:
start-ngrok.bat
```

## 🔧 Available Tunnels

The system provides multiple pre-configured tunnels:

| Tunnel Name | Port | Purpose | Usage |
|-------------|------|---------|-------|
| `qr-project` | 80 | **Main tunnel** | Production use |
| `qr-project-https` | 443 | HTTPS version | SSL testing |
| `qr-dev` | 8080 | Development | Dev server |
| `qr-mobile` | 80 | Mobile testing | Mobile optimization |
| `qr-api` | 8000 | API access | API development |

## 🖥️ GUI Manager Features

The `ngrok-manager.ps1` provides:

- **Visual tunnel selection**
- **Real-time status monitoring**
- **One-click start/stop**
- **Auto-refresh every 5 seconds**
- **Direct access to ngrok web interface**
- **Integrated setup runner**

## 📊 Monitoring & Management

### Check Status
```powershell
# Check current tunnel status:
powershell -ExecutionPolicy Bypass -File check-ngrok-status.ps1
```

### Stop All Tunnels
```powershell
# Stop all running tunnels:
powershell -ExecutionPolicy Bypass -File stop-ngrok.ps1
```

### Web Interface
Once ngrok is running, access the web interface at:
- **Local:** http://localhost:4040
- **Features:** Real-time requests, replay, inspect traffic

## 🔐 Security Features

### Built-in Security
- **Request inspection** enabled by default
- **Enhanced headers** for better compatibility
- **CORS support** for API tunnels
- **Optional basic authentication** (commented in config)

### Optional Enhancements
Uncomment in `ngrok-config.yml` for:
- Custom subdomains (requires paid plan)
- Basic authentication
- Custom domains (requires paid plan)

## 🚨 Troubleshooting

### Common Issues

**"Ngrok not found"**
- Run `setup-ngrok.ps1` first
- Check internet connection for download

**"Authentication failed"**
- Verify authtoken in `ngrok-config.yml`
- Sign up at https://ngrok.com for free token

**"Port already in use"**
- Stop existing web servers
- Use different tunnel (e.g., `qr-dev` on port 8080)

**"PowerShell execution policy"**
- Run as administrator
- Or use: `powershell -ExecutionPolicy Bypass -File script.ps1`

### Log Files
Check these files for detailed information:
- `ngrok-setup.log` - Setup process
- `ngrok-tunnel.log` - Tunnel operations
- `ngrok-manager.log` - GUI manager actions

## 🔄 Automatic Features

### Self-Healing
- **Auto-download** ngrok if missing
- **Auto-configure** authentication
- **Auto-retry** on connection issues
- **Auto-logging** for debugging

### Desktop Integration
- **Desktop shortcuts** created automatically
- **Start menu integration** (where supported)
- **System tray notifications** (in GUI mode)

## 🌐 Network Configuration

### Firewall
The system automatically handles:
- **Outbound connections** to ngrok servers
- **Local port binding** for tunnels
- **HTTP/HTTPS traffic** routing

### Router/NAT
- **No port forwarding** required
- **No router configuration** needed
- **Works behind firewalls** and NAT

## 📱 Mobile Testing

For mobile device testing:

1. **Start tunnel:** Use `qr-mobile` tunnel
2. **Get URL:** Check ngrok web interface (localhost:4040)
3. **Test:** Access from any mobile device worldwide
4. **Debug:** Use ngrok web interface to inspect requests

## 🔧 Advanced Configuration

### Custom Tunnels
Add to `ngrok-config.yml`:
```yaml
tunnels:
  my-custom-tunnel:
    proto: http
    addr: 3000
    inspect: true
    subdomain: my-app  # Requires paid plan
```

### Environment Variables
Set these for advanced control:
- `NGROK_AUTHTOKEN` - Override config file token
- `NGROK_REGION` - Set tunnel region (us, eu, ap, au, sa, jp, in)

## 📈 Performance Optimization

### Best Practices
- Use **specific tunnels** for different purposes
- Enable **inspection** only when debugging
- Use **HTTPS tunnels** for production testing
- Monitor **bandwidth usage** in web interface

### Resource Usage
- **Minimal CPU** impact
- **Low memory** footprint (~10-20MB)
- **Bandwidth** depends on traffic

## 🆘 Support

### Getting Help
1. **Check logs** in the project directory
2. **Run diagnostics** with GUI manager
3. **Verify configuration** in `ngrok-config.yml`
4. **Test connectivity** with simple tunnel first

### Useful Commands
```powershell
# Test ngrok installation
.\ngrok\ngrok.exe version

# Test configuration
.\ngrok\ngrok.exe config check --config=ngrok-config.yml

# List available tunnels
.\ngrok\ngrok.exe tunnel --help
```

## 🎉 Success Indicators

**Setup Complete When:**
- ✅ `ngrok.exe` exists in `ngrok/` folder
- ✅ Desktop shortcuts created
- ✅ Configuration file validated
- ✅ Test tunnel starts successfully

**Tunnel Running When:**
- ✅ Public URL displayed in terminal
- ✅ Web interface accessible at localhost:4040
- ✅ External access works from mobile/other devices
- ✅ Traffic appears in ngrok web interface

---

## 🚀 Ready to Go!

Your ROTC QR System is now equipped with a fully autonomous ngrok setup. Simply run `auto-start-ngrok.bat` whenever you need public access to your local development server!

**Happy tunneling! 🌐**