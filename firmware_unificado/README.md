# 🏁 Neurona Off Road Telemetry - Firmware Unificado

## 📡 Descripción

Firmware unificado para el sistema de telemetría Neurona Off Road.
Soporta múltiples fuentes de datos (CAN, OBD2, GPS, IMU) en una sola base de código

## 📁 Estructura del Proyecto

El proyecto se ha reorganizado para mayor claridad y estabilidad:

*   **`firmware_main/`**: Contiene el código fuente del **ESP32 Principal** (el que gestiona sensores, GPS, SD, Telemetría).
*   **`firmware_c3/`**: Contiene el código fuente del **ESP32-C3** (puente OBD2/BLE).
*   **`configurator/`**: Aplicación de escritorio Python para configurar los dispositivos.
*   **`MASTER_BUILD.bat`**: Herramienta unificada para compilar y subir firmware a ambos dispositivos.

## 🚀 Guía Rápida de Uso

### 1. Compilar y Subir Firmware

No necesitas comandos complejos. Hemos creado un script maestro para todo.

1.  Conecta tu ESP32 (Main o C3) al USB.
2.  Ejecuta el archivo **`MASTER_BUILD.bat`**.
3.  Selecciona la opción deseada:
    *   `1` para el **ESP32 Principal**.
    *   `2` para el **ESP32-C3**.
    *   `4` o `5` para ver el Monitor Serial (logs en vivo).

### 2. Configurar el Dispositivo

1.  Asegúrate de que el firmware esté subido.
2.  Ejecuta **`configurador.bat`** (o corre `python configurator/main_refactored.py`).
3.  Selecciona el **Puerto COM** y haz clic en **Conectar**.
4.  Si es la primera vez, el configurador descargará la configuración actual automáticamente.

---

## 📁 Estructura del Proyecto

```
firmware_unificado/
├── platformio.ini          # Configuración PlatformIO
├── upload.bat              # Script para subir firmware
├── monitor.bat             # Script para monitor serial
├── configurador.bat        # Script para lanzar configurador Python
├── src/
│   ├── main.cpp            # Punto de entrada
│   ├── config/             # Gestión de configuración
│   │   ├── config_schema.h     # Estructuras de config
│   │   ├── config_defaults.h   # Valores por defecto
│   │   ├── config_manager.h
│   │   └── config_manager.cpp
│   ├── telemetry/          # Bus de telemetría centralizado
│   │   ├── telemetry_bus.h
│   │   └── telemetry_bus.cpp
│   ├── sources/            # Fuentes de datos
│   │   ├── data_source.h       # Interface base
│   │   ├── source_gps.*        # GPS UART
│   │   ├── source_imu.*        # MPU6050 I2C
│   │   ├── source_can.*        # MCP2515 SPI
│   │   ├── source_obd_direct.* # ELM327 WiFi
│   │   └── source_obd_bridge.* # ESP32-C3 UART
│   ├── cloud/              # Comunicación cloud
│   │   ├── cloud_manager.h
│   │   └── cloud_manager.cpp
│   └── serial/             # Comunicación serial/USB
│       ├── serial_manager.h
│       └── serial_manager.cpp
├── data/
│   └── config.json         # Config por defecto (LittleFS)
├── configurator/           # Configurador Python (PySide6)
│   ├── main.py
│   ├── requirements.txt
│   └── README.md
└── firmware_c3/            # Firmware ESP32-C3 OBD Bridge
    ├── platformio.ini
    ├── upload.bat
    ├── README.md
    └── src/main.cpp
```

---

## ⚙️ Modos de Operación

El sistema soporta **3 modos de operación** basados en el hardware disponible:

| Modo | source | Descripción | Hardware Requerido |
|------|--------|-------------|--------------------|
| **CAN_ONLY** 🏎️ | `CAN_ONLY` | Lectura de sensores vía CAN Bus | MCP2515 + GPS* + IMU* |
| **OBD_BRIDGE** 🔌 | `OBD_BRIDGE` | Datos OBD2 vía ESP32-C3 como puente | ESP32-C3 + ELM327 WiFi + GPS + IMU |
| **SENSORS_ONLY** 📍 | `SENSORS_ONLY` | Solo tracking (sin datos de motor) | GPS + IMU |

*GPS e IMU son opcionales y configurables en todos los modos

> **Nota:** Los modos `OBD_DIRECT` y `CAN_OBD` fueron eliminados ya que no son soportados por la arquitectura de hardware actual.

---

## 📟 Comandos Serial

Conectar a **115200 baud**. Comandos disponibles:

