# 🔌 ESP32-C3 OBD2 Bridge Firmware

Firmware para el módulo ESP32-C3 que actúa como puente entre el ELM327 WiFi y el ESP32 principal.

## 🎯 Función

```
[ELM327 WiFi] <--WiFi--> [ESP32-C3] <--UART--> [ESP32 Principal]
```

El C3 se conecta al adaptador OBD2 ELM327 via WiFi y reenvía los datos al ESP32 principal via UART serial.

## ⚙️ Configuración

Los parámetros están hardcodeados al inicio del archivo `src/main.cpp`:

```cpp
// WiFi del ELM327
#define ELM_SSID     "WiFi_OBDII"
#define ELM_PASS     ""
#define ELM_IP       IPAddress(192, 168, 0, 10)
#define ELM_PORT     35000

// UART hacia ESP32 Principal
#define UART_TX_PIN  20
#define UART_RX_PIN  21
#define UART_BAUD    460800

// Intervalos
#define SEND_INTERVAL_MS    200    // Enviar cada 200ms
#define DTC_INTERVAL_MS     300000 // DTCs cada 5 min
#define SCAN_INTERVAL_MS    600000 // Re-scan cada 10 min
```

## 📦 Compilación

```bash
cd firmware_c3
python -m platformio run
```

## 📤 Subir al C3

```bash
python -m platformio run -t upload
```

## 📡 Protocolo UART

### Mensajes de C3 → Principal

```json
// Datos OBD2
{"t":"DATA", "ts":12345, "pids":{"0x0C":5000, "0x0D":120, "BAT":13.8}, "dtc":[]}

// Estado OBD
{"t":"OBD_STATUS", "data":"ON", "ts":12345}

// DTCs borrados
{"t":"DTC_CLEARED", "data":"SUCCESS", "ts":12345}
```

### Comandos de Principal → C3

```json
// Habilitar/deshabilitar OBD
{"t":"OBD_ENABLE", "data":"1"}
{"t":"OBD_ENABLE", "data":"0"}

// Borrar códigos de error
{"t":"CLEAR_DTC", "data":"{}"}

// Forzar re-escaneo de PIDs
{"t":"SCAN", "data":"{}"}
```

## 📊 PIDs Soportados

| PID | Nombre | Descripción |
|-----|--------|-------------|
| 0x0C | RPM | Revoluciones motor |
| BAT | BATT_V | Voltaje batería |
| 0x04 | LOAD | Carga motor |
| 0x05 | COOLANT | Temperatura refrigerante |
| 0x0D | SPEED | Velocidad |
| 0x10 | MAF | Flujo aire masa |
| 0x0B | MAP | Presión manifold |
| 0x11 | THROTTLE | Posición acelerador |
| 0x2F | FUEL_LEVEL | Nivel combustible |
| 0x5E | FUEL_RATE | Tasa consumo |
| 0x0F | IAT | Temp aire admisión |
| 0x5C | OIL_TEMP | Temp aceite |
| 0x3C | CAT_TEMP | Temp catalizador |

## 🔧 Características

1. **Escaneo automático** de PIDs disponibles al inicio
2. **Filtro EMA** para suavizar lecturas
3. **Detección de outliers** para rechazar valores erróneos
4. **Reconexión automática** si pierde WiFi o ELM
5. **Control remoto** vía UART desde el ESP32 principal
6. **Lectura no bloqueante** usando patrón ELMduino

## 📝 Notas

- El C3 mantiene su propia conexión WiFi al ELM327
- El ESP32 principal usa WiFi para cloud/MQTT
- La comunicación UART es a 460800 baud (configurable)
- Los PIDs se leen secuencialmente, uno por iteración de loop

---

**Neurona Racing Development © 2024**
