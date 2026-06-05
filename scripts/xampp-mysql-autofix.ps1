# XAMPP MySQL Auto-Fix Script
# Automatically detects and fixes common MySQL startup issues
# Author: SOLO Coding
# Version: 1.0

param(
    [switch]$Silent,
    [switch]$LogOnly,
    [string]$LogPath = "C:\xampp\logs\mysql-autofix.log"
)

# Ensure log directory exists
$logDir = Split-Path $LogPath -Parent
if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

# Logging function
function Write-Log {
    param(
        [string]$Message,
        [string]$Level = "INFO"
    )
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$Level] $Message"
    Add-Content -Path $LogPath -Value $logEntry
    
    if (!$Silent) {
        switch ($Level) {
            "ERROR" { Write-Host $logEntry -ForegroundColor Red }
            "WARNING" { Write-Host $logEntry -ForegroundColor Yellow }
            "SUCCESS" { Write-Host $logEntry -ForegroundColor Green }
            default { Write-Host $logEntry }
        }
    }
}

# Check if running as administrator
function Test-Administrator {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
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
    
    # Check registry for XAMPP installation
    try {
        $regPath = Get-ItemProperty -Path "HKLM:\SOFTWARE\xampp" -ErrorAction SilentlyContinue
        if ($regPath -and (Test-Path "$($regPath.Install_Dir)\mysql\bin\mysqld.exe")) {
            return $regPath.Install_Dir
        }
    } catch {
        # Registry key doesn't exist
    }
    
    return $null
}

# Check if MySQL service is running
function Test-MySQLService {
    $service = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
    return $service -and $service.Status -eq "Running"
}

# Check for port conflicts on 3306
function Test-PortConflict {
    try {
        $connections = Get-NetTCPConnection -LocalPort 3306 -ErrorAction SilentlyContinue
        return $connections.Count -gt 0
    } catch {
        return $false
    }
}

# Get processes using port 3306
function Get-PortProcesses {
    try {
        $connections = Get-NetTCPConnection -LocalPort 3306 -ErrorAction SilentlyContinue
        $processes = @()
        
        foreach ($conn in $connections) {
            $process = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
            if ($process) {
                $processes += $process
            }
        }
        
        return $processes
    } catch {
        return @()
    }
}

# Stop conflicting services
function Stop-ConflictingServices {
    Write-Log "Checking for port conflicts on 3306..."
    
    if (Test-PortConflict) {
        Write-Log "Port 3306 is in use. Identifying conflicting processes..." "WARNING"
        
        $processes = Get-PortProcesses
        foreach ($process in $processes) {
            if ($process.ProcessName -ne "mysqld") {
                Write-Log "Stopping conflicting process: $($process.ProcessName) (PID: $($process.Id))" "WARNING"
                try {
                    Stop-Process -Id $process.Id -Force
                    Write-Log "Successfully stopped process $($process.ProcessName)" "SUCCESS"
                    return $true
                } catch {
                    Write-Log "Failed to stop process $($process.ProcessName): $($_.Exception.Message)" "ERROR"
                }
            }
        }
    } else {
        Write-Log "No port conflicts detected on 3306" "SUCCESS"
        return $true
    }
    
    return $false
}

# Check MySQL data directory integrity
function Test-MySQLDataIntegrity {
    param([string]$XamppPath)
    
    $dataPath = "$XamppPath\mysql\data"
    if (!(Test-Path $dataPath)) {
        Write-Log "MySQL data directory not found: $dataPath" "ERROR"
        return $false
    }
    
    # Check for critical files
    $criticalFiles = @("ibdata1", "ib_logfile0", "ib_logfile1")
    foreach ($file in $criticalFiles) {
        if (!(Test-Path "$dataPath\$file")) {
            Write-Log "Critical MySQL file missing: $file" "ERROR"
            return $false
        }
    }
    
    # Check for corrupted files (basic check)
    try {
        $ibdata1 = Get-Item "$dataPath\ibdata1"
        if ($ibdata1.Length -eq 0) {
            Write-Log "ibdata1 file is corrupted (0 bytes)" "ERROR"
            return $false
        }
    } catch {
        Write-Log "Error checking ibdata1 file: $($_.Exception.Message)" "ERROR"
        return $false
    }
    
    Write-Log "MySQL data integrity check passed" "SUCCESS"
    return $true
}

