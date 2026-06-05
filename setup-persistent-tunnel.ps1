# Persistent Cloudflare Tunnel Setup
# This script creates a named tunnel that persists across reboots
# Run this once to set up, then use cloudflare-service.ps1 to manage

param(
    [switch]$Setup,
    [switch]$Install,
    [switch]$Configure,
    [switch]$Start,
    [switch]$Stop,
    [switch]$Status,
    [string]$TunnelName = "rotc-qr-system"
)

$cloudflaredPath = "$PWD\cloudflare\cloudflared.exe"
$configFile = "$PWD\cloudflare-tunnel.yml"
$credentialsDir = "$env:USERPROFILE\.cloudflared"
$urlFile = "$PWD\cloudflare-url.txt"

function Write-ColorOutput($Message, $Color = "White") {
    Write-Host $Message -ForegroundColor $Color
}

function Test-CloudflaredInstalled {
    return Test-Path $cloudflaredPath
}

function Install-Cloudflared {
    Write-ColorOutput "Installing cloudflared..." "Green"
    
    if (-not (Test-Path "cloudflare")) {
        New-Item -ItemType Directory -Path "cloudflare" -Force | Out-Null
    }
    
    $downloadUrl = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
    
    try {
        Write-ColorOutput "Downloading cloudflared..."
        Invoke-WebRequest -Uri $downloadUrl -OutFile $cloudflaredPath -UseBasicParsing
        Write-ColorOutput "✓ cloudflared downloaded successfully" "Green"
        return $true
    } catch {
        Write-ColorOutput "✗ Failed to download cloudflared: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Setup-PersistentTunnel {
    Write-ColorOutput "Setting up persistent Cloudflare tunnel..." "Green"
    
    # Check if already authenticated
    if (-not (Test-Path "$credentialsDir\cert.pem")) {
        Write-ColorOutput "Authenticating with Cloudflare..." "Yellow"
        Write-ColorOutput "A browser window will open for authentication." "Yellow"
        
        try {
            & $cloudflaredPath tunnel login
            if ($LASTEXITCODE -ne 0) {
                Write-ColorOutput "✗ Authentication failed" "Red"
                return $false
            }
        } catch {
            Write-ColorOutput "✗ Authentication error: $($_.Exception.Message)" "Red"
            return $false
        }
    }
    
    # Create tunnel
    Write-ColorOutput "Creating tunnel '$TunnelName'..." "Yellow"
    
    try {
        $tunnelOutput = & $cloudflaredPath tunnel create $TunnelName 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-ColorOutput "✓ Tunnel '$TunnelName' created successfully" "Green"
        } else {
            # Check if tunnel already exists
            if ($tunnelOutput -match "already exists") {
                Write-ColorOutput "Tunnel '$TunnelName' already exists, continuing..." "Yellow"
            } else {
                Write-ColorOutput "✗ Failed to create tunnel: $tunnelOutput" "Red"
                return $false
            }
        }
    } catch {
        Write-ColorOutput "✗ Tunnel creation error: $($_.Exception.Message)" "Red"
        return $false
    }
    
    # Create configuration file
    Write-ColorOutput "Creating tunnel configuration..." "Yellow"
    
    $configContent = @"
tunnel: $TunnelName
credentials-file: $credentialsDir\$TunnelName.json

ingress:
  # Main ROTC QR System
  - hostname: "*.trycloudflare.com"
    service: http://localhost:80
    originRequest:
      noTLSVerify: true
  
  # Admin Dashboard
  - hostname: "*.trycloudflare.com"
    path: /admin*
    service: http://localhost:80/admin
    originRequest:
      noTLSVerify: true
  
  # QR Scanner
  - hostname: "*.trycloudflare.com"
    path: /QR*
    service: http://localhost:80/QR
    originRequest:
      noTLSVerify: true
  
  # API Endpoints
  - hostname: "*.trycloudflare.com"
    path: /api*
    service: http://localhost:80
    originRequest:
      noTLSVerify: true
  
  # Catch-all rule
  - service: http://localhost:80
    originRequest:
      noTLSVerify: true
"@
    
    try {
        $configContent | Out-File -FilePath $configFile -Encoding UTF8
        Write-ColorOutput "✓ Configuration file created" "Green"
    } catch {
        Write-ColorOutput "✗ Failed to create config file: $($_.Exception.Message)" "Red"
        return $false
    }
    
    # Get tunnel info and create route
    Write-ColorOutput "Setting up tunnel route..." "Yellow"
    
    try {
        $tunnelInfo = & $cloudflaredPath tunnel list --output json | ConvertFrom-Json
        $tunnel = $tunnelInfo | Where-Object { $_.name -eq $TunnelName }
        
        if ($tunnel) {
            $tunnelId = $tunnel.id
            Write-ColorOutput "Tunnel ID: $tunnelId" "Cyan"
            
            # Create a route (this will give us a trycloudflare.com subdomain)
            Write-ColorOutput "Creating tunnel route..." "Yellow"
            & $cloudflaredPath tunnel route dns $TunnelName "$TunnelName.trycloudflare.com"
        }
    } catch {
        Write-ColorOutput "Note: Route setup may require manual configuration" "Yellow"
    }
    
    Write-ColorOutput "\n✓ Persistent tunnel setup complete!" "Green"
    Write-ColorOutput "Use the following commands to manage your tunnel:" "Cyan"
    Write-ColorOutput "  Start: .\setup-persistent-tunnel.ps1 -Start" "White"
    Write-ColorOutput "  Stop:  .\setup-persistent-tunnel.ps1 -Stop" "White"
    Write-ColorOutput "  Status: .\setup-persistent-tunnel.ps1 -Status" "White"
    
    return $true
}

