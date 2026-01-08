# BITÁCORA DE DESARROLLO (MASTER LOG)
**Proyecto:** Neurona Off Road Telemetry  
**Control de Versión de Configurator**

---

## 📊 Registro de Cambios

| Fecha | ID | Módulo Afectado | Descripción Técnica | Estado |
|-------|----|-----------------|---------------------|--------|
| 2024-12-23 | 001 | Arquitectura | Auditoría profunda del sistema. Diagnóstico de deuda técnica en `main.py` y diseño de nueva estructura modular. | ✅ COMPLETADO |
| 2024-12-23 | 002 | UI/UX | Diseño de estrategia de UI Reactiva para filtrado de opciones según Source Mode. | ✅ COMPLETADO |
| 2024-12-23 | 003 | Core | Refactorización de `json_generator.py` hacia un motor de modelos basado en objetos. | 📋 PENDIENTE |
| 2024-12-23 | 004 | Serial | Implementación de sistema de Batch Processing para ráfagas de telemetría de alta velocidad. | 📋 PENDIENTE |
| 2024-12-23 | 005 | UI/Codebase | Implementación completa de Arquitectura Modular (Clean Architecture) y separación de componentes. | ✅ COMPLETADO |
| 2024-12-23 | 006 | UI/UX | **RELEASE CANDIDATE 1.0** - Implementación de UI Reactiva, Tema Dark Racing, JSON Hardening. | ✅ COMPLETADO |
| 2024-12-23 | 007 | Arquitectura | **PURGA CRÍTICA** - Reducción de 5 a 3 modos. Eliminados OBD_DIRECT y CAN_OBD. Nueva sección bridge_wifi. | ✅ COMPLETADO |
| 2024-12-23 | 008 | Documentación | **AUDITORÍA PAYLOADS** - Generado PAYLOAD_AUDIT.md. Clarificación CONFIG vs UART vs CLOUD payloads. | ✅ COMPLETADO |
| 2024-12-26 | 009 | Firmware/Config | **FIX MQTT REAL-TIME** - Removido checksum C3, reducido latencia cloud, botón Factory Reset, GET_DIAG mejorado. | ✅ COMPLETADO |
| 2024-12-26 | 010 | Firmware/Cloud | **FIX CRITICAL: getLocalTime() BLOCKING** - `getLocalTime()` tenía timeout default de 5000ms. Cambiado a 10ms. MQTT ahora envía a 100ms real. | ✅ COMPLETADO |
| 2024-12-26 | 011 | Firmware/C3 | **ROBUSTEZ CONEXIÓN C3↔ELM327** - Heartbeat cada 2s, reconexión más rápida, WiFi más robusto, timeout reducido, fix bug doble llamada. | ✅ COMPLETADO |
| 2024-12-26 | 012 | Firmware/C3 | **OPTIMIZACIÓN TIMING HEARTBEAT** - Desacoplado Heartbeat (1s) de Verificación (2s). Mejora margen seguridad vs Timeout Principal (3s). | ✅ COMPLETADO |
| 2024-12-26 | 013 | Firmware/C3 | **FIX BLOQUEO C3** - Inyectado `serviceHeartbeat()` en loops bloqueantes (WiFi, ELM, Scan) y reducido timeout ELM init (5s->2.5s). Evita desconexión durante recuperación. | ✅ COMPLETADO |
| 2024-12-26 | 014 | Firmware/C3+Main | **ROBUSTEZ CRÍTICA** - Eliminados bloqueos largos: timeouts reducidos (800→500ms), ESP.restart() eliminado, escaneo en 2 fases, timeout Principal 3s→4s. | ✅ COMPLETADO |
| 2024-12-26 | 015 | Firmware/Main | **FIX FALSOS TIMEOUTS** - Heartbeat (OBD_STATUS) ahora actualiza `_lastReceiveTime` para evitar desconexiones falsas cuando C3 está ocupado. | ✅ COMPLETADO |
| 2024-12-26 | 016 | Firmware/C3 | **FIX PÉRDIDA DATA** - Escaneo oportunista: 2s→10s, timeout 300ms, logging de ELM ocupado. Evita pausas de 3-4s sin DATA. | ✅ COMPLETADO |
| 2024-12-26 | 017 | Firmware/C3 | **FIX SATURACIÓN ELM** - Throttle 80ms mínimo entre peticiones de PIDs. Evita SEARCHING/TIMEOUT por saturar al ELM327. | ✅ COMPLETADO |
| 2024-12-26 | 018 | Firmware/C3 | **FIX VALORES CONGELADOS** - Corregido throttle que impedía procesar respuestas. Ahora sigue llamando mientras espera respuesta. | ✅ COMPLETADO |
| 2024-12-26 | 019 | Firmware/C3 | **REDUCCIÓN RE-ESCANEO** - Escaneo agresivo: 30s→2min, período 5min→2min, skip si >=4 PIDs. Menos interrupciones. | ✅ COMPLETADO |

---

## 🚀 Entrada 006: Release Candidate 1.0 - Refactor Mayor

### Resumen
Se ha completado el refactor integral del configurador para llevarlo a nivel de producción. El objetivo era implementar los 3 pilares: **Usabilidad**, **Solidez** y **Modernidad**.

### Archivos Creados/Modificados

| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `assets/dark_racing.qss` | ✨ NUEVO | Hoja de estilos profesional "Dark Racing Theme" con 400+ líneas de CSS |
| `ui/main_window.py` | 🔄 REESCRITO | Implementación completa de MainWindow con UI reactiva (800+ líneas) |
| `ui/tabs/device_tab.py` | 🔧 MODIFICADO | Añadido modo "SENSORS_ONLY" y emojis en opciones |
| `main_refactored.py` | 🔧 MODIFICADO | Punto de entrada limpio con metadatos de aplicación |

