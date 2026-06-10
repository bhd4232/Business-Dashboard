@echo off
title ZamZam ERP — Server Launcher
color 0A

echo.
echo  ======================================
echo   ZamZam ERP Server Starting...
echo  ======================================
echo.

:: Laravel Server
echo  [1/2] Starting Laravel server (port 8000)...
start "ZamZam — Laravel" cmd /k "cd /d "E:\BHD Projects\Plan Projects\ZamZam ERP\zamzam-erp" && "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan serve --host=127.0.0.1 --port=8000"

timeout /t 2 /nobreak >nul

:: Vite Dev Server
echo  [2/2] Starting Vite dev server (port 5173)...
start "ZamZam — Vite" cmd /k "cd /d "E:\BHD Projects\Plan Projects\ZamZam ERP\zamzam-erp" && npm run dev"

timeout /t 3 /nobreak >nul

:: Open browser
echo.
echo  Opening browser...
start "" "http://127.0.0.1:8000"

echo.
echo  ======================================
echo   Servers are running!
echo   Laravel : http://127.0.0.1:8000
echo   Vite    : http://127.0.0.1:5173
echo  ======================================
echo.
echo  (এই window বন্ধ করা যাবে)
pause
