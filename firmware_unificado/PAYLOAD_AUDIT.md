# 📡 AUDITORÍA DE PAYLOADS Y PROTOCOLOS

**Proyecto:** Neurona Off Road Telemetry  
**Fecha:** 2024-12-23  
**Alcance:** Verificación de congruencia entre todos los formatos de datos

---

## ⚠️ ACLARACIÓN IMPORTANTE

Existen **3 tipos diferentes de datos JSON** en el sistema. NO confundir:

| Nombre | Origen → Destino | Propósito |
|--------|------------------|-----------|
| **CONFIG JSON** | Python Configurador → ESP32 Principal | Configuración inicial del dispositivo |
| **UART JSON** | ESP32-C3 → ESP32 Principal | Comunicación entre ESPs (datos OBD) |
| **CLOUD PAYLOAD** | ESP32 Principal → Servidor MQTT/HTTP | Telemetría hacia la nube |

---

## 1️⃣ CONFIG JSON (Configurador Python → ESP32)

**Usado para:** Configuración inicial. El usuario lo edita en el configurador UI.

### Modo CAN_ONLY
```json
{
  "version": "3.2",
  "device": {
    "id": "NEURONA_001",
    "car_id": "BAJA_2025",
    "source": "CAN_ONLY"
  },
  "wifi": {
    "ssid": "MiRedWiFi",
    "password": "password123"
  },
  "cloud": {
    "protocol": "mqtt",
    "interval_ms": 1000,
    "debug_mode": false,
    "mqtt": {...}
  },
  "can": {
    "enabled": true,
    "cs_pin": 5,
    "int_pin": 4,
    "baud_kbps": 500,
    "crystal_mhz": 8
  },
  "gps": {"enabled": true, "rx_pin": 16, "tx_pin": 17},
  "imu": {"enabled": true},
  "sensors": [
    {
      "name": "RPM",
      "cloud_id": "rpm",
      "can_id": 1520,
      "start_byte": 0,
      "length": 16,
      "signed": false,
      "multiplier": 1.0,
      "offset": 0
    }
  ]
}
```

### Modo OBD_BRIDGE
```json
{
  "version": "3.2",
  "device": {
    "id": "NEURONA_001",
    "source": "OBD_BRIDGE"
  },
  "wifi": {...},
  "cloud": {...},
  "obd": {
    "enabled": true,
    "mode": "bridge",
    "pids_enabled": "0x0C,0x0D,0x05,0x04",
    "poll_interval_ms": 200,
    "uart": {
      "rx_pin": 32,
      "tx_pin": 33,
      "baud": 460800
    }
  },
  "bridge_wifi": {
    "ssid": "WiFi_OBDII",
    "password": "",
    "ip": "192.168.0.10",
    "port": 35000
  },
  "gps": {...},
  "imu": {...},
  "fuel": {
    "method": "AUTO",
    "displacement_l": 2.0
  }
}
```

**Nota:** `bridge_wifi` es la configuración que el ESP32 Principal envía al C3 para que este se conecte al ELM327.

---

## 2️⃣ UART JSON (ESP32-C3 → ESP32 Principal)

**Usado para:** Comunicación interna entre los dos ESPs por UART.

### Formato de Trama  C3 → Principal
```
{JSON}#CHECKSUM\n
```

### Mensaje tipo DATA (PIDs OBD)
```json
{
  "t": "DATA",
  "ts": 123456,
  "pids": {
    "0x0C": 3500.0,
    "0x0D": 85.0,
    "0x05": 92.0,
    "0x04": 45.2,
    "0x11": 23.5,
    "0x0B": 101.0,
    "BAT": 14.2
  },
  "dtc": ["P0300", "P0420"]
}
```

**Campos:**
- `t`: Tipo de mensaje ("DATA", "OBD_STATUS", "DTC_CLEARED")
- `ts`: Timestamp en millis()
- `pids`: Objeto con PIDs como keys en formato hex (ej: "0x0C" = RPM)
- `dtc`: Array de códigos de falla activos

### Mensaje tipo OBD_STATUS
```json
{"t": "OBD_STATUS", "data": "CONNECTED", "ts": 123456}
```

### Mensaje tipo DTC_CLEARED
```json
{"t": "DTC_CLEARED", "data": "SUCCESS", "ts": 123456}
```

---

## 3️⃣ CLOUD PAYLOAD (ESP32 Principal → Servidor MQTT)

**Usado para:** Enviar telemetría al backend. Formato compatible con sistema MoTeC legacy.