---

### 1️⃣ Visual & Theming (Dark Racing Theme)

**Archivo:** `assets/dark_racing.qss`

**Características implementadas:**
- 🎨 Paleta de colores profesional:
  - Primary Background: `#1a1a1a` (Deep Carbon)
  - Accent Orange: `#ff6b35` (Racing Orange)
  - Success Green: `#00e676`
  - Danger Red: `#ff5252`
  - Info Blue: `#2196f3`

- 📐 Componentes estilizados:
  - QTabWidget con tabs redondeados y highlight naranja
  - QPushButton con variantes (primary, success, danger, warning, accent, ghost)
  - QTableWidget con headers estilizados y bordes naranja
  - QGroupBox como "Cards" con títulos de color
  - QScrollBar personalizado con hover en naranja
  - QTextEdit/Console con estilo terminal verde

- 🔘 Botones con gradientes:
  ```css
  QPushButton[variant="success"] {
      background: qlineargradient(x1:0, y1:0, x2:0, y2:1, 
          stop:0 #00e676, stop:1 #00c853);
  }
  ```

---

### 2️⃣ Lógica de UI Reactiva (The Switch)

**Archivo:** `ui/main_window.py`

**Función clave:** `update_ui_for_mode(mode: str)`

**Comportamiento implementado:**

| Modo | Tab Sensores | Tab OBD | Botones CAN | Badge Status |
|------|--------------|---------|-------------|--------------|
| CAN_ONLY | ✅ Visible | ❌ Oculto | ✅ Visible | 🏎️ MODO: CAN (naranja) |
| OBD_BRIDGE | ❌ Oculto | ✅ Visible | ❌ Oculto | 🔌 MODO: OBD BRIDGE (azul) |
| OBD_DIRECT | ❌ Oculto | ✅ Visible | ❌ Oculto | 📡 MODO: OBD DIRECTO (púrpura) |
| CAN_OBD | ✅ Visible | ✅ Visible | ✅ Visible | ⚡ MODO: HÍBRIDO (verde) |
| SENSORS_ONLY | ❌ Oculto | ❌ Oculto | ❌ Oculto | 📍 MODO: TRACKING (amarillo) |

**Banner de Estado:**
```
┌─────────────────────────────────────────────────────────────────┐
│ 🏎️ MODO: CAN │ 🟢 CONECTADO │ OBD: ✓ │ Puerto: COM3 │ FW: 3.1 │
└─────────────────────────────────────────────────────────────────┘
```

**Sincronización automática:**
- Al seleccionar OBD_BRIDGE → `obd.mode` se fuerza a "c3_bridge"
- Al seleccionar OBD_DIRECT → `obd.mode` se fuerza a "direct"
- Controles bloqueados cuando el modo los controla

---

### 3️⃣ Generación de JSON (Hardening)

**Función clave:** `get_config_data() -> Dict[str, Any]`

**Patrón implementado: Filtrado Agresivo**

```python
MODE_ALLOWED_SECTIONS = {
    "CAN_ONLY": {"device", "wifi", "cloud", "serial", "can", "gps", "imu", "sensors"},
    "OBD_BRIDGE": {"device", "wifi", "cloud", "serial", "obd", "gps", "imu", "fuel"},
    "OBD_DIRECT": {"device", "wifi", "cloud", "serial", "obd", "gps", "imu", "fuel"},
    "CAN_OBD": {"device", "wifi", "cloud", "serial", "can", "obd", "gps", "imu", "fuel", "sensors"},
    "SENSORS_ONLY": {"device", "wifi", "cloud", "serial", "gps", "imu"},
}
```

**Antes (JSON con datos zombie):**
```json
{
  "device": {...},
  "can": {...},         // ❌ Innecesario si modo es OBD
  "obd": {...},
  "sensors": [...]      // ❌ Innecesario si modo es OBD
}
```

**Después (JSON limpio):**
```json
{
  "version": "3.1",
  "device": {...},
  "obd": {...},
  "fuel": {...}
  // Solo secciones permitidas para el modo
}
```

---

### 4️⃣ Mejoras Adicionales

**Nuevas funcionalidades:**
- ✅ `preview_payload()` - Visualizador de JSON con estadísticas
- ✅ Auto-request de configuración al conectar
- ✅ Validación de tamaño de config (máx 4KB)
- ✅ OBD status badge dinámico (verde/rojo)
- ✅ Soporte para modo SENSORS_ONLY (Tracking)
- ✅ Fallback de tema si QSS no disponible

**Señales implementadas:**
- `DeviceTab.source_changed(str)` → `MainWindow.on_source_changed()`
- `AppController.source_mode_changed(str)` → `MainWindow.on_controller_mode_changed()`

---

### 📋 Testing Recomendado

```bash
# Ejecutar configurador refactorizado
cd configurator
python main_refactored.py

# Verificar cambio de modos
1. Ir a tab "Dispositivo"
2. Cambiar "Modo de Operación"
3. Observar ocultación dinámica de tabs

# Verificar JSON limpio
1. Seleccionar modo "OBD Bridge"
2. Click "Ver Payload"
3. Confirmar que NO hay sección "can" ni "sensors"
```

---

## 📁 Estructura Final del Proyecto

