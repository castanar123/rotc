param(
    [Parameter(Mandatory = $true)]
    [string]$SqlFile,

    [Parameter(Mandatory = $true)]
    [string]$HostName,

    [int]$Port = 3306,
    [Parameter(Mandatory = $true)]
    [string]$User,

    [string]$Password = "",
    [string]$Database = "rotc_db"
)

$ErrorActionPreference = "Stop"

$mysql = "C:\xampp\mysql\bin\mysql.exe"
if (!(Test-Path $mysql)) {
    throw "mysql.exe not found at $mysql"
}

if (!(Test-Path $SqlFile)) {
    throw "SQL file not found: $SqlFile"
}

$passwordArg = if ($Password -ne "") { "-p$Password" } else { "" }

Write-Host "Creating database if needed..."
& $mysql -h $HostName -P $Port -u $User $passwordArg -e "CREATE DATABASE IF NOT EXISTS $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

Write-Host "Importing $SqlFile into $Database on $HostName:$Port..."
Get-Content -Raw $SqlFile | & $mysql -h $HostName -P $Port -u $User $passwordArg $Database

Write-Host "Verifying table count..."
& $mysql -h $HostName -P $Port -u $User $passwordArg -D $Database -e "SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = '$Database';"

Write-Host "Import complete."
