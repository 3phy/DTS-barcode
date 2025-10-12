# Document Tracking System - PowerShell Launcher
Write-Host "Document Tracking System Launcher" -ForegroundColor Green
Write-Host "=================================" -ForegroundColor Green
Write-Host ""

# Add Node.js to PATH
$env:PATH += ";C:\Program Files\nodejs"

# Check if XAMPP is running
Write-Host "Checking if XAMPP is running..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost/" -TimeoutSec 5 -UseBasicParsing
    Write-Host "XAMPP is running ✓" -ForegroundColor Green
} catch {
    Write-Host "ERROR: XAMPP is not running!" -ForegroundColor Red
    Write-Host "Please start XAMPP (Apache and MySQL) and try again." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""

# Install dependencies
Write-Host "Installing dependencies..." -ForegroundColor Yellow
npm install

Write-Host ""

# Start development server
Write-Host "Starting Document Tracking System..." -ForegroundColor Yellow
Write-Host "The application will open in a desktop window." -ForegroundColor Cyan
Write-Host ""

npm run electron-dev
