# IONOS Domain to Cloudflare Setup Guide

## 🎯 Your Domain: `lspulbrotcunit.online`

This guide will help you transfer your IONOS domain to Cloudflare and set up permanent tunnels for your ROTC QR System.

## 📋 Step-by-Step Setup

### Step 1: Add Domain to Cloudflare

1. **Login to Cloudflare:**
   - Go to [https://dash.cloudflare.com](https://dash.cloudflare.com)
   - Sign in to your account

2. **Add Your Site:**
   - Click "Add a Site"
   - Enter: `lspulbrotcunit.online`
   - Click "Add Site"

3. **Select Plan:**
   - Choose "Free" plan
   - Click "Continue"

4. **DNS Records Scan:**
   - Cloudflare will scan for existing DNS records
   - Review and click "Continue"

### Step 2: Get Cloudflare Nameservers

Cloudflare will provide you with 2 nameservers like:
```
name1.cloudflare.com
name2.cloudflare.com
```

**📝 Write these down - you'll need them for IONOS!**

### Step 3: Update Nameservers in IONOS

1. **Login to IONOS:**
   - Go to [https://www.ionos.com](https://www.ionos.com)
   - Login to your account

2. **Access Domain Management:**
   - Go to "Domains & SSL"
   - Find `lspulbrotcunit.online`
   - Click on the domain

3. **Change Nameservers:**
   - Look for "DNS" or "Nameservers" section
   - Click "Edit" or "Manage"
   - **IMPORTANT: Turn off DNSSEC if it's enabled**
   - Select "Custom Nameservers" or "External Nameservers"
   - Replace IONOS nameservers with Cloudflare nameservers:
     
     **Remove these IONOS nameservers:**
     - ns1091.ui-dns.org
     - ns1105.ui-dns.com
     - ns1124.ui-dns.de
     - ns1073.ui-dns.biz
     
     **Add these Cloudflare nameservers:**
     - cheryl.ns.cloudflare.com
     - sevki.ns.cloudflare.com
   - Save changes

### Step 4: Wait for Propagation

- **Time Required:** 2-48 hours (usually 2-4 hours)
- **Check Status:** Return to Cloudflare dashboard
- **Verification:** Cloudflare will show "Active" when ready

### Step 5: Get Required Cloudflare Information

Once your domain is active in Cloudflare:

1. **Account ID:**
   - In Cloudflare dashboard, right sidebar
   - Copy the "Account ID"

2. **Zone ID:**
   - In your domain overview, right sidebar
   - Copy the "Zone ID"

3. **API Token:**
   - Go to "My Profile" → "API Tokens"
   - Click "Create Token"
   - Use "Custom Token" with these permissions:
     ```
     Zone:Zone:Read
     Zone:DNS:Edit
     Account:Cloudflare Tunnel:Edit
     ```
   - Zone Resources: Include → Specific zone → `lspulbrotcunit.online`
   - Click "Continue to summary" → "Create Token"
   - **Copy and save this token securely!**

## 🚀 Setup Permanent Cloudflare Tunnel

### Method 1: Automated Setup (Recommended)

1. **Run the setup script:**
   ```bash
   ./setup-tunnel.bat
   ```

2. **Enter your information when prompted:**
   - **Domain:** `lspulbrotcunit.online`
   - **API Token:** (the token you created above)
   - **Account ID:** (from Cloudflare dashboard)
   - **Zone ID:** (from Cloudflare dashboard)

3. **The script will automatically:**
   - Install cloudflared
   - Create tunnel: `rotc-qr-system`
   - Set up subdomains:
     - `rotc.lspulbrotcunit.online` → Main system
     - `admin.lspulbrotcunit.online` → Admin dashboard
     - `scanner.lspulbrotcunit.online` → QR Scanner
     - `api.lspulbrotcunit.online` → API endpoint
   - Create DNS records
   - Install as Windows service

### Method 2: Manual Setup

If you prefer manual setup, use the existing automated script:

```powershell
./setup-automated-cloudflare-tunnel.ps1 -Domain "lspulbrotcunit.online" -ApiToken "YOUR_API_TOKEN" -AccountId "YOUR_ACCOUNT_ID" -ZoneId "YOUR_ZONE_ID"
```

## 🔧 Expected Results

After successful setup, you'll have these permanent URLs:

- **Main System:** `https://rotc.lspulbrotcunit.online`
- **Admin Dashboard:** `https://admin.lspulbrotcunit.online`
- **QR Scanner:** `https://scanner.lspulbrotcunit.online`
- **API Endpoint:** `https://api.lspulbrotcunit.online`

## ✅ Verification Steps

1. **Check DNS Records in Cloudflare:**
   - Go to DNS tab in Cloudflare
   - Verify CNAME records exist for all subdomains

2. **Test URLs:**
   - Visit each URL in your browser
   - Should show your ROTC system

3. **Check Tunnel Status:**
   ```bash
   ./tunnel-status.bat
   ```

## 🛠️ Troubleshooting

### Issue: "Domain not found in Cloudflare"
**Solution:**
- Ensure nameservers are updated in IONOS
- Wait for DNS propagation (up to 48 hours)
- Check domain status in Cloudflare dashboard

### Issue: "API Token Invalid"
**Solution:**
- Verify token permissions include:
  - Zone:Zone:Read
  - Zone:DNS:Edit
  - Account:Cloudflare Tunnel:Edit
- Ensure token is for the correct zone

### Issue: "Tunnel not connecting"
**Solution:**
- Ensure XAMPP is running (use `quick-xampp-check.bat`)
- Check localhost:80 is accessible
- Verify Windows Firewall isn't blocking cloudflared

### Issue: "DNS records not created"
**Solution:**
- Check Zone ID is correct
- Verify API token has DNS:Edit permission
- Try manual DNS record creation in Cloudflare dashboard

## 🔐 Security Notes

1. **Keep API Token Secure:**
   - Don't share or commit to version control
   - Regenerate if compromised

2. **Domain Security:**
   - Enable Cloudflare security features
   - Consider enabling "Under Attack Mode" if needed

3. **XAMPP Security:**
   - Change default MySQL password
   - Secure phpMyAdmin access
   - Use HTTPS when possible

## 📞 Support

If you encounter issues:

1. **Check the logs:**
   - Cloudflare tunnel logs
   - XAMPP error logs
   - Windows Event Viewer

2. **Use diagnostic tools:**
   - `quick-xampp-check.bat`
   - `tunnel-status.bat`
   - `check-xampp-status.bat`

3. **Common Commands:**
   ```bash
   # Check tunnel status
   cloudflared tunnel list
   
   # Test DNS resolution
   nslookup lspulbrotcunit.online
   
   # Check port 80
   netstat -an | find ":80"
   ```

## 🎉 Next Steps

Once everything is working:

1. **Test all functionality:**
   - QR code generation
   - Student registration
   - Attendance scanning
   - Admin dashboard

2. **Share your URLs:**
   - `https://rotc.lspulbrotcunit.online` - Main access
   - `https://admin.lspulbrotcunit.online` - Admin panel
   - `https://scanner.lspulbrotcunit.online` - Mobile scanning

3. **Monitor and maintain:**
   - Use XAMPP auto-startup solution
   - Monitor tunnel status regularly
   - Keep Cloudflare dashboard bookmarked

---

**🎯 Your domain `lspulbrotcunit.online` will provide professional, permanent URLs for your ROTC QR System!**