```
configurator/
├── main_refactored.py          # ✨ Punto de entrada (limpio)
├── main.py                      # Legacy (para compatibilidad)
├── MASTER_LOG.md               # 📝 Esta bitácora
├── assets/
│   └── dark_racing.qss         # ✨ Tema profesional
├── core/
│   ├── app_controller.py       # Controlador central
│   ├── constants.py            # Constantes globales
│   └── models.py               # Modelos de datos
├── ui/
│   ├── main_window.py          # ✨ Ventana principal (reescrita)
│   ├── tabs/
│   │   ├── can_tab.py          # Tab de sensores CAN
│   │   ├── device_tab.py       # 🔧 Tab de dispositivo (modificado)
│   │   ├── cloud_tab.py        # Tab de configuración cloud
│   │   ├── obd_tab.py          # Tab de OBD
│   │   └── live_tab.py         # Tab de datos en vivo
│   └── widgets/
│       └── console.py          # Widget de consola
├── serial_manager.py           # Gestión de puerto serial
├── serial_worker.py            # Worker thread para serial
├── json_generator.py           # Generador de JSON
├── dbc_parser.py               # Parser de archivos DBC
└── xml_loader.py               # Cargador de XML MoTeC
```

---

## 🎯 Próximos Pasos (Roadmap)

| Prioridad | Tarea | Descripción |
|-----------|-------|-------------|
| 🔴 Alta | Testing E2E | Probar todos los modos con hardware real |
| 🟡 Media | Refactor json_generator.py | Usar modelos tipados (Pydantic) |
| 🟡 Media | Validación de Pines | Detectar colisiones CAN/GPS/UART |
| 🟢 Baja | Internacionalización | Soporte para inglés/español |
| 🟢 Baja | Empaquetado | Crear instalador con PyInstaller |

---

---

*Última actualización: 2024-12-26*  
*Autor: Gemini Engineering*  
*Versión del documento: 3.0*

---

## 🚀 Entrada 009: Fix MQTT Real-Time y Diagnóstico Mejorado
**Fecha:** 2024-12-26  
**Sesión:** Debugging de latencia MQTT en firmware unificado

### 🎯 Problema Identificado
El firmware unificado no estaba enviando datos MQTT en tiempo real (tardaba ~5 segundos entre envíos en lugar de 100ms). Se identificaron múltiples causas raíz tras comparar con los prototipos funcionales.

---

### 📋 Cambios Realizados

