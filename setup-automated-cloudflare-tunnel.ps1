# Automated Cloudflare Tunnel Setup Script
# This script sets up Cloudflare Tunnel without requiring manual browser authentication
# Uses API tokens and service tokens for automated authentication

param(
    [Parameter(Mandatory=$true)]
    [string]$CloudflareApiToken,
    
    [Parameter(Mandatory=$true)]
    [string]$AccountId,
    
    [Parameter(Mandatory=$true)]
    [string]$ZoneId,
    
    [Parameter(Mandatory=$true)]
    [string]$Domain,
    
    [Parameter(Mandatory=$false)]
    [string]$TunnelName = "rotc-qr-system-auto",
    
    [Parameter(Mandatory=$false)]
    [int]$LocalPort = 80
)

# Global variables
$script:TunnelId = ""
$script:TunnelToken = ""
$script:CloudflaredPath = "C:\cloudflared\cloudflared.exe"
$script:ConfigPath = "C:\cloudflared\config.yml"
$script:LogPath = "C:\cloudflared\tunnel.log"

function Write-ColorOutput {
    param(
        [string]$Message,
        [string]$Color = "White"
    )
    Write-Host $Message -ForegroundColor $Color
}

function Test-CloudflareApiToken {
    param([string]$Token)
    
    try {
        $headers = @{
            "Authorization" = "Bearer $Token"
            "Content-Type" = "application/json"
        }
        
        $response = Invoke-RestMethod -Uri "https://api.cloudflare.com/client/v4/user/tokens/verify" -Method GET -Headers $headers
        return $response.success
    }
    catch {
        Write-ColorOutput "Error verifying API token: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Install-Cloudflared {
    Write-ColorOutput "Installing cloudflared..." "Yellow"
    
    # Create cloudflared directory
    if (!(Test-Path "C:\cloudflared")) {
        New-Item -ItemType Directory -Path "C:\cloudflared" -Force | Out-Null
    }
    
    # Download cloudflared if not exists
    if (!(Test-Path $script:CloudflaredPath)) {
        $downloadUrl = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
        Write-ColorOutput "Downloading cloudflared from $downloadUrl" "Cyan"
        
        try {
            Invoke-WebRequest -Uri $downloadUrl -OutFile $script:CloudflaredPath
            Write-ColorOutput "Cloudflared downloaded successfully" "Green"
        }
        catch {
            Write-ColorOutput "Failed to download cloudflared: $($_.Exception.Message)" "Red"
            return $false
        }
    }
    else {
        Write-ColorOutput "Cloudflared already exists" "Green"
    }
    
    return $true
}

function Create-CloudflareTunnel {
    param(
        [string]$ApiToken,
        [string]$AccountId,
        [string]$TunnelName
    )
    
    Write-ColorOutput "Creating Cloudflare Tunnel: $TunnelName" "Yellow"
    
    try {
        $headers = @{
            "Authorization" = "Bearer $ApiToken"
            "Content-Type" = "application/json"
        }
        
        $body = @{
            "name" = $TunnelName
            "config_src" = "cloudflare"
        } | ConvertTo-Json
        
        $response = Invoke-RestMethod -Uri "https://api.cloudflare.com/client/v4/accounts/$AccountId/cfd_tunnel" -Method POST -Headers $headers -Body $body
        
        if ($response.success) {
            $script:TunnelId = $response.result.id
            $script:TunnelToken = $response.result.token
            
            Write-ColorOutput "Tunnel created successfully!" "Green"
            Write-ColorOutput "Tunnel ID: $($script:TunnelId)" "Cyan"
            Write-ColorOutput "Tunnel Token: $($script:TunnelToken)" "Cyan"
            
            return $true
        }
        else {
            Write-ColorOutput "Failed to create tunnel: $($response.errors)" "Red"
            return $false
        }
    }
    catch {
        Write-ColorOutput "Error creating tunnel: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Configure-TunnelIngress {
    param(
        [string]$ApiToken,
        [string]$AccountId,
        [string]$TunnelId,
        [string]$Domain,
        [int]$LocalPort
    )
    
    Write-ColorOutput "Configuring tunnel ingress rules..." "Yellow"
    
    try {
        $headers = @{
            "Authorization" = "Bearer $ApiToken"
            "Content-Type" = "application/json"
        }
        
        $ingressRules = @(
            @{
                "hostname" = $Domain
                "service" = "http://localhost:$LocalPort"
                "originRequest" = @{}
            },
            @{
                "hostname" = "admin-$Domain"
                "service" = "http://localhost:$LocalPort"
                "path" = "/admin/*"
                "originRequest" = @{}
            },
            @{
                "hostname" = "qr-$Domain"
                "service" = "http://localhost:$LocalPort"
                "path" = "/qr/*"
                "originRequest" = @{}
            },
            @{
                "hostname" = "api-$Domain"
                "service" = "http://localhost:$LocalPort"
                "path" = "/api/*"
                "originRequest" = @{}
            },
            @{
                "service" = "http_status:404"
            }
        )
        
        $body = @{
            "config" = @{
                "ingress" = $ingressRules
            }
        } | ConvertTo-Json -Depth 10
        
        $response = Invoke-RestMethod -Uri "https://api.cloudflare.com/client/v4/accounts/$AccountId/cfd_tunnel/$TunnelId/configurations" -Method PUT -Headers $headers -Body $body
        
        if ($response.success) {
            Write-ColorOutput "Tunnel ingress configured successfully!" "Green"
            return $true
        }
        else {
            Write-ColorOutput "Failed to configure tunnel ingress: $($response.errors)" "Red"
            return $false
        }
    }
    catch {
        Write-ColorOutput "Error configuring tunnel ingress: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Create-DnsRecords {
    param(
        [string]$ApiToken,
        [string]$ZoneId,
        [string]$Domain,
        [string]$TunnelId
    )
    
    Write-ColorOutput "Creating DNS records..." "Yellow"
    
    $domains = @($Domain, "admin-$Domain", "qr-$Domain", "api-$Domain")
    $tunnelTarget = "$TunnelId.cfargotunnel.com"
    
    try {
        $headers = @{
            "Authorization" = "Bearer $ApiToken"
            "Content-Type" = "application/json"
        }
        
        foreach ($domainName in $domains) {
            $body = @{
                "type" = "CNAME"
                "proxied" = $true
                "name" = $domainName
                "content" = $tunnelTarget
            } | ConvertTo-Json
            
            $response = Invoke-RestMethod -Uri "https://api.cloudflare.com/client/v4/zones/$ZoneId/dns_records" -Method POST -Headers $headers -Body $body
            
            if ($response.success) {
                Write-ColorOutput "DNS record created for $domainName" "Green"
            }
            else {
                Write-ColorOutput "Failed to create DNS record for $domainName - $($response.errors | ConvertTo-Json)" "Red"
            }
        }
        
        return $true
    }
    catch {
        Write-ColorOutput "Error creating DNS records: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Create-ConfigFile {
    param(
        [string]$TunnelToken
    )
    
    Write-ColorOutput "Creating cloudflared configuration file..." "Yellow"
    
    $configContent = @"
tunnel: $script:TunnelId
credentials-file: C:\cloudflared\$script:TunnelId.json

# Metrics and logging
metrics: 0.0.0.0:8080
logfile: $script:LogPath
loglevel: info

# Auto-update
autoupdate-freq: 24h
"@
    
    try {
        Set-Content -Path $script:ConfigPath -Value $configContent -Encoding UTF8
        Write-ColorOutput "Configuration file created at $script:ConfigPath" "Green"
        return $true
    }
    catch {
        Write-ColorOutput "Failed to create configuration file: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Start-TunnelService {
    param(
        [string]$TunnelToken
    )
    
    Write-ColorOutput "Starting Cloudflare Tunnel service..." "Yellow"
    
    try {
        # Install as Windows service
        $installArgs = @(
            "service",
            "install",
            $TunnelToken
        )
        
        $process = Start-Process -FilePath $script:CloudflaredPath -ArgumentList $installArgs -Wait -PassThru -NoNewWindow
        
        if ($process.ExitCode -eq 0) {
            Write-ColorOutput "Cloudflare Tunnel service installed successfully!" "Green"
            
            # Start the service
            Start-Service -Name "cloudflared"
            Write-ColorOutput "Cloudflare Tunnel service started!" "Green"
            
            return $true
        }
        else {
            Write-ColorOutput "Failed to install Cloudflare Tunnel service" "Red"
            return $false
        }
    }
    catch {
        Write-ColorOutput "Error starting tunnel service: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Show-TunnelInfo {
    Write-ColorOutput "`n=== ROTC QR System - Cloudflare Tunnel Setup Complete ===" "Green"
    Write-ColorOutput "Tunnel Name: $TunnelName" "Cyan"
    Write-ColorOutput "Tunnel ID: $script:TunnelId" "Cyan"
    Write-ColorOutput "`nYour applications are now accessible at:" "Yellow"
    Write-ColorOutput "• Main Application: https://$Domain" "White"
    Write-ColorOutput "• Admin Dashboard: https://admin-$Domain" "White"
    Write-ColorOutput "• QR Scanner: https://qr-$Domain" "White"
    Write-ColorOutput "• API Endpoint: https://api-$Domain" "White"
    Write-ColorOutput "`nTunnel Status: Service is running automatically" "Green"
    Write-ColorOutput "Configuration: $script:ConfigPath" "Cyan"
    Write-ColorOutput "Logs: $script:LogPath" "Cyan"
    Write-ColorOutput "`nNote: These URLs are permanent and will not change on restart!" "Green"
}

# Main execution
function Main {
    Write-ColorOutput "=== Automated Cloudflare Tunnel Setup ===" "Cyan"
    Write-ColorOutput "Setting up permanent tunnel for ROTC QR System..." "Yellow"
    
    # Validate API token
    Write-ColorOutput "Validating Cloudflare API token..." "Yellow"
    if (!(Test-CloudflareApiToken -Token $CloudflareApiToken)) {
        Write-ColorOutput "Invalid API token. Please check your token and try again." "Red"
        return
    }
    Write-ColorOutput "API token validated successfully!" "Green"
    
    # Install cloudflared
    if (!(Install-Cloudflared)) {
        Write-ColorOutput "Failed to install cloudflared. Exiting." "Red"
        return
    }
    
    # Create tunnel
    if (!(Create-CloudflareTunnel -ApiToken $CloudflareApiToken -AccountId $AccountId -TunnelName $TunnelName)) {
        Write-ColorOutput "Failed to create tunnel. Exiting." "Red"
        return
    }
    
    # Configure tunnel ingress
    if (!(Configure-TunnelIngress -ApiToken $CloudflareApiToken -AccountId $AccountId -TunnelId $script:TunnelId -Domain $Domain -LocalPort $LocalPort)) {
        Write-ColorOutput "Failed to configure tunnel ingress. Exiting." "Red"
        return
    }
    
    # Create DNS records
    if (!(Create-DnsRecords -ApiToken $CloudflareApiToken -ZoneId $ZoneId -Domain $Domain -TunnelId $script:TunnelId)) {
        Write-ColorOutput "Failed to create DNS records. Exiting." "Red"
        return
    }
    
    # Create configuration file
    if (!(Create-ConfigFile -TunnelToken $script:TunnelToken)) {
        Write-ColorOutput "Failed to create configuration file. Exiting." "Red"
        return
    }
    
    # Start tunnel service
    if (!(Start-TunnelService -TunnelToken $script:TunnelToken)) {
        Write-ColorOutput "Failed to start tunnel service. Exiting." "Red"
        return
    }
    
    # Show final information
    Show-TunnelInfo
    
    Write-ColorOutput "`nSetup completed successfully! Your tunnel is now running." "Green"
}

# Run main function
Main