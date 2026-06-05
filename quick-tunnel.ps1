# Quick Cloudflare Tunnel Setup (Temporary URLs)
# This provides immediate access without domain setup, but URLs change on restart
# Use this for testing or when you don't have a domain yet

param(
    [Parameter(Mandatory=$false)]
    [int]$LocalPort = 80,
    
    [Parameter(Mandatory=$false)]
    [string]$TunnelName = "rotc-qr-quick"
)

$script:CloudflaredPath = "C:\cloudflared\cloudflared.exe"
$script:ConfigPath = "C:\cloudflared\quick-config.yml"
$script:LogPath = "C:\cloudflared\quick-tunnel.log"
$script:UrlsFile = "C:\cloudflared\tunnel-urls.txt"

function Write-ColorOutput {
    param(
        [string]$Message,
        [string]$Color = "White"
    )
    Write-Host $Message -ForegroundColor $Color
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

function Create-QuickConfig {
    Write-ColorOutput "Creating quick tunnel configuration..." "Yellow"
    
    $configContent = @"
# Quick tunnel configuration for testing
# Uses TryCloudflare for immediate access (temporary URLs)

url: http://localhost:$LocalPort
logfile: $script:LogPath
loglevel: info
metrics: 127.0.0.1:8081
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

function Start-QuickTunnel {
    Write-ColorOutput "Starting quick tunnel (TryCloudflare)..." "Yellow"
    Write-ColorOutput "This will provide temporary URLs for immediate testing" "Cyan"
    
    try {
        # Stop any existing tunnel
        Stop-ExistingTunnel
        
        # Start tunnel with TryCloudflare
        $tunnelArgs = @(
            "tunnel",
            "--config", $script:ConfigPath,
            "--url", "http://localhost:$LocalPort"
        )
        
        Write-ColorOutput "Starting tunnel process..." "Cyan"
        $process = Start-Process -FilePath $script:CloudflaredPath -ArgumentList $tunnelArgs -PassThru -NoNewWindow
        
        if ($process) {
            # Save process ID
            $process.Id | Out-File "C:\cloudflared\tunnel-pid.txt"
            
            Write-ColorOutput "Tunnel started with Process ID: $($process.Id)" "Green"
            Write-ColorOutput "Waiting for tunnel to establish connection..." "Yellow"
            
            # Wait for tunnel to start and get URLs
            Start-Sleep -Seconds 10
            
            # Try to extract URLs from log
            if (Test-Path $script:LogPath) {
                $logContent = Get-Content $script:LogPath -Raw
                $urlPattern = 'https://[a-zA-Z0-9-]+\.trycloudflare\.com'
                $urls = [regex]::Matches($logContent, $urlPattern) | ForEach-Object { $_.Value } | Select-Object -Unique
                
                if ($urls) {
                    Write-ColorOutput "`n=== ROTC QR System - Quick Tunnel URLs ===" "Green"
                    Write-ColorOutput "Your application is accessible at:" "Yellow"
                    
                    foreach ($url in $urls) {
                        Write-ColorOutput "• $url" "White"
                        Write-ColorOutput "• $url/admin (Admin Dashboard)" "White"
                        Write-ColorOutput "• $url/qr (QR Scanner)" "White"
                        Write-ColorOutput "• $url/api (API Endpoint)" "White"
                    }
                    
                    # Save URLs to file
                    $urls | Out-File $script:UrlsFile
                    
                    Write-ColorOutput "`nURLs saved to: $script:UrlsFile" "Cyan"
                    Write-ColorOutput "`nNote: These are temporary URLs that change on restart" "Yellow"
                    Write-ColorOutput "For permanent URLs, use the automated setup with your domain" "Yellow"
                }
                else {
                    Write-ColorOutput "Tunnel started but URLs not detected yet. Check log file: $script:LogPath" "Yellow"
                }
            }
            
            Write-ColorOutput "`nTunnel is running in the background" "Green"
            Write-ColorOutput "To stop: run Stop-QuickTunnel or close this PowerShell session" "Cyan"
            
            return $true
        }
        else {
            Write-ColorOutput "Failed to start tunnel process" "Red"
            return $false
        }
    }
    catch {
        Write-ColorOutput "Error starting tunnel: $($_.Exception.Message)" "Red"
        return $false
    }
}

function Stop-ExistingTunnel {
    Write-ColorOutput "Checking for existing tunnel processes..." "Yellow"
    
    # Stop by process ID if exists
    if (Test-Path "C:\cloudflared\tunnel-pid.txt") {
        $pid = Get-Content "C:\cloudflared\tunnel-pid.txt"
        try {
            $process = Get-Process -Id $pid -ErrorAction SilentlyContinue
            if ($process) {
                Stop-Process -Id $pid -Force
                Write-ColorOutput "Stopped existing tunnel (PID: $pid)" "Green"
            }
        }
        catch {
            # Process might already be stopped
        }
        Remove-Item "C:\cloudflared\tunnel-pid.txt" -ErrorAction SilentlyContinue
    }
    
    # Kill any cloudflared processes
    Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue | Stop-Process -Force
}

function Stop-QuickTunnel {
    Write-ColorOutput "Stopping quick tunnel..." "Yellow"
    Stop-ExistingTunnel
    Write-ColorOutput "Quick tunnel stopped" "Green"
}

function Show-TunnelStatus {
    if (Test-Path "C:\cloudflared\tunnel-pid.txt") {
        $pid = Get-Content "C:\cloudflared\tunnel-pid.txt"
        $process = Get-Process -Id $pid -ErrorAction SilentlyContinue
        
        if ($process) {
            Write-ColorOutput "Tunnel Status: Running (PID: $pid)" "Green"
            
            if (Test-Path $script:UrlsFile) {
                Write-ColorOutput "`nCurrent URLs:" "Yellow"
                Get-Content $script:UrlsFile | ForEach-Object {
                    Write-ColorOutput "• $_" "White"
                }
            }
        }
        else {
            Write-ColorOutput "Tunnel Status: Stopped" "Red"
        }
    }
    else {
        Write-ColorOutput "Tunnel Status: Not running" "Yellow"
    }
}

# Main execution
function Main {
    Write-ColorOutput "=== ROTC QR System - Quick Tunnel Setup ===" "Cyan"
    Write-ColorOutput "Setting up temporary tunnel for immediate testing..." "Yellow"
    Write-ColorOutput "Note: URLs will change on restart. For permanent URLs, use the automated setup." "Yellow"
    Write-ColorOutput ""
    
    # Install cloudflared
    if (!(Install-Cloudflared)) {
        Write-ColorOutput "Failed to install cloudflared. Exiting." "Red"
        return
    }
    
    # Create configuration
    if (!(Create-QuickConfig)) {
        Write-ColorOutput "Failed to create configuration. Exiting." "Red"
        return
    }
    
    # Start tunnel
    if (!(Start-QuickTunnel)) {
        Write-ColorOutput "Failed to start tunnel. Exiting." "Red"
        return
    }
    
    Write-ColorOutput "`nQuick tunnel setup completed!" "Green"
    Write-ColorOutput "Your ROTC QR System is now accessible via the URLs shown above." "Green"
}

# Export functions for external use
Export-ModuleMember -Function Stop-QuickTunnel, Show-TunnelStatus

# Run main function if script is executed directly
if ($MyInvocation.InvocationName -ne '.') {
    Main
}