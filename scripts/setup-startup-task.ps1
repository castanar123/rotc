# XAMPP MySQL Startup Task Setup Script
# This script creates a Windows Task Scheduler task to run the auto-fix on startup
# Author: SOLO Coding
# Version: 1.0

# Requires Administrator privileges
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "This script requires Administrator privileges. Please run as Administrator." -ForegroundColor Red
    Write-Host "Press any key to exit..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Configuration
$TaskName = "XAMPP-MySQL-AutoFix"
$TaskDescription = "Automatically fixes XAMPP MySQL startup issues on system boot"
$ScriptPath = Join-Path $PSScriptRoot "xampp-startup-fix.ps1"
$LogPath = "C:\xampp\logs\task-setup.log"

# Ensure log directory exists
$logDir = Split-Path $LogPath -Parent
if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

# Logging function
function Write-SetupLog {
    param(
        [string]$Message,
        [string]$Level = "INFO"
    )
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [SETUP] [$Level] $Message"
    Add-Content -Path $LogPath -Value $logEntry
    Write-Host "[$Level] $Message" -ForegroundColor $(if($Level -eq "ERROR"){"Red"} elseif($Level -eq "WARNING"){"Yellow"} elseif($Level -eq "SUCCESS"){"Green"} else {"White"})
}

# Check if script exists
if (!(Test-Path $ScriptPath)) {
    Write-SetupLog "Startup script not found at: $ScriptPath" "ERROR"
    Write-Host "Press any key to exit..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

Write-SetupLog "=== XAMPP MySQL Auto-Fix Task Setup Started ==="
Write-SetupLog "Script path: $ScriptPath"

try {
    # Remove existing task if it exists
    $existingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($existingTask) {
        Write-SetupLog "Removing existing task: $TaskName" "WARNING"
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    }
    
    # Create task action
    $Action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-WindowStyle Hidden -ExecutionPolicy Bypass -File `"$ScriptPath`""
    
    # Create task trigger (at startup)
    $Trigger = New-ScheduledTaskTrigger -AtStartup
    
    # Create task settings
    $Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RunOnlyIfNetworkAvailable:$false
    
    # Create task principal (run as SYSTEM with highest privileges)
    $Principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
    
    # Register the task
    Write-SetupLog "Creating scheduled task: $TaskName"
    $Task = Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Description $TaskDescription
    
    if ($Task) {
        Write-SetupLog "Task created successfully: $TaskName" "SUCCESS"
        
        # Verify task creation
        $verifyTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
        if ($verifyTask) {
            Write-SetupLog "Task verification successful" "SUCCESS"
            Write-SetupLog "Task State: $($verifyTask.State)"
            
            # Test the task (optional)
            Write-Host ""
            $testChoice = Read-Host "Do you want to test the task now? (y/n)"
            if ($testChoice -eq 'y' -or $testChoice -eq 'Y') {
                Write-SetupLog "Testing task execution..."
                Start-ScheduledTask -TaskName $TaskName
                Write-SetupLog "Task test initiated. Check logs for results." "SUCCESS"
            }
        } else {
            Write-SetupLog "Task verification failed" "ERROR"
        }
    } else {
        Write-SetupLog "Failed to create task" "ERROR"
    }
    
} catch {
    Write-SetupLog "Error setting up task: $($_.Exception.Message)" "ERROR"
    Write-Host "Press any key to exit..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

Write-SetupLog "=== Task Setup Completed ==="
Write-Host ""
Write-Host "Setup Summary:" -ForegroundColor Cyan
Write-Host "- Task Name: $TaskName" -ForegroundColor White
Write-Host "- Description: $TaskDescription" -ForegroundColor White
Write-Host "- Script Path: $ScriptPath" -ForegroundColor White
Write-Host "- Trigger: At system startup" -ForegroundColor White
Write-Host "- Run Level: Highest (Administrator)" -ForegroundColor White
Write-Host ""
Write-Host "The task will now automatically run when Windows starts up." -ForegroundColor Green
Write-Host "You can manage this task through Task Scheduler (taskschd.msc)." -ForegroundColor Yellow
Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")