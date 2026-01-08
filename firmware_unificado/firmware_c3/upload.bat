@echo off
echo ==========================================
echo   UPDATING C3 BRIDGE FIRMWARE
echo ==========================================
pio run -e esp32c3 -t upload
if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] Fallo la subida. Revisa errores arriba.
    pause
    exit /b 1
)
echo.
echo [SUCCESS] Firmware actualizado correctamente.
pause
