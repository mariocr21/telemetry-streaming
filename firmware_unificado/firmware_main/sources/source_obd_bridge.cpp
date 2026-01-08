/**
 * @file source_obd_bridge.cpp
 * @brief Implementación de SourceOBDBridge
 *
 * Recibe datos OBD2 del ESP32-C3 via UART.
 * Basado en el código probado de OBD2/ESP_PRINCIPAL_V4
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#include "source_obd_bridge.h"
#include "../cloud/cloud_manager.h"
#include "../config/config_manager.h"
#include "../telemetry/telemetry_bus.h"
#include <esp_task_wdt.h>

// ============================================================================
// CONSTRUCTOR
// ============================================================================

SourceOBDBridge::SourceOBDBridge()
    : BaseDataSource("OBD_BRIDGE"), _serial(nullptr), _bufferIndex(0),
      _c3Connected(false), _obdEnabled(true), _lastReceiveTime(0), _pidCount(0),
      _rpm(0), _speed(0), _coolant(0), _throttle(0), _load(0), _maf(0), _map(0),
      _intakeTemp(0), _oilTemp(0), _fuelLevel(0), _fuelRate(0),
      _batteryVoltage(0), _rxPin(-1), _txPin(-1), _baud(460800) {
  memset(_buffer, 0, sizeof(_buffer));
}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

bool SourceOBDBridge::begin() {
  Serial.println(F("[OBD_BRIDGE] Initializing UART bridge to ESP32-C3..."));
  setState(SourceState::INITIALIZING);

  // Obtener configuración
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (!cfg.obd.enabled || strcmp(cfg.obd.mode, "bridge") != 0) {
    Serial.println(F("[OBD_BRIDGE] OBD Bridge disabled in configuration"));
    setState(SourceState::SOURCE_DISABLED);
    return false;
  }

  _rxPin = cfg.obd.uart_rx_pin;
  _txPin = cfg.obd.uart_tx_pin;
  _baud = cfg.obd.uart_baud;

  // Usar UART1 para comunicación con C3
  _serial = new HardwareSerial(1);

  Serial.printf("[OBD_BRIDGE] Starting UART1 on RX=%d, TX=%d @ %lu baud\n",
                _rxPin, _txPin, _baud);
  _serial->begin(_baud, SERIAL_8N1, _rxPin, _txPin);

  // Esperar a que el puerto se estabilice
  delay(100);

  // Enviar enable al C3
  _obdEnabled = cfg.obd.enabled;
  sendToC3("OBD_ENABLE", _obdEnabled ? "1" : "0");

  setState(SourceState::READY);
  Serial.println(F("[OBD_BRIDGE] Ready, waiting for data from C3..."));

  // Init Status in TelemetryBus so it appears in JSON immediately
  TelemetryBus::getInstance().setCustomValue("OBD_Status", 0.0f);

  return true;
}

// ============================================================================
// TAREA FREERTOS
// ============================================================================

void SourceOBDBridge::startTask() {
  if (getState() != SourceState::READY) {
    Serial.println(F("[OBD_BRIDGE] Cannot start task, not ready"));
    return;
  }

  TaskHandle_t handle = nullptr;

  xTaskCreatePinnedToCore(taskFunction, "ObdBridgeTask",
                          8192, // 8KB stack (JSON parsing)
                          this,
                          1, // Prioridad baja
                          &handle,
                          1 // Core 1
  );

  if (handle != nullptr) {
    setTaskHandle(handle);
    setState(SourceState::RUNNING);
    Serial.println(F("[OBD_BRIDGE] Task started on Core 1"));
  } else {
    Serial.println(F("[OBD_BRIDGE] Failed to create task!"));
    setState(SourceState::ERROR_STATE);
  }
}

void SourceOBDBridge::stopTask() {
  TaskHandle_t handle = getTaskHandle();
  if (handle != nullptr) {
    vTaskDelete(handle);
    setTaskHandle(nullptr);
    setState(SourceState::READY);
    Serial.println(F("[OBD_BRIDGE] Task stopped"));
  }
}

void SourceOBDBridge::taskFunction(void *param) {
  SourceOBDBridge *self = static_cast<SourceOBDBridge *>(param);

  Serial.printf("[OBD_BRIDGE] Task running on core %d\n", xPortGetCoreID());

  esp_task_wdt_add(NULL);

  while (true) {
    self->taskLoop();
  }
}

void SourceOBDBridge::taskLoop() {
  esp_task_wdt_reset();

  // Procesar datos del C3
  processC3Data();

  // Verificar timeout de conexión
  if (_lastReceiveTime > 0 &&
      (millis() - _lastReceiveTime) > OBD_BRIDGE_TIMEOUT_MS) {
    if (_c3Connected) {
      Serial.println(F("[OBD_BRIDGE] ❌ Connection to C3 LOST (timeout)"));
      _c3Connected = false;
      TelemetryBus::getInstance().setCustomValue("OBD_Status", 0.0f);
    }
  }

  // Log de estado periódico cada 5 segundos (para diagnóstico)
  static uint32_t lastStatusLog = 0;
  if (millis() - lastStatusLog >= 5000) {
    lastStatusLog = millis();
    Serial.printf("[OBD_BRIDGE] Status: C3=%s, PIDs=%d, LastRx=%lums ago\n",
                  _c3Connected ? "OK" : "DISC", _pidCount,
                  _lastReceiveTime > 0 ? (millis() - _lastReceiveTime) : 0);
  }

  vTaskDelay(pdMS_TO_TICKS(10));
}

// ============================================================================
// PROCESAMIENTO DE DATOS DEL C3
// ============================================================================

void SourceOBDBridge::processC3Data() {
  if (_serial == nullptr)
    return;

  while (_serial->available()) {
    char c = _serial->read();

    if (c == '\n' || c == '\r') {
      if (_bufferIndex > 0) {
        _buffer[_bufferIndex] = '\0';
        processC3Message(String(_buffer));
        _bufferIndex = 0;
      }
    } else if (_bufferIndex < OBD_BRIDGE_BUFFER_SIZE - 1) {
      _buffer[_bufferIndex++] = c;
    } else {
      // Buffer overflow, reset
      _bufferIndex = 0;
      incrementErrorCount();
    }
  }
}

void SourceOBDBridge::processC3Message(const String &json) {
  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, json);

  if (error) {
    Serial.printf("[OBD_BRIDGE] JSON parse error: %s\n", error.c_str());
    incrementErrorCount();
    return;
  }

  String type = doc["t"] | "";

  if (type == "DATA") {
    processDataMessage(doc);
    incrementReadCount();
  } else if (type == "OBD_STATUS") {
    String status = doc["data"] | "";
    Serial.printf("[OBD_BRIDGE] C3 OBD Status: %s\n", status.c_str());
    _c3Connected = (status == "CONNECTED" || status == "OK");

    // CRÍTICO: Actualizar _lastReceiveTime también con heartbeat
    // Esto evita falsos timeouts cuando C3 está ocupado (scan, DTC) pero vivo
    if (_c3Connected) {
      _lastReceiveTime = millis();
    }

    // Publish status to shared bus for visibility in Configurator
    TelemetryBus::getInstance().setValue("OBD_Status",
                                         _c3Connected ? 1.0f : 0.0f, "", "OBD");
  } else if (type == "DTC_CLEARED") {
    String result = doc["data"] | "";
    Serial.printf("[OBD_BRIDGE] DTCs cleared: %s\n", result.c_str());
    if (result == "OK") {
      _dtcCodes.clear();
    }
  } else {
    Serial.printf("[OBD_BRIDGE] Unknown message type: %s\n", type.c_str());
  }
}

void SourceOBDBridge::processDataMessage(JsonDocument &doc) {
  _lastReceiveTime = millis();

  // Log si es la primera conexión o si se reconectó
  if (!_c3Connected) {
    Serial.println(F("[OBD_BRIDGE] ✅ C3 connected! Receiving OBD data."));
  }
  _c3Connected = true;

  // Confirm connection on every data packet
  TelemetryBus::getInstance().setCustomValue("OBD_Status", 1.0f);

  // Procesar PIDs
  JsonObject pids = doc["pids"];
  if (!pids.isNull()) {
    _pidCount = 0;

    // RPM (0x0C)
    if (pids.containsKey("0x0C")) {
      _rpm = pids["0x0C"].as<float>();
      _pidCount++;
    }

    // Velocidad (0x0D)
    if (pids.containsKey("0x0D")) {
      _speed = pids["0x0D"].as<float>();
      _pidCount++;
    }

    // Engine Load (0x04)
    if (pids.containsKey("0x04")) {
      _load = pids["0x04"].as<float>();
      _pidCount++;
    }

    // Coolant Temp (0x05)
    if (pids.containsKey("0x05")) {
      _coolant = pids["0x05"].as<float>();
      _pidCount++;
    }

    // Intake Air Temp (0x0F)
    if (pids.containsKey("0x0F")) {
      _intakeTemp = pids["0x0F"].as<float>();
      _pidCount++;
    }

    // MAF (0x10)
    if (pids.containsKey("0x10")) {
      _maf = pids["0x10"].as<float>();
      _pidCount++;
    }

    // MAP (0x0B)
    if (pids.containsKey("0x0B")) {
      _map = pids["0x0B"].as<float>();
      _pidCount++;
    }

    // Throttle (0x11)
    if (pids.containsKey("0x11")) {
      _throttle = pids["0x11"].as<float>();
      _pidCount++;
    }

    // Fuel Level (0x2F)
    if (pids.containsKey("0x2F")) {
      _fuelLevel = pids["0x2F"].as<float>();
      _pidCount++;
    }

    // Oil Temp (0x5C)
    if (pids.containsKey("0x5C")) {
      _oilTemp = pids["0x5C"].as<float>();
      _pidCount++;
    }

    // Fuel Rate (0x5E)
    if (pids.containsKey("0x5E")) {
      _fuelRate = pids["0x5E"].as<float>();
      _pidCount++;
    }

    // Battery Voltage (custom "BAT")
    if (pids.containsKey("BAT")) {
      _batteryVoltage = pids["BAT"].as<float>();
      _pidCount++;
    }

    // Control Module Voltage (0x42)
    if (pids.containsKey("0x42")) {
      _batteryVoltage = pids["0x42"].as<float>();
      _pidCount++;
    }
  }

  // Procesar DTCs
  JsonArray dtcArray = doc["dtc"];
  if (!dtcArray.isNull()) {
    _dtcCodes.clear();
    for (JsonVariant dtc : dtcArray) {
      DtcCode code;
      strncpy(code.code, dtc.as<const char *>(), sizeof(code.code) - 1);
      _dtcCodes.push_back(code);
    }
  }

  // Log de PIDs recibidos para diagnóstico
  Serial.printf("[OBD_BRIDGE] 📊 DATA: RPM=%.0f, SPD=%.0f, TEMP=%.0f, "
                "BATT=%.1f, PIDs=%d\n",
                _rpm, _speed, _coolant, _batteryVoltage, _pidCount);

  // Publicar al bus
  publishToTelemetryBus();

  // FAST PATH: pedir publish inmediato (sin bloquear) para que el payload
  // completo (GPS/IMU/CAN/OBD) se envíe lo antes posible al MQTT.
  // Respeta throttle cloud_interval_ms dentro de CloudManager.
  CloudManager::getInstance().requestImmediatePublish();
}

void SourceOBDBridge::publishToTelemetryBus() {
  TelemetryBus &bus = TelemetryBus::getInstance();

  // Publicar todos los valores al bus
  if (_rpm > 0)
    bus.setEngineRpm(_rpm);
  if (_speed >= 0)
    bus.setEngineSpeed(_speed);
  if (_coolant > -40)
    bus.setEngineCoolantTemp(_coolant); // -40 es el mínimo OBD
  if (_throttle >= 0)
    bus.setEngineThrottle(_throttle);
  if (_load >= 0)
    bus.setEngineLoad(_load);
  if (_maf >= 0)
    bus.setEngineMaf(_maf);
  if (_map > 0)
    bus.setEngineMap(_map);
  if (_oilTemp > -40)
    bus.setEngineOilTemp(_oilTemp);
  if (_fuelLevel >= 0)
    bus.setFuelLevel(_fuelLevel);
  if (_fuelRate >= 0)
    bus.setFuelRate(_fuelRate);
  if (_batteryVoltage > 0)
    bus.setBatteryVoltage(_batteryVoltage);

  // Valores custom para intake temp ya que no hay setter directo
  if (_intakeTemp > -40) {
    bus.setCustomValue("engine.intake_temp", _intakeTemp);
  }
}

// ============================================================================
// COMANDOS AL C3
// ============================================================================

void SourceOBDBridge::setOBDEnabled(bool enabled) {
  _obdEnabled = enabled;
  sendToC3("OBD_ENABLE", enabled ? "1" : "0");
  Serial.printf("[OBD_BRIDGE] OBD %s\n", enabled ? "enabled" : "disabled");
}

void SourceOBDBridge::clearDTCs() {
  sendToC3("CLEAR_DTC", "{}");
  Serial.println(F("[OBD_BRIDGE] Clear DTC request sent"));
}

void SourceOBDBridge::sendToC3(const char *type, const char *data) {
  if (_serial == nullptr)
    return;

  JsonDocument doc;
  doc["t"] = type;
  doc["data"] = data;

  String output;
  serializeJson(doc, output);
  _serial->println(output);

  Serial.printf("[OBD_BRIDGE] TX-> C3: %s\n", output.c_str());
}