### Formato Actual (cloud_manager.cpp buildPayload())
```json
{
  "id": "00000000000000001",
  "idc": "BAJA-2025-0001",
  "d": false,
  "dt": "2024-12-23 15:30:45",
  "s": {
    "lat": {"v": 32.123456},
    "lng": {"v": -117.654321},
    "vel_kmh": {"v": 85.5},
    "alt_m": {"v": 450},
    "rumbo": {"v": 180},
    "gps_sats": {"v": 12},
    "accel_x": {"v": 0.15},
    "accel_y": {"v": -0.08},
    "accel_z": {"v": 9.81},
    "gyro_x": {"v": 0.01},
    "gyro_y": {"v": 0.02},
    "gyro_z": {"v": 0.00},
    "rpm": {"v": 3500},
    "speed_kmh": {"v": 85},
    "coolant_temp": {"v": 92},
    "oil_temp": {"v": 105},
    "tps": {"v": 23.5},
    "load": {"v": 45.2},
    "maf": {"v": 12.5},
    "map": {"v": 101},
    "fuel_level": {"v": 65.0},
    "fuel_rate": {"v": 8.5},
    "batt_v": {"v": 14.2},
    "wifi_rssi": {"v": -65},
    "heap_free": {"v": 125000},
    "OBD_Status": {"v": 1.0}
  },
  "DTC": []
}
```

### Campos Clave del Payload Cloud
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | string | ID del dispositivo (17 chars) |
| `idc` | string | ID del vehículo |
| `d` | bool | Debug mode (true = no guardar en BD) |
| `dt` | string | Fecha/hora ISO8601 |
| `s` | object | Sensores (cada uno con `{"v": valor}`) |
| `DTC` | array | Códigos de falla activos |

---

## 4️⃣ TABLA DE MAPEO: PID OBD → Cloud Payload

| PID Hex | Nombre C3 | Key en UART | TelemetryBus | Key Cloud |
|---------|-----------|-------------|--------------|-----------|
| 0x0C | RPM | pids["0x0C"] | setEngineRpm() | s.rpm |
| 0x0D | SPEED | pids["0x0D"] | setEngineSpeed() | s.speed_kmh |
| 0x05 | COOLANT | pids["0x05"] | setEngineCoolantTemp() | s.coolant_temp |
| 0x04 | LOAD | pids["0x04"] | setEngineLoad() | s.load |
| 0x0F | IAT | pids["0x0F"] | setCustomValue() | s.engine.intake_temp |
| 0x10 | MAF | pids["0x10"] | setEngineMaf() | s.maf |
| 0x0B | MAP | pids["0x0B"] | setEngineMap() | s.map |
| 0x11 | THROTTLE | pids["0x11"] | setEngineThrottle() | s.tps |
| 0x2F | FUEL_LEVEL | pids["0x2F"] | setFuelLevel() | s.fuel_level |
| 0x5C | OIL_TEMP | pids["0x5C"] | setEngineOilTemp() | s.oil_temp |
| 0x5E | FUEL_RATE | pids["0x5E"] | setFuelRate() | s.fuel_rate |
| BAT | BATT_V | pids["BAT"] | setBatteryVoltage() | s.batt_v |

---

## 5️⃣ DIAGRAMA DE FLUJO DE DATOS

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              FLUJO DE DATOS                                  │
└─────────────────────────────────────────────────────────────────────────────┘

                  ┌─────────────────────────────────────┐
                  │       CONFIGURADOR PYTHON           │
                  │     (main_refactored.py)            │
                  └──────────────────┬──────────────────┘
                                     │
                                     │ CONFIG JSON (Serial USB)
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         ESP32 PRINCIPAL                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │ SourceCAN   │  │ SourceGPS   │  │ SourceIMU   │  │ SourceOBDBridge    │ │
│  │ (MCP2515)   │  │ (UART GPS)  │  │ (I2C IMU)   │  │ (UART ← C3)        │ │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └─────────┬───────────┘ │
│         │                │                │                    │            │
│         └────────────────┴────────────────┴────────────────────┘            │
│                                    │                                         │
│                                    ▼                                         │
│                          ┌─────────────────┐                                 │
│                          │  TelemetryBus   │                                 │
│                          │ (Snapshot Pool) │                                 │
│                          └────────┬────────┘                                 │
│                                   │                                          │
│                                   ▼                                          │
│                          ┌─────────────────┐                                 │
│                          │  CloudManager   │                                 │
│                          │ buildPayload()  │                                 │
│                          └────────┬────────┘                                 │
└───────────────────────────────────┼─────────────────────────────────────────┘
                                    │
                                    │ CLOUD PAYLOAD (MQTT/HTTP)
                                    ▼
                          ┌─────────────────┐
                          │  SERVIDOR MQTT  │
                          │ (Backend/Cloud) │
                          └─────────────────┘


                        === FLUJO OBD BRIDGE ===

