# XAMPP MySQL Startup Auto-Fix Script
# This script is designed to run at Windows startup to prevent MySQL issues
# Author: SOLO Coding
# Version: 1.0

param(
    [int]$DelaySeconds = 30,
    [string]$LogPath = "C:\xampp\logs\startup-autofix.log"
)

# Ensure log directory exists
$logDir = Split-Path $LogPath -Parent
if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

# Logging function
function Write-StartupLog {
    param(
        [string]$Message,
        [string]$Level = "INFO"
    )
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [STARTUP] [$Level] $Message"
    Add-Content -Path $LogPath -Value $logEntry
}

# Wait for system to stabilize after boot
function Wait-SystemStable {
    param([int]$Seconds)
    
    Write-StartupLog "Waiting $Seconds seconds for system to stabilize after boot..."
    Start-Sleep -Seconds $Seconds
    
    # Additional check: wait for network to be available
    $maxWait = 60
    $waited = 0
    
    while ($waited -lt $maxWait) {
        try {
            $networkTest = Test-NetConnection -ComputerName "127.0.0.1" -Port 80 -InformationLevel Quiet -ErrorAction SilentlyContinue
            if ($networkTest) {
                Write-StartupLog "Network connectivity confirmed"
                break
            }
        } catch {
            # Network not ready yet
        }
        
        Start-Sleep -Seconds 5
        $waited += 5
    }
}

# Check if XAMPP should be running
function Test-XamppShouldRun {
    # Check if XAMPP Control Panel is set to auto-start services
    $xamppPath = Get-XamppPath
    if (!$xamppPath) {
        return $false
    }
    
    # Check for XAMPP service installations
    $mysqlService = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
    $apacheService = Get-Service -Name "Apache*" -ErrorAction SilentlyContinue
    
    return ($mysqlService -or $apacheService)
}

# Get XAMPP installation path
function Get-XamppPath {
    $commonPaths = @(
        "C:\xampp",
        "C:\Program Files\xampp",
        "C:\Program Files (x86)\xampp"
    )
    
    foreach ($path in $commonPaths) {
        if (Test-Path "$path\mysql\bin\mysqld.exe") {
            return $path
        }
    }
    
    return $null
}

# Monitor MySQL service and apply fixes if needed
function Start-MySQLMonitoring {
    Write-StartupLog "Starting MySQL monitoring and auto-fix service..."
    
    $maxAttempts = 3
    $attempt = 0
    
    while ($attempt -lt $maxAttempts) {
        $attempt++
        Write-StartupLog "MySQL check attempt $attempt of $maxAttempts"
        
        # Check if MySQL is running
        $mysqlService = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
        
        if ($mysqlService -and $mysqlService.Status -eq "Running") {
            Write-StartupLog "MySQL service is running normally" "SUCCESS"
            return $true
        }
        
        if ($mysqlService -and $mysqlService.Status -eq "Stopped") {
            Write-StartupLog "MySQL service is stopped. Attempting to start..." "WARNING"
            
            try {
                Start-Service -Name "mysql"
                Start-Sleep -Seconds 10
                
                $mysqlService = Get-Service -Name "mysql"
                if ($mysqlService.Status -eq "Running") {
                    Write-StartupLog "MySQL service started successfully" "SUCCESS"
                    return $true
                }
            } catch {
                Write-StartupLog "Failed to start MySQL service: $($_.Exception.Message)" "ERROR"
            }
        }
        
        # If MySQL failed to start, run the auto-fix script
        Write-StartupLog "MySQL startup failed. Running auto-fix script..." "WARNING"
        
        $scriptPath = Join-Path (Split-Path $PSScriptRoot -Parent) "scripts\xampp-mysql-autofix.ps1"
        if (Test-Path $scriptPath) {
            try {
                & $scriptPath -Silent
                Write-StartupLog "Auto-fix script executed"
                
                # Wait and check again
                Start-Sleep -Seconds 15
                
                $mysqlService = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
                if ($mysqlService -and $mysqlService.Status -eq "Running") {
                    Write-StartupLog "MySQL service recovered after auto-fix" "SUCCESS"
                    return $true
                }
            } catch {
                Write-StartupLog "Error running auto-fix script: $($_.Exception.Message)" "ERROR"
            }
        } else {
            Write-StartupLog "Auto-fix script not found at: $scriptPath" "ERROR"
        }
        
        # Wait before next attempt
        if ($attempt -lt $maxAttempts) {
            Write-StartupLog "Waiting 30 seconds before next attempt..."
            Start-Sleep -Seconds 30
        }
    }
    
    Write-StartupLog "Failed to start MySQL after $maxAttempts attempts" "ERROR"
    return $false
}

# Main startup function
function Start-XamppStartupFix {
    Write-StartupLog "=== XAMPP Startup Auto-Fix Started ==="
    Write-StartupLog "System boot time: $(Get-Date)"
    
    # Wait for system to stabilize
    Wait-SystemStable -Seconds $DelaySeconds
    
    # Check if XAMPP should be running
    if (!(Test-XamppShouldRun)) {
        Write-StartupLog "XAMPP services not configured for auto-start. Exiting."
        return
    }
    
    # Monitor and fix MySQL
    $result = Start-MySQLMonitoring
    
    if ($result) {
        Write-StartupLog "=== XAMPP Startup Auto-Fix Completed Successfully ===" "SUCCESS"
    } else {
        Write-StartupLog "=== XAMPP Startup Auto-Fix Failed ===" "ERROR"
        
        # Send notification to user (optional)
        try {
            Add-Type -AssemblyName System.Windows.Forms
            [System.Windows.Forms.MessageBox]::Show(
                "XAMPP MySQL failed to start automatically. Please check the logs at $LogPath",
                "XAMPP Auto-Fix",
                [System.Windows.Forms.MessageBoxButtons]::OK,
                [System.Windows.Forms.MessageBoxIcon]::Warning
            )
        } catch {
            # Notification failed, but continue
        }
    }
}

# Execute the main function
Start-XamppStartupFix