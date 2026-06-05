# ROTC QR System - Ngrok Management Dashboard
# Provides a comprehensive GUI for managing ngrok tunnels

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

# Configuration
$PROJECT_DIR = Get-Location
$NGROK_EXE = Join-Path $PROJECT_DIR "ngrok\ngrok.exe"
$CONFIG_FILE = Join-Path $PROJECT_DIR "ngrok-config.yml"
$LOG_FILE = Join-Path $PROJECT_DIR "ngrok-manager.log"

# Function to log messages
function Write-ManagerLog {
    param($Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Add-Content -Path $LOG_FILE -Value $logMessage
}

# Function to update status
function Update-Status {
    param($Message, $Color = "Black")
    $statusLabel.Text = $Message
    $statusLabel.ForeColor = $Color
    $form.Refresh()
    Write-ManagerLog $Message
}

# Function to get tunnel status
function Get-TunnelStatus {
    try {
        if (-not (Test-Path $NGROK_EXE)) {
            return "Ngrok not installed"
        }
        
        $result = & $NGROK_EXE api tunnels 2>&1
        if ($result -match "no tunnels") {
            return "No active tunnels"
        }
        elseif ($result -match "error") {
            return "Ngrok not running"
        }
        else {
            return "Active tunnels found"
        }
    }
    catch {
        return "Error checking status"
    }
}

# Function to start tunnel
function Start-Tunnel {
    param($TunnelName)
    
    Update-Status "Starting tunnel: $TunnelName..." "Blue"
    
    try {
        if (-not (Test-Path $NGROK_EXE)) {
            Update-Status "Error: Ngrok not found. Run setup first." "Red"
            return
        }
        
        if (-not (Test-Path $CONFIG_FILE)) {
            Update-Status "Error: Config file not found." "Red"
            return
        }
        
        # Start tunnel in background
        $process = Start-Process -FilePath $NGROK_EXE -ArgumentList "start", "--config=$CONFIG_FILE", $TunnelName -PassThru -WindowStyle Hidden
        
        Start-Sleep -Seconds 3
        
        if ($process.HasExited) {
            Update-Status "Error: Tunnel failed to start" "Red"
        }
        else {
            Update-Status "Tunnel '$TunnelName' started successfully" "Green"
            Refresh-TunnelList
        }
    }
    catch {
        Update-Status "Error starting tunnel: $($_.Exception.Message)" "Red"
    }
}

# Function to stop all tunnels
function Stop-AllTunnels {
    Update-Status "Stopping all tunnels..." "Orange"
    
    try {
        Get-Process -Name "ngrok" -ErrorAction SilentlyContinue | Stop-Process -Force
        Update-Status "All tunnels stopped" "Green"
        Refresh-TunnelList
    }
    catch {
        Update-Status "Error stopping tunnels: $($_.Exception.Message)" "Red"
    }
}

# Function to refresh tunnel list
function Refresh-TunnelList {
    $tunnelListBox.Items.Clear()
    
    try {
        if (Test-Path $NGROK_EXE) {
            $result = & $NGROK_EXE api tunnels 2>&1
            
            if ($result -match "no tunnels") {
                $tunnelListBox.Items.Add("No active tunnels")
            }
            elseif ($result -match "error") {
                $tunnelListBox.Items.Add("Ngrok not running")
            }
            else {
                # Parse tunnel information
                $lines = $result -split "`n"
                foreach ($line in $lines) {
                    if ($line.Trim() -ne "") {
                        $tunnelListBox.Items.Add($line.Trim())
                    }
                }
            }
        }
        else {
            $tunnelListBox.Items.Add("Ngrok not installed")
        }
    }
    catch {
        $tunnelListBox.Items.Add("Error getting tunnel info")
    }
}

# Function to open web interface
function Open-WebInterface {
    try {
        Start-Process "http://localhost:4040"
        Update-Status "Opened ngrok web interface" "Green"
    }
    catch {
        Update-Status "Error opening web interface" "Red"
    }
}

# Function to run setup
function Run-Setup {
    Update-Status "Running ngrok setup..." "Blue"
    
    try {
        $setupScript = Join-Path $PROJECT_DIR "setup-ngrok.ps1"
        if (Test-Path $setupScript) {
            Start-Process -FilePath "powershell.exe" -ArgumentList "-ExecutionPolicy", "Bypass", "-File", "`"$setupScript`"" -Wait
            Update-Status "Setup completed" "Green"
        }
        else {
            Update-Status "Setup script not found" "Red"
        }
    }
    catch {
        Update-Status "Error running setup: $($_.Exception.Message)" "Red"
    }
}

# Create main form
$form = New-Object System.Windows.Forms.Form
$form.Text = "ROTC QR System - Ngrok Manager"
$form.Size = New-Object System.Drawing.Size(600, 500)
$form.StartPosition = "CenterScreen"
$form.FormBorderStyle = "FixedDialog"
$form.MaximizeBox = $false
$form.BackColor = "#2C3E50"
$form.ForeColor = "White"

# Title label
$titleLabel = New-Object System.Windows.Forms.Label
$titleLabel.Text = "ROTC QR System - Ngrok Tunnel Manager"
$titleLabel.Font = New-Object System.Drawing.Font("Arial", 14, [System.Drawing.FontStyle]::Bold)
$titleLabel.ForeColor = "#ECF0F1"
$titleLabel.Location = New-Object System.Drawing.Point(20, 20)
$titleLabel.Size = New-Object System.Drawing.Size(550, 30)
$titleLabel.TextAlign = "MiddleCenter"
$form.Controls.Add($titleLabel)

# Status label
$statusLabel = New-Object System.Windows.Forms.Label
$statusLabel.Text = "Ready"
$statusLabel.Font = New-Object System.Drawing.Font("Arial", 10)
$statusLabel.ForeColor = "#27AE60"
$statusLabel.Location = New-Object System.Drawing.Point(20, 60)
$statusLabel.Size = New-Object System.Drawing.Size(550, 25)
$form.Controls.Add($statusLabel)

# Tunnel selection group
$tunnelGroup = New-Object System.Windows.Forms.GroupBox
$tunnelGroup.Text = "Available Tunnels"
$tunnelGroup.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$tunnelGroup.ForeColor = "#ECF0F1"
$tunnelGroup.Location = New-Object System.Drawing.Point(20, 100)
$tunnelGroup.Size = New-Object System.Drawing.Size(270, 200)
$form.Controls.Add($tunnelGroup)

# Tunnel radio buttons
$y = 25
$tunnels = @(
    @{Name="qr-project"; Description="Main QR Project (Port 80)"},
    @{Name="qr-project-https"; Description="HTTPS Version (Port 443)"},
    @{Name="qr-dev"; Description="Development (Port 8080)"},
    @{Name="qr-mobile"; Description="Mobile Testing (Port 80)"},
    @{Name="qr-api"; Description="API Tunnel (Port 8000)"}
)

$tunnelRadios = @()
foreach ($tunnel in $tunnels) {
    $radio = New-Object System.Windows.Forms.RadioButton
    $radio.Text = $tunnel.Description
    $radio.Tag = $tunnel.Name
    $radio.Font = New-Object System.Drawing.Font("Arial", 9)
    $radio.ForeColor = "#BDC3C7"
    $radio.Location = New-Object System.Drawing.Point(10, $y)
    $radio.Size = New-Object System.Drawing.Size(250, 25)
    $tunnelGroup.Controls.Add($radio)
    $tunnelRadios += $radio
    $y += 30
}

# Set default selection
$tunnelRadios[0].Checked = $true

# Active tunnels group
$activeGroup = New-Object System.Windows.Forms.GroupBox
$activeGroup.Text = "Active Tunnels"
$activeGroup.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$activeGroup.ForeColor = "#ECF0F1"
$activeGroup.Location = New-Object System.Drawing.Point(310, 100)
$activeGroup.Size = New-Object System.Drawing.Size(260, 200)
$form.Controls.Add($activeGroup)

# Tunnel list box
$tunnelListBox = New-Object System.Windows.Forms.ListBox
$tunnelListBox.Font = New-Object System.Drawing.Font("Consolas", 8)
$tunnelListBox.BackColor = "#34495E"
$tunnelListBox.ForeColor = "#ECF0F1"
$tunnelListBox.Location = New-Object System.Drawing.Point(10, 25)
$tunnelListBox.Size = New-Object System.Drawing.Size(240, 165)
$activeGroup.Controls.Add($tunnelListBox)

# Control buttons
$buttonY = 320
$buttonWidth = 120
$buttonHeight = 35
$buttonSpacing = 10

# Start button
$startButton = New-Object System.Windows.Forms.Button
$startButton.Text = "Start Tunnel"
$startButton.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$startButton.BackColor = "#27AE60"
$startButton.ForeColor = "White"
$startButton.FlatStyle = "Flat"
$startButton.Location = New-Object System.Drawing.Point(20, $buttonY)
$startButton.Size = New-Object System.Drawing.Size($buttonWidth, $buttonHeight)
$startButton.Add_Click({
    $selectedTunnel = ($tunnelRadios | Where-Object {$_.Checked}).Tag
    Start-Tunnel $selectedTunnel
})
$form.Controls.Add($startButton)

# Stop button
$stopButton = New-Object System.Windows.Forms.Button
$stopButton.Text = "Stop All"
$stopButton.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$stopButton.BackColor = "#E74C3C"
$stopButton.ForeColor = "White"
$stopButton.FlatStyle = "Flat"
$stopButton.Location = New-Object System.Drawing.Point((20 + $buttonWidth + $buttonSpacing), $buttonY)
$stopButton.Size = New-Object System.Drawing.Size($buttonWidth, $buttonHeight)
$stopButton.Add_Click({ Stop-AllTunnels })
$form.Controls.Add($stopButton)

# Refresh button
$refreshButton = New-Object System.Windows.Forms.Button
$refreshButton.Text = "Refresh"
$refreshButton.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$refreshButton.BackColor = "#3498DB"
$refreshButton.ForeColor = "White"
$refreshButton.FlatStyle = "Flat"
$refreshButton.Location = New-Object System.Drawing.Point((20 + 2*($buttonWidth + $buttonSpacing)), $buttonY)
$refreshButton.Size = New-Object System.Drawing.Size($buttonWidth, $buttonHeight)
$refreshButton.Add_Click({ Refresh-TunnelList })
$form.Controls.Add($refreshButton)

# Web interface button
$webButton = New-Object System.Windows.Forms.Button
$webButton.Text = "Web Interface"
$webButton.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$webButton.BackColor = "#9B59B6"
$webButton.ForeColor = "White"
$webButton.FlatStyle = "Flat"
$webButton.Location = New-Object System.Drawing.Point((20 + 3*($buttonWidth + $buttonSpacing)), $buttonY)
$webButton.Size = New-Object System.Drawing.Size($buttonWidth, $buttonHeight)
$webButton.Add_Click({ Open-WebInterface })
$form.Controls.Add($webButton)

# Setup button
$setupButton = New-Object System.Windows.Forms.Button
$setupButton.Text = "Run Setup"
$setupButton.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$setupButton.BackColor = "#F39C12"
$setupButton.ForeColor = "White"
$setupButton.FlatStyle = "Flat"
$setupButton.Location = New-Object System.Drawing.Point(20, ($buttonY + $buttonHeight + $buttonSpacing))
$setupButton.Size = New-Object System.Drawing.Size($buttonWidth, $buttonHeight)
$setupButton.Add_Click({ Run-Setup })
$form.Controls.Add($setupButton)

# Auto-refresh checkbox
$autoRefreshCheck = New-Object System.Windows.Forms.CheckBox
$autoRefreshCheck.Text = "Auto-refresh (5s)"
$autoRefreshCheck.Font = New-Object System.Drawing.Font("Arial", 9)
$autoRefreshCheck.ForeColor = "#BDC3C7"
$autoRefreshCheck.Location = New-Object System.Drawing.Point((20 + $buttonWidth + $buttonSpacing), ($buttonY + $buttonHeight + $buttonSpacing + 10))
$autoRefreshCheck.Size = New-Object System.Drawing.Size(150, 25)
$form.Controls.Add($autoRefreshCheck)

# Timer for auto-refresh
$timer = New-Object System.Windows.Forms.Timer
$timer.Interval = 5000
$timer.Add_Tick({ 
    if ($autoRefreshCheck.Checked) {
        Refresh-TunnelList
    }
})
$timer.Start()

# Initial status update
Update-Status "Ngrok Manager initialized - $(Get-TunnelStatus)" "Green"
Refresh-TunnelList

# Show form
$form.Add_Shown({$form.Activate()})
[void]$form.ShowDialog()

# Cleanup
$timer.Stop()
$timer.Dispose()