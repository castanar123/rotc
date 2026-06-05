# Simple script to fix MySQL port from 3307 to 3306
# Run as Administrator

$configPath = "C:\xampp\mysql\bin\my.ini"

if (Test-Path $configPath) {
    Write-Host "Reading current configuration..."
    $content = Get-Content -Path $configPath
    
    # Create backup
    $backupPath = "$configPath.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Copy-Item -Path $configPath -Destination $backupPath
    Write-Host "Backup created: $backupPath"
    
    # Replace port 3307 with 3306
    $newContent = $content -replace "port\s*=\s*3307", "port = 3306"
    
    # Write back to file
    Set-Content -Path $configPath -Value $newContent -Encoding UTF8
    Write-Host "Port changed from 3307 to 3306 in my.ini"
    
    # Show the changes
    Write-Host "\nPort configuration lines:"
    $newContent | Where-Object { $_ -match "port\s*=" }
    
} else {
    Write-Host "Configuration file not found: $configPath"
}

Write-Host "\nDone. You can now start MySQL and it should use port 3306."