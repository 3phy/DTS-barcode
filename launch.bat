@echo off
echo Document Tracking System Launcher
echo =================================
echo.

echo Checking if XAMPP is running...
curl -s http://localhost/ > nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: XAMPP is not running!
    echo Please start XAMPP (Apache and MySQL) and try again.
    echo.
    pause
    exit /b 1
)

echo XAMPP is running ✓
echo.

echo Installing dependencies...
"C:\Program Files\nodejs\npm.cmd" install

echo.
echo Building React application...
"C:\Program Files\nodejs\npm.cmd" run build

echo.
echo Starting Document Tracking System...
echo The application will open in a desktop window.
echo.

"C:\Program Files\nodejs\npm.cmd" run electron

pause