┌─────────────────────────────────────────────────────────────────────────────┐
│                              ESP32-C3                                        │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  WiFi → WiFi_OBDII (192.168.0.10:35000) → ELM327 → ECU del auto    │    │
│  └────────────────────────────────┬────────────────────────────────────┘    │
│                                   │                                          │
│                       ┌───────────▼───────────┐                              │
│                       │   enviarDatos()       │                              │
│                       │   JSON + Checksum     │                              │
│                       └───────────┬───────────┘                              │
└───────────────────────────────────┼──────────────────────────────────────────┘
                                    │
                                    │ UART JSON (460800 baud)
                                    │ {"t":"DATA","pids":{...}}#XX
                                    ▼
┌───────────────────────────────────┼──────────────────────────────────────────┐
│  ESP32 PRINCIPAL                  │                                          │
│                       ┌───────────▼───────────┐                              │
│                       │  SourceOBDBridge      │                              │
│                       │  processC3Message()   │                              │
│                       └───────────────────────┘                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 6️⃣ VERIFICACIÓN DE CONGRUENCIA

### ✅ ESP32-C3 → ESP32 Principal: CORRECTO

| Aspecto | Emisor (C3) | Receptor (Principal) | Estado |
|---------|-------------|----------------------|--------|
| Formato | `{"t":"DATA","pids":{...}}#CS\n` | `processC3Message()` parsea JSON | ✅ OK |
| Keys PIDs | "0x0C", "0x0D", "BAT" | Mapeo exacto en `processDataMessage()` | ✅ OK |
| Checksum | `#XX` (hex XOR) | *No verificado actualmente* | ⚠️ TODO |
| Baud | 460800 | 460800 configurable | ✅ OK |

### ✅ ESP32 Principal → Cloud: CORRECTO

| Aspecto | Generador | Formato | Estado |
|---------|-----------|---------|--------|
| Función | `buildPayload()` | JSON con `id`, `idc`, `d`, `dt`, `s{...}` | ✅ OK |
| Sensores | TelemetrySnapshot | Cada sensor: `{"v": valor}` | ✅ OK |
| GPS | snapshot.gps_* | lat, lng, vel_kmh, alt_m, rumbo | ✅ OK |
| OBD | Engine fields + custom | rpm, speed_kmh, coolant_temp, etc. | ✅ OK |

---

## 7️⃣ ISSUES DETECTADOS

### ⚠️ Issue 1: Checksum UART No Verificado
**Ubicación:** `source_obd_bridge.cpp:processC3Message()`

El C3 envía checksum (`#XX`) pero el Principal no lo valida. Esto puede causar parsing de datos corruptos.

**Recomendación:**
```cpp
// Antes de deserializeJson:
int hashPos = json.indexOf('#');
if (hashPos > 0) {
    String payload = json.substring(0, hashPos);
    String csReceived = json.substring(hashPos + 1);
    uint8_t csCalc = calcularChecksum(payload);
    if (csReceived != String(csCalc, HEX)) {
        Serial.println("[OBD_BRIDGE] Checksum FAIL!");
        return;
    }
}
```

### ⚠️ Issue 2: C3 Credenciales WiFi Hardcodeadas
**Ubicación:** `firmware_c3/src/main.cpp:18-23`

```cpp
#define ELM_SSID "WiFi_OBDII"
#define ELM_PASS ""
#define ELM_IP IPAddress(192, 168, 0, 10)
#define ELM_PORT 35000
```

El C3 no recibe estos valores del Principal. Debería recibirlos en un mensaje de configuración.

**Recomendación:** Implementar comando `CONFIG_BRIDGE`

---

## 8️⃣ CONFIGURACIÓN PARA PUSH A C3 (TODO)

Para que el Principal envíe la configuración `bridge_wifi` al C3:

### Mensaje Principal → C3:
```json
{
  "t": "CONFIG_BRIDGE",
  "data": {
    "ssid": "WiFi_OBDII",
    "password": "",
    "ip": "192.168.0.10",
    "port": 35000
  }
}
```

**Esto requiere modificar firmware_c3 para:**
1. Recibir el comando `CONFIG_BRIDGE`
2. Guardar en variables globales (o NVS)
3. Reconectar al ELM con los nuevos valores

---

*Auditoría generada el 2024-12-23*
