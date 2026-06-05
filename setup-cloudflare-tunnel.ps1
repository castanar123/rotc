# ROTC QR System - Cloudflare Tunnel Setup Script
# Sets up Cloudflare Tunnel for permanent, reliable internet access

param(
    [string]$TunnelName = "rotc-qr-system",
    [string]$Domain = "",
    [switch]$Install,
    [switch]$Configure,
    [switch]$Start,
    [switch]$Help
)

$PROJECT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$CLOUDFLARED_DIR = Join-Path $PROJECT_DIR "cloudflare"
$CLOUDFLARED_EXE = Join-Path $CLOUDFLARED_DIR "cloudflared.exe"
$CONFIG_FILE = Join-Path $PROJECT_DIR "cloudflare-tunnel.yml"
$CREDENTIALS_FILE = Join-Path $PROJECT_DIR ".cloudflared\credentials.json"
$LOG_FILE = Join-Path $PROJECT_DIR "cloudflare-tunnel.log"
$URL_FILE = Join-Path $PROJECT_DIR "cloudflare-url.txt"

function Write-Log {
    param($Message, $Color = "White")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LOG_FILE -Value $logMessage -ErrorAction SilentlyContinue
}

function Show-Help {
    Write-Host "=== ROTC QR System - Cloudflare Tunnel Setup ===" -ForegroundColor Green
    Write-Host ""
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  .\setup-cloudflare-tunnel.ps1 -Install" -ForegroundColor Cyan
    Write-Host "  .\setup-cloudflare-tunnel.ps1 -Configure" -ForegroundColor Cyan
    Write-Host "  .\setup-cloudflare-tunnel.ps1 -Start" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Parameters:" -ForegroundColor Yellow
    Write-Host "  -Install     Download and install cloudflared" -ForegroundColor Cyan
    Write-Host "  -Configure   Set up tunnel configuration" -ForegroundColor Cyan
    Write-Host "  -Start       Start the tunnel service" -ForegroundColor Cyan
    Write-Host "  -TunnelName  Custom tunnel name (default: rotc-qr-system)" -ForegroundColor Cyan
    Write-Host "  -Domain      Custom domain (optional)" -ForegroundColor Cyan
    Write-Host "  -Help        Show this help" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Examples:" -ForegroundColor Yellow
    Write-Host "  .\setup-cloudflare-tunnel.ps1 -Install -Configure -Start" -ForegroundColor Gray
    Write-Host "  .\setup-cloudflare-tunnel.ps1 -TunnelName 'my-rotc' -Configure" -ForegroundColor Gray
}

function Install-Cloudflared {
    Write-Log "Installing Cloudflare Tunnel (cloudflared)..." "Green"
    
    # Create cloudflare directory
    if (-not (Test-Path $CLOUDFLARED_DIR)) {
        New-Item -ItemType Directory -Path $CLOUDFLARED_DIR -Force | Out-Null
        Write-Log "Created cloudflare directory" "Cyan"
    }
    
    # Download cloudflared
    $downloadUrl = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
    Write-Log "Downloading cloudflared from GitHub..." "Cyan"
    
    try {
        Invoke-WebRequest -Uri $downloadUrl -OutFile $CLOUDFLARED_EXE -UseBasicParsing
        Write-Log "Cloudflared downloaded successfully" "Green"
    }
    catch {
        Write-Log "Error downloading cloudflared: $($_.Exception.Message)" "Red"
        return $false
    }
    
    # Verify installation
    if (Test-Path $CLOUDFLARED_EXE) {
        try {
            $version = & $CLOUDFLARED_EXE version
            Write-Log "Cloudflared installed successfully: $version" "Green"
            return $true
        }
        catch {
            Write-Log "Error verifying cloudflared installation" "Red"
            return $false
        }
    }
    else {
        Write-Log "Cloudflared installation failed" "Red"
        return $false
    }
}

function Configure-Tunnel {
    Write-Log "Configuring Cloudflare Tunnel..." "Green"
    
    if (-not (Test-Path $CLOUDFLARED_EXE)) {
        Write-Log "Cloudflared not found. Please run with -Install first." "Red"
        return $false
    }
    
    Write-Host ""
    Write-Host "=== Cloudflare Tunnel Configuration ===" -ForegroundColor Green
    Write-Host ""
    Write-Host "To set up Cloudflare Tunnel, you need to:" -ForegroundColor Yellow
    Write-Host "1. Have a Cloudflare account (free)" -ForegroundColor Cyan
    Write-Host "2. Add a domain to Cloudflare (or use a subdomain)" -ForegroundColor Cyan
    Write-Host "3. Authenticate with Cloudflare" -ForegroundColor Cyan
    Write-Host ""
    
    # Step 1: Authentication
    Write-Host "Step 1: Authenticate with Cloudflare" -ForegroundColor Yellow
    Write-Host "This will open a browser window for authentication..." -ForegroundColor Gray
    
    try {
        & $CLOUDFLARED_EXE tunnel login
        Write-Log "Authentication completed" "Green"
    }
    catch {
        Write-Log "Authentication failed: $($_.Exception.Message)" "Red"
        return $false
    }
    
    # Step 2: Create tunnel
    Write-Host ""
    Write-Host "Step 2: Creating tunnel '$TunnelName'..." -ForegroundColor Yellow
    
    try {
        $createResult = & $CLOUDFLARED_EXE tunnel create $TunnelName 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Log "Tunnel '$TunnelName' created successfully" "Green"
        }
        else {
            # Tunnel might already exist
            Write-Log "Tunnel creation result: $createResult" "Yellow"
        }
    }
    catch {
        Write-Log "Error creating tunnel: $($_.Exception.Message)" "Red"
    }
    
    # Step 3: Create configuration file
    Write-Host ""
    Write-Host "Step 3: Creating configuration file..." -ForegroundColor Yellow
    
    $configContent = @"