| # | Archivo | Cambio | Impacto |
|---|---------|--------|---------|
| 1 | `firmware_c3/src/main.cpp` | Removido checksum (#XX) del envío UART | 🔴 CRÍTICO |
| 2 | `firmware_main/cloud/cloud_manager.cpp` | Reducido `vTaskDelay` de 10ms a 1ms | 🟡 ALTO |
| 3 | `firmware_main/cloud/cloud_manager.cpp` | Habilitado logging completo (todos los TX) | 🟢 DIAGNÓSTICO |
| 4 | `firmware_main/serial/serial_manager.cpp` | GET_DIAG ahora muestra `cloud_interval_ms` | 🟡 ALTO |
| 5 | `configurator/ui/main_window.py` | Corregido bug `tab_device` → `tab_cloud` | 🟡 ALTO |
| 6 | `configurator/ui/main_window.py` | Añadido botón **"🔄 Reset"** (Factory Reset) | 🟢 MEJORA |

---

### 🔬 FIX 1: Incompatibilidad de Checksum C3 → Main

**Problema:** El firmware_c3 unificado enviaba datos con checksum:
```cpp
MainSerial.print(output);
MainSerial.print("#");
MainSerial.println(String(cs, HEX));  // → "{...json...}#A5"
```

Pero `source_obd_bridge.cpp` esperaba JSON puro, causando errores de parsing.

**Solución:** Remover checksum en `enviarDatos()` y `enviarMensaje()`:
```cpp
MainSerial.println(output);  // JSON puro sin #XX
```

---

### ⚡ FIX 2: Reducción de Latencia CloudTask

**Problema:** El `vTaskDelay(10ms)` después de cada ciclo de envío agregaba latencia acumulativa.

**Solución:**
```cpp
// ANTES
vTaskDelay(pdMS_TO_TICKS(10));

// DESPUÉS
vTaskDelay(pdMS_TO_TICKS(1));  // Mínimo yield
```

---

### 📊 FIX 3: Logging Completo para Diagnóstico

**Problema:** Solo se logueaba 1 de cada 10 envíos MQTT, ocultando problemas intermitentes.

**Solución:**
```cpp
// ANTES
bool logThisTime = (sendCount % 10 == 1);

// DESPUÉS  
bool logThisTime = true;  // TODOS los envíos (temporal para debug)
```

---

### 🔧 FIX 4: GET_DIAG Mejorado

**Problema:** El comando GET_DIAG no mostraba la configuración crítica como `cloud_interval_ms`.

**Solución:** Añadido bloque `config` al JSON de diagnóstico:
```json
{
  "chip_model": "ESP32",
  "heap_free": 170000,
  "config": {
    "source": "OBD_BRIDGE",
    "cloud_interval_ms": 100,
    "serial_interval_ms": 30,
    "protocol": "MQTT",
    "debug_mode": false,
    "obd": {"enabled": true, "mode": "bridge", "poll_interval_ms": 200},
    "can": {"enabled": false, "baud_kbps": 500},
    "gps_enabled": true,
    "imu_enabled": true
  }
}
```

---

### 🖱️ FIX 5 y 6: Configurador - Bug Fix y Factory Reset

**Bug corregido:**
```python
# ANTES (error)
self.tab_device.input_cloud_interval.setText(...)

# DESPUÉS (correcto)
self.tab_cloud.input_cloud_interval.setText(...)
```

**Nuevo botón Factory Reset:**
- Ubicación: Barra de acciones inferior
- Icono: "🔄 Reset"
- Función: Envía `FACTORY_RESET` al ESP32
- Confirmación: Diálogo con descripción de cambios

---

### 📝 Diagnóstico del Problema Original

El análisis del log reveló que el **intervalo real de envío MQTT era ~5 segundos** aunque la configuración decía 100ms. Esto se debía a que el valor `cloud_interval_ms` guardado en la **FLASH del ESP32** era diferente al default.

```
[18:03:45] MQTT TX #12
[18:03:50] MQTT TX #13   ← 5 segundos (debería ser 100ms)
[18:03:55] MQTT TX #14   ← 5 segundos
```

**Solución:** Usar el botón Factory Reset o cambiar manualmente el interval en el configurador.

---

### ✅ Estado Post-Fix

| Componente | Estado |
|------------|--------|
| C3 → Main (UART) | ✅ JSON puro parseable |
| CloudTask Latency | ✅ Reducida de 10ms a 1ms |
| GET_DIAG | ✅ Muestra cloud_interval_ms |
| Configurador | ✅ Bug corregido + Reset button |

---

### 📋 Pendiente para Usuario

1. **Recompilar y subir `firmware_main`** al ESP32 Principal ✅
2. **Recompilar y subir `firmware_c3`** al ESP32-C3 ✅
3. Usar **GET_DIAG** para verificar `cloud_interval_ms` ✅
4. Si es >100ms, usar **Factory Reset** o cambiar en configurador ✅

---

## 🚀 Entrada 010: FIX CRÍTICO - getLocalTime() Blocking (5 segundos)
**Fecha:** 2024-12-26  
**Sesión:** Resolución definitiva del delay de 5 segundos en MQTT

### 🎯 Problema Identificado

El firmware enviaba MQTT cada **~5 segundos** en lugar de los **100ms** configurados. Los logs mostraban:

```
[CLOUD] 📡 MQTT TX #10 (OBD_BRIDGE) - OK @ 100ms interval
[CLOUD] 📡 MQTT TX #11 (OBD_BRIDGE) - OK @ 100ms interval  ← 5 segundos después
```

A pesar de que `GET_DIAG` confirmaba `cloud_interval_ms: 100`.

---

### 🔬 Proceso de Diagnóstico

#### Paso 1: Agregar Instrumentación
Se modificó `taskLoop()` en `cloud_manager.cpp` para medir tiempos por etapa:

```cpp
// DIAGNÓSTICO: Medir tiempo de buildPayload
uint32_t t2 = millis();
String payload = buildPayload();
uint32_t buildTime = millis() - t2;

// DIAGNÓSTICO: Medir tiempo de sendMqtt
uint32_t t3 = millis();
success = sendMqtt(payload);
uint32_t sendTime = millis() - t3;

Serial.printf("[CLOUD] 📡 MQTT TX #%lu - OK (%d bytes, elapsed=%lums, build=%lums, send=%lums)\n",
    sendCount, payload.length(), elapsed, buildTime, sendTime);
```

#### Paso 2: Identificar el Culpable
El log de diagnóstico reveló:

```
[CLOUD] 📡 MQTT TX #13 - OK (197 bytes, elapsed=5030ms, build=5012ms, send=5ms)
```

| Métrica | Valor | Interpretación |
|---------|-------|----------------|
| `elapsed` | 5030ms | Tiempo total entre envíos |
| **`build`** | **5012ms** | ⚠️ **¡buildPayload() tardaba 5 segundos!** |
| `send` | 5ms | El envío MQTT estaba bien |

#### Paso 3: Localizar el Bug
Revisando `buildPayload()`, encontramos en línea ~538:

```cpp
// PROBLEMA: getLocalTime() tiene timeout DEFAULT de 5000ms
struct tm timeinfo;
if (getLocalTime(&timeinfo)) {  // ← Bloqueaba 5 segundos si NTP no sincronizado
    strftime(dt_buffer, sizeof(dt_buffer), "%Y-%m-%d %H:%M:%S", &timeinfo);
}
```

---

### 🔧 Causa Raíz

La función `getLocalTime()` del ESP32 tiene la siguiente firma:

```cpp
bool getLocalTime(struct tm * info, uint32_t ms = 5000);
```

El segundo parámetro `ms` es el **timeout máximo** que espera para obtener la hora del sistema. **Por defecto es 5000ms (5 segundos)**.

Si el NTP no está sincronizado (o tarda en responder), la función bloquea el hilo completo durante **5 segundos** antes de retornar `false`.

---

### ✅ Solución Implementada

**Archivo:** `firmware_main/cloud/cloud_manager.cpp`  
**Línea:** ~538

```cpp
// ANTES (bloqueaba 5 segundos si no hay NTP)
if (getLocalTime(&timeinfo)) {

// DESPUÉS (máximo 10ms de espera)
if (getLocalTime(&timeinfo, 10)) {  // 10ms timeout
```

Con timeout de 10ms:
- Si la hora está disponible → retorna inmediatamente
- Si no está disponible → retorna `false` después de 10ms
- El timestamp será `1970-01-01 00:00:00` hasta que NTP sincronice

---

### 📊 Resultados Post-Fix

#### Log ANTES del fix:
```
[CLOUD] 📡 MQTT TX #13 - OK (197 bytes, elapsed=5030ms, build=5012ms, send=5ms)
[CLOUD] 📡 MQTT TX #14 - OK (197 bytes, elapsed=5029ms, build=5012ms, send=5ms)
```

#### Log DESPUÉS del fix:
```
[CLOUD] 📡 MQTT TX #53 - OK (197 bytes, elapsed=100ms, build=22ms, send=1ms)
[CLOUD] 📡 MQTT TX #54 - OK (197 bytes, elapsed=100ms, build=22ms, send=2ms)
```

#### Comparación de Métricas:

| Métrica | ANTES | DESPUÉS | Mejora |
|---------|-------|---------|--------|
| `elapsed` | 5000ms | **100ms** | **50x más rápido** |
| `build` | 5012ms | **22ms** | **227x más rápido** |
| `send` | 5ms | 1-2ms | OK |
| **Tasa TX** | 0.2 Hz | **10 Hz** | **Real-time!** |
| Loop overhead | 5017ms | **0ms** | **100% eliminado** |

---

### 📁 Archivos Modificados

| Archivo | Cambio |
|---------|--------|
| `cloud_manager.cpp` | Timeout de `getLocalTime()` cambiado de 5000ms a 10ms |
| `cloud_manager.cpp` | Añadido diagnóstico de tiempos (build, send, elapsed) |
| `cloud_manager.cpp` | Recálculo de `now` después de `updateNetworkState()` |

---

### 🧠 Lecciones Aprendidas

1. **Siempre revisar defaults de funciones** - `getLocalTime()` tiene un timeout de 5 segundos por defecto que no es documentado de forma prominente.

2. **Instrumentar antes de asumir** - Sin medir los tiempos por etapa, habríamos asumido que el problema era en la red o en MQTT.

3. **NTP puede no estar disponible** - En sistemas embebidos, no se puede asumir conectividad constante a servidores NTP.

4. **Timeouts agresivos para sistemas real-time** - En telemetría de carreras, es preferible tener un timestamp incorrecto que perder 5 segundos de datos.

---

### ✅ Estado Final

| Componente | Estado |
|------------|--------|
| MQTT @ 100ms | ✅ Funcionando a 10Hz |
| OBD Bridge | ✅ C3=OK, PIDs=4 |
| Cloud Loop | ✅ 0ms overhead |
| Build Payload | ✅ ~22ms (JSON serialization) |
| Send MQTT | ✅ ~1-2ms |

---

## 🚀 Entrada 011: Robustez Conexión ESP32 ↔ C3 ↔ ELM327
**Fecha:** 2024-12-26  
**Sesión:** Mejorar estabilidad y velocidad de reconexión de la cadena completa

### 🎯 Problema Identificado

Se observaban desconexiones intermitentes entre:
- ESP32-C3 ↔ ELM327 (WiFi)
- ESP32 Principal ↔ ESP32-C3 (UART)

Los LEDs mostraban parpadeos y los logs mostraban pausas en el flujo de datos.

---

### 🔧 Cambios Realizados

#### 1. **Bug Fix: Doble llamada a `verificarConexiones()`** (firmware_c3)

```cpp
// ANTES (BUG - se llamaba DOS veces!)
if (ahora - ultimaVerificacion > 5000) {
    ultimaVerificacion = ahora;
    verificarConexiones();
    verificarConexiones();  // ← DUPLICADO
}

// DESPUÉS (Correcto)
if (ahora - ultimaVerificacion > 2000) {
    ultimaVerificacion = ahora;
    verificarConexiones();  // Solo una vez
}
```

#### 2. **Heartbeat C3 → Principal cada 2 segundos**

El C3 ahora envía un mensaje `OBD_STATUS` cada 2 segundos al ESP32 Principal:

```cpp
// En el loop, cada 2 segundos
if (elmConectado && obdEnabled) {
    enviarMensaje("OBD_STATUS", "CONNECTED");
} else if (!elmConectado) {
    enviarMensaje("OBD_STATUS", "DISCONNECTED");
}
```

Esto permite al Principal detectar desconexiones más rápido.

#### 3. **Mejoras en `conectarWiFi()`**

| Parámetro | Antes | Después |
|-----------|-------|---------|
| Intentos máximos | 20 (10s) | 30 (15s) |
| Reset WiFi previo | No | Sí (`disconnect(true)` + `WIFI_OFF`) |
| Auto-reconexión | No | `WiFi.setAutoReconnect(true)` |
| Diagnóstico | `.` cada intento | Status code cada 10 intentos |
| Log conexión | IP | IP + RSSI |

#### 4. **Mejoras en `verificarConexiones()`**

| Parámetro | Antes | Después |
|-----------|-------|---------|
| Intervalo chequeo | 5000ms | 2000ms |
| Fallos antes de reconexión completa | 3 | 2 |
| Socket timeout para reconexión rápida | N/A | 1000ms |
| Limpieza de socket antes de reconectar | No | `elmClient.stop()` |
| Contadores de reconexiones | No | Sí (para diagnóstico) |

#### 5. **Reducción de timeout en ESP32 Principal**

```cpp
// source_obd_bridge.h
#define OBD_BRIDGE_TIMEOUT_MS 3000  // Reducido de 5000ms
```

---

### 📊 Comparación de Tiempos de Detección

| Escenario | Antes | Después |
|-----------|-------|---------|
| Detección desconexión WiFi | 5-15s | 2-4s |
| Reconexión ELM tras fallo | 15s (3 fallos × 5s) | 4s (2 fallos × 2s) |
| Heartbeat al Principal | Solo con DATA | Cada 2s |
| Timeout Principal para detectar pérdida C3 | 5s | 3s |

---

### 📁 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `firmware_c3/src/main.cpp` | Fix bug doble llamada, heartbeat, WiFi robusto, verificación más rápida |
| `firmware_main/sources/source_obd_bridge.h` | Timeout reducido de 5s a 3s |

---

### ✅ Resultado Esperado

1. **Reconexión automática más rápida** - De ~15s a ~4s
2. **Detección temprana de problemas** - Heartbeat cada 2s
3. **Menos interrupciones en telemetría** - WiFi más estable
4. **Mejor diagnóstico** - Logs con contadores de reconexiones y RSSI

---

## 🚀 Entrada 014: ROBUSTEZ CRÍTICA - Eliminación de Bloqueos Largos
**Fecha:** 2024-12-26  
**Sesión:** Resolución definitiva de desconexión C3 ↔ Principal durante operaciones ELM

### 🎯 Problema Identificado

La conexión entre ESP32 Principal y ESP32-C3 se **rompía después de algunos segundos** cuando el C3 entraba en operaciones bloqueantes:
- Escaneo inicial de PIDs (~14 PIDs × 800ms = 11+ segundos)
- Reconexión fallida al ELM327 (`delay(10000) + ESP.restart()`)
- Inicialización del ELM327 (`elm.begin()` con timeout de 2500ms)

El timeout del Principal (3s) detectaba **falsa desconexión** mientras el C3 estaba ocupado.

---

### 🔧 Cambios Realizados

#### 1. **Reducción de Timeouts en C3** (firmware_c3/main.cpp)

| Función | Antes | Después | Impacto |
|---------|-------|---------|---------|
| `queryPIDBlocking()` timeout | 800ms | **500ms** | 37% más rápido |
| `queryPIDBlocking()` delay interno | 10ms | **5ms** | 50% más responsivo |
| `elm.begin()` timeout | 2500ms | **1500ms** | 40% más rápido |
| Escaneo PID timeout | 700ms | **400ms** | 43% más rápido |
| Delay post-comando ELM | 100ms | **50ms** | 50% más rápido |
| Delay reset AT Z | 2000ms | **800ms** | 60% más rápido |

#### 2. **Eliminación de ESP.restart() y delay(10000)** (CRÍTICO)

```cpp
// ANTES (BLOQUEABA 10s + REINICIO!)
if (!conectado) {
    Serial.println("[ELM] FALLO TOTAL - Reiniciando en 10s...");
    delay(10000);
    ESP.restart();
}

// DESPUÉS (Reconexión gradual)
if (!conectado) {
    Serial.println("[ELM] Fallo conexión - se reintentará en próximo ciclo");
    elmConectado = false;
    return;
}
```

#### 3. **Escaneo de PIDs en 2 Fases**

```
Fase 1: Solo PIDs BASE (RPM, Carga, Temp, Batería)
        → Arranque rápido en ~2 segundos
        
Fase 2: PIDs EXTRA (opcionales)
        → Solo si Fase 1 tuvo éxito
        → Con heartbeat cada 3 PIDs
```

#### 4. **Heartbeat más agresivo durante bloqueos**

```cpp
// Antes: serviceHeartbeat() al final de cada PID
// Después: serviceHeartbeat() ANTES y DESPUÉS de operaciones críticas
serviceHeartbeat(); // ANTES de cada intento de conexión
if (!elm.begin(...)) {
    serviceHeartbeat(); // En caso de fallo
}
```

#### 5. **Timeout del ESP32 Principal aumentado**

```cpp
// source_obd_bridge.h
#define OBD_BRIDGE_TIMEOUT_MS 4000  // Aumentado de 3s a 4s
```

---

### 📊 Comparación de Tiempos

| Operación | Tiempo ANTES | Tiempo DESPUÉS |
|-----------|--------------|----------------|
| Escaneo inicial (14 PIDs) | ~11 segundos | **~4 segundos** |
| Reconexión ELM fallida | 10s + reinicio | **~2 segundos** |
| `conectarELM()` completo | ~8 segundos | **~3 segundos** |
| Margen heartbeat vs timeout | 3s vs 2s = **NEGATIVO** | 4s vs 1s = **+3s margen** |

---

### 📁 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `firmware_c3/src/main.cpp` | Reducción timeouts, eliminación restart, escaneo en fases, más heartbeat |
| `firmware_main/sources/source_obd_bridge.h` | Timeout aumentado de 3s a 4s |

---

### ✅ Resultado Esperado

1. **Conexión estable** - El C3 nunca bloquea más de ~1.5s sin enviar heartbeat
2. **Arranque rápido** - Solo los 4 PIDs base se escanean primero
3. **Reconexión sin reinicio** - El sistema se recupera sin perder datos
4. **Margen de seguridad +3s** - Heartbeat cada 1s vs timeout 4s

---

### 📋 Pendiente para Usuario

1. **Recompilar y subir `firmware_c3`** al ESP32-C3
2. **Recompilar y subir `firmware_main`** al ESP32 Principal
3. **Probar** desconectando/reconectando el ELM327 para verificar estabilidad
4. **Monitorear** los logs del C3 para confirmar arranque en <5 segundos

---

*Última actualización: 2024-12-26*  
*Autor: Gemini Engineering*  
*Versión del documento: 3.3*

---

## 🚀 Entrada 015: FIX Falsos Timeouts - Heartbeat como Keep-Alive
**Fecha:** 2024-12-26  
**Sesión:** Corrección de desconexiones falsas cuando C3 está ocupado

### 🎯 Problema Identificado

Log de falla:
```
20:00:41.846 [OBD_BRIDGE] 📊 DATA: RPM=768...        ← Último DATA
... (4 segundos sin DATA, pero heartbeat SÍ llega) ...
20:00:45.735 [OBD_BRIDGE] C3 OBD Status: CONNECTED  ← Heartbeat OK!
20:00:45.846 [OBD_BRIDGE] ❌ Connection to C3 LOST   ← FALSO TIMEOUT
20:00:45.964 [OBD_BRIDGE] ✅ C3 connected!           ← Reconecta inmediato
```

**Causa Raíz**: El mensaje `OBD_STATUS: CONNECTED` (heartbeat) **NO actualizaba** `_lastReceiveTime`. Solo los mensajes `DATA` lo hacían.

Cuando el C3 estaba ocupado (escaneo oportunista, DTCs, re-escaneo) y no enviaba DATA por >4s, pero SÍ enviaba heartbeat cada 1s, el Principal detectaba falso timeout.

---

### 🔧 Solución Implementada

**Archivo:** `firmware_main/sources/source_obd_bridge.cpp`

```cpp
} else if (type == "OBD_STATUS") {
    String status = doc["data"] | "";
    _c3Connected = (status == "CONNECTED" || status == "OK");
    
    // CRÍTICO: Actualizar _lastReceiveTime también con heartbeat
    // Esto evita falsos timeouts cuando C3 está ocupado (scan, DTC) pero vivo
    if (_c3Connected) {
      _lastReceiveTime = millis();  // ← NUEVO
    }
    // ...
}
```

---

### 📊 Impacto

| Escenario | ANTES | DESPUÉS |
|-----------|-------|---------|
| C3 ocupado 4s (scan) + heartbeat cada 1s | ❌ FALSO TIMEOUT | ✅ Keep-alive OK |
| C3 desconectado real (sin heartbeat) | ✅ Detecta en 4s | ✅ Detecta en 4s |
| C3 enviando DATA normal | ✅ OK | ✅ OK |

---

### ✅ Estado Final

El heartbeat (`OBD_STATUS: CONNECTED`) ahora funciona como **verdadero keep-alive**:
- Si llega heartbeat → El C3 está vivo → No hay timeout
- Si NO llega nada (ni DATA ni heartbeat) por 4s → Timeout real

---

*Última actualización: 2024-12-26*  
*Autor: Gemini Engineering*  
*Versión del documento: 3.4*

---

## 🚀 Entrada 016: FIX Pérdida de DATA por Escaneo Oportunista
**Fecha:** 2024-12-26  
**Sesión:** Corregir bloqueos de 3-4 segundos sin DATA durante escaneo

### 🎯 Problema Identificado

Log de síntoma:
```
20:08:22.138 [OBD_BRIDGE] 📊 DATA: RPM=753        ← Último DATA
... (~3.5 segundos sin DATA, solo MQTT TX) ...
20:08:25.680 [OBD_BRIDGE] 📊 DATA: RPM=750, PIDs=8  ← DATA vuelve (ahora 8 PIDs!)
```

**El LED indicador se apagaba** durante estos 3.5 segundos, indicando pérdida de lectura.

**Causa Raíz**: El **escaneo oportunista** (que probaba PIDs cada 2 segundos) usaba una llamada al ELM que:
1. No esperaba completamente la respuesta
2. Dejaba al ELM en estado `ELM_GETTING_MSG`
3. Bloqueaba la lectura normal de PIDs porque `elmOcupado()` retornaba `true`
4. El `enviarDatos()` seguía siendo llamado, pero sin valores nuevos

---

### 🔧 Cambios Realizados

#### 1. Reducir frecuencia de escaneo oportunista

```cpp
// ANTES
#define OPPORTUNISTIC_INTERVAL_MS 2000  // Cada 2 segundos

// DESPUÉS  
#define OPPORTUNISTIC_INTERVAL_MS 10000 // Cada 10 segundos (menos intrusivo)
```

#### 2. Usar queryPIDBlocking() con timeout corto

```cpp
// Escaneo oportunista ahora usa timeout de 300ms máximo
bool ok = queryPIDBlocking(p, 300); // Max 300ms, no infinito
```

#### 3. Agregar advertencia de ELM bloqueado

```cpp
// Log de advertencia si ELM está ocupado >1 segundo
if (duracion > 1000) {
    Serial.printf("[WARN] ELM ocupado por %lums - posible bloqueo\n", duracion);
}
```

---

### 📊 Impacto Esperado

| Métrica | ANTES | DESPUÉS |
|---------|-------|---------|
| Frecuencia escaneo oportunista | Cada 2s | Cada 10s |
| Timeout por PID oportunista | Sin límite | 300ms máximo |
| Pérdida de DATA durante scan | ~3.5 segundos | ~300ms máximo |
| Logs de diagnóstico | No | Sí (ELM ocupado) |

---

### 📁 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `firmware_c3/src/main.cpp` | Escaneo oportunista: 2s→10s, usar queryPIDBlocking(300ms), logging de ELM ocupado |

---

*Última actualización: 2024-12-26*  
*Autor: Gemini Engineering*  
*Versión del documento: 3.5*

---

## 🚀 Entrada 017: FIX Saturación ELM327 - Throttle en leerPIDs()
**Fecha:** 2024-12-26  
**Sesión:** Evitar saturar el ELM327 con peticiones más rápidas de lo que puede responder

### 🎯 Problema Identificado

Log mostraba:
```
[WARN] ELM ocupado por 1698ms - posible bloqueo
[WARN] ELM ocupado por 1708ms - posible bloqueo
... (cientos de líneas)
Received: SEARCHING...
ERROR: ELM_TIMEOUT
```

**Causa Raíz**:
- El loop del C3 corre cada **~10ms** (delay(10) al final)
- `leerPIDs()` se llamaba cada iteración
- El ELM327 necesita **~50-150ms** para responder un PID
- **Saturación 10:1** → El ELM se confundía y respondía "SEARCHING..."

---

### 🔧 Cambios Realizados

#### 1. Throttle de 80ms en `leerPIDs()`

```cpp
// NUEVO: No pedir nuevo PID si el anterior aún no terminó
static unsigned long ultimaPeticion = 0;
const unsigned long INTERVALO_MINIMO_PID = 80; // 80ms mínimo entre peticiones

// Si el ELM está ocupado, solo esperar
if (elmOcupado()) {
    return;
}

// Verificar si ha pasado suficiente tiempo
if (millis() - ultimaPeticion < INTERVALO_MINIMO_PID) {
    return; // Demasiado pronto
}
```

#### 2. Warning de ELM ocupado mejorado (sin spam)

```cpp
// Solo 1 warning por período de bloqueo, no cada 10ms
static bool warnEmitido = false;
if (duracion > 500 && !warnEmitido) {
    Serial.printf("[WARN] ELM ocupado por >500ms (posible timeout)\n");
    warnEmitido = true;
}
```

---

### 📊 Impacto Esperado

| Métrica | ANTES | DESPUÉS |
|---------|-------|---------|
| Frecuencia peticiones al ELM | Cada 10ms | **Cada 80ms mínimo** |
| Peticiones mientras ELM ocupado | Sí (causa SEARCHING) | **No (espera)** |
| Spam de warnings | Cada 10ms | **1 por bloqueo** |
| Ratio petición:respuesta | 10:1 (saturación) | **~1:1 (óptimo)** |

---

### 📁 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `firmware_c3/src/main.cpp` | Throttle 80ms en leerPIDs(), warning sin spam |

---

*Última actualización: 2024-12-26*  
*Autor: Gemini Engineering*  
*Versión del documento: 3.6*

---

## 🚀 Entrada 018: FIX CRÍTICO - Throttle Rompía Patrón ELMduino
**Fecha:** 2024-12-26  
**Sesión:** Valores congelados porque el throttle impedía procesar respuestas

### 🎯 Problema Identificado

Log mostraba:
```
[DATA] PIDs: RPM=0 BATT_V=0.00 COOLANT=84.00 LOAD=33... (valores congelados)
[DATA] PIDs: RPM=0 BATT_V=0.00 COOLANT=84.00 LOAD=33... (siempre iguales)
```

El **ELM327 dejó de parpadear** - no estaba recibiendo comandos.

**Causa Raíz**: El throttle del fix #017 hacía:
```cpp
if (elmOcupado()) {
    return;  // ← ¡NUNCA PROCESAMOS LA RESPUESTA PENDIENTE!
}
```

**El patrón de ELMduino requiere seguir llamando a la función** para procesar respuestas. Si dejamos de llamar cuando `GETTING_MSG`, la respuesta nunca se lee.

---

### 🔧 Solución Implementada

```cpp
// Variable para rastrear qué PID está esperando respuesta
static int8_t pidEnProceso = -1;

if (pidEnProceso != -1) {
    // HAY un PID esperando respuesta - NO aplicar throttle
    // Seguir llamando para procesar la respuesta
} else {
    // NO hay PID en proceso - aplicar throttle normal
    if (millis() - ultimaPeticion < INTERVALO_MINIMO_PID) {
        return;
    }
}

// Llamar a la función (envía comando O procesa respuesta)
float valor = (elm.*(p.funcion))();

if (elm.nb_rx_state == ELM_GETTING_MSG) {
    pidEnProceso = idxParametro; // Marcar que hay respuesta pendiente
    // NO avanzamos - seguiremos llamando
} else {
    pidEnProceso = -1; // Limpiamos cuando termina
    ultimaPeticion = millis(); // Ahora sí aplicamos throttle
}
```

---

### 📊 Diferencia

| Aspecto | Fix #017 (BUG) | Fix #018 (CORRECTO) |
|---------|----------------|---------------------|
| Si ELM ocupado | `return` (no procesa) | Sigue llamando |
| Respuestas | Nunca se leen | Se procesan |
| Valores | Congelados | Actualizados |
| ELM parpadea | No ❌ | Sí ✅ |

---

### 📁 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `firmware_c3/src/main.cpp` | `leerPIDs()` reescrito para distinguir entre esperar respuesta vs throttle |

---

*Última actualización: 2024-12-26*  
*Autor: Gemini Engineering*  
*Versión del documento: 3.7*

---

## 🚀 Entrada 019: Reducción de Re-Escaneo Agresivo
**Fecha:** 2024-12-26  
**Sesión:** El re-escaneo cada 30s interrumpía la lectura normal

### 🎯 Problema Identificado

Log mostraba:
```
20:49:17.211 [SCAN] Re-escaneo agresivo (209s restantes)...
... (3 segundos de escaneo que interrumpe lectura normal)
20:51:03.663 [SCAN] Re-escaneo agresivo (239s restantes)...
... (otro escaneo - perdió THROTTLE, de 9 a 8 PIDs!)
```

**Causa**: Re-escaneo cada 30 segundos durante los primeros 5 minutos era:
1. Demasiado frecuente
2. Innecesario si ya teníamos PIDs funcionando
3. Causaba pérdida de PIDs por race conditions

---

### 🔧 Cambios Realizados

#### 1. Intervalos menos agresivos

| Parámetro | ANTES | DESPUÉS |
|-----------|-------|---------|
| `SCAN_AGGRESSIVE_MS` | 30 segundos | **2 minutos** |
| `AGGRESSIVE_PERIOD_MS` | 5 minutos | **2 minutos** |

#### 2. Saltar re-escaneo si ya hay suficientes PIDs

```cpp
// Si ya tenemos suficientes PIDs, no necesitamos escaneo agresivo
if (enPeriodoAgresivo && parametrosDisponibles >= 4) {
    enPeriodoAgresivo = false; // Desactivar agresividad
}
```

---

### 📊 Impacto Esperado

| Métrica | ANTES | DESPUÉS |
|---------|-------|---------|
| Re-escaneos primeros 5min | ~10 veces | **0-1 vez** (si hay PIDs) |
| Interrupciones de lectura | Cada 30s | Cada 5min (normal) |
| Estabilidad de PIDs | Puede perder | Más estable |

---

### 📁 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `firmware_c3/src/main.cpp` | Intervalos reducidos, skip si >=4 PIDs |

---

*Última actualización: 2024-12-26*  
*Autor: Gemini Engineering*  
*Versión del documento: 3.8*
