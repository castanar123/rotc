# XAMPP Service Setup Script
# This script sets up XAMPP Apache and MySQL as Windows services
# Run as Administrator

param(
    [switch]$Install,
    [switch]$Uninstall,
    [switch]$Start,
    [switch]$Stop,
    [switch]$Status
)

# Check if running as administrator
function Test-Administrator {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

if (-not (Test-Administrator)) {
    Write-Host "This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

# XAMPP paths
$xamppPath = "C:\xampp"
$apachePath = "$xamppPath\apache\bin\httpd.exe"
$mysqlPath = "$xamppPath\mysql\bin\mysqld.exe"
$apacheService = "Apache2.4"
$mysqlService = "MySQL"

function Install-XAMPPServices {
    Write-Host "Installing XAMPP services..." -ForegroundColor Green
    
    # Install Apache service
    if (Test-Path $apachePath) {
        Write-Host "Installing Apache service..."
        & "$xamppPath\apache\bin\httpd.exe" -k install -n $apacheService
        
        # Set service to start automatically
        Set-Service -Name $apacheService -StartupType Automatic
        Write-Host "✓ Apache service installed and set to auto-start" -ForegroundColor Green
    } else {
        Write-Host "✗ Apache not found at $apachePath" -ForegroundColor Red
    }
    
    # Install MySQL service
    if (Test-Path $mysqlPath) {
        Write-Host "Installing MySQL service..."
        & "$xamppPath\mysql\bin\mysqld.exe" --install $mysqlService --defaults-file="$xamppPath\mysql\bin\my.ini"
        
        # Set service to start automatically
        Set-Service -Name $mysqlService -StartupType Automatic
        Write-Host "✓ MySQL service installed and set to auto-start" -ForegroundColor Green
    } else {
        Write-Host "✗ MySQL not found at $mysqlPath" -ForegroundColor Red
    }
    
    Write-Host "\nXAMPP services installation complete!" -ForegroundColor Green
    Write-Host "Services will now start automatically on system boot." -ForegroundColor Yellow
}

function Uninstall-XAMPPServices {
    Write-Host "Uninstalling XAMPP services..." -ForegroundColor Yellow
    
    # Stop services first
    Stop-XAMPPServices
    
    # Uninstall Apache service
    try {
        & "$xamppPath\apache\bin\httpd.exe" -k uninstall -n $apacheService
        Write-Host "✓ Apache service uninstalled" -ForegroundColor Green
    } catch {
        Write-Host "Apache service removal failed or not found" -ForegroundColor Yellow
    }
    
    # Uninstall MySQL service
    try {
        & "$xamppPath\mysql\bin\mysqld.exe" --remove $mysqlService
        Write-Host "✓ MySQL service uninstalled" -ForegroundColor Green
    } catch {
        Write-Host "MySQL service removal failed or not found" -ForegroundColor Yellow
    }
    
    Write-Host "\nXAMPP services uninstallation complete!" -ForegroundColor Green
}

function Start-XAMPPServices {
    Write-Host "Starting XAMPP services..." -ForegroundColor Green
    
    try {
        Start-Service -Name $apacheService -ErrorAction Stop
        Write-Host "✓ Apache service started" -ForegroundColor Green
    } catch {
        Write-Host "✗ Failed to start Apache service: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    try {
        Start-Service -Name $mysqlService -ErrorAction Stop
        Write-Host "✓ MySQL service started" -ForegroundColor Green
    } catch {
        Write-Host "✗ Failed to start MySQL service: $($_.Exception.Message)" -ForegroundColor Red
    }
}

function Stop-XAMPPServices {
    Write-Host "Stopping XAMPP services..." -ForegroundColor Yellow
    
    try {
        Stop-Service -Name $apacheService -Force -ErrorAction Stop
        Write-Host "✓ Apache service stopped" -ForegroundColor Green
    } catch {
        Write-Host "Apache service not running or not found" -ForegroundColor Yellow
    }
    
    try {
        Stop-Service -Name $mysqlService -Force -ErrorAction Stop
        Write-Host "✓ MySQL service stopped" -ForegroundColor Green
    } catch {
        Write-Host "MySQL service not running or not found" -ForegroundColor Yellow
    }
}

function Get-XAMPPStatus {
    Write-Host "XAMPP Services Status:" -ForegroundColor Cyan
    Write-Host "=====================" -ForegroundColor Cyan
    
    # Check Apache service
    try {
        $apacheStatus = Get-Service -Name $apacheService -ErrorAction Stop
        Write-Host "Apache ($apacheService): $($apacheStatus.Status)" -ForegroundColor $(if($apacheStatus.Status -eq 'Running') {'Green'} else {'Red'})
    } catch {
        Write-Host "Apache ($apacheService): Not Installed" -ForegroundColor Red
    }
    
    # Check MySQL service
    try {
        $mysqlStatus = Get-Service -Name $mysqlService -ErrorAction Stop
        Write-Host "MySQL ($mysqlService): $($mysqlStatus.Status)" -ForegroundColor $(if($mysqlStatus.Status -eq 'Running') {'Green'} else {'Red'})
    } catch {
        Write-Host "MySQL ($mysqlService): Not Installed" -ForegroundColor Red
    }
    
    # Check if localhost is accessible
    Write-Host "\nTesting localhost access..." -ForegroundColor Cyan
    try {
        $response = Invoke-WebRequest -Uri "http://localhost" -TimeoutSec 5 -ErrorAction Stop
        Write-Host "✓ Localhost is accessible" -ForegroundColor Green
    } catch {
        Write-Host "✗ Localhost is not accessible" -ForegroundColor Red
    }
}

# Main execution
if ($Install) {
    Install-XAMPPServices
} elseif ($Uninstall) {
    Uninstall-XAMPPServices
} elseif ($Start) {
    Start-XAMPPServices
} elseif ($Stop) {
    Stop-XAMPPServices
} elseif ($Status) {
    Get-XAMPPStatus
} else {
    Write-Host "XAMPP Service Manager" -ForegroundColor Cyan
    Write-Host "===================" -ForegroundColor Cyan
    Write-Host "Usage:"
    Write-Host "  .\setup-xampp-service.ps1 -Install    # Install XAMPP as Windows services"
    Write-Host "  .\setup-xampp-service.ps1 -Uninstall  # Remove XAMPP services"
    Write-Host "  .\setup-xampp-service.ps1 -Start      # Start XAMPP services"
    Write-Host "  .\setup-xampp-service.ps1 -Stop       # Stop XAMPP services"
    Write-Host "  .\setup-xampp-service.ps1 -Status     # Check service status"
    Write-Host "\nNote: Must be run as Administrator" -ForegroundColor Yellow
}