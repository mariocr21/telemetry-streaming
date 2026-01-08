@echo off
REM ============================================================
REM  Neurona Telemetry - Ejecutar Configurador
REM  Ejecutar desde firmware_unificado/
REM ============================================================

echo.
echo ======================================
echo   NEURONA - CONFIGURADOR
echo ======================================
echo.

cd /d "%~dp0configurator"

REM Verificar dependencias
python -c "import PySide6" >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [INFO] Instalando dependencias...
    pip install -r requirements.txt
)

echo [INFO] Iniciando configurador...
echo.

python main.py
