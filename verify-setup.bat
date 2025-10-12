@echo off
echo Document Tracking System - Setup Verification
echo ============================================
echo.

echo 1. Checking Node.js...
node --version > nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Node.js not found. Please install Node.js first.
    pause
    exit /b 1
) else (
    echo ✅ Node.js found
)

echo.
echo 2. Checking XAMPP...
curl -s http://localhost/ > nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ XAMPP not running. Please start XAMPP (Apache and MySQL).
    pause
    exit /b 1
) else (
    echo ✅ XAMPP is running
)

echo.
echo 3. Checking database...
curl -s http://localhost/document-tracking/test-setup.php > nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Database setup incomplete. Please run database/schema.sql first.
    pause
    exit /b 1
) else (
    echo ✅ Database is configured
)

echo.
echo 4. Installing dependencies...
call npm install
if %errorlevel% neq 0 (
    echo ❌ Failed to install dependencies
    pause
    exit /b 1
) else (
    echo ✅ Dependencies installed
)

echo.
echo 5. Building application...
call npm run build
if %errorlevel% neq 0 (
    echo ❌ Failed to build application
    pause
    exit /b 1
) else (
    echo ✅ Application built successfully
)

echo.
echo ============================================
echo ✅ Setup verification complete!
echo.
echo To launch the application:
echo - Production: launch.bat
echo - Development: launch-dev.bat
echo.
pause
