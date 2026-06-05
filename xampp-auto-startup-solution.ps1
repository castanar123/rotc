# XAMPP Auto-Startup and Monitoring Solution
# This script creates a comprehensive solution to auto-start XAMPP and keep it running

param(
    [string]$Action = "install",
    [string]$XamppPath = "C:\xampp"
)

# Function to check if running as administrator
function Test-Administrator {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# Function to install XAMPP as Windows Services
function Install-XamppServices {
    Write-Host "Installing XAMPP services..." -ForegroundColor Green
    
    # Install Apache as service
    $apacheServicePath = "$XamppPath\apache\bin\httpd.exe"
    if (Test-Path $apacheServicePath) {
        & "$XamppPath\apache\bin\httpd.exe" -k install -n "Apache2.4"
        Write-Host "Apache service installed" -ForegroundColor Green
    }
    
    # Install MySQL as service
    $mysqlServicePath = "$XamppPath\mysql\bin\mysqld.exe"
    if (Test-Path $mysqlServicePath) {
        & "$XamppPath\mysql\bin\mysqld.exe" --install "MySQL"
        Write-Host "MySQL service installed" -ForegroundColor Green
    }
    
    # Set services to start automatically
    Set-Service -Name "Apache2.4" -StartupType Automatic -ErrorAction SilentlyContinue
    Set-Service -Name "MySQL" -StartupType Automatic -ErrorAction SilentlyContinue
    
    Write-Host "XAMPP services configured for automatic startup" -ForegroundColor Green
}

# Function to create startup script
function Create-StartupScript {
    $startupScript = @'
@echo off
echo Starting XAMPP Services...

REM Start Apache service
net start Apache2.4
if %errorlevel% neq 0 (
    echo Apache service failed to start, trying manual start...
    "C:\xampp\apache\bin\httpd.exe" -k start
)

REM Start MySQL service
net start MySQL
if %errorlevel% neq 0 (
    echo MySQL service failed to start, trying manual start...
    "C:\xampp\mysql\bin\mysqld.exe" --console
)

echo XAMPP services started
pause
'@
    
    $startupScriptPath = "$XamppPath\auto-start-xampp.bat"
    $startupScript | Out-File -FilePath $startupScriptPath -Encoding ASCII
    Write-Host "Startup script created at: $startupScriptPath" -ForegroundColor Green
    
    return $startupScriptPath
}

# Function to create monitoring script
function Create-MonitoringScript {
    $monitoringScript = @'
# XAMPP Service Monitor
# This script monitors XAMPP services and restarts them if they stop

while ($true) {
    # Check Apache service
    $apacheService = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
    if ($apacheService -and $apacheService.Status -ne "Running") {
        Write-Host "$(Get-Date): Apache service stopped, restarting..." -ForegroundColor Yellow
        Start-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
        if ($?) {
            Write-Host "$(Get-Date): Apache service restarted successfully" -ForegroundColor Green
        } else {
            Write-Host "$(Get-Date): Failed to restart Apache service" -ForegroundColor Red
        }
    }
    
    # Check MySQL service
    $mysqlService = Get-Service -Name "MySQL" -ErrorAction SilentlyContinue
    if ($mysqlService -and $mysqlService.Status -ne "Running") {
        Write-Host "$(Get-Date): MySQL service stopped, restarting..." -ForegroundColor Yellow
        Start-Service -Name "MySQL" -ErrorAction SilentlyContinue
        if ($?) {
            Write-Host "$(Get-Date): MySQL service restarted successfully" -ForegroundColor Green
        } else {
            Write-Host "$(Get-Date): Failed to restart MySQL service" -ForegroundColor Red
        }
    }
    
    # Wait 30 seconds before next check
    Start-Sleep -Seconds 30
}
'@
    
    $monitoringScriptPath = "$XamppPath\xampp-monitor.ps1"
    $monitoringScript | Out-File -FilePath $monitoringScriptPath -Encoding UTF8
    Write-Host "Monitoring script created at: $monitoringScriptPath" -ForegroundColor Green
    
    return $monitoringScriptPath
}

# Function to add to Windows startup
function Add-ToStartup {
    param([string]$ScriptPath)
    
    $startupFolder = [Environment]::GetFolderPath("Startup")
    $shortcutPath = "$startupFolder\XAMPP Auto Start.lnk"
    
    $WshShell = New-Object -comObject WScript.Shell
    $Shortcut = $WshShell.CreateShortcut($shortcutPath)
    $Shortcut.TargetPath = $ScriptPath
    $Shortcut.WorkingDirectory = $XamppPath
    $Shortcut.Description = "Auto-start XAMPP services"
    $Shortcut.Save()
    
    Write-Host "Startup shortcut created at: $shortcutPath" -ForegroundColor Green
}

# Function to create scheduled task for monitoring
function Create-MonitoringTask {
    param([string]$MonitoringScriptPath)
    
    $taskName = "XAMPP Service Monitor"
    
    # Remove existing task if it exists
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    
    # Create new task
    $action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-WindowStyle Hidden -ExecutionPolicy Bypass -File `"$MonitoringScriptPath`""
    $trigger = New-ScheduledTaskTrigger -AtStartup
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
    $principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
    
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal
    
    Write-Host "Monitoring scheduled task created: $taskName" -ForegroundColor Green
}

# Function to start XAMPP services
function Start-XamppServices {
    Write-Host "Starting XAMPP services..." -ForegroundColor Green
    
    # Start Apache
    Start-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
    if ($?) {
        Write-Host "Apache service started" -ForegroundColor Green
    } else {
        Write-Host "Starting Apache manually..." -ForegroundColor Yellow
        Start-Process -FilePath "$XamppPath\apache\bin\httpd.exe" -WindowStyle Hidden
    }
    
    # Start MySQL
    Start-Service -Name "MySQL" -ErrorAction SilentlyContinue
    if ($?) {
        Write-Host "MySQL service started" -ForegroundColor Green
    } else {
        Write-Host "Starting MySQL manually..." -ForegroundColor Yellow
        Start-Process -FilePath "$XamppPath\mysql\bin\mysqld.exe" -WindowStyle Hidden
    }
}

# Function to check XAMPP status
function Get-XamppStatus {
    Write-Host "\nXAMPP Service Status:" -ForegroundColor Cyan
    Write-Host "=====================" -ForegroundColor Cyan
    
    # Check Apache
    $apacheService = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
    if ($apacheService) {
        Write-Host "Apache Service: $($apacheService.Status)" -ForegroundColor $(if($apacheService.Status -eq "Running") {"Green"} else {"Red"})
    } else {
        $apacheProcess = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
        if ($apacheProcess) {
            Write-Host "Apache Process: Running (Manual)" -ForegroundColor Green
        } else {
            Write-Host "Apache: Not Running" -ForegroundColor Red
        }
    }
    
    # Check MySQL
    $mysqlService = Get-Service -Name "MySQL" -ErrorAction SilentlyContinue
    if ($mysqlService) {
        Write-Host "MySQL Service: $($mysqlService.Status)" -ForegroundColor $(if($mysqlService.Status -eq "Running") {"Green"} else {"Red"})
    } else {
        $mysqlProcess = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
        if ($mysqlProcess) {
            Write-Host "MySQL Process: Running (Manual)" -ForegroundColor Green
        } else {
            Write-Host "MySQL: Not Running" -ForegroundColor Red
        }
    }
    
    # Test localhost
    try {
        $response = Invoke-WebRequest -Uri "http://localhost" -TimeoutSec 5 -ErrorAction Stop
        Write-Host "Localhost Test: Accessible" -ForegroundColor Green
    } catch {
        Write-Host "Localhost Test: Not Accessible" -ForegroundColor Red
    }
}

# Main execution
if (-not (Test-Administrator)) {
    Write-Host "This script requires administrator privileges. Please run as administrator." -ForegroundColor Red
    exit 1
}

Write-Host "XAMPP Auto-Startup Solution" -ForegroundColor Cyan
Write-Host "===========================" -ForegroundColor Cyan

switch ($Action.ToLower()) {
    "install" {
        Write-Host "Installing complete XAMPP auto-startup solution..." -ForegroundColor Yellow
        
        # Install services
        Install-XamppServices
        
        # Create scripts
        $startupScript = Create-StartupScript
        $monitoringScript = Create-MonitoringScript
        
        # Add to startup
        Add-ToStartup -ScriptPath $startupScript
        
        # Create monitoring task
        Create-MonitoringTask -MonitoringScriptPath $monitoringScript
        
        # Start services
        Start-XamppServices
        
        Write-Host "\nInstallation complete!" -ForegroundColor Green
        Write-Host "XAMPP will now auto-start with Windows and be monitored for failures." -ForegroundColor Green
    }
    
    "start" {
        Start-XamppServices
    }
    
    "status" {
        Get-XamppStatus
    }
    
    "uninstall" {
        Write-Host "Removing XAMPP auto-startup configuration..." -ForegroundColor Yellow
        
        # Remove scheduled task
        Unregister-ScheduledTask -TaskName "XAMPP Service Monitor" -Confirm:$false -ErrorAction SilentlyContinue
        
        # Remove startup shortcut
        $startupFolder = [Environment]::GetFolderPath("Startup")
        $shortcutPath = "$startupFolder\XAMPP Auto Start.lnk"
        if (Test-Path $shortcutPath) {
            Remove-Item $shortcutPath -Force
        }
        
        Write-Host "Auto-startup configuration removed" -ForegroundColor Green
    }
    
    default {
        Write-Host "Usage: .\xampp-auto-startup-solution.ps1 -Action [install|start|status|uninstall]" -ForegroundColor Yellow
        Write-Host "\nActions:" -ForegroundColor Cyan
        Write-Host "  install   - Install complete auto-startup solution" -ForegroundColor White
        Write-Host "  start     - Start XAMPP services now" -ForegroundColor White
        Write-Host "  status    - Check XAMPP service status" -ForegroundColor White
        Write-Host "  uninstall - Remove auto-startup configuration" -ForegroundColor White
    }
}

Get-XamppStatus