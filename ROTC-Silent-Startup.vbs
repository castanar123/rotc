' ROTC QR System - Silent Startup Script
' This VBScript runs completely silently without any visible windows
' Place this file in: C:\Users\User\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup

Set objShell = CreateObject("WScript.Shell")
Set objFSO = CreateObject("Scripting.FileSystemObject")

' Define the working directory
strWorkingDir = "c:\xampp\htdocs\generate qr"

' Change to the working directory
objShell.CurrentDirectory = strWorkingDir

' Function to run command silently
Sub RunSilent(strCommand)
    objShell.Run strCommand, 0, False
End Sub

' Function to run command and wait silently
Sub RunSilentWait(strCommand)
    objShell.Run strCommand, 0, True
End Sub

' Function to check if process is running
Function IsProcessRunning(strProcessName)
    Set objWMIService = GetObject("winmgmts:\\" & "." & "\root\cimv2")
    Set colProcesses = objWMIService.ExecQuery("SELECT * FROM Win32_Process WHERE Name = '" & strProcessName & "'")
    IsProcessRunning = (colProcesses.Count > 0)
End Function

' Wait a moment for system to fully boot
WScript.Sleep 5000

' Step 1: Start XAMPP Services (Apache and MySQL)
If Not IsProcessRunning("httpd.exe") Then
    If objFSO.FileExists("c:\xampp\apache\bin\httpd.exe") Then
        RunSilent "c:\xampp\apache\bin\httpd.exe"
        WScript.Sleep 3000
    End If
End If

If Not IsProcessRunning("mysqld.exe") Then
    If objFSO.FileExists("c:\xampp\mysql\bin\mysqld.exe") Then
        RunSilent "c:\xampp\mysql\bin\mysqld.exe --defaults-file=c:\xampp\mysql\bin\my.ini --standalone"
        WScript.Sleep 3000
    End If
End If

' Step 2: Start Cloudflare Tunnel
If Not IsProcessRunning("cloudflared.exe") Then
    If objFSO.FileExists(strWorkingDir & "\cloudflare\cloudflared.exe") And objFSO.FileExists(strWorkingDir & "\cloudflare-tunnel.yml") Then
        RunSilent "\"" & strWorkingDir & "\cloudflare\cloudflared.exe\" tunnel --config \"" & strWorkingDir & "\cloudflare-tunnel.yml\" run"
    End If
End If

' Script completes silently - no output or windows