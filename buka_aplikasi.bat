@echo off
title Membuka e-Mediqua...
cd /d "%~dp0"

:: Cek apakah server e-Mediqua di port 8088 sudah aktif
netstat -ano | findstr ":8088" | findstr "LISTENING" >nul
if %errorlevel% neq 0 (
    wscript.exe "%~dp0start_server_silent.vbs"
    timeout /t 2 /nobreak >nul
)

start http://localhost/e-Mediqua/login
exit
