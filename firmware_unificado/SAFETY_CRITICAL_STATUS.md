# PLAN SAFETY-CRITICAL - ESTADO DE IMPLEMENTACIÓN

**Fecha:** 20 diciembre 2025  
**Rama:** `feature/safety-critical`  
**Estado:** EN PROGRESO

---

## ✅ FASE 0 – PREPARACIÓN (COMPLETADA)

- [x] Repositorio git inicializado
- [x] Backup creado en commit inicial
- [x] Rama `feature/safety-critical` creada
- [x] Build congelado (solo correcciones críticas)

---

## ✅ FASE 1 – SUPERVIVENCIA DEL SISTEMA (COMPLETADA)

### P0.1 – Buffer Offline MQTT (RAM) ✅
- Creado `offline_buffer.h` y `offline_buffer.cpp`
- RingBuffer de 300 frames en RAM (~300KB)
- Si MQTT falla → push al buffer
- Al reconectar → drenar FIFO
- Si buffer lleno → sobrescribir más antiguo

**Archivos:**
- `firmware_unificado/src/cloud/offline_buffer.h`
- `firmware_unificado/src/cloud/offline_buffer.cpp`

### P0.2 – Red NO bloqueante (State Machine) ✅
- Implementada máquina de estados:
  ```
  DISCONNECTED → CONNECTING_WIFI → WIFI_OK → CONNECTING_MQTT → MQTT_OK
  ```
- WiFi y MQTT con timeouts agresivos (3s, 2s)
- Backoff exponencial (2s→60s WiFi, 1s→30s MQTT)
- No bloquea otras tareas

**Archivos:**
- `firmware_unificado/src/cloud/cloud_manager.h` (reescrito)
- `firmware_unificado/src/cloud/cloud_manager.cpp` (reescrito)

### P0.3 – Watchdog alineado a telemetría ✅
- WDT reducido de 10s a **5s** para reset rápido
- Cloud task **NO** alimenta el WDT (puede bloquearse sin panic)
- Solo tareas críticas (CAN) alimentan el WDT

**Archivos:**
- `firmware_unificado/src/main.cpp` (modificado)

### P0.4 – Timeouts agresivos en I/O ✅
- WiFi connect timeout: 3s
- MQTT connect timeout: 2s
- HTTP timeout: 2s
- Constantes definidas en `cloud_manager.h`

---

## ✅ FASE 2 – INTEGRIDAD DE DATOS (COMPLETADA)

### P1.1 – Timestamps y flags de validez ✅
- Añadidos timestamps por fuente en `TelemetrySnapshot`:
  - `ts_gps`, `ts_imu`, `ts_engine`, `ts_fuel`, `ts_battery`
- Flags de validez calculados en `getSnapshot()`:
  - `gps_valid`, `engine_valid`
- Threshold de stale: **2000ms**
- Setters actualizados para registrar timestamps

**Archivos:**
- `firmware_unificado/src/telemetry/telemetry_bus.h` (modificado)
- `firmware_unificado/src/telemetry/telemetry_bus.cpp` (modificado)

### P1.2 – Validación dura de configuración (Anti-brick) ✅
- Implementada función `validateConfig()`:
  - Valida pines CAN (rango, pines reservados)
  - Valida baudrate CAN (250/500/1000)
  - Valida cristal CAN (8/16 MHz)
  - Valida pines GPS e IMU
  - Valida cloud interval (50-60000ms)
  - Valida número de sensores (max 50)
  - Valida puerto MQTT

**Archivos:**
- `firmware_unificado/src/config/config_manager.h` (modificado)
- `firmware_unificado/src/config/config_manager.cpp` (modificado)

### P1.3 – Protección mínima CAN flood ✅
- Máximo 10 tramas por ciclo (ya existía)
- `yield()` después del lote
- Contadores añadidos:
- `_framesDiscarded` (frames perdidos por límite)
- `_errorCount` (errores de bus)
- `_maxFramesPerCycle` (diagnóstico de flood)

**Archivos:**
- `firmware_unificado/src/sources/source_can.h` (modificado)
- `firmware_unificado/src/sources/source_can.cpp` (modificado)

