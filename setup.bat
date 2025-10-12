@echo off
echo Document Tracking System Setup
echo =============================
echo.

echo Installing Node.js dependencies...
call npm install

echo.
echo Installing additional development dependencies...
call npm install --save-dev electron-is-dev

echo.
echo Building React application...
call npm run build

echo.
echo Setup completed!
echo.
echo Next steps:
echo 1. Start XAMPP (Apache and MySQL)
echo 2. Import database/schema.sql into MySQL
echo 3. Update database credentials in api/config/database.php if needed
echo 4. Update JWT secret in api/config/jwt.php
echo 5. Run: npm run electron-dev
echo.
echo Default login credentials:
echo Admin: admin@doctrack.com / admin123
echo Staff: staff@doctrack.com / staff123
echo.
pause