function Start-PersistentTunnel {
    Write-ColorOutput "Starting persistent tunnel..." "Green"
    
    if (-not (Test-Path $configFile)) {
        Write-ColorOutput "✗ Configuration file not found. Run -Setup first." "Red"
        return $false
    }
    
    try {
        # Start tunnel in background
        $process = Start-Process -FilePath $cloudflaredPath -ArgumentList "tunnel", "--config", $configFile, "run", $TunnelName -PassThru -WindowStyle Hidden
        
        # Wait a moment for startup
        Start-Sleep -Seconds 5
        
        if (-not $process.HasExited) {
            Write-ColorOutput "✓ Persistent tunnel started successfully" "Green"
            
            # Try to get tunnel URL
            try {
                $tunnelInfo = & $cloudflaredPath tunnel info $TunnelName --output json | ConvertFrom-Json
                if ($tunnelInfo -and $tunnelInfo.conns) {
                    $url = $tunnelInfo.conns[0].conns[0].url
                    if ($url) {
                        Write-ColorOutput "\nTunnel URL: $url" "Cyan"
                        
                        # Save URL to file
                        $urlContent = @"
# ROTC QR System - Persistent Cloudflare Tunnel URLs
# Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
# Tunnel Type: Named Persistent Tunnel
# Tunnel Name: $TunnelName

# Main Access URL
Main URL: $url

# Direct Access Links
Admin Dashboard: $url/admin_dashboard.php
QR Scanner: $url/QR/scanner.html
Student Dashboard: $url/cadet_dashboard.php
Officer Dashboard: $url/officer_dashboard.php

# API Endpoints
Attendance API: $url/attendance/scan.php
Student Data: $url/get_students.php

# Management
# This is a persistent tunnel that will maintain the same URL
# To manage: Use setup-persistent-tunnel.ps1 -Start/-Stop/-Status
# To restart: setup-persistent-tunnel.ps1 -Stop then -Start
"@
                        $urlContent | Out-File -FilePath $urlFile -Encoding UTF8
                        Write-ColorOutput "✓ URLs saved to cloudflare-url.txt" "Green"
                    }
                }
            } catch {
                Write-ColorOutput "Note: Could not retrieve tunnel URL automatically" "Yellow"
            }
            
            return $true
        } else {
            Write-ColorOutput "✗ Tunnel failed to start" "Red"
            return $false
        }
    } catch {
        Write-ColorOutput "✗ Failed to start tunnel: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Stop-PersistentTunnel {
    Write-ColorOutput "Stopping persistent tunnel..." "Yellow"
    
    try {
        # Find and stop cloudflared processes
        $processes = Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue
        
        if ($processes) {
            $processes | Stop-Process -Force
            Write-ColorOutput "✓ Tunnel stopped" "Green"
        } else {
            Write-ColorOutput "No tunnel processes found" "Yellow"
        }
    } catch {
        Write-ColorOutput "✗ Error stopping tunnel: $($_.Exception.Message)" "Red"
    }
}

function Get-TunnelStatus {
    Write-ColorOutput "Persistent Tunnel Status" "Cyan"
    Write-ColorOutput "========================" "Cyan"
    
    # Check if cloudflared is running
    $processes = Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue
    
    if ($processes) {
        Write-ColorOutput "✓ Tunnel is running" "Green"
        Write-ColorOutput "Process ID(s): $($processes.Id -join ', ')" "White"
        
        # Try to get tunnel info
        try {
            $tunnelList = & $cloudflaredPath tunnel list --output json | ConvertFrom-Json
            $activeTunnel = $tunnelList | Where-Object { $_.name -eq $TunnelName }
            
            if ($activeTunnel) {
                Write-ColorOutput "\nTunnel Details:" "Cyan"
                Write-ColorOutput "Name: $($activeTunnel.name)" "White"
                Write-ColorOutput "ID: $($activeTunnel.id)" "White"
                Write-ColorOutput "Created: $($activeTunnel.created_at)" "White"
            }
        } catch {
            Write-ColorOutput "Could not retrieve detailed tunnel info" "Yellow"
        }
        
        # Show URL if available
        if (Test-Path $urlFile) {
            Write-ColorOutput "\nSaved URLs:" "Cyan"
            Get-Content $urlFile | Where-Object { $_ -match "^Main URL:" -or $_ -match "^Admin Dashboard:" -or $_ -match "^QR Scanner:" } | ForEach-Object {
                Write-ColorOutput $_ "White"
            }
        }
    } else {
        Write-ColorOutput "✗ Tunnel is not running" "Red"
        
        if (Test-Path $configFile) {
            Write-ColorOutput "Configuration exists. Use -Start to run the tunnel." "Yellow"
        } else {
            Write-ColorOutput "No configuration found. Use -Setup to create a persistent tunnel." "Yellow"
        }
    }
}

# Main execution
if (-not (Test-CloudflaredInstalled) -and ($Setup -or $Install)) {
    if (-not (Install-Cloudflared)) {
        exit 1
    }
}

if ($Setup) {
    if (-not (Test-CloudflaredInstalled)) {
        Write-ColorOutput "Installing cloudflared first..." "Yellow"
        if (-not (Install-Cloudflared)) {
            exit 1
        }
    }
    Setup-PersistentTunnel
} elseif ($Install) {
    Install-Cloudflared
} elseif ($Configure) {
    Setup-PersistentTunnel
} elseif ($Start) {
    Start-PersistentTunnel
} elseif ($Stop) {
    Stop-PersistentTunnel
} elseif ($Status) {
    Get-TunnelStatus
} else {
    Write-ColorOutput "Persistent Cloudflare Tunnel Manager" "Cyan"
    Write-ColorOutput "===================================" "Cyan"
    Write-ColorOutput "Usage:"
    Write-ColorOutput "  .\setup-persistent-tunnel.ps1 -Setup     # Complete setup (install + configure)"
    Write-ColorOutput "  .\setup-persistent-tunnel.ps1 -Install   # Install cloudflared only"
    Write-ColorOutput "  .\setup-persistent-tunnel.ps1 -Configure # Configure tunnel only"
    Write-ColorOutput "  .\setup-persistent-tunnel.ps1 -Start     # Start the tunnel"
    Write-ColorOutput "  .\setup-persistent-tunnel.ps1 -Stop      # Stop the tunnel"
    Write-ColorOutput "  .\setup-persistent-tunnel.ps1 -Status    # Check tunnel status"
    Write-ColorOutput "\nFor automatic startup, use cloudflare-service.ps1" "Yellow"
}