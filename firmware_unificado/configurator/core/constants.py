
# =====================================================
# CONFIGURACIÓN DE INTERVALOS EN TIEMPO REAL
# =====================================================
# Intervalo de actualización de la UI en milisegundos.
# Valores más bajos = mayor reactividad pero más carga de CPU.
# Recomendado: 30-50ms para telemetría de ECU.
UI_REFRESH_INTERVAL_MS = 30

# Intervalo por defecto para subida a la nube (ms).
# Este valor se usa como default en Device Settings.
# El firmware usará este valor para enviar datos al servidor.
DEFAULT_CLOUD_INTERVAL_MS = 1000

# Intervalo para telemetría serial ESP32->PC (ms).
# Define cada cuánto el ESP32 envía datos por serial.
# Valores más bajos = datos más frescos pero más tráfico serial.
DEFAULT_SERIAL_INTERVAL_MS = 30
