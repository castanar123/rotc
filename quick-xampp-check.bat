@echo off
echo Quick XAMPP Status Check
echo ========================
echo.

echo Checking Apache processes...
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if %ERRORLEVEL% EQU 0 (
    echo [✓] Apache is running
) else (
    echo [✗] Apache is not running
)

echo.
echo Checking MySQL processes...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul
if %ERRORLEVEL% EQU 0 (
    echo [✓] MySQL is running
) else (
    echo [✗] MySQL is not running
)

echo.
echo Checking port 80 (Apache)...
netstat -an | find ":80 " >nul
if %ERRORLEVEL% EQU 0 (
    echo [✓] Port 80 is in use (likely Apache)
) else (
    echo [✗] Port 80 is not in use
)

echo.
echo Checking port 3306 (MySQL)...
netstat -an | find ":3306 " >nul
if %ERRORLEVEL% EQU 0 (
    echo [✓] Port 3306 is in use (likely MySQL)
) else (
    echo [✗] Port 3306 is not in use
)

echo.
echo Testing localhost connection...
ping -n 1 127.0.0.1 >nul
if %ERRORLEVEL% EQU 0 (
    echo [✓] Localhost is reachable
) else (
    echo [✗] Localhost is not reachable
)

echo.
echo ========================
echo Quick Status Complete
echo ========================
echo.
echo For full setup with auto-startup:
echo 1. Right-click 'setup-xampp-autostart.bat'
echo 2. Select 'Run as administrator'
echo.
pause