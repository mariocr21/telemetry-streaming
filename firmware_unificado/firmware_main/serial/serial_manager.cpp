/**
 * @file serial_manager.cpp
 * @brief Implementación del SerialManager
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#include "serial_manager.h"
#include "../cloud/cloud_manager.h"
#include "../config/config_manager.h"
#include "../telemetry/telemetry_bus.h"
#include <ArduinoJson.h>

// ============================================================================
// CONSTRUCTOR
// ============================================================================

SerialManager::SerialManager()
    : _bufferIndex(0), _liveMode(false), _lastTelemetrySend(0) {
  memset(_buffer, 0, sizeof(_buffer));
}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

void SerialManager::begin(uint32_t baud) {
  Serial.begin(baud);
  Serial.println(F("\n\n========================================"));
  Serial.println(F("   NEURONA OFF ROAD TELEMETRY v3.0"));
  Serial.println(F("   Unified Firmware"));
  Serial.println(F("========================================\n"));
  Serial.println(F("[SERIAL] SerialManager ready"));
  Serial.println(F("[SERIAL] Type HELP for available commands\n"));
}

// ============================================================================
// PROCESAMIENTO
// ============================================================================

void SerialManager::process() {
  // Leer caracteres entrantes
  while (Serial.available()) {
    char c = Serial.read();

    if (c == '\n' || c == '\r') {
      if (_bufferIndex > 0) {
        _buffer[_bufferIndex] = '\0';
        String cmd(_buffer);
        cmd.trim();

        if (cmd.length() > 0) {
          processCommand(cmd);
        }

        _bufferIndex = 0;
        memset(_buffer, 0, sizeof(_buffer));
      }
    } else if (_bufferIndex < sizeof(_buffer) - 1) {
      _buffer[_bufferIndex++] = c;
    }
  }

  // Envío periódico de telemetría si está en modo live
  if (_liveMode) {
    auto &cfg = ConfigManager::getInstance().getConfig();
    unsigned long now = millis();

    if (now - _lastTelemetrySend >= cfg.serial_interval_ms) {
      _lastTelemetrySend = now;
      sendTelemetry();
    }
  }
}

void SerialManager::processCommand(const String &cmd) {
  // Comandos sin parámetros
  if (cmd.equalsIgnoreCase("PING")) {
    handlePing();
  } else if (cmd.equalsIgnoreCase("GET_CONFIG")) {
    handleGetConfig();
  } else if (cmd.equalsIgnoreCase("SAVE_CONFIG")) {
    handleSaveConfig();
  } else if (cmd.equalsIgnoreCase("GET_STATUS")) {
    handleGetStatus();
  } else if (cmd.equalsIgnoreCase("GET_TELEMETRY")) {
    handleGetTelemetry();
  } else if (cmd.equalsIgnoreCase("GET_SENSORS")) {
    handleGetSensors();
  } else if (cmd.equalsIgnoreCase("GET_DIAG")) {
    handleGetDiag();
  } else if (cmd.equalsIgnoreCase("REBOOT")) {
    handleReboot();
  } else if (cmd.equalsIgnoreCase("FACTORY_RESET")) {
    handleFactoryReset();
  } else if (cmd.equalsIgnoreCase("HELP") || cmd.equals("?")) {
    handleHelp();
  } else if (cmd.equalsIgnoreCase("LIVE_ON")) {
    _liveMode = true;
    sendResponse("LIVE", true, "Live mode enabled");
  } else if (cmd.equalsIgnoreCase("LIVE_OFF")) {
    _liveMode = false;
    sendResponse("LIVE", true, "Live mode disabled");
  }
  // Comandos con parámetros
  else if (cmd.startsWith("SET_CONFIG:")) {
    String json = cmd.substring(11);
    handleSetConfig(json);
  } else if (cmd.startsWith("SET_SENSORS:")) {
    String json = cmd.substring(12);
    handleSetSensors(json);
  } else {
    sendResponse("ERROR", false,
                 "Unknown command. Type HELP for available commands.");
  }
}

// ============================================================================
// HANDLERS
// ============================================================================

void SerialManager::handlePing() { Serial.println(F("ACK:PONG")); }

void SerialManager::handleGetConfig() {
  String json = ConfigManager::getInstance().exportToJson(false);
  sendJson("CONFIG", json);
}

void SerialManager::handleSetConfig(const String &json) {
  if (ConfigManager::getInstance().loadFromJson(json)) {
    sendResponse("SET_CONFIG", true,
                 "Configuration updated (not saved to flash)");
  } else {
    sendResponse("SET_CONFIG", false, "Failed to parse configuration JSON");
  }
}

void SerialManager::handleSaveConfig() {
  if (ConfigManager::getInstance().saveToPreferences()) {
    ConfigManager::getInstance().saveSensorsToPreferences();
    sendResponse("SAVE_CONFIG", true, "Configuration saved to flash");
  } else {
    sendResponse("SAVE_CONFIG", false, "Failed to save configuration");
  }
}

void SerialManager::handleGetStatus() {
  auto &cfg = ConfigManager::getInstance().getConfig();

  JsonDocument doc;

  doc["device_id"] = cfg.device_id;
  doc["car_id"] = cfg.car_id;
  doc["source"] = dataSourceToString(cfg.source);
  doc["uptime_ms"] = millis();

  // WiFi
  JsonObject wifi = doc["wifi"].to<JsonObject>();
  wifi["connected"] = WiFi.isConnected();
  wifi["ssid"] = WiFi.isConnected() ? WiFi.SSID() : "";
  wifi["ip"] = WiFi.isConnected() ? WiFi.localIP().toString() : "";
  wifi["rssi"] = WiFi.isConnected() ? WiFi.RSSI() : 0;

  // Cloud
  JsonObject cloud = doc["cloud"].to<JsonObject>();
  auto &cloudMgr = CloudManager::getInstance();
  cloud["mqtt_connected"] = cloudMgr.isMqttConnected();
  cloud["success"] = cloudMgr.getSuccessCount();
  cloud["fail"] = cloudMgr.getFailCount();

  // Memory
  JsonObject mem = doc["memory"].to<JsonObject>();
  mem["heap_free"] = ESP.getFreeHeap();
  mem["heap_total"] = ESP.getHeapSize();
  mem["heap_min"] = ESP.getMinFreeHeap();

  // Sensors
  doc["sensors_count"] = ConfigManager::getInstance().getSensors().size();

  String output;
  serializeJson(doc, output);
  sendJson("STATUS", output);
}

void SerialManager::handleGetTelemetry() {
  // Usar el mismo builder del CloudManager
  TelemetrySnapshot snapshot;
  TelemetryBus::getInstance().getSnapshot(snapshot);

  JsonDocument doc;

  doc["gps_lat"] = snapshot.gps_lat;
  doc["gps_lng"] = snapshot.gps_lng;
  doc["gps_speed"] = snapshot.gps_speed;
  doc["gps_fix"] = snapshot.gps_fix;

  doc["rpm"] = snapshot.engine_rpm;
  doc["speed"] = snapshot.engine_speed;
  doc["coolant"] = snapshot.engine_coolant_temp;
  doc["throttle"] = snapshot.engine_throttle;

  doc["accel_x"] = snapshot.imu_accel_x;
  doc["accel_y"] = snapshot.imu_accel_y;
  doc["accel_z"] = snapshot.imu_accel_z;

  doc["battery"] = snapshot.battery_voltage;
  doc["fuel_level"] = snapshot.fuel_level;

  String output;
  serializeJson(doc, output);
  sendJson("TELEMETRY", output);
}

void SerialManager::handleGetSensors() {
  String json = ConfigManager::getInstance().exportSensorsToJson(false);
  sendJson("SENSORS", json);
}

void SerialManager::handleSetSensors(const String &json) {
  if (ConfigManager::getInstance().loadSensorsFromJson(json)) {
    sendResponse("SET_SENSORS", true, "Sensors updated");
  } else {
    sendResponse("SET_SENSORS", false, "Failed to parse sensors JSON");
  }
}

void SerialManager::handleGetDiag() {
  auto &cfg = ConfigManager::getInstance().getConfig();
  JsonDocument doc;

  // Sistema
  doc["chip_model"] = ESP.getChipModel();
  doc["chip_revision"] = ESP.getChipRevision();
  doc["cpu_freq_mhz"] = ESP.getCpuFreqMHz();
  doc["flash_size"] = ESP.getFlashChipSize();
  doc["sdk_version"] = ESP.getSdkVersion();

  // Memoria
  doc["heap_free"] = ESP.getFreeHeap();
  doc["heap_total"] = ESP.getHeapSize();
  doc["heap_min_free"] = ESP.getMinFreeHeap();
  doc["psram_free"] = ESP.getFreePsram();

  // Tareas FreeRTOS
  doc["task_count"] = uxTaskGetNumberOfTasks();

  // === CONFIGURACIÓN CRÍTICA (NUEVO) ===
  JsonObject config = doc["config"].to<JsonObject>();
  config["source"] = dataSourceToString(cfg.source);
  config["cloud_interval_ms"] = cfg.cloud_interval_ms;
  config["serial_interval_ms"] = cfg.serial_interval_ms;
  config["protocol"] =
      (cfg.cloud_protocol == CloudProtocol::MQTT) ? "MQTT" : "HTTP";
  config["debug_mode"] = cfg.debug_mode;

  // OBD Config
  JsonObject obd = config["obd"].to<JsonObject>();
  obd["enabled"] = cfg.obd.enabled;
  obd["mode"] = cfg.obd.mode;
  obd["poll_interval_ms"] = cfg.obd.poll_interval_ms;

  // CAN Config
  JsonObject can = config["can"].to<JsonObject>();
  can["enabled"] = cfg.can.enabled;
  can["baud_kbps"] = cfg.can.baud_kbps;

  // GPS/IMU
  config["gps_enabled"] = cfg.gps.enabled;
  config["imu_enabled"] = cfg.imu.enabled;

  String output;
  serializeJson(doc, output);
  sendJson("DIAG", output);
}

void SerialManager::handleReboot() {
  sendResponse("REBOOT", true, "Rebooting in 1 second...");
  delay(1000);
  ESP.restart();
}

void SerialManager::handleFactoryReset() {
  ConfigManager::getInstance().resetToDefaults();
  ConfigManager::getInstance().saveToPreferences();
  sendResponse("FACTORY_RESET", true,
               "Configuration reset to defaults and saved");
}

void SerialManager::handleHelp() {
  Serial.println(F("\n========== AVAILABLE COMMANDS =========="));
  Serial.println(F("PING              - Test connection (returns ACK:PONG)"));
  Serial.println(F("GET_CONFIG        - Get current configuration as JSON"));
  Serial.println(F("SET_CONFIG:{json} - Set configuration from JSON"));
  Serial.println(F("SAVE_CONFIG       - Save configuration to flash"));
  Serial.println(F("GET_STATUS        - Get system status"));
  Serial.println(F("GET_TELEMETRY     - Get current telemetry snapshot"));
  Serial.println(F("GET_SENSORS       - Get configured CAN sensors"));
  Serial.println(F("SET_SENSORS:{json}- Set CAN sensors from JSON"));
  Serial.println(F("GET_DIAG          - Get diagnostic info"));
  Serial.println(F("LIVE_ON           - Enable live telemetry stream"));
  Serial.println(F("LIVE_OFF          - Disable live telemetry stream"));
  Serial.println(F("REBOOT            - Restart the device"));
  Serial.println(F("FACTORY_RESET     - Reset to factory defaults"));
  Serial.println(F("HELP or ?         - Show this help"));
  Serial.println(F("==========================================\n"));
}

// ============================================================================
// HELPERS
// ============================================================================

void SerialManager::sendResponse(const char *type, bool success,
                                 const char *message) {
  Serial.printf("RSP:%s:%s", type, success ? "OK" : "ERROR");
  if (message != nullptr) {
    Serial.printf(":%s", message);
  }
  Serial.println();
}

void SerialManager::sendJson(const char *type, const String &json) {
  Serial.printf("%s:%s\n", type, json.c_str());
}

void SerialManager::sendTelemetry() {
  // Use JSON format compatible with Configurator main.py ({"s": ...})
  TelemetrySnapshot snapshot;
  TelemetryBus::getInstance().getSnapshot(snapshot);

  JsonDocument doc;
  JsonObject s = doc.createNestedObject("s");

  // Only send ENGINE data if valid (fresh & > 0)
  if (snapshot.engine_valid) {
    s["rpm"] = snapshot.engine_rpm;
    s["speed"] = snapshot.engine_speed;
    s["temp"] = snapshot.engine_coolant_temp;
    s["throttle"] = snapshot.engine_throttle;
    s["load"] = snapshot.engine_load;
    s["maf"] = snapshot.engine_maf;
    s["map"] = snapshot.engine_map;
  }

  // Always send Battery and Fuel if they have been updated at least once
  if (snapshot.battery_voltage > 0)
    s["batt"] = snapshot.battery_voltage;
  if (snapshot.fuel_level > 0)
    s["fuel"] = snapshot.fuel_level;

  // Only send GPS if we have a FIX or recent data
  if (snapshot.gps_valid || snapshot.gps_sats > 0) {
    s["lat"] = snapshot.gps_lat;
    s["lng"] = snapshot.gps_lng;
    s["gps_spd"] = snapshot.gps_speed;
    s["gps_sats"] = snapshot.gps_sats;
  }

  // Use timestamp to check for recent IMU data (2 seconds window)
  if (millis() - snapshot.ts_imu < 2000) {
    s["ax"] = snapshot.imu_accel_x;
    s["ay"] = snapshot.imu_accel_y;
    s["az"] = snapshot.imu_accel_z;
  }

  // Custom Values (CAN) - Already filtered by existence
  for (int i = 0; i < snapshot.custom_count; i++) {
    s[snapshot.custom_values[i].key] = snapshot.custom_values[i].value;
  }

  String output;
  serializeJson(doc, output);
  Serial.println(output);
}
