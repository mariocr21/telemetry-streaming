/**
 * @file config_manager.cpp
 * @brief Implementación del ConfigManager
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#include "config_manager.h"

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

bool ConfigManager::begin() {
  Serial.println(F("[CONFIG] Initializing ConfigManager..."));

  _prefs.begin(PREFS_NAMESPACE, false);

  // Intentar cargar configuración existente
  if (loadFromPreferences()) {
    Serial.println(F("[CONFIG] Loaded configuration from Preferences"));
    _firstRun = false;

    // También cargar sensores
    loadSensorsFromPreferences();
  } else {
    Serial.println(F("[CONFIG] No saved config, using defaults"));
    resetToDefaults();
    _firstRun = true;
  }

  return !_firstRun;
}

// ============================================================================
// PREFERENCES (PERSISTENCIA)
// ============================================================================

bool ConfigManager::loadFromPreferences() {
  size_t len = _prefs.getBytesLength(PREFS_KEY_CONFIG);

  if (len == 0 || len != sizeof(UnifiedConfig)) {
    Serial.printf(
        "[CONFIG] Invalid or no config in Preferences (len=%d, expected=%d)\n",
        len, sizeof(UnifiedConfig));
    return false;
  }

  size_t read =
      _prefs.getBytes(PREFS_KEY_CONFIG, &_config, sizeof(UnifiedConfig));

  if (read != sizeof(UnifiedConfig)) {
    Serial.println(F("[CONFIG] Failed to read config from Preferences"));
    return false;
  }

  // Verificar versión
  if (strcmp(_config.version, CONFIG_VERSION) != 0) {
    Serial.printf("[CONFIG] Version mismatch: stored=%s, current=%s\n",
                  _config.version, CONFIG_VERSION);
    // Por ahora, resetear si la versión no coincide
    return false;
  }

  return true;
}

bool ConfigManager::saveToPreferences() {
  // Asegurar que la versión esté correcta
  strncpy(_config.version, CONFIG_VERSION, sizeof(_config.version) - 1);

  size_t written =
      _prefs.putBytes(PREFS_KEY_CONFIG, &_config, sizeof(UnifiedConfig));

  if (written != sizeof(UnifiedConfig)) {
    Serial.println(F("[CONFIG] Failed to save config to Preferences"));
    return false;
  }

  Serial.println(F("[CONFIG] Configuration saved to Preferences"));
  return true;
}

bool ConfigManager::loadSensorsFromPreferences() {
  String json = _prefs.getString(PREFS_KEY_SENSORS, "");

  if (json.isEmpty()) {
    Serial.println(F("[CONFIG] No sensors in Preferences"));
    return false;
  }

  return loadSensorsFromJson(json);
}

bool ConfigManager::saveSensorsToPreferences() {
  String json = exportSensorsToJson(false);

  if (json.length() > 4000) { // Límite de Preferences
    Serial.println(F("[CONFIG] Sensors JSON too large for Preferences"));
    return false;
  }

  _prefs.putString(PREFS_KEY_SENSORS, json);
  Serial.println(F("[CONFIG] Sensors saved to Preferences"));
  return true;
}

// ============================================================================
// JSON SERIALIZATION
// ============================================================================

bool ConfigManager::loadFromJson(const String &json) {
  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, json);

  if (error) {
    Serial.printf("[CONFIG] JSON parse error: %s\n", error.c_str());
    return false;
  }

  jsonToConfig(doc);

  // P2.0: Normalizar para eliminar estados zombie
  normalizeConfig();

  Serial.println(F("[CONFIG] Configuration loaded from JSON"));
  return true;
}

String ConfigManager::exportToJson(bool pretty) {
  JsonDocument doc;
  configToJson(doc);

  String output;
  if (pretty) {
    serializeJsonPretty(doc, output);
  } else {
    serializeJson(doc, output);
  }

  return output;
}

void ConfigManager::configToJson(JsonDocument &doc) {
  doc["version"] = _config.version;

  // Device
  JsonObject device = doc["device"].to<JsonObject>();
  device["id"] = _config.device_id;
  device["car_id"] = _config.car_id;
  device["source"] = dataSourceToString(_config.source);

  // WiFi
  JsonObject wifi = doc["wifi"].to<JsonObject>();
  wifi["ssid"] = _config.wifi.ssid;
  wifi["password"] = _config.wifi.password;

  // Cloud
  JsonObject cloud = doc["cloud"].to<JsonObject>();
  cloud["protocol"] =
      (_config.cloud_protocol == CloudProtocol::MQTT) ? "mqtt" : "http";
  cloud["interval_ms"] = _config.cloud_interval_ms;
  cloud["debug_mode"] = _config.debug_mode;

  JsonObject mqtt = cloud["mqtt"].to<JsonObject>();
  mqtt["server"] = _config.mqtt.server;
  mqtt["port"] = _config.mqtt.port;
  mqtt["user"] = _config.mqtt.user;
  mqtt["password"] = _config.mqtt.password;
  mqtt["topic"] = _config.mqtt.topic;

  JsonObject http = cloud["http"].to<JsonObject>();
  http["url"] = _config.http.url;

  // Serial
  JsonObject serial = doc["serial"].to<JsonObject>();
  serial["interval_ms"] = _config.serial_interval_ms;

  // CAN
  JsonObject can = doc["can"].to<JsonObject>();
  can["enabled"] = _config.can.enabled;
  can["cs_pin"] = _config.can.cs_pin;
  can["int_pin"] = _config.can.int_pin;
  can["baud_kbps"] = _config.can.baud_kbps;
  can["crystal_mhz"] = _config.can.crystal_mhz;

  // OBD
  JsonObject obd = doc["obd"].to<JsonObject>();
  obd["enabled"] = _config.obd.enabled;
  obd["mode"] = _config.obd.mode;
  obd["pids_enabled"] = _config.obd.pids_enabled;
  obd["poll_interval_ms"] = _config.obd.poll_interval_ms;

  JsonObject elm = obd["elm_wifi"].to<JsonObject>();
  elm["ssid"] = _config.obd.elm_ssid;
  elm["password"] = _config.obd.elm_password;
  elm["ip"] = _config.obd.elm_ip;
  elm["port"] = _config.obd.elm_port;

  JsonObject uart = obd["uart"].to<JsonObject>();
  uart["rx_pin"] = _config.obd.uart_rx_pin;
  uart["tx_pin"] = _config.obd.uart_tx_pin;
  uart["baud"] = _config.obd.uart_baud;

  // GPS
  JsonObject gps = doc["gps"].to<JsonObject>();
  gps["enabled"] = _config.gps.enabled;
  gps["rx_pin"] = _config.gps.rx_pin;
  gps["tx_pin"] = _config.gps.tx_pin;
  gps["baud"] = _config.gps.baud;

  // IMU
  JsonObject imu = doc["imu"].to<JsonObject>();
  imu["enabled"] = _config.imu.enabled;
  imu["sda_pin"] = _config.imu.sda_pin;
  imu["scl_pin"] = _config.imu.scl_pin;

  // Fuel
  JsonObject fuel = doc["fuel"].to<JsonObject>();
  fuel["method"] = fuelMethodToString(_config.fuel.method);
  fuel["displacement_l"] = _config.fuel.displacement_l;
  fuel["volumetric_efficiency"] = _config.fuel.volumetric_efficiency;
  fuel["air_fuel_ratio"] = _config.fuel.air_fuel_ratio;
}

void ConfigManager::jsonToConfig(JsonDocument &doc) {
  // Version (solo lectura, forzamos la actual)
  strncpy(_config.version, CONFIG_VERSION, sizeof(_config.version) - 1);

  // Device
  if (doc["device"].is<JsonObject>()) {
    JsonObject device = doc["device"];
    if (device["id"])
      strncpy(_config.device_id, device["id"], sizeof(_config.device_id) - 1);
    if (device["car_id"])
      strncpy(_config.car_id, device["car_id"], sizeof(_config.car_id) - 1);
    if (device["source"])
      _config.source = stringToDataSource(device["source"]);
  }

  // WiFi
  if (doc["wifi"].is<JsonObject>()) {
    JsonObject wifi = doc["wifi"];
    if (wifi["ssid"])
      strncpy(_config.wifi.ssid, wifi["ssid"], sizeof(_config.wifi.ssid) - 1);
    if (wifi["password"])
      strncpy(_config.wifi.password, wifi["password"],
              sizeof(_config.wifi.password) - 1);
  }

  // Cloud
  if (doc["cloud"].is<JsonObject>()) {
    JsonObject cloud = doc["cloud"];
    if (cloud["protocol"]) {
      const char *proto = cloud["protocol"];
      _config.cloud_protocol = (strcmp(proto, "http") == 0)
                                   ? CloudProtocol::HTTP
                                   : CloudProtocol::MQTT;
    }
    if (cloud["interval_ms"])
      _config.cloud_interval_ms = cloud["interval_ms"];
    if (cloud["debug_mode"])
      _config.debug_mode = cloud["debug_mode"];

    if (cloud["mqtt"].is<JsonObject>()) {
      JsonObject mqtt = cloud["mqtt"];
      if (mqtt["server"])
        strncpy(_config.mqtt.server, mqtt["server"],
                sizeof(_config.mqtt.server) - 1);
      if (mqtt["port"])
        _config.mqtt.port = mqtt["port"];
      if (mqtt["user"])
        strncpy(_config.mqtt.user, mqtt["user"], sizeof(_config.mqtt.user) - 1);
      if (mqtt["password"])
        strncpy(_config.mqtt.password, mqtt["password"],
                sizeof(_config.mqtt.password) - 1);
      if (mqtt["topic"])
        strncpy(_config.mqtt.topic, mqtt["topic"],
                sizeof(_config.mqtt.topic) - 1);
    }

    if (cloud["http"].is<JsonObject>()) {
      JsonObject http = cloud["http"];
      if (http["url"])
        strncpy(_config.http.url, http["url"], sizeof(_config.http.url) - 1);
    }
  }

  // Serial
  if (doc["serial"].is<JsonObject>()) {
    JsonObject serial = doc["serial"];
    if (serial["interval_ms"])
      _config.serial_interval_ms = serial["interval_ms"];
  }

  // CAN
  if (doc["can"].is<JsonObject>()) {
    JsonObject can = doc["can"];
    if (can.containsKey("enabled"))
      _config.can.enabled = can["enabled"];
    if (can["cs_pin"])
      _config.can.cs_pin = can["cs_pin"];
    if (can["int_pin"])
      _config.can.int_pin = can["int_pin"];
    if (can["baud_kbps"])
      _config.can.baud_kbps = can["baud_kbps"];
    if (can["crystal_mhz"])
      _config.can.crystal_mhz = can["crystal_mhz"];
  }

  // OBD
  if (doc["obd"].is<JsonObject>()) {
    JsonObject obd = doc["obd"];
    if (obd.containsKey("enabled"))
      _config.obd.enabled = obd["enabled"];
    if (obd["mode"])
      strncpy(_config.obd.mode, obd["mode"], sizeof(_config.obd.mode) - 1);
    if (obd["pids_enabled"])
      strncpy(_config.obd.pids_enabled, obd["pids_enabled"],
              sizeof(_config.obd.pids_enabled) - 1);
    if (obd["poll_interval_ms"])
      _config.obd.poll_interval_ms = obd["poll_interval_ms"];

    if (obd["elm_wifi"].is<JsonObject>()) {
      JsonObject elm = obd["elm_wifi"];
      if (elm["ssid"])
        strncpy(_config.obd.elm_ssid, elm["ssid"],
                sizeof(_config.obd.elm_ssid) - 1);
      if (elm["password"])
        strncpy(_config.obd.elm_password, elm["password"],
                sizeof(_config.obd.elm_password) - 1);
      if (elm["ip"])
        strncpy(_config.obd.elm_ip, elm["ip"], sizeof(_config.obd.elm_ip) - 1);
      if (elm["port"])
        _config.obd.elm_port = elm["port"];
    }

    if (obd["uart"].is<JsonObject>()) {
      JsonObject uart = obd["uart"];
      if (uart["rx_pin"])
        _config.obd.uart_rx_pin = uart["rx_pin"];
      if (uart["tx_pin"])
        _config.obd.uart_tx_pin = uart["tx_pin"];
      if (uart["baud"])
        _config.obd.uart_baud = uart["baud"];
    }
  }

  // GPS
  if (doc["gps"].is<JsonObject>()) {
    JsonObject gps = doc["gps"];
    if (gps.containsKey("enabled"))
      _config.gps.enabled = gps["enabled"];
    if (gps["rx_pin"])
      _config.gps.rx_pin = gps["rx_pin"];
    if (gps["tx_pin"])
      _config.gps.tx_pin = gps["tx_pin"];
    if (gps["baud"])
      _config.gps.baud = gps["baud"];
  }

  // IMU
  if (doc["imu"].is<JsonObject>()) {
    JsonObject imu = doc["imu"];
    if (imu.containsKey("enabled"))
      _config.imu.enabled = imu["enabled"];
    if (imu["sda_pin"])
      _config.imu.sda_pin = imu["sda_pin"];
    if (imu["scl_pin"])
      _config.imu.scl_pin = imu["scl_pin"];
  }

  // Fuel
  if (doc["fuel"].is<JsonObject>()) {
    JsonObject fuel = doc["fuel"];
    if (fuel["method"])
      _config.fuel.method = stringToFuelMethod(fuel["method"]);
    if (fuel["displacement_l"])
      _config.fuel.displacement_l = fuel["displacement_l"];
    if (fuel["volumetric_efficiency"])
      _config.fuel.volumetric_efficiency = fuel["volumetric_efficiency"];
    if (fuel["air_fuel_ratio"])
      _config.fuel.air_fuel_ratio = fuel["air_fuel_ratio"];
  }
}

// ============================================================================
// SENSORS JSON
// ============================================================================

bool ConfigManager::loadSensorsFromJson(const String &json) {
  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, json);

  if (error) {
    Serial.printf("[CONFIG] Sensors JSON parse error: %s\n", error.c_str());
    return false;
  }

  jsonToSensors(doc);
  Serial.printf("[CONFIG] Loaded %d sensors from JSON\n", _sensors.size());
  return true;
}

String ConfigManager::exportSensorsToJson(bool pretty) {
  JsonDocument doc;
  sensorsToJson(doc);

  String output;
  if (pretty) {
    serializeJsonPretty(doc, output);
  } else {
    serializeJson(doc, output);
  }

  return output;
}

void ConfigManager::sensorsToJson(JsonDocument &doc) {
  JsonArray arr = doc["sensors"].to<JsonArray>();

  for (const auto &sensor : _sensors) {
    JsonObject obj = arr.add<JsonObject>();
    obj["name"] = sensor.name;
    obj["cloud_id"] = sensor.cloud_id;
    obj["can_id"] = sensor.can_id;
    obj["start_byte"] = sensor.start_byte;
    obj["start_bit"] = sensor.start_bit;
    obj["length"] = sensor.length;
    obj["signed"] = sensor.signed_val;
    obj["multiplier"] = sensor.multiplier;
    obj["offset"] = sensor.offset;
    obj["big_endian"] = sensor.big_endian;
    obj["enabled"] = sensor.enabled;
  }
}

void ConfigManager::jsonToSensors(JsonDocument &doc) {
  _sensors.clear();

  JsonArray arr = doc["sensors"].as<JsonArray>();
  if (!arr) {
    // Intentar con el doc como array directo
    arr = doc.as<JsonArray>();
  }

  if (!arr) {
    Serial.println(F("[CONFIG] No sensors array in JSON"));
    return;
  }

  for (JsonObject obj : arr) {
    if (_sensors.size() >= MAX_SENSORS) {
      Serial.println(F("[CONFIG] Max sensors reached"));
      break;
    }

    SensorConfig sensor = {};

    if (obj["name"])
      strncpy(sensor.name, obj["name"], sizeof(sensor.name) - 1);
    if (obj["cloud_id"])
      strncpy(sensor.cloud_id, obj["cloud_id"], sizeof(sensor.cloud_id) - 1);
    sensor.can_id = obj["can_id"] | 0;
    sensor.start_byte = obj["start_byte"] | 0;
    sensor.start_bit = obj["start_bit"] | 0;
    sensor.length = obj["length"] | 8;
    sensor.signed_val = obj["signed"] | false;
    sensor.multiplier = obj["multiplier"] | 1.0f;
    sensor.offset = obj["offset"] | 0.0f;
    sensor.big_endian = obj["big_endian"] | false;
    sensor.enabled = obj["enabled"] | true;

    // Runtime init
    sensor.value = 0;
    sensor.updated = false;

    // Detectar tipo de mapeo basado en cloud_id
    if (obj.containsKey("cloud_id")) {
      const char *cid = obj["cloud_id"];
      if (strcmp(cid, "engine.rpm") == 0 || strcmp(cid, "rpm") == 0)
        sensor.map_type = SensorConfig::MappingType::ENGINE_RPM;
      else if (strcmp(cid, "engine.speed") == 0 || strcmp(cid, "speed") == 0)
        sensor.map_type = SensorConfig::MappingType::ENGINE_SPEED;
      else if (strcmp(cid, "engine.coolant_temp") == 0 ||
               strcmp(cid, "temp") == 0)
        sensor.map_type = SensorConfig::MappingType::ENGINE_COOLANT;
      else if (strcmp(cid, "engine.oil_temp") == 0)
        sensor.map_type = SensorConfig::MappingType::ENGINE_OIL_TEMP;
      else if (strcmp(cid, "engine.throttle") == 0 || strcmp(cid, "tps") == 0)
        sensor.map_type = SensorConfig::MappingType::ENGINE_THROTTLE;
      else if (strcmp(cid, "engine.load") == 0)
        sensor.map_type = SensorConfig::MappingType::ENGINE_LOAD;
      else if (strcmp(cid, "engine.maf") == 0)
        sensor.map_type = SensorConfig::MappingType::ENGINE_MAF;
      else if (strcmp(cid, "engine.map") == 0)
        sensor.map_type = SensorConfig::MappingType::ENGINE_MAP;
      else if (strcmp(cid, "fuel.level") == 0 || strcmp(cid, "fuel") == 0)
        sensor.map_type = SensorConfig::MappingType::FUEL_LEVEL;
      else if (strcmp(cid, "fuel.rate") == 0)
        sensor.map_type = SensorConfig::MappingType::FUEL_RATE;
      else if (strcmp(cid, "battery.voltage") == 0 || strcmp(cid, "batt") == 0)
        sensor.map_type = SensorConfig::MappingType::BATTERY_VOLT;
      else
        sensor.map_type = SensorConfig::MappingType::CUSTOM;
    } else {
      sensor.map_type = SensorConfig::MappingType::CUSTOM;
    }

    _sensors.push_back(sensor);
  }
}

// ============================================================================
// HELPERS
// ============================================================================

void ConfigManager::resetToDefaults() {
  _config = getDefaultConfig();
  _sensors.clear();
  Serial.println(F("[CONFIG] Reset to defaults"));
}

void ConfigManager::printConfig() {
  Serial.println(F("\n========== CURRENT CONFIGURATION =========="));
  Serial.printf("Version: %s\n", _config.version);
  Serial.printf("Device ID: %s\n", _config.device_id);
  Serial.printf("Car ID: %s\n", _config.car_id);
  Serial.printf("Data Source: %s\n", dataSourceToString(_config.source));
  Serial.println(F("---"));
  Serial.printf("WiFi SSID: %s\n", _config.wifi.ssid);
  Serial.printf("Cloud Protocol: %s\n",
                (_config.cloud_protocol == CloudProtocol::MQTT) ? "MQTT"
                                                                : "HTTP");
  Serial.printf("Cloud Interval: %d ms\n", _config.cloud_interval_ms);
  Serial.printf("Debug Mode: %s\n", _config.debug_mode ? "YES" : "NO");
  Serial.println(F("---"));
  Serial.printf("CAN Enabled: %s (CS=%d, INT=%d, %dkbps)\n",
                _config.can.enabled ? "YES" : "NO", _config.can.cs_pin,
                _config.can.int_pin, _config.can.baud_kbps);
  Serial.printf("OBD Enabled: %s (mode=%s)\n",
                _config.obd.enabled ? "YES" : "NO", _config.obd.mode);
  Serial.printf("GPS Enabled: %s (RX=%d, TX=%d)\n",
                _config.gps.enabled ? "YES" : "NO", _config.gps.rx_pin,
                _config.gps.tx_pin);
  Serial.printf("IMU Enabled: %s\n", _config.imu.enabled ? "YES" : "NO");
  Serial.println(F("---"));
  Serial.printf("Sensors configured: %d\n", _sensors.size());
  Serial.println(F("=============================================\n"));
}

// ============================================================================
// P1.2: VALIDACIÓN DE CONFIGURACIÓN (ANTI-BRICK)
// ============================================================================

bool ConfigManager::validateConfig(String *errors) {
  bool valid = true;
  String errList = "";

  // === Validar pines CAN ===
  if (_config.can.enabled) {
    // ESP32 tiene pines 0-39, algunos reservados
    if (_config.can.cs_pin < 0 || _config.can.cs_pin > 39) {
      errList += "CAN CS pin invalid; ";
      valid = false;
    }
    if (_config.can.cs_pin >= 34 && _config.can.cs_pin <= 39) {
      errList += "CAN CS pin is Input-Only; ";
      valid = false;
    }
    // Pines reservados: 0, 1, 2, 6-11
    int reservedPins[] = {0, 1, 6, 7, 8, 9, 10, 11};
    for (int rp : reservedPins) {
      if (_config.can.cs_pin == rp || _config.can.int_pin == rp) {
        errList += "CAN uses reserved pin; ";
        valid = false;
        break;
      }
    }
  }

  // === Validar baudrate CAN ===
  if (_config.can.enabled) {
    if (_config.can.baud_kbps != 250 && _config.can.baud_kbps != 500 &&
        _config.can.baud_kbps != 1000) {
      errList += "CAN baud invalid (use 250/500/1000); ";
      valid = false;
    }
    if (_config.can.crystal_mhz != 8 && _config.can.crystal_mhz != 16) {
      errList += "CAN crystal invalid (use 8/16); ";
      valid = false;
    }
  }

  // === Validar pines GPS ===
  if (_config.gps.enabled) {
    if (_config.gps.rx_pin < 0 || _config.gps.rx_pin > 39) {
      errList += "GPS RX pin invalid; ";
      valid = false;
    }
    if (_config.gps.tx_pin < 0 || _config.gps.tx_pin > 39) {
      errList += "GPS TX pin invalid; ";
      valid = false;
    }
    if (_config.gps.tx_pin >= 34 && _config.gps.tx_pin <= 39) {
      errList += "GPS TX pin is Input-Only; ";
      valid = false;
    }
  }

  // === Validar pines IMU ===
  if (_config.imu.enabled) {
    if (_config.imu.sda_pin < 0 || _config.imu.sda_pin > 39) {
      errList += "IMU SDA pin invalid; ";
      valid = false;
    }
    if (_config.imu.scl_pin < 0 || _config.imu.scl_pin > 39) {
      errList += "IMU SCL pin invalid; ";
      valid = false;
    }
    if ((_config.imu.sda_pin >= 34 && _config.imu.sda_pin <= 39) ||
        (_config.imu.scl_pin >= 34 && _config.imu.scl_pin <= 39)) {
      errList += "IMU pins are Input-Only; ";
      valid = false;
    }
  }

  // === Validar pines OBD Bridge ===
  if (_config.obd.enabled && strcmp(_config.obd.mode, "bridge") == 0) {
    if (_config.obd.uart_tx_pin >= 34 && _config.obd.uart_tx_pin <= 39) {
      errList += "OBD TX pin is Input-Only; ";
      valid = false;
    }
  }

  // === Validar cloud interval ===
  if (_config.cloud_interval_ms < 50 || _config.cloud_interval_ms > 60000) {
    errList += "Cloud interval out of range (50-60000ms); ";
    valid = false;
  }

  // === Validar número de sensores ===
  if (_sensors.size() > MAX_SENSORS) {
    errList += "Too many sensors (max " + String(MAX_SENSORS) + "); ";
    valid = false;
  }

  // === Validar MQTT port ===
  if (_config.cloud_protocol == CloudProtocol::MQTT) {
    if (_config.mqtt.port == 0 || _config.mqtt.port > 65535) {
      errList += "MQTT port invalid; ";
      valid = false;
    }
  }

  // === Reportar errores ===
  if (!valid) {
    Serial.printf("[CONFIG] Validation FAILED: %s\n", errList.c_str());
    if (errors != nullptr) {
      *errors = errList;
    }
  } else {
    Serial.println(F("[CONFIG] Validation OK"));
  }

  return valid;
}

// ============================================================================
// P2.0: NORMALIZACIÓN DE CONFIGURACIÓN (ANTI-ZOMBIE)
// ============================================================================

void ConfigManager::normalizeConfig() {
  Serial.println(F("[CONFIG] Normalizing configuration based on source..."));

  // La Master Key es device.source - todos los demás flags deben ser coherentes
  switch (_config.source) {

  case DataSource::CAN_ONLY:
    // Solo CAN, OBD debe estar deshabilitado
    _config.can.enabled = true;
    _config.obd.enabled = false;
    Serial.println(F("[CONFIG] -> CAN_ONLY: can=ON, obd=OFF"));
    break;

  case DataSource::OBD_DIRECT:
    // Solo OBD Directo, CAN deshabilitado
    _config.can.enabled = false;
    _config.obd.enabled = true;
    strncpy(_config.obd.mode, "direct", sizeof(_config.obd.mode) - 1);
    Serial.println(F("[CONFIG] -> OBD_DIRECT: can=OFF, obd=ON, mode=direct"));
    break;

  case DataSource::OBD_BRIDGE:
    // Solo OBD Bridge, CAN deshabilitado
    _config.can.enabled = false;
    _config.obd.enabled = true;
    strncpy(_config.obd.mode, "bridge", sizeof(_config.obd.mode) - 1);
    Serial.println(F("[CONFIG] -> OBD_BRIDGE: can=OFF, obd=ON, mode=bridge"));
    break;

  case DataSource::CAN_OBD:
    // Híbrido: ambos activos, mode se respeta del JSON
    _config.can.enabled = true;
    _config.obd.enabled = true;
    // Validar que mode sea válido
    if (strcmp(_config.obd.mode, "bridge") != 0 &&
        strcmp(_config.obd.mode, "direct") != 0) {
      strncpy(_config.obd.mode, "bridge",
              sizeof(_config.obd.mode) - 1); // Default
      Serial.println(
          F("[CONFIG] -> CAN_OBD: Invalid obd.mode, defaulting to bridge"));
    }
    Serial.printf("[CONFIG] -> CAN_OBD: can=ON, obd=ON, mode=%s\n",
                  _config.obd.mode);
    break;

  case DataSource::SENSORS_ONLY:
    // Solo GPS/IMU, sin CAN ni OBD
    _config.can.enabled = false;
    _config.obd.enabled = false;
    Serial.println(F("[CONFIG] -> SENSORS_ONLY: can=OFF, obd=OFF"));
    break;

  default:
    // Modo desconocido, default a CAN_ONLY
    _config.source = DataSource::CAN_ONLY;
    _config.can.enabled = true;
    _config.obd.enabled = false;
    Serial.println(F("[CONFIG] -> UNKNOWN source, defaulting to CAN_ONLY"));
    break;
  }

  // Logging final
  Serial.printf("[CONFIG] Normalized: source=%s, can.enabled=%d, "
                "obd.enabled=%d, obd.mode=%s\n",
                dataSourceToString(_config.source), _config.can.enabled,
                _config.obd.enabled, _config.obd.mode);
}