---

## ✅ FASE 2.5 – CORRECCIONES AUDITORÍA V2 (COMPLETADA)

### P0.5 – Resiliencia Cloud con WDT ✅
- Cloud task ahora registrada en Watchdog (5s)
- Protege contra bloqueos de stack TCP/IP o librerías zombies
- Reinicio automático si la tarea se cuelga

### P0.6 – Recuperación de Bus I2C (Anti-Freeze) ✅
- Implementado `performBusRecovery()` en `SourceIMU`
- Secuencia de 9-clocks manual para liberar esclavos I2C bloqueados
- Previene pérdida permanente de IMU por ruido/vibración

### P1.5 – Diagnostic LEDs (4-System Layout) ✅
- Implementado controlador `StatusLed` (non-blocking)
- **LED WIFI (GPIO 25):** Conectividad Capa 1/2 (Fijo=OK, Blink=Buscando)
- **LED CLOUD (GPIO 26):** Conectividad Capa 3/4 (MQTT)
- **LED CAN (GPIO 27):** Actividad real en Bus CAN (Flash con RPM)
- **LED OBD (GPIO 14):** Actividad ECU/OBD (Flash con datos)
- Secuencia de inicio "Knight Rider" para test de hardware

---

## ⏳ FASE 3 – ESTABILIDAD A LARGO PLAZO (PENDIENTE)

### P2.1 – Control de heap
- [ ] Limitar `custom_values` (cap fijo)
- [ ] Evitar crecimiento dinámico sin control
- [ ] No introducir nuevas `String` en loop

### P2.2 – Aislamiento de HTTP (backup)
- [ ] HTTP solo si MQTT falló X veces
- [ ] HTTP solo si buffer offline > 50%
- [ ] Ejecutar fuera del flujo principal MQTT

---

## 📋 RESUMEN DE CAMBIOS

| Archivo | Tipo | Descripción |
|---------|------|-------------|
| `cloud/offline_buffer.h` | **NUEVO** | Buffer offline en RAM |
| `cloud/offline_buffer.cpp` | **NUEVO** | Implementación RingBuffer |
| `cloud/cloud_manager.h` | REESCRITO | State Machine, backoff, timeouts |
| `cloud/cloud_manager.cpp` | REESCRITO | Implementación resiliente |
| `telemetry/telemetry_bus.h` | MODIFICADO | Timestamps y flags validez |
| `telemetry/telemetry_bus.cpp` | MODIFICADO | Setters con timestamps, stale detection |
| `config/config_manager.h` | MODIFICADO | Declaración validateConfig |
| `config/config_manager.cpp` | MODIFICADO | Implementación validateConfig |
| `sources/source_can.h` | MODIFICADO | Estadísticas flood |
| `sources/source_can.cpp` | MODIFICADO | Protección flood, yield |
| `main.cpp` | MODIFICADO | WDT 5s |

---

## ⚠️ NOTA DE COMPILACIÓN

La compilación puede fallar con error de permisos en `esptool`:
```
PermissionError: [Errno 13] Permission denied: '.platformio\packages\tool-esptoolpy\_contrib\intelhex\__init__.py'
```

**Solución:** Cerrar cualquier proceso que use PlatformIO, reiniciar terminal, o ejecutar como administrador.

El código fuente compila correctamente (todos los `.cpp.o` se generan).

---

## 🎯 CRITERIO DE GO/NO-GO

| Criterio | Estado |
|----------|--------|
| Sistema sobrevive 30-60s sin red | ✅ Implementado (buffer offline) |
| No se bloquea bajo CAN flood | ✅ Implementado (límite + yield) |
| No envía datos stale como válidos | ✅ Implementado (flags validez) |
| Watchdog resetea rápido y limpio | ✅ 5s timeout |

---

## 📌 PRÓXIMOS PASOS

1. Resolver problema de permisos de PlatformIO
2. Compilar y subir firmware al ESP32
3. Test de supervivencia sin red
4. Test de CAN flood
5. Implementar FASE 3 (si hay tiempo)
