# ROTC QR System - Cloudflare Tunnel Service
# Automatic startup and monitoring service for Cloudflare Tunnel
# Works with both persistent and quick tunnels

param(
    [string]$Action = "start",
    [int]$RestartDelay = 30,
    [switch]$Install,
    [switch]$Uninstall,
    [switch]$Help,
    [switch]$Setup,
    [string]$TunnelName = "rotc-qr-system"
)

$PROJECT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$SERVICE_NAME = "ROTCCloudflareService"
$SERVICE_SCRIPT = $MyInvocation.MyCommand.Path
$LOG_FILE = Join-Path $PROJECT_DIR "cloudflare-service.log"
$PID_FILE = Join-Path $PROJECT_DIR "cloudflare-tunnel.pid"
$MANAGER_SCRIPT = Join-Path $PROJECT_DIR "cloudflare-tunnel-manager.ps1"

function Write-ServiceLog {
    param($Message, $Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"
    Add-Content -Path $LOG_FILE -Value $logMessage -ErrorAction SilentlyContinue
    Write-Host $logMessage
}

function Show-Help {
    Write-Host "=== ROTC QR System - Cloudflare Tunnel Service ===" -ForegroundColor Green
    Write-Host ""
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  .\cloudflare-service.ps1 [action]" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Actions:" -ForegroundColor Yellow
    Write-Host "  start     Start monitoring service (default)" -ForegroundColor Cyan
    Write-Host "  stop      Stop monitoring service" -ForegroundColor Cyan
    Write-Host "  status    Check service status" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Options:" -ForegroundColor Yellow
    Write-Host "  -Install      Install as Windows service" -ForegroundColor Cyan
    Write-Host "  -Uninstall    Remove Windows service" -ForegroundColor Cyan
    Write-Host "  -RestartDelay Seconds to wait before restart (default: 30)" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Examples:" -ForegroundColor Yellow
    Write-Host "  .\cloudflare-service.ps1 start" -ForegroundColor Gray
    Write-Host "  .\cloudflare-service.ps1 -Install" -ForegroundColor Gray
}

function Test-TunnelHealth {
    # Check if tunnel process is running
    if (Test-Path $PID_FILE) {
        $processId = Get-Content $PID_FILE -ErrorAction SilentlyContinue
        if ($processId) {
            try {
                $process = Get-Process -Id $processId -ErrorAction Stop
                if ($process.ProcessName -eq "cloudflared") {
                    return $true
                }
            }
            catch {
                # Process not found
            }
        }
    }
    return $false
}

function Start-MonitoringService {
    Write-ServiceLog "Starting Cloudflare Tunnel monitoring service" "INFO"
    
    # Initial tunnel start
    Write-ServiceLog "Starting initial tunnel" "INFO"
    & $MANAGER_SCRIPT start
    
    # Monitoring loop
    while ($true) {
        try {
            Start-Sleep -Seconds 60  # Check every minute
            
            if (-not (Test-TunnelHealth)) {
                Write-ServiceLog "Tunnel is down, attempting restart" "WARN"
                
                # Try to stop any remaining processes
                & $MANAGER_SCRIPT stop
                Start-Sleep -Seconds 5
                
                # Restart tunnel
                & $MANAGER_SCRIPT start
                
                if (Test-TunnelHealth) {
                    Write-ServiceLog "Tunnel restarted successfully" "INFO"
                }
                else {
                    Write-ServiceLog "Failed to restart tunnel, will retry in $RestartDelay seconds" "ERROR"
                    Start-Sleep -Seconds $RestartDelay
                }
            }
            else {
                Write-ServiceLog "Tunnel is healthy" "DEBUG"
            }
        }
        catch {
            Write-ServiceLog "Error in monitoring loop: $($_.Exception.Message)" "ERROR"
            Start-Sleep -Seconds $RestartDelay
        }
    }
}

function Stop-MonitoringService {
    Write-ServiceLog "Stopping Cloudflare Tunnel monitoring service" "INFO"
    
    # Stop the tunnel
    & $MANAGER_SCRIPT stop
    
    # Find and stop monitoring processes
    $monitoringProcesses = Get-Process | Where-Object { 
        $_.ProcessName -eq "powershell" -and 
        $_.CommandLine -like "*cloudflare-service.ps1*"
    }
    
    foreach ($process in $monitoringProcesses) {
        try {
            $process.Kill()
            Write-ServiceLog "Stopped monitoring process PID: $($process.Id)" "INFO"
        }
        catch {
            Write-ServiceLog "Failed to stop process PID: $($process.Id)" "WARN"
        }
    }
}

function Get-ServiceStatus {
    Write-Host "=== Cloudflare Tunnel Service Status ===" -ForegroundColor Green
    Write-Host ""
    
    # Check tunnel health
    if (Test-TunnelHealth) {
        Write-Host "✅ Tunnel is running" -ForegroundColor Green
    }
    else {
        Write-Host "❌ Tunnel is not running" -ForegroundColor Red
    }
    
    # Check for monitoring processes
    $monitoringProcesses = Get-Process | Where-Object { 
        $_.ProcessName -eq "powershell" -and 
        $_.CommandLine -like "*cloudflare-service.ps1*"
    }
    
    if ($monitoringProcesses.Count -gt 0) {
        Write-Host "✅ Monitoring service is active" -ForegroundColor Green
        Write-Host "   Processes: $($monitoringProcesses.Count)" -ForegroundColor Gray
    }
    else {
        Write-Host "❌ Monitoring service is not running" -ForegroundColor Red
    }
    
    # Show recent logs
    if (Test-Path $LOG_FILE) {
        Write-Host ""
        Write-Host "📋 Recent Service Logs:" -ForegroundColor Yellow
        $recentLogs = Get-Content $LOG_FILE -Tail 5 -ErrorAction SilentlyContinue
        foreach ($log in $recentLogs) {
            Write-Host "   $log" -ForegroundColor Gray
        }
    }
}

function Install-WindowsService {
    Write-Host "Installing Cloudflare Tunnel as Windows Service..." -ForegroundColor Green
    
    # Check if running as administrator
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    $isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    
    if (-not $isAdmin) {
        Write-Host "❌ Administrator privileges required for service installation" -ForegroundColor Red
        Write-Host "   Please run PowerShell as Administrator" -ForegroundColor Gray
        return $false
    }
    
    try {
        # Create service using NSSM (Non-Sucking Service Manager) or sc.exe
        $servicePath = "powershell.exe"
        $serviceArgs = "-ExecutionPolicy Bypass -File `"$SERVICE_SCRIPT`" start"
        
        # Use sc.exe to create service
        $result = & sc.exe create $SERVICE_NAME binPath= "$servicePath $serviceArgs" start= auto DisplayName= "ROTC Cloudflare Tunnel Service"
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✅ Service installed successfully" -ForegroundColor Green
            Write-Host "   Service Name: $SERVICE_NAME" -ForegroundColor Gray
            Write-Host "   To start: sc start $SERVICE_NAME" -ForegroundColor Gray
            Write-Host "   To stop: sc stop $SERVICE_NAME" -ForegroundColor Gray
            return $true
        }
        else {
            Write-Host "❌ Failed to install service" -ForegroundColor Red
            return $false
        }
    }
    catch {
        Write-Host "❌ Error installing service: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
}

function Uninstall-WindowsService {
    Write-Host "Uninstalling Cloudflare Tunnel Windows Service..." -ForegroundColor Yellow
    
    # Check if running as administrator
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    $isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    
    if (-not $isAdmin) {
        Write-Host "❌ Administrator privileges required for service removal" -ForegroundColor Red
        return $false
    }
    
    try {
        # Stop service first
        & sc.exe stop $SERVICE_NAME 2>$null
        Start-Sleep -Seconds 3
        
        # Delete service
        $result = & sc.exe delete $SERVICE_NAME
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✅ Service uninstalled successfully" -ForegroundColor Green
            return $true
        }
        else {
            Write-Host "❌ Failed to uninstall service" -ForegroundColor Red
            return $false
        }
    }
    catch {
        Write-Host "❌ Error uninstalling service: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
}

# Main execution
if ($Help) {
    Show-Help
    exit 0
}

if ($Install) {
    Install-WindowsService
    exit 0
}

if ($Uninstall) {
    Uninstall-WindowsService
    exit 0
}

switch ($Action.ToLower()) {
    "start" { 
        Write-ServiceLog "Service start requested" "INFO"
        Start-MonitoringService 
    }
    "stop" { 
        Stop-MonitoringService 
    }
    "status" { 
        Get-ServiceStatus 
    }
    default {
        Write-Host "Unknown action: $Action" -ForegroundColor Red
        Write-Host "Use -Help for available actions" -ForegroundColor Gray
    }
}