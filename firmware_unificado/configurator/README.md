# 🎛️ Neurona Telemetry - Configurador Unificado

Configurador PySide6 para el firmware unificado de telemetría Neurona Off Road.

## 📋 Características

- **Tab CAN/Sensores**: Configuración de sensores CAN con import DBC/XML/JSON
- **Tab Dispositivo**: Device ID, WiFi, GPS, IMU, Source Mode
- **Tab Nube**: Protocolo (HTTP/MQTT), credenciales
- **Tab OBD**: Configuración OBD2 completa (direct/bridge, ELM WiFi, PIDs, Fuel)
- **Tab En Vivo**: Visualización de datos en tiempo real
- **Consola**: Log de comunicación serial

## 🚀 Instalación

```bash
# Instalar dependencias
pip install -r requirements.txt

# O instalar individualmente
pip install PySide6 cantools pyserial
```

## ▶️ Ejecución

```bash
python main.py
```

## 🔧 Modos de Operación (Source)

| Modo | Descripción |
|------|-------------|
| `can_only` | Solo CAN bus (MoTeC, ECU) |
| `obd_direct` | OBD2 via ELM327 WiFi directamente |
| `obd_bridge` | OBD2 via ESP32-C3 UART bridge |
| `can_obd` | CAN + OBD2 simultáneos |
| `sensors_only` | Solo GPS + IMU (sin CAN ni OBD) |

## 📡 Comandos Serial

El configurador se comunica con el ESP32 vía los siguientes comandos:

| Comando | Descripción |
|---------|-------------|
| `PING` | Verifica conexión |
| `GET_CONFIG` | Obtiene configuración actual |
| `SET_CONFIG {...}` | Envía nueva configuración |
| `GET_STATUS` | Obtiene estado del sistema |
| `GET_TELEMETRY` | Obtiene snapshot de telemetría |
| `LIVE_START` | Inicia streaming de datos |
| `LIVE_STOP` | Detiene streaming |
| `CLEAR_DTC` | Borra códigos de error OBD |
| `FACTORY_RESET` | Reset a valores por defecto |

## 📁 Estructura

```
configurator/
├── main.py              # Aplicación principal
├── dbc_parser.py        # Parser de archivos DBC
├── xml_loader.py        # Loader de configuración XML
├── json_generator.py    # Generador de JSON para firmware
├── serial_manager.py    # Gestión de puerto serial
├── serial_worker.py     # Worker thread para serial
└── requirements.txt     # Dependencias Python
```

## 📤 Formato de Configuración

El configurador genera un JSON compatible con el firmware unificado:

```json
{
  "wifi": {
    "ssid": "MiRedWiFi",
    "pass": "password123"
  },
  "device": {
    "id": "00000000000000001",
    "car_id": "RACE-2025-001",
    "cloud_interval": 1000,
    "serial_interval": 30,
    "source": "can_only",
    "protocol": "mqtt",
    "mqtt_server": "74.208.234.106",
    "mqtt_port": 1883,
    "mqtt_topic": "vehicles/telemetry",
    "gps_enabled": true,
    "imu_enabled": true
  },
  "obd": {
    "enabled": true,
    "mode": "direct",
    "elm_ssid": "WiFi_OBDII",
    "elm_ip": "192.168.0.10",
    "elm_port": 35000,
    "pids": ["0x0C", "0x0D", "0x05", "0x04"],
    "fuel_method": "AUTO",
    "engine_disp": 2.0,
    "uart_rx_pin": 16,
    "uart_tx_pin": 17,
    "uart_baud": 460800
  },
  "sensors": [
    {
      "enabled": true,
      "can_id": 1632,
      "name": "RPM",
      "cloud_id": "engine.rpm",
      "start_byte": 0,
      "length": 16,
      "multiplier": 1.0,
      "offset": 0.0,
      "big_endian": true
    }
  ]
}
```

## 🔌 Conexión

1. Conectar ESP32 vía USB
2. Seleccionar puerto COM en el menú desplegable
3. Click en **Connect**
4. Usar **Leer Config** para cargar configuración actual
5. Modificar valores según necesidad
6. Click en **Enviar a ESP** para guardar

## 📝 Notas

- El configurador soporta archivos DBC (CAN Database)
- Soporta archivos XML de configuración MoTeC
- Los archivos JSON se pueden importar/exportar para backup
- El modo debug envía datos al broker MQTT pero NO los guarda en BD

---

**Neurona Racing Development © 2024**
