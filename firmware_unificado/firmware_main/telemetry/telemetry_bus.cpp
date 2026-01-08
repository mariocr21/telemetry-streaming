/**
 * @file telemetry_bus.cpp
 * @brief Implementación del TelemetryBus
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#include "telemetry_bus.h"
#include <WiFi.h>

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

void TelemetryBus::begin() {
  Serial.println(F("[TELEMETRY] Initializing TelemetryBus..."));

  // Crear mutex
  _mutex = xSemaphoreCreateMutex();

  if (_mutex == nullptr) {
    Serial.println(F("[TELEMETRY] ERROR: Failed to create mutex!"));
    return;
  }

  // Inicializar snapshot a ceros
  memset(&_snapshot, 0, sizeof(TelemetrySnapshot));

  // Inicializar valores genéricos
  _generic_count = 0;
  for (int i = 0; i < MAX_CUSTOM_VALUES; i++) {
    _generic_values[i].valid = false;
    _generic_keys[i][0] = '\0';
  }

  Serial.println(F("[TELEMETRY] TelemetryBus ready"));
}

// ============================================================================
// MUTEX HELPERS
// ============================================================================

bool TelemetryBus::takeMutex(TickType_t timeout) {
  if (_mutex == nullptr)
    return false;
  return xSemaphoreTake(_mutex, timeout) == pdTRUE;
}

void TelemetryBus::giveMutex() {
  if (_mutex != nullptr) {
    xSemaphoreGive(_mutex);
  }
}

// ============================================================================
// ESCRITURA GENÉRICA
// ============================================================================

bool TelemetryBus::setValue(const String &key, float value, const char *unit,
                            const char *source) {
  if (!takeMutex())
    return false;

  int index = -1;
  const char *keyStr = key.c_str();

  // Buscar si ya existe
  for (int i = 0; i < _generic_count; i++) {
    if (strcmp(_generic_keys[i], keyStr) == 0) {
      index = i;
      break;
    }
  }

  // Si no existe, buscar slot libre o crear uno
  if (index == -1 && _generic_count < MAX_CUSTOM_VALUES) {
    index = _generic_count++;
    strncpy(_generic_keys[index], keyStr, MAX_KEY_LEN - 1);
    _generic_keys[index][MAX_KEY_LEN - 1] = '\0';
  } else if (index == -1) {
    Serial.printf("[TELEMETRY] CRITICAL: Buffer full! Dropping key: %s\n",
                  keyStr);
  }

  if (index != -1) {
    _generic_values[index].value = value;
    _generic_values[index].timestamp = millis();
    _generic_values[index].updated = true;
    _generic_values[index].valid = true;
    strncpy(_generic_values[index].unit, unit,
            sizeof(_generic_values[index].unit) - 1);
    strncpy(_generic_values[index].source, source,
            sizeof(_generic_values[index].source) - 1);
  }

  giveMutex();
  return (index != -1);
}

void TelemetryBus::setValues(const String keys[], const float values[],
                             size_t count, const char *source) {
  for (size_t i = 0; i < count; i++) {
    setValue(keys[i], values[i], "", source);
  }
}

// ============================================================================
// SETTERS RÁPIDOS
// ============================================================================

void TelemetryBus::setGps(float lat, float lng, float alt, float speed,
                          float course, uint8_t sats, bool fix) {
  if (!takeMutex())
    return;

  _snapshot.gps_lat = lat;
  _snapshot.gps_lng = lng;
  _snapshot.gps_alt = alt;
  _snapshot.gps_speed = speed;
  _snapshot.gps_course = course;
  _snapshot.gps_sats = sats;
  _snapshot.gps_fix = fix;
  _snapshot.ts_gps = millis(); // P1.1: timestamp

  giveMutex();
}

void TelemetryBus::setImuAccel(float x, float y, float z) {
  if (!takeMutex())
    return;

  _snapshot.imu_accel_x = x;
  _snapshot.imu_accel_y = y;
  _snapshot.imu_accel_z = z;
  _snapshot.ts_imu = millis(); // P1.1: timestamp

  giveMutex();
}

void TelemetryBus::setImuGyro(float x, float y, float z) {
  if (!takeMutex())
    return;

  _snapshot.imu_gyro_x = x;
  _snapshot.imu_gyro_y = y;
  _snapshot.imu_gyro_z = z;

  giveMutex();
}

void TelemetryBus::setEngineRpm(float rpm) {
  if (!takeMutex())
    return;
  _snapshot.engine_rpm = rpm;
  _snapshot.ts_engine = millis(); // P1.1: timestamp
  giveMutex();
}

void TelemetryBus::setEngineSpeed(float speed) {
  if (!takeMutex())
    return;
  _snapshot.engine_speed = speed;
  _snapshot.ts_engine = millis(); // P1.1: timestamp (cualquier dato de motor)
  giveMutex();
}

void TelemetryBus::setEngineCoolantTemp(float temp) {
  if (!takeMutex())
    return;
  _snapshot.engine_coolant_temp = temp;
  _snapshot.ts_engine = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setEngineOilTemp(float temp) {
  if (!takeMutex())
    return;
  _snapshot.engine_oil_temp = temp;
  _snapshot.ts_engine = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setEngineThrottle(float throttle) {
  if (!takeMutex())
    return;
  _snapshot.engine_throttle = throttle;
  _snapshot.ts_engine = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setEngineLoad(float load) {
  if (!takeMutex())
    return;
  _snapshot.engine_load = load;
  _snapshot.ts_engine = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setEngineMaf(float maf) {
  if (!takeMutex())
    return;
  _snapshot.engine_maf = maf;
  _snapshot.ts_engine = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setEngineMap(float mapVal) {
  if (!takeMutex())
    return;
  _snapshot.engine_map = mapVal;
  _snapshot.ts_engine = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setFuelLevel(float level) {
  if (!takeMutex())
    return;
  _snapshot.fuel_level = level;
  _snapshot.ts_fuel = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setFuelRate(float rate) {
  if (!takeMutex())
    return;
  _snapshot.fuel_rate = rate;
  _snapshot.ts_fuel = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setFuelTotal(float total) {
  if (!takeMutex())
    return;
  _snapshot.fuel_total = total;
  _snapshot.ts_fuel = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setBatteryVoltage(float voltage) {
  if (!takeMutex())
    return;
  _snapshot.battery_voltage = voltage;
  _snapshot.ts_battery = millis(); // P1.1
  giveMutex();
}

void TelemetryBus::setSuspension(float fl, float fr, float rl, float rr) {
  if (!takeMutex())
    return;

  _snapshot.susp_fl = fl;
  _snapshot.susp_fr = fr;
  _snapshot.susp_rl = rl;
  _snapshot.susp_rr = rr;

  giveMutex();
}

void TelemetryBus::setCustomValue(const char *cloud_id, float value) {
  if (!takeMutex())
    return;

  int index = -1;
  // Buscar en el array de snapshot
  for (int i = 0; i < _snapshot.custom_count; i++) {
    if (strcmp(_snapshot.custom_values[i].key, cloud_id) == 0) {
      index = i;
      break;
    }
  }

  // Si no existe y hay espacio, agregar
  if (index == -1 && _snapshot.custom_count < MAX_CUSTOM_VALUES) {
    index = _snapshot.custom_count++;
    strncpy(_snapshot.custom_values[index].key, cloud_id, MAX_KEY_LEN - 1);
    _snapshot.custom_values[index].key[MAX_KEY_LEN - 1] = '\0';
  } else if (index == -1) {
    Serial.printf(
        "[TELEMETRY] CRITICAL: Custom Buffer full! Dropping key: %s\n",
        cloud_id);
  }

  if (index != -1) {
    _snapshot.custom_values[index].value = value;
    _snapshot.custom_values[index].updated = true;
  }

  giveMutex();
}

// ============================================================================
// LECTURA
// ============================================================================

bool TelemetryBus::getValue(const String &key, TelemetryValue &out) {
  if (!takeMutex())
    return false;

  bool found = false;
  const char *keyStr = key.c_str();

  for (int i = 0; i < _generic_count; i++) {
    if (strcmp(_generic_keys[i], keyStr) == 0) {
      out = _generic_values[i];
      found = true;
      break;
    }
  }

  giveMutex();
  return found;
}

void TelemetryBus::getSnapshot(TelemetrySnapshot &snapshot) {
  if (!takeMutex())
    return;

  // Copiar snapshot directo
  snapshot = _snapshot;

  // Agregar metadata
  uint32_t now = millis();
  snapshot.uptime_ms = now;
  snapshot.wifi_rssi = WiFi.isConnected() ? WiFi.RSSI() : 0;
  snapshot.heap_free = ESP.getFreeHeap();

  // ================================================================
  // P1.1: Calcular flags de validez (stale detection)
  // Datos son STALE si tienen más de 2000ms de antigüedad
  // ================================================================
  constexpr uint32_t STALE_THRESHOLD_MS = 2000;

  // GPS válido si tiene fix Y datos frescos
  snapshot.gps_valid = snapshot.gps_fix && (snapshot.ts_gps > 0) &&
                       ((now - snapshot.ts_gps) < STALE_THRESHOLD_MS);

  // Engine válido si hay RPM > 0 Y datos frescos
  // Engine válido si hay datos frescos (NO depende de RPM>0 para permitir 0)
  snapshot.engine_valid = (snapshot.ts_engine > 0) &&
                          ((now - snapshot.ts_engine) < STALE_THRESHOLD_MS);

  giveMutex();
}

void TelemetryBus::getAllValues(TelemetryValue *outArray,
                                char keys[][MAX_KEY_LEN], size_t maxCount,
                                size_t &actualCount) {
  if (!takeMutex()) {
    actualCount = 0;
    return;
  }

  actualCount = (_generic_count < maxCount) ? _generic_count : maxCount;

  for (size_t i = 0; i < actualCount; i++) {
    outArray[i] = _generic_values[i];
    strncpy(keys[i], _generic_keys[i], MAX_KEY_LEN - 1);
    keys[i][MAX_KEY_LEN - 1] = '\0';
  }

  giveMutex();
}

void TelemetryBus::clearUpdatedFlags() {
  if (!takeMutex())
    return;

  for (int i = 0; i < _generic_count; i++) {
    _generic_values[i].updated = false;
  }

  for (int i = 0; i < _snapshot.custom_count; i++) {
    _snapshot.custom_values[i].updated = false;
  }

  giveMutex();
}

size_t TelemetryBus::countUpdated() {
  if (!takeMutex())
    return 0;

  size_t count = 0;
  for (int i = 0; i < _generic_count; i++) {
    if (_generic_values[i].updated)
      count++;
  }

  for (int i = 0; i < _snapshot.custom_count; i++) {
    if (_snapshot.custom_values[i].updated)
      count++;
  }

  giveMutex();
  return count;
}

void TelemetryBus::printStatus() {
  if (!takeMutex())
    return;

  Serial.println(F("\n========== TELEMETRY BUS STATUS =========="));
  Serial.printf(
      "GPS: %.6f, %.6f (alt=%.1fm, speed=%.1f km/h, sats=%d, fix=%s)\n",
      _snapshot.gps_lat, _snapshot.gps_lng, _snapshot.gps_alt,
      _snapshot.gps_speed, _snapshot.gps_sats,
      _snapshot.gps_fix ? "YES" : "NO");
  Serial.printf("IMU Accel: X=%.2f Y=%.2f Z=%.2f\n", _snapshot.imu_accel_x,
                _snapshot.imu_accel_y, _snapshot.imu_accel_z);
  Serial.printf("Engine: RPM=%.0f Speed=%.1f Coolant=%.1f Throttle=%.1f%%\n",
                _snapshot.engine_rpm, _snapshot.engine_speed,
                _snapshot.engine_coolant_temp, _snapshot.engine_throttle);
  Serial.printf("Fuel: Level=%.1f%% Rate=%.2f L/h Total=%.2f L\n",
                _snapshot.fuel_level, _snapshot.fuel_rate,
                _snapshot.fuel_total);
  Serial.printf("Battery: %.2f V\n", _snapshot.battery_voltage);
  Serial.printf("Custom values: %d\n", _snapshot.custom_count);
  for (int i = 0; i < _snapshot.custom_count; i++) {
    Serial.printf("  [%s]: %.2f\n", _snapshot.custom_values[i].key,
                  _snapshot.custom_values[i].value);
  }
  Serial.printf("Generic values: %d\n", _generic_count);
  Serial.println(F("=============================================\n"));

  giveMutex();
}
