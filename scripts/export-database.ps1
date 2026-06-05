param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root",
    [string]$Password = "root",
    [string]$Database = "rotc_db",
    [string]$OutputDir = "backups\migration"
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$mysqlBin = "C:\xampp\mysql\bin"
$mysql = Join-Path $mysqlBin "mysql.exe"
$mysqldump = Join-Path $mysqlBin "mysqldump.exe"

if (!(Test-Path $mysql)) {
    throw "mysql.exe not found at $mysql"
}

if (!(Test-Path $mysqldump)) {
    throw "mysqldump.exe not found at $mysqldump"
}

$resolvedOutputDir = Join-Path $repoRoot $OutputDir
New-Item -ItemType Directory -Force -Path $resolvedOutputDir | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$schemaFile = Join-Path $resolvedOutputDir "$Database-schema-$timestamp.sql"
$dataFile = Join-Path $resolvedOutputDir "$Database-data-$timestamp.sql"
$fullFile = Join-Path $resolvedOutputDir "$Database-full-$timestamp.sql"

$passwordArg = if ($Password -ne "") { "-p$Password" } else { "" }

Write-Host "Testing database connection..."
& $mysql -h $HostName -P $Port -u $User $passwordArg -e "SELECT VERSION() AS version; USE $Database; SHOW TABLES;" | Out-Host

Write-Host "Exporting schema to $schemaFile"
& $mysqldump -h $HostName -P $Port -u $User $passwordArg `
    --no-data `
    --single-transaction `
    --routines `
    --triggers `
    --events `
    --default-character-set=utf8mb4 `
    $Database | Out-File -Encoding utf8 $schemaFile
if ($LASTEXITCODE -ne 0) { throw "Schema export failed with exit code $LASTEXITCODE" }

Write-Host "Exporting data to $dataFile"
& $mysqldump -h $HostName -P $Port -u $User $passwordArg `
    --no-create-info `
    --single-transaction `
    --skip-triggers `
    --default-character-set=utf8mb4 `
    $Database | Out-File -Encoding utf8 $dataFile
if ($LASTEXITCODE -ne 0) { throw "Data export failed with exit code $LASTEXITCODE" }

Write-Host "Exporting full database to $fullFile"
& $mysqldump -h $HostName -P $Port -u $User $passwordArg `
    --single-transaction `
    --routines `
    --triggers `
    --events `
    --default-character-set=utf8mb4 `
    $Database | Out-File -Encoding utf8 $fullFile
if ($LASTEXITCODE -ne 0) { throw "Full export failed with exit code $LASTEXITCODE" }

Write-Host "Done."
Write-Host "Schema: $schemaFile"
Write-Host "Data:   $dataFile"
Write-Host "Full:   $fullFile"
