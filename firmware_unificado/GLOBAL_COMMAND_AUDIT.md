# 📋 Auditoría Global de Comandos y Protocolos
*Neurona Off Road Telemetry - Firmware Unificado v3.2*

---

Este documento detalla exhaustivamente los protocolos de comunicación utilizados en todo el sistema.

## 1. Configurator (PC) ↔ ESP32 Main (Físico)
**Medio:** USB Serial (UART) @ 115200 baudios.
**Formato:** Comandos de texto ASCII terminados en `\n`.

### Comandos del Configurador al ESP32

| Comando | Parámetros | Descripción | Respuesta Esperada |
| :--- | :--- | :--- | :--- |
| `PING` | Ninguno | Test de conectividad | `RSP:PING:OK` (Legacy) o `ACK:PONG` (Actual) |
| `GET_CONFIG` | Ninguno | Solicita la configuración actual | `CONFIG:{json...}` |
| `SET_CONFIG:{json}` | JSON compacto (una línea) | Envía nueva configuración | `SET_CONFIG:OK` (seguido opcional de mensaje) |
| `SAVE_CONFIG` | Ninguno | Guarda la configuración en memoria Flash (NVS) | `SAVE_CONFIG:OK` o `Configuration saved` |
| `GET_STATUS` | Ninguno | Solicita estado del sistema (WiFi, Memoria, Uptime) | `STATUS:{json_status...}` |
| `GET_TELEMETRY` | Ninguno | Solicita snapshot inmediata de sensores | `TELEMETRY:{json_data...}` |
| `GET_SENSORS` | Ninguno | Solicita lista de sensores CAN configurados | `SENSORS:{json_array...}` |
| `SET_SENSORS:{json}` | JSON Array | Envía nueva lista de sensores CAN | `SET_SENSORS:OK` |
| `GET_DIAG` | Ninguno | Solicita info de diagnóstico (Chip, SDK, Heap) | `DIAG:{json...}` |
| `LIVE_ON` | Ninguno | Activa el stream de telemetría en vivo | `RSP:LIVE:OK:Live mode enabled` |
| `LIVE_OFF` | Ninguno | Desactiva el stream de telemetría | `RSP:LIVE:OK:Live mode disabled` |
| `REBOOT` | Ninguno | Reinicia el ESP32 | `RSP:REBOOT:OK` |
| `FACTORY_RESET` | Ninguno | Restaura valores de fábrica | `RSP:FACTORY_RESET:OK` |

### Respuestas del ESP32 al Configurador (Stream en Vivo)

Cuando `LIVE_ON` está activo, el ESP32 envía periódicamente:

*   **Formato:** JSON en una sola línea.
*   **Estructura:** `{"s": { "rpm": 3000, "temp": 90, ... }}`
*   **Clave "s":** Contiene todos los sensores (standard y custom).

---

## 2. ESP32 Main ↔ ESP32 C3 (OBD Bridge)
**Medio:** UART Física (RX=16, TX=17 en Main -> TX=20, RX=21 en C3) @ 460800 baudios.
**Formato:** JSON plano en una sola línea.

### Flujo de Datos (C3 → Main)

El C3 actúa como "esclavo inteligente", enviando datos proactivamente.

| Tipo (Key "t") | Data | Descripción |
| :--- | :--- | :--- |
| `DATA` | Objeto `pids` | Paquete de telemetría OBD. Ej: `{"t":"DATA", "pids": {"0x0C": 3000, ...}}` |
| `OBD_STATUS` | String | Estado de la conexión ELM/C3. Ej: `{"t":"OBD_STATUS", "data":"CONNECTED"}` |
| `DTC_CLEARED` | String | Resultado de borrado de códigos. Ej: `{"t":"DTC_CLEARED", "data":"OK"}` |

### Comandos de Control (Main → C3)

El Main puede comandar al C3 mediante JSONs simples.

| Tipo (Key "t") | Data | Acción en C3 |
| :--- | :--- | :--- |
| `OBD_ENABLE` | "1" o "0" | Activa/Desactiva el polleo OBD para ahorrar recursos/batería. |
| `CLEAR_DTC` | "{}" | Ordena borrar los códigos de error de la ECU del vehículo. |
| `SCAN` | Ninguno | Fuerza un re-escaneo de PIDs disponibles. |

### Mecanismo de Integridad
El C3 agrega un checksum simple al final de cada línea para que el Main valide la integridad:
`{"t":"DATA"...}#CS_HEX` (donde CS es checksum XOR).

---

## 3. Notas de Implementación

*   **Sin Prefijos:** Se ha eliminado el prefijo `CMD:` en el protocolo PC-Main para simplificar el parsing en C++.
*   **JSON Compacto:** Es crítico enviar los JSONs minificados (sin saltos de línea) para no saturar los buffers de UART (limitados a buffer de lectura serial habitual de 256-1024 bytes).
*   **Watchdogs:** Ambos firmwares implementan Watchdogs independientes, pero la comunicación es asíncrona ("fire and forget") para evitar bloqueos mutuos.
*   **Buffers:**
    *   Main Serial Buffer: 4096 bytes (para recibir Config JSON).
    *   C3 UART Buffer: 2048 bytes (para recibir respuestas ELM grandes).

---

*Auditoría generada automáticamente por asistente de IA el 2024-12-26.*
