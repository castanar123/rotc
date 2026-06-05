# ROTC QR System - Ngrok Troubleshoot Script
# This script helps diagnose and fix Ngrok connectivity issues

Write-Host "=== ROTC QR System - Ngrok Troubleshoot ==="
Write-Host ""

# Step 1: Check if XAMPP is running
Write-Host "1. Checking XAMPP/Apache status..."
$apacheRunning = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
if ($apacheRunning) {
    Write-Host "   ✅ Apache is running" -ForegroundColor Green
} else {
    Write-Host "   ❌ Apache is NOT running" -ForegroundColor Red
    Write-Host "   Please start XAMPP Control Panel and start Apache"
    return
}

# Step 2: Test local connection
Write-Host "\n2. Testing local connection..."
try {
    $response = Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing -TimeoutSec 5
    Write-Host "   ✅ Local server is accessible" -ForegroundColor Green
} catch {
    Write-Host "   ❌ Local server is NOT accessible" -ForegroundColor Red
    Write-Host "   Error: $($_.Exception.Message)"
    return
}

# Step 3: Check Ngrok process
Write-Host "\n3. Checking Ngrok process..."
$ngrokProcess = Get-Process -Name "ngrok" -ErrorAction SilentlyContinue
if ($ngrokProcess) {
    Write-Host "   ✅ Ngrok process is running" -ForegroundColor Green
} else {
    Write-Host "   ❌ Ngrok process is NOT running" -ForegroundColor Red
    Write-Host "   Starting Ngrok..."
    Start-Process -FilePath ".\auto-start-ngrok.bat" -NoNewWindow
    Start-Sleep -Seconds 5
}

# Step 4: Get tunnel information
Write-Host "\n4. Getting tunnel information..."
try {
    $tunnelResponse = Invoke-WebRequest -Uri "http://localhost:4040/api/tunnels" -UseBasicParsing -TimeoutSec 10
    $tunnelData = $tunnelResponse.Content | ConvertFrom-Json
    
    if ($tunnelData.tunnels.Count -gt 0) {
        Write-Host "   ✅ Tunnel is active" -ForegroundColor Green
        foreach ($tunnel in $tunnelData.tunnels) {
            Write-Host "   Name: $($tunnel.name)"
            Write-Host "   Public URL: $($tunnel.public_url)" -ForegroundColor Cyan
            Write-Host "   Local URL: $($tunnel.config.addr)"
            
            # Test the public URL
            Write-Host "\n5. Testing public URL accessibility..."
            try {
                $publicResponse = Invoke-WebRequest -Uri $tunnel.public_url -UseBasicParsing -TimeoutSec 15
                Write-Host "   ✅ Public URL is accessible!" -ForegroundColor Green
                Write-Host "   Status Code: $($publicResponse.StatusCode)"
                
                Write-Host "\n=== SUCCESS! Your ROTC QR System is accessible at: ==="
                Write-Host $tunnel.public_url -ForegroundColor Yellow
                Write-Host "\nDirect links:"
                Write-Host "• Main System: $($tunnel.public_url)"
                Write-Host "• Admin Dashboard: $($tunnel.public_url)/admin_dashboard.php"
                Write-Host "• Login: $($tunnel.public_url)/login.php"
                Write-Host "• Scanner: $($tunnel.public_url)/scanner.php"
                
            } catch {
                Write-Host "   ❌ Public URL is NOT accessible" -ForegroundColor Red
                Write-Host "   Error: $($_.Exception.Message)"
                Write-Host "   This might be a temporary DNS issue. Try again in a few minutes."
            }
        }
    } else {
        Write-Host "   ❌ No active tunnels found" -ForegroundColor Red
        Write-Host "   Restarting Ngrok..."
        
        # Kill existing ngrok processes
        Get-Process -Name "ngrok" -ErrorAction SilentlyContinue | Stop-Process -Force
        Start-Sleep -Seconds 2
        
        # Start fresh
        Start-Process -FilePath ".\auto-start-ngrok.bat" -NoNewWindow
        Write-Host "   Ngrok restarted. Please run this script again in 10 seconds."
    }
} catch {
    Write-Host "   ❌ Cannot connect to Ngrok web interface" -ForegroundColor Red
    Write-Host "   Error: $($_.Exception.Message)"
    Write-Host "   Ngrok might not be running properly."
}

Write-Host "\n=== Troubleshoot Complete ==="
Write-Host "If issues persist, try:"
Write-Host "1. Restart XAMPP"
Write-Host "2. Run: .\stop-ngrok.ps1"
Write-Host "3. Run: .\auto-start-ngrok.bat"
Write-Host "4. Wait 30 seconds and run this script again"