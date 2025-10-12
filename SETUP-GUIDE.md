# Document Tracking System - Complete Setup Guide

## Prerequisites

### 1. Install Node.js
1. Download Node.js from https://nodejs.org/
2. Install the LTS version (recommended)
3. Verify installation by opening Command Prompt and running:
   ```cmd
   node --version
   npm --version
   ```

### 2. Install XAMPP
1. Download XAMPP from https://www.apachefriends.org/
2. Install XAMPP
3. Start XAMPP Control Panel
4. Start **Apache** and **MySQL** services

## Setup Steps

### Step 1: Database Setup
1. Open XAMPP Control Panel
2. Click "Admin" next to MySQL to open phpMyAdmin
3. Create a new database called `document_tracking`
4. Import the database schema:
   - Click on `document_tracking` database
   - Go to "Import" tab
   - Choose file: `database/schema.sql`
   - Click "Go"

### Step 2: Install Dependencies
Open Command Prompt in the project directory and run:
```cmd
npm install
```

### Step 3: Build Application
```cmd
npm run build
```

### Step 4: Launch Application

#### Option A: PowerShell Launchers (Recommended)
- Right-click `launch.ps1` → "Run with PowerShell" (Development Mode)
- Right-click `launch-prod.ps1` → "Run with PowerShell" (Production Mode)

#### Option B: Command Line
```cmd
# Add Node.js to PATH first
set PATH=%PATH%;C:\Program Files\nodejs

# Development Mode
npm run electron-dev

# Production Mode
npm run build
npm run electron
```

#### Option C: Batch Files
- Double-click `launch.bat` for production
- Double-click `launch-dev.bat` for development

## Troubleshooting

### If you get "npm not recognized":
1. Restart Command Prompt after installing Node.js
2. Or add Node.js to your system PATH manually

### If you get database connection errors:
1. Make sure XAMPP MySQL is running
2. Check database credentials in `api/config/database.php`
3. Verify database exists and schema is imported

### If the app opens in browser instead of desktop:
1. Make sure you're running `npm run electron` not `npm start`
2. Check that Electron is properly installed: `npm list electron`

### If you get 401 Unauthorized errors:
1. Make sure XAMPP Apache is running
2. Check that the project is in `C:\xampp\htdocs\document-tracking\`
3. Verify the database has the default users

## Default Login Credentials
- **Admin**: admin@doctrack.com / admin123
- **Staff**: staff@doctrack.com / staff123

## File Structure
```
document-tracking/
├── api/                    # PHP backend
├── database/              # Database schema
├── src/                   # React frontend
├── public/                # Electron main process
├── uploads/               # Document storage
├── launch.bat            # Production launcher
├── launch-dev.bat        # Development launcher
└── verify-setup.bat      # Setup verification
```

## Quick Start Commands
```cmd
# Install dependencies
npm install

# Build for production
npm run build

# Run desktop app
npm run electron

# Run in development mode
npm run electron-dev
```

## Support
If you encounter issues:
1. Run `verify-setup.bat` to check your setup
2. Check the troubleshooting section above
3. Ensure all prerequisites are installed
