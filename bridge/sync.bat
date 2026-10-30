@echo off
title ZKTeco Cloud Bridge
cd /d "%~dp0"
echo ===================================================
echo   ZKTeco Cloud Bridge Runner
echo ===================================================
echo.

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" zk_bridge.php
) else (
    php zk_bridge.php
)

echo.
echo ===================================================
timeout /t 5