tunnel: $TunnelName
credentials-file: $CREDENTIALS_FILE

ingress:
  # Main ROTC QR System
  - hostname: $TunnelName.trycloudflare.com
    service: http://localhost:80
    originRequest:
      httpHostHeader: localhost
  
  # Admin Dashboard
  - hostname: admin-$TunnelName.trycloudflare.com
    service: http://localhost:80
    path: /admin_dashboard.php
    originRequest:
      httpHostHeader: localhost
  
  # QR Scanner
  - hostname: scanner-$TunnelName.trycloudflare.com
    service: http://localhost:80
    path: /QR/scanner.html
    originRequest:
      httpHostHeader: localhost
  
  # API Endpoint
  - hostname: api-$TunnelName.trycloudflare.com
    service: http://localhost:80
    path: /QR/session.php
    originRequest:
      httpHostHeader: localhost
  
  # Catch-all rule (must be last)
  - service: http_status:404
"@
    
    Set-Content -Path $CONFIG_FILE -Value $configContent -Encoding UTF8
    Write-Log "Configuration file created: $CONFIG_FILE" "Green"
    
    # Step 4: Show next steps
    Write-Host ""
    Write-Host "=== Configuration Complete ===" -ForegroundColor Green
    Write-Host ""
    Write-Host "Your tunnel URLs will be:" -ForegroundColor Yellow
    Write-Host "  Main Site: https://$TunnelName.trycloudflare.com" -ForegroundColor Cyan
    Write-Host "  Admin: https://admin-$TunnelName.trycloudflare.com" -ForegroundColor Cyan
    Write-Host "  Scanner: https://scanner-$TunnelName.trycloudflare.com" -ForegroundColor Cyan
    Write-Host "  API: https://api-$TunnelName.trycloudflare.com" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "To start the tunnel, run:" -ForegroundColor Yellow
    Write-Host "  .\setup-cloudflare-tunnel.ps1 -Start" -ForegroundColor Cyan
    
    return $true
}

function Start-Tunnel {
    Write-Log "Starting Cloudflare Tunnel..." "Green"
    
    if (-not (Test-Path $CLOUDFLARED_EXE)) {
        Write-Log "Cloudflared not found. Please run with -Install first." "Red"
        return $false
    }
    
    if (-not (Test-Path $CONFIG_FILE)) {
        Write-Log "Configuration file not found. Please run with -Configure first." "Red"
        return $false
    }
    
    Write-Host "Starting tunnel with configuration..." -ForegroundColor Cyan
    
    try {
        # Start tunnel in background
        $process = Start-Process -FilePath $CLOUDFLARED_EXE -ArgumentList "tunnel", "--config", $CONFIG_FILE, "run", $TunnelName -PassThru -WindowStyle Hidden
        
        Write-Log "Tunnel started with PID: $($process.Id)" "Green"
        
        # Save process ID
        Set-Content -Path (Join-Path $PROJECT_DIR "cloudflare-tunnel.pid") -Value $process.Id
        
        # Wait a moment for tunnel to establish
        Start-Sleep -Seconds 5
        
        # Save URLs
        $urls = @(
            "https://$TunnelName.trycloudflare.com",
            "https://admin-$TunnelName.trycloudflare.com",
            "https://scanner-$TunnelName.trycloudflare.com",
            "https://api-$TunnelName.trycloudflare.com"
        )
        
        Set-Content -Path $URL_FILE -Value ($urls -join "`n")
        
        Write-Host ""
        Write-Host "=== Cloudflare Tunnel Started Successfully ===" -ForegroundColor Green
        Write-Host ""
        Write-Host "Your ROTC QR System is now accessible at:" -ForegroundColor Yellow
        foreach ($url in $urls) {
            Write-Host "  $url" -ForegroundColor Cyan
        }
        Write-Host ""
        Write-Host "URLs saved to: $URL_FILE" -ForegroundColor Gray
        
        return $true
    }
    catch {
        Write-Log "Error starting tunnel: $($_.Exception.Message)" "Red"
        return $false
    }
}

# Main execution
Write-Host "=== ROTC QR System - Cloudflare Tunnel Setup ===" -ForegroundColor Green
Write-Host ""

if ($Help) {
    Show-Help
    exit 0
}

if (-not $Install -and -not $Configure -and -not $Start) {
    Write-Host "No action specified. Use -Help for usage information." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Quick start:" -ForegroundColor Cyan
    Write-Host "  .\setup-cloudflare-tunnel.ps1 -Install -Configure -Start" -ForegroundColor Gray
    exit 0
}

# Execute requested actions
$success = $true

if ($Install) {
    $success = Install-Cloudflared
    if (-not $success) {
        Write-Log "Installation failed. Stopping." "Red"
        exit 1
    }
}

if ($Configure) {
    $success = Configure-Tunnel
    if (-not $success) {
        Write-Log "Configuration failed. Stopping." "Red"
        exit 1
    }
}

if ($Start) {
    $success = Start-Tunnel
    if (-not $success) {
        Write-Log "Failed to start tunnel. Stopping." "Red"
        exit 1
    }
}

Write-Host ""
Write-Host "=== Setup Complete ===" -ForegroundColor Green
Write-Log "Cloudflare Tunnel setup completed successfully" "Green"