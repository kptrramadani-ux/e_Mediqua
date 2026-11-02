@echo off
title Server E-Mediqua
echo ==========================================
echo   Menjalankan E-Mediqua Server (Port 8080)
echo ==========================================
echo.
echo Pastikan MySQL di XAMPP sudah AKTIF (Start).
echo Server aktif di: http://localhost:8080/auth/login
echo.
cd /d "%~dp0"
php spark serve --port 8080
pause
