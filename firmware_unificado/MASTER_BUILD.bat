@echo off
cls
echo ===================================================
echo   NEURONA TELEMETRY - MASTER BUILD SYSTEM
echo ===================================================
echo.
echo 1. Compilar y Subir MAIN ESP32 (Firmware Principal)
echo 2. Compilar y Subir C3 (CAN/OBD Bridge)
echo 3. Limpiar Todo (Clean All)
echo 4. Monitor Serial (MAIN)
echo 5. Monitor Serial (C3)
echo.
set /p opt="Selecciona una opcion: "

if "%opt%"=="1" goto upload_main
if "%opt%"=="2" goto upload_c3
if "%opt%"=="3" goto clean_all
if "%opt%"=="4" goto monitor_main
if "%opt%"=="5" goto monitor_c3

:upload_main
cd firmware_main
call upload.bat
cd ..
goto end

:upload_c3
cd firmware_c3
call upload.bat
cd ..
goto end

:clean_all
echo [INFO] Limpiando MAIN...
cd firmware_main
call pio run -t clean
cd ..
echo [INFO] Limpiando C3...
cd firmware_c3
call pio run -t clean
cd ..
echo Limpieza completada.
pause
goto end

:monitor_main
cd firmware_main
pio device monitor
cd ..
goto end

:monitor_c3
cd firmware_c3
pio device monitor
cd ..
goto end

:end