| Comando | Descripción |
|---------|-------------|
| `PING` | Test de conexión → `ACK:PONG` |
| `GET_CONFIG` | Obtener configuración completa (JSON) |
| `SET_CONFIG:{json}` | Establecer configuración (no guarda) |
| `SAVE_CONFIG` | Guardar en flash persistente |
| `GET_STATUS` | Estado del sistema |
| `GET_TELEMETRY` | Snapshot de telemetría actual |
| `GET_SENSORS` | Sensores CAN configurados |
| `LIVE_ON` | Activar streaming continuo |
| `LIVE_OFF` | Desactivar streaming |
| `REBOOT` | Reiniciar dispositivo |
| `FACTORY_RESET` | Reset a valores por defecto |
| `HELP` | Mostrar ayuda |

---

## 📡 Formato de Trama MQTT

Compatible con el formato MoTeC original:

```json
{
  "id": "00000000000000001",
  "idc": "OBD-2025-0001",
  "dt": "2025-12-19 22:15:00",
  "d": false,
  "s": {
    "lat": {"v": "-33.123456"},
    "lng": {"v": "-70.654321"},
    "vel_kmh": {"v": 85.5},
    "alt_m": {"v": 550},
    "rumbo": {"v": 180.0},
    "gps_sats": {"v": 8},
    "accel_x": {"v": 0.1},
    "accel_y": {"v": -0.05},
    "accel_z": {"v": 9.8},
    "rpm": {"v": 3500},
    "speed_kmh": {"v": 85},
    "coolant_temp": {"v": 92},
    "tps": {"v": 45},
    "load": {"v": 65},
    "batt_v": {"v": 13.8},
    "mi_sensor_custom": {"v": 123.5},
    "wifi_rssi": {"v": -65}
  },
  "DTC": []
}
```

### Campos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | string | Device ID |
| `idc` | string | Car ID |
| `dt` | string | Timestamp (YYYY-MM-DD HH:MM:SS) |
| `d` | bool | Debug mode (true = no guarda en DB) |
| `s` | object | Sensores: `{"sensor_id": {"v": valor}}` |
| `DTC` | array | Códigos de error OBD |

### Sensores Predefinidos

Los sensores CAN usan el campo `cloud_id` del configurador.
Nombres especiales se mapean automáticamente:

| Nombre/cloud_id | Campo en JSON |
|-----------------|---------------|
| `RPM`, `engine.rpm` | `rpm` |
| `COOLANT`, `engine.coolant_temp` | `coolant_temp` |
| `TPS`, `engine.throttle` | `tps` |
| `SPEED`, `engine.speed` | `speed_kmh` |
| Cualquier otro | El cloud_id textual |

---

## 🔌 Conexiones Hardware

### ESP32 Principal

| Función | GPIO | Notas |
|---------|------|-------|
| **CAN CS** | 5 | MCP2515 Chip Select |
| **CAN INT** | 4 | MCP2515 Interrupt |
| **GPS RX** | 16 | UART2 RX |
| **GPS TX** | 17 | UART2 TX |
| **IMU SDA** | 21 | I2C Data |
| **IMU SCL** | 22 | I2C Clock |
| **C3 RX** | 32 | UART Bridge (modo obd_bridge) |
| **C3 TX** | 33 | UART Bridge (modo obd_bridge) |

### ESP32-C3 Bridge

| Función | GPIO |
|---------|------|
| **TX → ESP32** | 20 |
| **RX ← ESP32** | 21 |
| **USB CDC** | Nativo |

---

## 🛠️ Configurador Python

Lanzar con:
```bash
doble-click configurador.bat
# o
cd configurator && python main.py
```

### Características

- Importar DBC (CAN database)
- Importar XML MoTeC
- Configurar sensores CAN con cloud_id
- Configurar WiFi, MQTT, HTTP
- Configurar OBD (directo/bridge)
- Vista en vivo de telemetría
- Consola serial integrada

---

## 📦 Compilación

### ESP32 Principal
```bash
cd firmware_unificado
python -m platformio run          # Solo compilar
python -m platformio run -t upload # Compilar y subir
```

### ESP32-C3 Bridge
```bash
cd firmware_unificado/firmware_c3
python -m platformio run -t upload
```

### Estadísticas Actuales

**ESP32 Principal:**
```
RAM:   [==        ]  16.3% (53KB / 320KB)
Flash: [========  ]  80.5% (1MB / 1.3MB)
```

**ESP32-C3 Bridge:**
```
RAM:   [=         ]  12.4% (40KB / 320KB)
Flash: [====      ]  39.7% (780KB / 1.9MB)
```

---

## 📝 Changelog

### v3.0 (Diciembre 2024)
- ✅ Firmware unificado con múltiples fuentes de datos
- ✅ Soporte OBD2 directo y bridge (ESP32-C3)
- ✅ Configurador Python PySide6
- ✅ Formato de trama compatible con MoTeC original
- ✅ Scripts .bat para subida fácil
- ✅ Documentación completa

---

## 👥 Equipo

**Neurona Racing Development**
Diciembre 2024

---

## 📄 Licencia

Propiedad de Neurona Racing Development.
