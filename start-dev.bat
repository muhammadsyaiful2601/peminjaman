@echo off
setlocal

cd /d "%~dp0"

if not exist "backend\artisan" (
    echo Backend Laravel tidak ditemukan: backend\artisan
    pause
    exit /b 1
)

if not exist "frontend\package.json" (
    echo Frontend tidak ditemukan: frontend\package.json
    pause
    exit /b 1
)

start "Laravel Backend" cmd /k "cd /d "%~dp0backend" && php artisan serve"
start "Vite Frontend" cmd /k "cd /d "%~dp0frontend" && npm run dev"
start "" "http://localhost:5173"

echo Backend dan frontend sedang dijalankan di jendela masing-masing.
endlocal