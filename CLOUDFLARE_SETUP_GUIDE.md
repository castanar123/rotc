# Cloudflare Tunnel Automated Setup Guide

This guide will help you set up a **permanent Cloudflare Tunnel** that doesn't require re-authentication and provides stable URLs that never change.

## Prerequisites

1. **Cloudflare Account** (free tier works)
2. **Domain added to Cloudflare** (you need to own a domain)
3. **API Token with proper permissions**

## Step 1: Get Your Cloudflare Credentials

### 1.1 Get Account ID
1. Go to [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Select your domain
3. In the right sidebar, copy your **Account ID**

### 1.2 Get Zone ID
1. In the same page, copy your **Zone ID** from the right sidebar

### 1.3 Create API Token
1. Go to [Cloudflare API Tokens](https://dash.cloudflare.com/profile/api-tokens)
2. Click **"Create Token"**
3. Use **"Custom token"** template
4. Configure permissions:
   - **Account** - `Cloudflare Tunnel:Edit`
   - **Zone** - `DNS:Edit`
   - **Zone** - `Zone:Read`
5. Set **Account Resources** to `Include - Your Account`
6. Set **Zone Resources** to `Include - Your Domain`
7. Click **"Continue to summary"** then **"Create Token"**
8. **Copy and save the token** (you won't see it again!)

## Step 2: Run the Automated Setup

### Option A: Interactive Setup
```powershell
# Run this command and follow the prompts
.\setup-automated-cloudflare-tunnel.ps1 -CloudflareApiToken "YOUR_API_TOKEN" -AccountId "YOUR_ACCOUNT_ID" -ZoneId "YOUR_ZONE_ID" -Domain "your-domain.com"
```

### Option B: Example with Real Values
```powershell
# Replace with your actual values
.\setup-automated-cloudflare-tunnel.ps1 `
    -CloudflareApiToken "abc123def456ghi789jkl012mno345pqr678stu901vwx234yz" `
    -AccountId "1234567890abcdef1234567890abcdef" `
    -ZoneId "abcdef1234567890abcdef1234567890" `
    -Domain "myrotcapp.com"
```

## Step 3: What Happens During Setup

The script will automatically:

1. ✅ **Download and install cloudflared**
2. ✅ **Create a permanent tunnel** (no browser authentication needed)
3. ✅ **Configure multiple subdomains**:
   - `myrotcapp.com` - Main application
   - `admin-myrotcapp.com` - Admin dashboard
   - `qr-myrotcapp.com` - QR scanner
   - `api-myrotcapp.com` - API endpoint
4. ✅ **Create DNS records** automatically
5. ✅ **Install as Windows service** (starts automatically on boot)
6. ✅ **Generate permanent URLs** that never change

## Step 4: Verify Setup

After setup completes, test your URLs:
- https://your-domain.com
- https://admin-your-domain.com
- https://qr-your-domain.com
- https://api-your-domain.com

## Benefits of This Solution

✅ **Permanent URLs** - Never change, even after restarts
✅ **No re-authentication** - Runs automatically
✅ **No manual setup** - Fully automated
✅ **Multiple subdomains** - Organized access
✅ **Windows service** - Starts with system
✅ **Free** - Works with Cloudflare free tier
✅ **Secure** - All traffic encrypted

## Troubleshooting

### Common Issues

**"Invalid API token"**
- Double-check your API token permissions
- Ensure token includes Cloudflare Tunnel:Edit and DNS:Edit

**"Domain not found"**
- Make sure your domain is added to Cloudflare
- Verify nameservers are pointing to Cloudflare

**"Service failed to start"**
- Check if port 80 is available
- Run PowerShell as Administrator

### Check Service Status
```powershell
# Check if service is running
Get-Service cloudflared

# View service logs
Get-Content "C:\cloudflared\tunnel.log" -Tail 20

# Restart service if needed
Restart-Service cloudflared
```

## Alternative: Free Domain Options

If you don't have a domain, consider these options:

1. **EU.org** - Free subdomain (takes time for approval)
2. **Cheap domains** - .xyz domains for $0.99/year
3. **Cloudflare Registrar** - Domains at cost ($2-3/year)

## Security Notes

- Keep your API token secure
- The tunnel only allows outbound connections
- All traffic is encrypted end-to-end
- No ports need to be opened on your firewall

## Need Help?

If you encounter issues:
1. Check the troubleshooting section above
2. Review the service logs
3. Ensure all prerequisites are met
4. Verify your API token permissions

---

**This solution provides a permanent, stable tunnel that works exactly like Ngrok but with permanent URLs that never change!**