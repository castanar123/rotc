# PowerShell script to force close the terminal window after a delay
# This ensures the terminal closes even if the batch file doesn't exit properly

param(
    [int]$DelaySeconds = 3,
    [string]$ProcessName = "cmd"
)

# Wait for the specified delay
Start-Sleep -Seconds $DelaySeconds

# Get the current process ID of the calling batch file
$currentPID = $PID

# Find and close the cmd window that started this script
try {
    # Get all cmd processes
    $cmdProcesses = Get-Process -Name $ProcessName -ErrorAction SilentlyContinue
    
    # Find the parent process (the cmd window running our batch file)
    foreach ($proc in $cmdProcesses) {
        try {
            # Close the main window of cmd processes
            if ($proc.MainWindowHandle -ne [System.IntPtr]::Zero) {
                $proc.CloseMainWindow()
                Start-Sleep -Milliseconds 500
                
                # If it didn't close gracefully, force kill it
                if (!$proc.HasExited) {
                    $proc.Kill()
                }
            }
        } catch {
            # Ignore errors for individual processes
        }
    }
} catch {
    Write-Host "Error closing terminal: $($_.Exception.Message)"
}

# Exit the PowerShell script
exit 0