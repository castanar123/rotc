# ROTC QR System - Silent PowerShell Startup Script
# This PowerShell script runs completely silently without any visible windows
# Place this file in: C:\Users\User\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup
# Or create a shortcut with: powershell.exe -WindowStyle Hidden -ExecutionPolicy Bypass -File "path\to\this\script.ps1"

# Hide PowerShell window completely
Add-Type -Name Window -Namespace Console -MemberDefinition '
[DllImport("Kernel32.dll")]
public static extern IntPtr GetConsoleWindow();

[DllImport("user32.dll")]
public static extern bool ShowWindow(IntPtr hWnd, Int32 nCmdShow);
'

$consolePtr = [Console.Window]::GetConsoleWindow()
[Console.Window]::ShowWindow($consolePtr, 0) # 0 = Hide window

# Set working directory
$workingDir = "c:\xampp\htdocs\generate qr"
Set-Location $workingDir

# Function to check if process is running
function Test-ProcessRunning {
    param([string]$ProcessName)
    return (Get-Process -Name $ProcessName.Replace('.exe', '') -ErrorAction SilentlyContinue) -ne $null
}

# Function to start process silently
function Start-SilentProcess {
    param(
        [string]$FilePath,
        [string]$Arguments = "",
        [string]$WorkingDirectory = $workingDir
    )
    
    try {
        $processInfo = New-Object System.Diagnostics.ProcessStartInfo
        $processInfo.FileName = $FilePath
        $processInfo.Arguments = $Arguments
        $processInfo.WorkingDirectory = $WorkingDirectory
        $processInfo.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Hidden
        $processInfo.CreateNoWindow = $true
        $processInfo.UseShellExecute = $false
        
        $process = [System.Diagnostics.Process]::Start($processInfo)
        return $true
    }
    catch {
        return $false
    }
}

# Wait for system to fully boot
Start-Sleep -Seconds 5

# Step 1: Start Apache if not running
if (-not (Test-ProcessRunning "httpd")) {
    $apachePath = "c:\xampp\apache\bin\httpd.exe"
    if (Test-Path $apachePath) {
        Start-SilentProcess -FilePath $apachePath
        Start-Sleep -Seconds 3
    }
}

# Step 2: Start MySQL if not running
if (-not (Test-ProcessRunning "mysqld")) {
    $mysqlPath = "c:\xampp\mysql\bin\mysqld.exe"
    $mysqlArgs = "--defaults-file=c:\xampp\mysql\bin\my.ini --standalone"
    if (Test-Path $mysqlPath) {
        Start-SilentProcess -FilePath $mysqlPath -Arguments $mysqlArgs
        Start-Sleep -Seconds 3
    }
}

# Step 3: Start Cloudflare Tunnel if not running
if (-not (Test-ProcessRunning "cloudflared")) {
    $cloudflaredPath = "$workingDir\cloudflare\cloudflared.exe"
    $configPath = "$workingDir\cloudflare-tunnel.yml"
    
    if ((Test-Path $cloudflaredPath) -and (Test-Path $configPath)) {
        $tunnelArgs = "tunnel --config `"$configPath`" run"
        Start-SilentProcess -FilePath $cloudflaredPath -Arguments $tunnelArgs
    }
}

# Script completes silently
exit 0