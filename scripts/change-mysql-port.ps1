# XAMPP MySQL Port Change Script
# Changes MySQL port from 3306 to 3307 to avoid conflicts
# Author: SOLO Coding
# Version: 1.0

param(
    [int]$NewPort = 3307,
    [int]$OldPort = 3306,
    [string]$LogPath = "C:\xampp\logs\port-change.log"
)

# Ensure log directory exists
$logDir = Split-Path $LogPath -Parent
if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

# Logging function
function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$Level] $Message"
    Add-Content -Path $LogPath -Value $logEntry
    Write-Host $logEntry -ForegroundColor $(if($Level -eq "ERROR"){"Red"} elseif($Level -eq "WARNING"){"Yellow"} elseif($Level -eq "SUCCESS"){"Green"} else {"White"})
}

# Find XAMPP installation path
function Get-XamppPath {
    $commonPaths = @(
        "C:\xampp",
        "C:\Program Files\xampp",
        "C:\Program Files (x86)\xampp",
        "D:\xampp"
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
function Test-MySQLRunning {
    try {
        $mysqlProcess = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
        return $mysqlProcess -ne $null
    } catch {
        return $false
    }
}

# Stop MySQL service
function Stop-MySQLService {
    Write-Log "Stopping MySQL service..."
    
    # Try to stop via service
    try {
        $service = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
        if ($service -and $service.Status -eq "Running") {
            Stop-Service -Name "mysql" -Force
            Start-Sleep -Seconds 5
        }
    } catch {
        Write-Log "Service stop failed, trying process termination" "WARNING"
    }
    
    # Force kill mysqld processes
    try {
        Get-Process -Name "mysqld" -ErrorAction SilentlyContinue | Stop-Process -Force
        Start-Sleep -Seconds 3
    } catch {
        # Process not running
    }
    
    return !(Test-MySQLRunning)
}

# Start MySQL service
function Start-MySQLService {
    param([string]$XamppPath)
    
    Write-Log "Starting MySQL service with new port..."
    
    # Try to start via service first
    try {
        $service = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
        if ($service) {
            Start-Service -Name "mysql"
            Start-Sleep -Seconds 10
            
            if (Test-MySQLRunning) {
                Write-Log "MySQL service started successfully" "SUCCESS"
                return $true
            }
        }
    } catch {
        Write-Log "Service start failed, trying direct execution" "WARNING"
    }
    
    # Try direct execution
    try {
        $mysqldPath = "$XamppPath\mysql\bin\mysqld.exe"
        $configPath = "$XamppPath\mysql\bin\my.ini"
        
        if (Test-Path $mysqldPath) {
            Start-Process -FilePath $mysqldPath -ArgumentList "--defaults-file=$configPath", "--standalone" -WindowStyle Hidden
            Start-Sleep -Seconds 10
            
            if (Test-MySQLRunning) {
                Write-Log "MySQL started successfully via direct execution" "SUCCESS"
                return $true
            }
        }
    } catch {
        Write-Log "Direct execution failed: $($_.Exception.Message)" "ERROR"
    }
    
    return $false
}

# Modify my.ini file
function Update-MySQLConfig {
    param([string]$ConfigPath, [int]$OldPort, [int]$NewPort)
    
    Write-Log "Updating MySQL configuration file: $ConfigPath"
    
    if (!(Test-Path $ConfigPath)) {
        Write-Log "Configuration file not found: $ConfigPath" "ERROR"
        return $false
    }
    
    try {
        # Create backup
        $backupPath = "$ConfigPath.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
        Copy-Item -Path $ConfigPath -Destination $backupPath
        Write-Log "Backup created: $backupPath" "SUCCESS"
        
        # Read current content
        $content = Get-Content -Path $ConfigPath
        $modified = $false
        
        # Update port settings
        for ($i = 0; $i -lt $content.Length; $i++) {
            $line = $content[$i]
            
            # Update port in [mysqld] section
            if ($line -match "^\s*port\s*=\s*$OldPort\s*$") {
                $content[$i] = "port = $NewPort"
                $modified = $true
                Write-Log "Updated port setting: $($content[$i])" "SUCCESS"
            }
            
            # Update socket path if it contains port
            if ($line -match "^\s*socket\s*=.*$OldPort") {
                $content[$i] = $line -replace $OldPort, $NewPort
                $modified = $true
                Write-Log "Updated socket setting: $($content[$i])" "SUCCESS"
            }
        }
        
        # If no port line found, add it to [mysqld] section
        if (!$modified) {
            $mysqldIndex = -1
            for ($i = 0; $i -lt $content.Length; $i++) {
                if ($content[$i] -match "^\s*\[mysqld\]\s*$") {
                    $mysqldIndex = $i
                    break
                }
            }
            
            if ($mysqldIndex -ge 0) {
                # Insert port setting after [mysqld] section
                $newContent = @()
                $newContent += $content[0..$mysqldIndex]
                $newContent += "port = $NewPort"
                if ($mysqldIndex + 1 -lt $content.Length) {
                    $newContent += $content[($mysqldIndex + 1)..($content.Length - 1)]
                }
                $content = $newContent
                $modified = $true
                Write-Log "Added port setting to [mysqld] section" "SUCCESS"
            }
        }
        
        if ($modified) {
            # Write updated content
            Set-Content -Path $ConfigPath -Value $content -Encoding UTF8
            Write-Log "Configuration file updated successfully" "SUCCESS"
            return $true
        } else {
            Write-Log "No changes needed in configuration file" "INFO"
            return $true
        }
        
    } catch {
        Write-Log "Failed to update configuration file: $($_.Exception.Message)" "ERROR"
        return $false
    }
}

# Main execution
Write-Log "Starting MySQL port change from $OldPort to $NewPort"

# Check if running as administrator
if (!([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Log "This script requires administrator privileges. Please run as administrator." "ERROR"
    exit 1
}

# Find XAMPP installation
$xamppPath = Get-XamppPath
if (!$xamppPath) {
    Write-Log "XAMPP installation not found" "ERROR"
    exit 1
}

Write-Log "Found XAMPP installation at: $xamppPath"

# Check if MySQL is running and stop it
if (Test-MySQLRunning) {
    Write-Log "MySQL is currently running. Stopping service..."
    if (!(Stop-MySQLService)) {
        Write-Log "Failed to stop MySQL service" "ERROR"
        exit 1
    }
    Write-Log "MySQL service stopped successfully" "SUCCESS"
}

# Update configuration file
$configPath = "$xamppPath\mysql\bin\my.ini"
if (!(Update-MySQLConfig -ConfigPath $configPath -OldPort $OldPort -NewPort $NewPort)) {
    Write-Log "Failed to update MySQL configuration" "ERROR"
    exit 1
}

# Start MySQL with new configuration
if (!(Start-MySQLService -XamppPath $xamppPath)) {
    Write-Log "Failed to start MySQL with new configuration" "ERROR"
    Write-Log "Please check the configuration and try starting MySQL manually" "WARNING"
    exit 1
}

Write-Log "MySQL port change completed successfully!" "SUCCESS"
Write-Log "MySQL is now running on port $NewPort" "SUCCESS"
Write-Log "Remember to update your application database connections to use port $NewPort" "WARNING"

exit 0