# Repair MySQL data corruption
function Repair-MySQLData {
    param([string]$XamppPath)
    
    Write-Log "Attempting to repair MySQL data corruption..." "WARNING"
    
    $dataPath = "$XamppPath\mysql\data"
    $backupPath = "$XamppPath\mysql\data_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
    
    try {
        # Create backup
        Write-Log "Creating backup of MySQL data directory..."
        Copy-Item -Path $dataPath -Destination $backupPath -Recurse -Force
        Write-Log "Backup created at: $backupPath" "SUCCESS"
        
        # Remove log files to force MySQL to recreate them
        $logFiles = @("ib_logfile0", "ib_logfile1")
        foreach ($logFile in $logFiles) {
            $logFilePath = "$dataPath\$logFile"
            if (Test-Path $logFilePath) {
                Remove-Item $logFilePath -Force
                Write-Log "Removed corrupted log file: $logFile"
            }
        }
        
        Write-Log "MySQL data repair completed" "SUCCESS"
        return $true
    } catch {
        Write-Log "Failed to repair MySQL data: $($_.Exception.Message)" "ERROR"
        return $false
    }
}

# Reset MySQL permissions
function Reset-MySQLPermissions {
    param([string]$XamppPath)
    
    Write-Log "Resetting MySQL permissions..."
    
    try {
        $dataPath = "$XamppPath\mysql\data"
        
        # Set permissions for XAMPP directory
        $acl = Get-Acl $dataPath
        $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule(
            "Everyone", "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow"
        )
        $acl.SetAccessRule($accessRule)
        Set-Acl -Path $dataPath -AclObject $acl
        
        Write-Log "MySQL permissions reset successfully" "SUCCESS"
        return $true
    } catch {
        Write-Log "Failed to reset MySQL permissions: $($_.Exception.Message)" "ERROR"
        return $false
    }
}

# Start MySQL service
function Start-MySQLService {
    param([string]$XamppPath)
    
    Write-Log "Starting MySQL service..."
    
    try {
        # Try to start via Windows service first
        $service = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
        if ($service) {
            Start-Service -Name "mysql"
            Start-Sleep -Seconds 5
            
            if ((Get-Service -Name "mysql").Status -eq "Running") {
                Write-Log "MySQL service started successfully" "SUCCESS"
                return $true
            }
        }
        
        # If service method fails, try direct execution
        Write-Log "Attempting to start MySQL directly..."
        $mysqldPath = "$XamppPath\mysql\bin\mysqld.exe"
        
        if (Test-Path $mysqldPath) {
            Start-Process -FilePath $mysqldPath -ArgumentList "--defaults-file=$XamppPath\mysql\bin\my.ini" -WindowStyle Hidden
            Start-Sleep -Seconds 10
            
            if (Test-MySQLService) {
                Write-Log "MySQL started successfully via direct execution" "SUCCESS"
                return $true
            }
        }
        
        Write-Log "Failed to start MySQL service" "ERROR"
        return $false
    } catch {
        Write-Log "Error starting MySQL service: $($_.Exception.Message)" "ERROR"
        return $false
    }
}

# Main execution function
function Start-MySQLAutoFix {
    Write-Log "=== XAMPP MySQL Auto-Fix Started ==="
    
    if (!(Test-Administrator)) {
        Write-Log "This script requires administrator privileges. Please run as administrator." "ERROR"
        return $false
    }
    
    $xamppPath = Get-XamppPath
    if (!$xamppPath) {
        Write-Log "XAMPP installation not found. Please ensure XAMPP is properly installed." "ERROR"
        return $false
    }
    
    Write-Log "XAMPP installation found at: $xamppPath"
    
    # If MySQL is already running, no need to fix
    if (Test-MySQLService) {
        Write-Log "MySQL service is already running. No action needed." "SUCCESS"
        return $true
    }
    
    Write-Log "MySQL service is not running. Starting diagnostic and repair process..."
    
    # Step 1: Check and resolve port conflicts
    if (!(Stop-ConflictingServices)) {
        Write-Log "Failed to resolve port conflicts" "ERROR"
    }
    
    # Step 2: Check data integrity
    if (!(Test-MySQLDataIntegrity -XamppPath $xamppPath)) {
        Write-Log "MySQL data integrity issues detected. Attempting repair..."
        if (!(Repair-MySQLData -XamppPath $xamppPath)) {
            Write-Log "Failed to repair MySQL data corruption" "ERROR"
        }
    }
    
    # Step 3: Reset permissions
    if (!(Reset-MySQLPermissions -XamppPath $xamppPath)) {
        Write-Log "Failed to reset MySQL permissions" "ERROR"
    }
    
    # Step 4: Attempt to start MySQL
    if (Start-MySQLService -XamppPath $xamppPath) {
        Write-Log "=== MySQL Auto-Fix Completed Successfully ===" "SUCCESS"
        return $true
    } else {
        Write-Log "=== MySQL Auto-Fix Failed ===" "ERROR"
        Write-Log "Please check the MySQL error logs for more details." "ERROR"
        return $false
    }
}

# Execute the main function if not in log-only mode
if (!$LogOnly) {
    Start-MySQLAutoFix
} else {
    Write-Log "Script executed in log-only mode. No fixes applied."
}