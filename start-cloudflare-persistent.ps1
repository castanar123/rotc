# PowerShell script to start Cloudflare tunnel as a persistent background process
# This script creates a detached process that survives terminal closure

$workingDir = "c:\xampp\htdocs\generate qr"
$cloudflaredPath = "$workingDir\cloudflare\cloudflared.exe"
$configPath = "$workingDir\cloudflare-tunnel.yml"

# Check if cloudflared exists
if (-not (Test-Path $cloudflaredPath)) {
    Write-Host "[ERROR] cloudflared.exe not found at $cloudflaredPath"
    exit 1
}

# Check if config exists
if (-not (Test-Path $configPath)) {
    Write-Host "[ERROR] cloudflare-tunnel.yml not found at $configPath"
    exit 1
}

# Kill any existing cloudflared processes
Get-Process -Name "cloudflared" -ErrorAction SilentlyContinue | Stop-Process -Force

# Start cloudflared as a detached process using Start-Process with specific parameters
# This creates a process that runs independently of the PowerShell session
Write-Host "[INFO] Starting Cloudflare tunnel as persistent background service..."

try {
    $process = Start-Process -FilePath $cloudflaredPath `
                            -ArgumentList "tunnel", "--config", $configPath, "run" `
                            -WorkingDirectory $workingDir `
                            -WindowStyle Hidden `
                            -PassThru
    
    # Wait a moment for the process to initialize
    Start-Sleep -Seconds 3
    
    # Check if the process is still running
    if ($process -and !$process.HasExited) {
        Write-Host "[SUCCESS] Cloudflare tunnel started successfully with PID: $($process.Id)"
        Write-Host "[INFO] Tunnel is running independently and will persist after terminal closure"
        return $true
    } else {
        Write-Host "[ERROR] Cloudflare tunnel failed to start or exited immediately"
        return $false
    }
} catch {
    Write-Host "[ERROR] Failed to start Cloudflare tunnel: $($_.Exception.Message)"
    return $false
}