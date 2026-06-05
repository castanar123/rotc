# ROTC QR System - Cloudflare Tunnel Setup Guide

## Overview

Cloudflare Tunnel (formerly Argo Tunnel) provides a secure, reliable way to expose your ROTC QR System to the internet without opening firewall ports or dealing with dynamic IP addresses. This setup offers:

- **Permanent URLs** - No more changing links
- **Better Uptime** - Cloudflare's global network
- **Enhanced Security** - No exposed ports
- **Free Tier Available** - No cost for basic usage
- **Automatic SSL** - HTTPS by default

## Quick Start

### Option 1: One-Click Setup (Recommended)
```batch
# Double-click this file for easy setup
start-cloudflare.bat
```

### Option 2: Command Line Setup
```powershell
# Complete setup in one command
.\setup-cloudflare-tunnel.ps1 -Install -Configure -Start
```

## Prerequisites

1. **Cloudflare Account** (free)
   - Sign up at [cloudflare.com](https://cloudflare.com)
   - No domain required (uses trycloudflare.com subdomain)

2. **XAMPP Running**
   - Apache server must be running on port 80
   - MySQL database should be active

3. **PowerShell Execution Policy**
   ```powershell
   Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   ```

## Step-by-Step Setup

### Step 1: Install Cloudflared
```powershell
.\setup-cloudflare-tunnel.ps1 -Install
```

### Step 2: Configure Tunnel
```powershell
.\setup-cloudflare-tunnel.ps1 -Configure
```
This will:
- Open browser for Cloudflare authentication
- Create a tunnel named "rotc-qr-system"
- Generate configuration file

### Step 3: Start Tunnel
```powershell
.\setup-cloudflare-tunnel.ps1 -Start
```

## Your Tunnel URLs

Once setup is complete, your system will be accessible at:

- **Main Dashboard**: `https://rotc-qr-system.trycloudflare.com`
- **Admin Panel**: `https://admin-rotc-qr-system.trycloudflare.com`
- **QR Scanner**: `https://scanner-rotc-qr-system.trycloudflare.com`
- **API Endpoint**: `https://api-rotc-qr-system.trycloudflare.com`

## Management Commands

### Using the Manager Script
```powershell
# Check tunnel status
.\cloudflare-tunnel-manager.ps1 status

# Start tunnel
.\cloudflare-tunnel-manager.ps1 start

# Stop tunnel
.\cloudflare-tunnel-manager.ps1 stop

# Restart tunnel
.\cloudflare-tunnel-manager.ps1 restart

# Show URLs
.\cloudflare-tunnel-manager.ps1 urls

# View logs
.\cloudflare-tunnel-manager.ps1 logs
```

### Using Batch Files
```batch
# Interactive menu
start-cloudflare.bat

# Quick status check
get-cloudflare-status.bat
```

## Automatic Startup

### Option 1: Service Mode (Recommended)
```powershell
# Install as Windows service (requires admin)
.\cloudflare-service.ps1 -Install

# Start service
sc start ROTCCloudflareService

# Check service status
.\cloudflare-service.ps1 status
```

### Option 2: Startup Script
1. Create shortcut to `start-cloudflare.bat`
2. Place in Windows Startup folder:
   ```
   Win+R → shell:startup
   ```

## Configuration Files

### Main Configuration (`cloudflare-tunnel.yml`)
```yaml
tunnel: rotc-qr-system
credentials-file: .cloudflared\credentials.json

ingress:
  - hostname: rotc-qr-system.trycloudflare.com
    service: http://localhost:80
  - hostname: admin-rotc-qr-system.trycloudflare.com
    service: http://localhost:80
    path: /admin_dashboard.php
  - service: http_status:404
```

### Custom Domain Setup
To use your own domain:

1. Add domain to Cloudflare
2. Update DNS records
3. Modify configuration:
```yaml
ingress:
  - hostname: rotc.yourdomain.com
    service: http://localhost:80
```

## Troubleshooting

### Common Issues

**1. Authentication Failed**
```powershell
# Re-authenticate
cloudflare\cloudflared.exe tunnel login
```

**2. Tunnel Won't Start**
```powershell
# Check XAMPP is running
# Verify port 80 is available
netstat -an | findstr :80

# Check logs
.\cloudflare-tunnel-manager.ps1 logs
```

**3. URLs Not Working**
```powershell
# Restart tunnel
.\cloudflare-tunnel-manager.ps1 restart

# Wait 30 seconds for propagation
```

**4. Permission Errors**
```powershell
# Run as administrator
# Or set execution policy
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Log Files
- **Setup Logs**: `cloudflare-tunnel.log`
- **Service Logs**: `cloudflare-service.log`
- **URLs**: `cloudflare-url.txt`
- **Process ID**: `cloudflare-tunnel.pid`

### Health Checks
```powershell
# Full system status
.\cloudflare-tunnel-manager.ps1 status

# Test URLs
Invoke-WebRequest -Uri "https://rotc-qr-system.trycloudflare.com" -Method HEAD
```

## Security Considerations

1. **No Firewall Changes** - Tunnel creates outbound connections only
2. **Automatic SSL** - All traffic encrypted
3. **Access Control** - Configure in Cloudflare dashboard
4. **Rate Limiting** - Built-in DDoS protection

## Performance Optimization

### Cloudflare Settings
1. Enable **Caching** for static assets
2. Use **Compression** (Gzip/Brotli)
3. Enable **HTTP/2** and **HTTP/3**
4. Configure **Page Rules** for optimization

### Local Optimization
```yaml
# Add to cloudflare-tunnel.yml
originRequest:
  httpHostHeader: localhost
  connectTimeout: 30s
  tlsTimeout: 10s
  keepAliveTimeout: 90s
```

## Monitoring and Alerts

### Built-in Monitoring
```powershell
# Start monitoring service
.\cloudflare-service.ps1 start

# Check service health
.\cloudflare-service.ps1 status
```

### Cloudflare Analytics
- Access logs in Cloudflare dashboard
- Monitor traffic patterns
- Set up alerts for downtime

## Migration from ngrok

If you're switching from ngrok:

1. **Stop ngrok tunnel**:
   ```powershell
   .\ngrok-manager.ps1 stop
   ```

2. **Setup Cloudflare**:
   ```powershell
   .\setup-cloudflare-tunnel.ps1 -Install -Configure -Start
   ```

3. **Update bookmarks** with new URLs

4. **Optional**: Remove ngrok files

## Advanced Configuration

### Multiple Environments
```powershell
# Development tunnel
.\setup-cloudflare-tunnel.ps1 -TunnelName "rotc-dev" -Configure

# Production tunnel
.\setup-cloudflare-tunnel.ps1 -TunnelName "rotc-prod" -Configure
```

### Load Balancing
```yaml
# Multiple origins
ingress:
  - hostname: rotc.example.com
    service: http://localhost:80
    originRequest:
      httpHostHeader: localhost
  - hostname: rotc.example.com
    service: http://localhost:8080
    originRequest:
      httpHostHeader: localhost
```

## Support and Resources

- **Cloudflare Docs**: [developers.cloudflare.com/cloudflare-one/connections/connect-apps](https://developers.cloudflare.com/cloudflare-one/connections/connect-apps)
- **Community**: [community.cloudflare.com](https://community.cloudflare.com)
- **Status Page**: [cloudflarestatus.com](https://cloudflarestatus.com)

## File Structure

```
ROTC QR System/
├── setup-cloudflare-tunnel.ps1     # Main setup script
├── cloudflare-tunnel-manager.ps1   # Management commands
├── cloudflare-service.ps1           # Service monitoring
├── start-cloudflare.bat             # Quick start menu
├── cloudflare-tunnel.yml            # Tunnel configuration
├── cloudflare-tunnel.log            # Setup logs
├── cloudflare-service.log           # Service logs
├── cloudflare-url.txt               # Current URLs
├── cloudflare-tunnel.pid            # Process ID
└── cloudflare/
    └── cloudflared.exe              # Tunnel binary
```

---

**Need Help?** Run `start-cloudflare.bat` for an interactive setup menu, or use `.\setup-cloudflare-tunnel.ps1 -Help` for command-line options.