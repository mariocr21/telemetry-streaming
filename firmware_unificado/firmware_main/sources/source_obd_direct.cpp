/**
 * @file source_obd_direct.cpp
 * @brief Implementación de SourceOBDDirect
 *
 * Basado en el código probado de OBD2/ESP32_C3_V4
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#include "source_obd_direct.h"
#include "../config/config_manager.h"
#include "../telemetry/telemetry_bus.h"
#include <esp_task_wdt.h>

// PIDs estándar OBD2 conocidos
struct StandardPid {
  uint8_t pid;
  const char *name;
};

static const StandardPid STANDARD_PIDS[] = {
    {0x0C, "RPM"},          {0x0D, "SPEED"},           {0x04, "ENGINE_LOAD"},
    {0x05, "COOLANT_TEMP"}, {0x0F, "INTAKE_TEMP"},     {0x10, "MAF"},
    {0x0B, "MAP"},          {0x11, "THROTTLE"},        {0x2F, "FUEL_LEVEL"},
    {0x5C, "OIL_TEMP"},     {0x42, "CONTROL_VOLTAGE"}, // Voltaje del módulo de
                                                       // control
};

// ============================================================================
// CONSTRUCTOR
// ============================================================================

SourceOBDDirect::SourceOBDDirect()
    : BaseDataSource("OBD"), _elmWifiConnected(false), _elmConnected(false),
      _pidCount(0), _activePidCount(0), _currentPidIndex(0),
      _waitingResponse(false), _pollStartTime(0), _pollIntervalMs(100) {
  memset(_pids, 0, sizeof(_pids));
  memset(_elmSsid, 0, sizeof(_elmSsid));
  memset(_elmPassword, 0, sizeof(_elmPassword));
  memset(_elmIp, 0, sizeof(_elmIp));
}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

bool SourceOBDDirect::begin() {
  Serial.println(F("[OBD] Initializing OBD2 Direct (ELM327 WiFi)..."));
  setState(SourceState::INITIALIZING);

  // Obtener configuración
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (!cfg.obd.enabled || strcmp(cfg.obd.mode, "direct") != 0) {
    Serial.println(F("[OBD] OBD Direct disabled in configuration"));
    setState(SourceState::SOURCE_DISABLED);
    return false;
  }

  // Copiar configuración
  strncpy(_elmSsid, cfg.obd.elm_ssid, sizeof(_elmSsid) - 1);
  strncpy(_elmPassword, cfg.obd.elm_password, sizeof(_elmPassword) - 1);
  strncpy(_elmIp, cfg.obd.elm_ip, sizeof(_elmIp) - 1);
  _elmPort = cfg.obd.elm_port;
  _pollIntervalMs = cfg.obd.poll_interval_ms;

  // Parsear PIDs desde configuración
  parsePidsFromString(cfg.obd.pids_enabled);

  if (_pidCount == 0) {
    Serial.println(F("[OBD] WARNING: No PIDs configured!"));
  }

  Serial.printf("[OBD] Configured: ELM=%s:%d, %d PIDs, poll=%dms\n", _elmIp,
                _elmPort, _pidCount, _pollIntervalMs);

  setState(SourceState::READY);
  return true;
}

void SourceOBDDirect::parsePidsFromString(const char *pidsStr) {
  _pidCount = 0;

  if (pidsStr == nullptr || strlen(pidsStr) == 0) {
    return;
  }

  // Copiar string para tokenizar
  char buffer[256];
  strncpy(buffer, pidsStr, sizeof(buffer) - 1);

  char *token = strtok(buffer, ",");

  while (token != nullptr && _pidCount < MAX_OBD_PIDS) {
    // Trim espacios
    while (*token == ' ')
      token++;

    if (strcasecmp(token, "BAT") == 0) {
      // Caso especial: BAT usa función batteryVoltage()
      _pids[_pidCount].pid = 0xFF; // Marcador especial
      _pids[_pidCount].name = "BATT_V";
      _pids[_pidCount].enabled = true;
      _pids[_pidCount].available = true;
      _pidCount++;
    } else if (strncasecmp(token, "0x", 2) == 0) {
      // PID en formato hexadecimal
      uint8_t pid = (uint8_t)strtol(token, nullptr, 16);

      _pids[_pidCount].pid = pid;
      _pids[_pidCount].enabled = true;
      _pids[_pidCount].available = true; // Se verificará en scan

      // Buscar nombre en PIDs estándar
      _pids[_pidCount].name = "UNKNOWN";
      for (const auto &std : STANDARD_PIDS) {
        if (std.pid == pid) {
          _pids[_pidCount].name = std.name;
          break;
        }
      }

      _pidCount++;
    }

    token = strtok(nullptr, ",");
  }

  Serial.printf("[OBD] Parsed %d PIDs from config\n", _pidCount);
}

// ============================================================================
// CONEXIÓN
// ============================================================================

bool SourceOBDDirect::connectToElm327Wifi() {
  Serial.printf("[OBD] Connecting to ELM327 WiFi: %s\n", _elmSsid);

  // Nota: Esto requiere manejo especial si ya estamos conectados a otra red
  // Por ahora asumimos que el ESP32 puede hacer dual-mode o no hay otra red

  WiFi.begin(_elmSsid, _elmPassword);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\n[OBD] Connected to ELM WiFi, IP: %s\n",
                  WiFi.localIP().toString().c_str());
    _elmWifiConnected = true;
    return true;
  }

  Serial.println(F("\n[OBD] Failed to connect to ELM WiFi"));
  _elmWifiConnected = false;
  return false;
}

bool SourceOBDDirect::connectToElmDevice() {
  Serial.printf("[OBD] Connecting to ELM327 at %s:%d\n", _elmIp, _elmPort);

  IPAddress ip;
  if (!ip.fromString(_elmIp)) {
    Serial.println(F("[OBD] Invalid ELM IP address"));
    return false;
  }

  if (!_elmClient.connect(ip, _elmPort)) {
    Serial.println(F("[OBD] Failed to connect to ELM327"));
    _elmConnected = false;
    return false;
  }

  Serial.println(F("[OBD] TCP connected, initializing ELM327..."));

  // Inicializar ELM327
  if (!_elm.begin(_elmClient, true, 2000)) {
    Serial.println(F("[OBD] ELM327 initialization failed"));
    _elmConnected = false;
    return false;
  }

  Serial.println(F("[OBD] ELM327 ready"));
  _elmConnected = true;

  return true;
}

// ============================================================================
// ESCANEO DE PIDs
// ============================================================================

void SourceOBDDirect::scanSupportedPids() {
  Serial.println(F("[OBD] Scanning supported PIDs..."));

  _activePidCount = 0;

  for (int i = 0; i < _pidCount; i++) {
    if (!_pids[i].enabled)
      continue;

    // PID especial BAT siempre disponible
    if (_pids[i].pid == 0xFF) {
      _pids[i].available = true;
      _activePidCount++;
      continue;
    }

    // Consultar PID
    // Usamos un timeout más largo para el scan
    // TODO: Implementar verificación real con supportedPIDs_1_20, etc.
    _pids[i].available = true; // Por ahora asumir disponible
    _activePidCount++;
  }

  Serial.printf("[OBD] Scan complete: %d PIDs available\n", _activePidCount);
}

// ============================================================================
// TAREA FREERTOS
// ============================================================================

void SourceOBDDirect::startTask() {
  if (getState() != SourceState::READY) {
    Serial.println(F("[OBD] Cannot start task, not ready"));
    return;
  }

  TaskHandle_t handle = nullptr;

  xTaskCreatePinnedToCore(taskFunction, "ObdTask",
                          8192, // 8KB stack (ELM necesita más memoria)
                          this,
                          1, // Prioridad baja
                          &handle,
                          1 // Core 1
  );

  if (handle != nullptr) {
    setTaskHandle(handle);
    setState(SourceState::RUNNING);
    Serial.println(F("[OBD] Task started on Core 1"));
  } else {
    Serial.println(F("[OBD] Failed to create task!"));
    setState(SourceState::ERROR_STATE);
  }
}

void SourceOBDDirect::stopTask() {
  TaskHandle_t handle = getTaskHandle();
  if (handle != nullptr) {
    vTaskDelete(handle);
    setTaskHandle(nullptr);
    setState(SourceState::READY);
    Serial.println(F("[OBD] Task stopped"));
  }
}

void SourceOBDDirect::taskFunction(void *param) {
  SourceOBDDirect *self = static_cast<SourceOBDDirect *>(param);

  Serial.printf("[OBD] Task running on core %d\n", xPortGetCoreID());

  esp_task_wdt_add(NULL);

  // Conectar al arrancar
  bool connected = false;

  while (true) {
    esp_task_wdt_reset();

    if (!connected) {
      // Intentar conectar
      if (self->connectToElm327Wifi() && self->connectToElmDevice()) {
        self->scanSupportedPids();
        connected = true;
      } else {
        vTaskDelay(pdMS_TO_TICKS(5000)); // Esperar 5s antes de reintentar
        continue;
      }
    }

    // Loop normal
    self->taskLoop();
  }
}

void SourceOBDDirect::taskLoop() {
  if (!_elmConnected || _pidCount == 0) {
    vTaskDelay(pdMS_TO_TICKS(100));
    return;
  }

  // Verificar conexión
  if (!_elmClient.connected()) {
    Serial.println(F("[OBD] Connection lost, reconnecting..."));
    _elmConnected = false;
    incrementErrorCount();
    return;
  }

  // Polling secuencial no bloqueante
  pollNextPid();

  vTaskDelay(pdMS_TO_TICKS(_pollIntervalMs));
}

void SourceOBDDirect::pollNextPid() {
  // Buscar siguiente PID activo
  int startIndex = _currentPidIndex;
  do {
    if (_pids[_currentPidIndex].enabled && _pids[_currentPidIndex].available) {
      break;
    }
    _currentPidIndex = (_currentPidIndex + 1) % _pidCount;
  } while (_currentPidIndex != startIndex);

  ObdPid &pid = _pids[_currentPidIndex];

  if (!pid.enabled || !pid.available) {
    return; // No hay PIDs activos
  }

  float value = 0;
  bool success = false;

  // Leer PID
  if (pid.pid == 0xFF) {
    // BAT especial
    value = _elm.batteryVoltage();
    success = (_elm.nb_rx_state == ELM_SUCCESS);
  } else {
    // Usar funciones específicas de ELMduino según PID
    switch (pid.pid) {
    case 0x0C:
      value = _elm.rpm();
      break;
    case 0x0D:
      value = _elm.kph();
      break;
    case 0x04:
      value = _elm.engineLoad();
      break;
    case 0x05:
      value = _elm.engineCoolantTemp();
      break;
    case 0x0F:
      value = _elm.intakeAirTemp();
      break;
    case 0x10:
      value = _elm.mafRate();
      break;
    case 0x0B:
      value = _elm.manifoldPressure();
      break;
    case 0x11:
      value = _elm.throttle();
      break;
    case 0x2F:
      value = _elm.fuelLevel();
      break;
    case 0x5C:
      value = _elm.oilTemp();
      break;
    default:
      // PID genérico - usar processPID
      // TODO: Implementar lectura genérica
      success = false;
    }
    success = (_elm.nb_rx_state == ELM_SUCCESS);
  }

  if (success) {
    // Aplicar filtro EMA
    if (pid.lastRead > 0) {
      pid.valueFiltered =
          EMA_ALPHA * value + (1 - EMA_ALPHA) * pid.valueFiltered;
    } else {
      pid.valueFiltered = value;
    }

    pid.value = value;
    pid.lastRead = millis();

    incrementReadCount();
  } else if (_elm.nb_rx_state != ELM_GETTING_MSG) {
    incrementErrorCount();
  }

  // Publicar al bus
  publishToTelemetryBus();

  // Siguiente PID
  _currentPidIndex = (_currentPidIndex + 1) % _pidCount;
}

void SourceOBDDirect::publishToTelemetryBus() {
  TelemetryBus &bus = TelemetryBus::getInstance();

  for (int i = 0; i < _pidCount; i++) {
    if (!_pids[i].enabled || !_pids[i].available || _pids[i].lastRead == 0)
      continue;

    float val = _pids[i].valueFiltered;

    switch (_pids[i].pid) {
    case 0x0C:
      bus.setEngineRpm(val);
      break;
    case 0x0D:
      bus.setEngineSpeed(val);
      break;
    case 0x04:
      bus.setEngineLoad(val);
      break;
    case 0x05:
      bus.setEngineCoolantTemp(val);
      break;
    case 0x10:
      bus.setEngineMaf(val);
      break;
    case 0x0B:
      bus.setEngineMap(val);
      break;
    case 0x11:
      bus.setEngineThrottle(val);
      break;
    case 0x2F:
      bus.setFuelLevel(val);
      break;
    case 0x5C:
      bus.setEngineOilTemp(val);
      break;
    case 0xFF:
      bus.setBatteryVoltage(val);
      break;
    default:
      bus.setCustomValue((String("obd.") + String(_pids[i].pid, HEX)).c_str(),
                         val);
    }
  }
}
