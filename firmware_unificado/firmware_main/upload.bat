@echo off
echo ==========================================
echo   UPDATING MAIN ESP32 FIRMWARE
echo ==========================================
pio run -e esp32dev -t upload
if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] Fallo la subida. Revisa errores arriba.
    pause
    exit /b 1
)
echo.
echo [SUCCESS] Firmware actualizado correctamente.
pause
