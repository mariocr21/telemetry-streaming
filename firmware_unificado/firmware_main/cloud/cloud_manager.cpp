/**
 * @file cloud_manager.cpp
 * @brief Implementación del CloudManager - VERSION RESILIENTE
 *
 * MODIFICADO: Plan Safety-Critical
 * - P0.1: Buffer Offline (OfflineBuffer)
 * - P0.2: Red no bloqueante (State Machine)
 * - P0.4: Timeouts agresivos
 *
 * @author Neurona Racing Development
 * @date 2024-12-20
 */

#include "cloud_manager.h"
#include "../config/config_manager.h"
#include "../status_led.h" // Importar StatusLed
#include "../telemetry/telemetry_bus.h"
#include <ArduinoJson.h>
#include <esp_task_wdt.h>

extern StatusLed ledCloud; // Referencia al LED definido en main.cpp

// ... (existing code)

// ============================================================================
// ENVÍO
// ============================================================================

bool CloudManager::sendMqtt(const String &payload) {
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (!_mqttClient.connected()) {
    return false;
  }

  uint32_t t0 = millis();
  bool success = _mqttClient.publish(cfg.mqtt.topic, payload.c_str());
  uint32_t elapsed = millis() - t0;

  // LOG SI TARDA MÁS DE 100ms
  if (elapsed > 100) {
    Serial.printf("[CLOUD] ⚠️ SLOW PUBLISH: %lums\n", elapsed);
  }

  if (success) {
    if (_statusLed)
      _statusLed->flash(); // Visual feedback safest way
  } else {
    Serial.println(F("[CLOUD] MQTT publish failed"));
  }

  return success;
}

bool CloudManager::sendHttp(const String &payload) {
  auto &cfg = ConfigManager::getInstance().getConfig();

  HTTPClient http;
  http.setTimeout(HTTP_TIMEOUT_MS); // P0.4: Timeout agresivo
  http.begin(cfg.http.url);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(payload);
  http.end();

  if (httpCode >= 200 && httpCode < 300) {
    if (_statusLed)
      _statusLed->flash(); // Visual feedback safest way
    return true;
  }

  Serial.printf("[CLOUD] HTTP POST failed, code: %d\n", httpCode);
  return false;
}

CloudManager::CloudManager()
    : _mqttClient(_wifiClient), _taskHandle(nullptr),
      _networkState(NetworkState::DISCONNECTED), _stateEnteredAt(0),
      _lastWifiAttempt(0), _lastMqttAttempt(0), _wifiRetryCount(0),
      _mqttRetryCount(0), _successCount(0), _failCount(0), _offlineSaved(0),
      _offlineSent(0), _lastSendTime(0) {}

// ==========================================================================
// FAST PATH: EVENT-DRIVEN PUBLISH
// ==========================================================================

void CloudManager::requestImmediatePublish() {
  portENTER_CRITICAL(&_immediateMux);
  _immediatePublishPending = true;
  portEXIT_CRITICAL(&_immediateMux);
}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

bool CloudManager::begin() {
  Serial.println(F("[CLOUD] Initializing CloudManager (RESILIENT MODE)..."));

  auto &cfg = ConfigManager::getInstance().getConfig();

  // Inicializar buffer offline (P0.1)
  OfflineBuffer::getInstance().begin();

  // Configurar MQTT con timeout (P0.4)
  if (cfg.cloud_protocol == CloudProtocol::MQTT) {
    _mqttClient.setServer(cfg.mqtt.server, cfg.mqtt.port);
    _mqttClient.setBufferSize(4096);
    _mqttClient.setSocketTimeout(MQTT_CONNECT_TIMEOUT_MS / 1000); // Segundos
  }

  // Estado inicial: DISCONNECTED - intentará conectar en el loop
  _networkState = NetworkState::DISCONNECTED;
  _stateEnteredAt = millis();

  Serial.println(F("[CLOUD] CloudManager ready (non-blocking mode)"));
  Serial.println(
      F("[CLOUD] *** VERSION 2024-12-26 FIX-REALTIME ***")); // MARKER
  return true;
}

// ============================================================================
// STATE MACHINE - RED NO BLOQUEANTE (P0.2)
// ============================================================================

void CloudManager::updateNetworkState() {
  auto &cfg = ConfigManager::getInstance().getConfig();
  unsigned long now = millis();

  switch (_networkState) {
  // -----------------------------------------------------------------
  case NetworkState::DISCONNECTED:
    // Verificar si es momento de intentar WiFi (backoff)
    if (now - _lastWifiAttempt >= getWifiRetryDelay()) {
      if (startWifiConnection()) {
        _networkState = NetworkState::CONNECTING_WIFI;
        _stateEnteredAt = now;
      }
      _lastWifiAttempt = now;
    }
    break;

  // -----------------------------------------------------------------
  case NetworkState::CONNECTING_WIFI:
    // Timeout de conexión WiFi
    if (now - _stateEnteredAt > WIFI_CONNECT_TIMEOUT_MS) {
      Serial.printf("[CLOUD] WiFi connection timeout (Status: %d)\n",
                    WiFi.status());
      // Status codes: 0=IDLE, 1=NO_SSID, 2=SCAN_COMPL, 3=CONNECTED, 4=FAIL,
      // 5=LOST, 6=DISCONNECTED

      WiFi.disconnect(true); // Cancelar intento
      _wifiRetryCount++;
      _networkState = NetworkState::DISCONNECTED;
      _stateEnteredAt = now;
      break;
    }

    // Verificar si conectó
    if (checkWifiConnection()) {
      Serial.printf("[CLOUD] WiFi connected! IP: %s, RSSI: %d dBm\n",
                    WiFi.localIP().toString().c_str(), WiFi.RSSI());
      resetWifiBackoff();
      _networkState = NetworkState::WIFI_OK;
      _stateEnteredAt = now;
    }
    break;

  // -----------------------------------------------------------------
  case NetworkState::WIFI_OK:
    // Verificar que WiFi sigue conectado
    if (!WiFi.isConnected()) {
      Serial.println(F("[CLOUD] WiFi lost!"));
      _networkState = NetworkState::DISCONNECTED;
      _stateEnteredAt = now;
      break;
    }

    // Si usamos MQTT, intentar conectar
    if (cfg.cloud_protocol == CloudProtocol::MQTT) {
      if (now - _lastMqttAttempt >= getMqttRetryDelay()) {
        if (startMqttConnection()) {
          _networkState = NetworkState::CONNECTING_MQTT;
          _stateEnteredAt = now;
        }
        _lastMqttAttempt = now;
      }
    }
    break;

  // -----------------------------------------------------------------
  case NetworkState::CONNECTING_MQTT:
    // Verificar WiFi primero
    if (!WiFi.isConnected()) {
      Serial.println(F("[CLOUD] WiFi lost during MQTT connect"));
      _networkState = NetworkState::DISCONNECTED;
      _stateEnteredAt = now;
      break;
    }

    // Timeout de conexión MQTT
    if (now - _stateEnteredAt > MQTT_CONNECT_TIMEOUT_MS) {
      Serial.printf("[CLOUD] MQTT connection timeout (State: %d)\n",
                    _mqttClient.state());
      _mqttRetryCount++;
      _networkState = NetworkState::WIFI_OK;
      _stateEnteredAt = now;
      break;
    }

    // Verificar si conectó
    if (checkMqttConnection()) {
      Serial.println(F("[CLOUD] MQTT connected!"));
      resetMqttBackoff();
      _networkState = NetworkState::MQTT_OK;
      _stateEnteredAt = now;

      // Drenar buffer offline (P0.1)
      drainOfflineBuffer();
    }
    break;

  // -----------------------------------------------------------------
  case NetworkState::MQTT_OK:
    // Verificar conexiones
    if (!WiFi.isConnected()) {
      Serial.println(F("[CLOUD] WiFi lost!"));
      _networkState = NetworkState::DISCONNECTED;
      _stateEnteredAt = now;
      break;
    }

    if (!_mqttClient.connected()) {
      Serial.println(F("[CLOUD] MQTT disconnected"));
      _networkState = NetworkState::WIFI_OK;
      _stateEnteredAt = now;
      break;
    }

    // Mantener conexión MQTT
    _mqttClient.loop();
    break;
  }
}

// ============================================================================
// CONEXIONES NO BLOQUEANTES
// ============================================================================

bool CloudManager::startWifiConnection() {
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (strlen(cfg.wifi.ssid) == 0) {
    return false;
  }

  // Debug de credenciales (longitud) para detectar caracteres ocultos
  Serial.printf("[CLOUD] Starting WiFi connection to: '%s' (Len: %d, PassLen: "
                "%d) (attempt #%d)\n",
                cfg.wifi.ssid, strlen(cfg.wifi.ssid), strlen(cfg.wifi.password),
                _wifiRetryCount + 1);

  // Secuencia estricta de reinicio WiFi
  WiFi.disconnect(true);          // Borrar credenciales previas y apagar
  WiFi.mode(WIFI_OFF);            // Apagar radio
  vTaskDelay(pdMS_TO_TICKS(100)); // Esperar un poco

  WiFi.mode(WIFI_STA); // Encender en modo estación
  WiFi.begin(cfg.wifi.ssid, cfg.wifi.password);

  return true; // Inició el intento (no bloqueante)
}

bool CloudManager::checkWifiConnection() {
  return WiFi.status() == WL_CONNECTED;
}

bool CloudManager::startMqttConnection() {
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (!WiFi.isConnected()) {
    return false;
  }

  Serial.printf("[CLOUD] Starting MQTT connection to: %s:%d (attempt #%d)\n",
                cfg.mqtt.server, cfg.mqtt.port, _mqttRetryCount + 1);

  String clientId = "neurona_" + String(cfg.device_id);

  bool connected;
  if (strlen(cfg.mqtt.user) > 0) {
    connected =
        _mqttClient.connect(clientId.c_str(), cfg.mqtt.user, cfg.mqtt.password);
  } else {
    connected = _mqttClient.connect(clientId.c_str());
  }

  return connected;
}

bool CloudManager::checkMqttConnection() { return _mqttClient.connected(); }

// ============================================================================
// BACKOFF EXPONENCIAL
// ============================================================================

void CloudManager::resetWifiBackoff() { _wifiRetryCount = 0; }

void CloudManager::resetMqttBackoff() { _mqttRetryCount = 0; }

unsigned long CloudManager::getWifiRetryDelay() {
  unsigned long delay = WIFI_RETRY_BASE_MS;
  for (uint8_t i = 0; i < _wifiRetryCount && i < 10; i++) {
    delay *= BACKOFF_MULTIPLIER;
    if (delay > WIFI_RETRY_MAX_MS) {
      delay = WIFI_RETRY_MAX_MS;
      break;
    }
  }
  return delay;
}

unsigned long CloudManager::getMqttRetryDelay() {
  unsigned long delay = MQTT_RETRY_BASE_MS;
  for (uint8_t i = 0; i < _mqttRetryCount && i < 10; i++) {
    delay *= BACKOFF_MULTIPLIER;
    if (delay > MQTT_RETRY_MAX_MS) {
      delay = MQTT_RETRY_MAX_MS;
      break;
    }
  }
  return delay;
}

// ============================================================================
// TAREA FREERTOS
// ============================================================================

void CloudManager::startTask() {
  xTaskCreatePinnedToCore(
      taskFunction, "CloudTask",
      16384, // 16KB (Increased from 8KB to prevent stack
             // overflow with large JSON/Snapshot)
      this,
      2, // Prioridad MEDIA (Mayor que GPS/Sensores para asegurar WDT)
      &_taskHandle,
      1 // Core 1
  );

  if (_taskHandle != nullptr) {
    Serial.println(F("[CLOUD] Task started on Core 1 (resilient mode)"));
  }
}

void CloudManager::stopTask() {
  if (_taskHandle != nullptr) {
    vTaskDelete(_taskHandle);
    _taskHandle = nullptr;
    Serial.println(F("[CLOUD] Task stopped"));
  }
}

void CloudManager::taskFunction(void *param) {
  CloudManager *self = static_cast<CloudManager *>(param);

  Serial.printf("[CLOUD] Task running on core %d\n", xPortGetCoreID());

  // P0.3: Cloud task NO se registra en WDT para evitar reinicios por delays de
  // red esp_task_wdt_add(NULL);

  while (true) {
    self->taskLoop();
  }
}

void CloudManager::taskLoop() {
  // Reset watchdog cada ciclo (DESACTIVADO - CloudTask no monitoreada)
  // esp_task_wdt_reset();

  static uint32_t loopCount = 0;
  loopCount++;
  uint32_t loopStart = millis();

  auto &cfg = ConfigManager::getInstance().getConfig();

  // Si no hay configuración de WiFi, no hacemos nada (evitar llenar buffer
  // offline inútilmente)
  if (strlen(cfg.wifi.ssid) == 0) {
    // Slow blink en LED Cloud (indicado en main loop, pero aquí dormimos)
    vTaskDelay(pdMS_TO_TICKS(1000));
    return;
  }

  // === Actualizar State Machine (P0.2) ===
  uint32_t t1 = millis();
  updateNetworkState();
  uint32_t stateTime = millis() - t1;

  // LOG SI updateNetworkState TARDA MÁS DE 100ms
  if (stateTime > 100) {
    Serial.printf("[CLOUD] ⚠️ SLOW updateNetworkState: %lums\n", stateTime);
  }

  // === Envío de telemetría ===
  // BUGFIX: Re-calcular 'now' AQUÍ para timing preciso después de
  // updateNetworkState
  unsigned long now = millis();
  unsigned long elapsed = now - _lastSendTime;

  // ==============================================================
  // FAST-PATH: si hay datos nuevos (ej: llegan del C3) pedimos publish.
  // Esto hace que el envío cloud sea "data-driven" en vez de un timer fijo.
  //
  // - Respeta el throttle cfg.cloud_interval_ms (100–200ms recomendado).
  // - También deja un "heartbeat" lento para mantener visibilidad si no
  //   llegan datos.
  // ==============================================================

  bool immediatePending = false;
  portENTER_CRITICAL(&_immediateMux);
  immediatePending = _immediatePublishPending;
  portEXIT_CRITICAL(&_immediateMux);

  constexpr uint32_t HEARTBEAT_TX_MS = 1000;
  const bool throttleOk = (elapsed >= cfg.cloud_interval_ms);
  const bool heartbeatDue = (elapsed >= HEARTBEAT_TX_MS);
  const bool shouldSend = throttleOk && (immediatePending || heartbeatDue);

  if (shouldSend) {
    _lastSendTime = now;

    // Si entramos por publish inmediato, limpiar el flag (sin bloquear otras
    // tareas más de lo necesario)
    if (immediatePending) {
      portENTER_CRITICAL(&_immediateMux);
      _immediatePublishPending = false;
      portEXIT_CRITICAL(&_immediateMux);
    }

    // DIAGNÓSTICO: Medir tiempo de buildPayload
    uint32_t t2 = millis();
    String payload = buildPayload();
    uint32_t buildTime = millis() - t2;

    bool success = false;

    // Log periódico de envío
    static uint32_t sendCount = 0;
    sendCount++;

    if (cfg.cloud_protocol == CloudProtocol::MQTT) {
      if (_networkState == NetworkState::MQTT_OK) {
        // DIAGNÓSTICO: Medir tiempo de sendMqtt
        uint32_t t3 = millis();
        success = sendMqtt(payload);
        uint32_t sendTime = millis() - t3;

        // Métricas de latencia
        _lastPublishMs = now;
        _lastPublishLatencyMs = buildTime + sendTime;

        const char *srcName = dataSourceToString(cfg.source);
        Serial.printf("[CLOUD] 📡 MQTT TX #%lu (%s) - %s (%d bytes, "
                      "elapsed=%lums, build=%lums, send=%lums)\n",
                      sendCount, srcName, success ? "OK" : "FAIL",
                      payload.length(), elapsed, buildTime, sendTime);
      } else {
        // DIAGNÓSTICO: Log cuando NO estamos en MQTT_OK
        Serial.printf(
            "[CLOUD] ⚠️ Skip TX #%lu - NetworkState=%s (not MQTT_OK)\n",
            sendCount, networkStateToString(_networkState));
      }

      if (!success) {
        // Guardar en buffer offline (P0.1)
        if (OfflineBuffer::getInstance().push(payload)) {
          _offlineSaved++;
        }
        _failCount++;
      } else {
        _successCount++;
      }
    } else {
      // HTTP mode
      if (WiFi.isConnected()) {
        success = sendHttp(payload);
        Serial.printf("[CLOUD] 📡 HTTP TX #%lu - %s\n", sendCount,
                      success ? "OK" : "FAIL");
      }

      if (!success) {
        _failCount++;
      } else {
        _successCount++;
      }
    }
  }

  // DIAGNÓSTICO: Log del tiempo total del loop cada 1000 ciclos
  uint32_t loopTime = millis() - loopStart;
  if (loopTime > 50 || loopCount % 1000 == 0) {
    Serial.printf("[CLOUD] Loop #%lu took %lums (state=%lums)\n", loopCount,
                  loopTime, stateTime);
  }

  // Pequeño yield - Mínimo para no bloquear otras tareas
  // OPTIMIZADO: Reducido de 10ms a 1ms para mejor tiempo real
  vTaskDelay(pdMS_TO_TICKS(1));
}

// ============================================================================
// BUFFER OFFLINE DRAIN (P0.1)
// ============================================================================

void CloudManager::drainOfflineBuffer() {
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (OfflineBuffer::getInstance().isEmpty()) {
    return;
  }

  Serial.printf("[CLOUD] Draining offline buffer (%d frames)...\n",
                OfflineBuffer::getInstance().count());

  int batchCount = 0;
  String payload;

  while (!OfflineBuffer::getInstance().isEmpty() &&
         batchCount < OFFLINE_DRAIN_BATCH_SIZE) {

    if (!_mqttClient.connected()) {
      Serial.println(F("[CLOUD] MQTT lost during drain, stopping"));
      return;
    }

    if (OfflineBuffer::getInstance().pop(payload)) {
      bool success = _mqttClient.publish(cfg.mqtt.topic, payload.c_str());

      if (success) {
        _offlineSent++;
        batchCount++;
      } else {
        // No pudimos enviar, devolver al buffer y salir
        OfflineBuffer::getInstance().push(payload);
        Serial.println(F("[CLOUD] Drain failed, stopping"));
        return;
      }

      // Pequeño delay entre mensajes para no saturar
      vTaskDelay(pdMS_TO_TICKS(OFFLINE_DRAIN_DELAY_MS));
    }
  }

  if (batchCount > 0) {
    Serial.printf("[CLOUD] Drained %d frames, %d remaining\n", batchCount,
                  OfflineBuffer::getInstance().count());
  }
}

// ============================================================================
// PAYLOAD
// ============================================================================

String CloudManager::buildPayload() {
  auto &cfg = ConfigManager::getInstance().getConfig();
  TelemetrySnapshot snapshot;
  TelemetryBus::getInstance().getSnapshot(snapshot);

  JsonDocument doc;

  // Formato de trama original MoTeC
  doc["id"] = cfg.device_id;
  doc["idc"] = cfg.car_id;
  doc["d"] = cfg.debug_mode;

  // Timestamp
  // BUGFIX: getLocalTime() tiene timeout default de 5000ms si NTP no está
  // sincronizado Usamos timeout de 10ms para no bloquear el envío de telemetría
  char dt_buffer[32] = "1970-01-01 00:00:00";
  struct tm timeinfo;
  if (getLocalTime(&timeinfo, 10)) { // 10ms timeout (era 5000ms default!)
    strftime(dt_buffer, sizeof(dt_buffer), "%Y-%m-%d %H:%M:%S", &timeinfo);
  }
  doc["dt"] = dt_buffer;

  // Objeto de sensores
  JsonObject s = doc["s"].to<JsonObject>();

  // === GPS ===
  if (snapshot.gps_fix) {
    JsonObject lat_obj = s["lat"].to<JsonObject>();
    lat_obj["v"] = serialized(String(snapshot.gps_lat, 6));

    JsonObject lng_obj = s["lng"].to<JsonObject>();
    lng_obj["v"] = serialized(String(snapshot.gps_lng, 6));

    JsonObject vel_obj = s["vel_kmh"].to<JsonObject>();
    vel_obj["v"] = snapshot.gps_speed;

    JsonObject alt_obj = s["alt_m"].to<JsonObject>();
    alt_obj["v"] = snapshot.gps_alt;

    JsonObject rumbo_obj = s["rumbo"].to<JsonObject>();
    rumbo_obj["v"] = snapshot.gps_course;

    JsonObject sats_obj = s["gps_sats"].to<JsonObject>();
    sats_obj["v"] = snapshot.gps_sats;
  }

  // === IMU ===
  if (cfg.imu.enabled) {
    JsonObject accel_x = s["accel_x"].to<JsonObject>();
    accel_x["v"] = snapshot.imu_accel_x;

    JsonObject accel_y = s["accel_y"].to<JsonObject>();
    accel_y["v"] = snapshot.imu_accel_y;

    JsonObject accel_z = s["accel_z"].to<JsonObject>();
    accel_z["v"] = snapshot.imu_accel_z;

    JsonObject gyro_x = s["gyro_x"].to<JsonObject>();
    gyro_x["v"] = snapshot.imu_gyro_x;

    JsonObject gyro_y = s["gyro_y"].to<JsonObject>();
    gyro_y["v"] = snapshot.imu_gyro_y;

    JsonObject gyro_z = s["gyro_z"].to<JsonObject>();
    gyro_z["v"] = snapshot.imu_gyro_z;
  }

  // === ENGINE ===
  if (snapshot.engine_rpm != 0) {
    JsonObject rpm = s["0x0C"].to<JsonObject>(); // RPM
    rpm["v"] = snapshot.engine_rpm;
  }
  if (snapshot.engine_speed != 0) {
    JsonObject speed = s["0x0D"].to<JsonObject>(); // Speed
    speed["v"] = snapshot.engine_speed;
  }
  if (snapshot.engine_coolant_temp != 0) {
    JsonObject coolant = s["0x05"].to<JsonObject>(); // Coolant Temp
    coolant["v"] = snapshot.engine_coolant_temp;
  }
  if (snapshot.engine_oil_temp != 0) {
    JsonObject oil = s["0x5C"].to<JsonObject>(); // Oil Temp
    oil["v"] = snapshot.engine_oil_temp;
  }
  if (snapshot.engine_throttle != 0) {
    JsonObject tps = s["0x11"].to<JsonObject>(); // TPS
    tps["v"] = snapshot.engine_throttle;
  }
  if (snapshot.engine_load != 0) {
    JsonObject load = s["0x04"].to<JsonObject>(); // Load
    load["v"] = snapshot.engine_load;
  }
  if (snapshot.engine_maf != 0) {
    JsonObject maf = s["0x10"].to<JsonObject>(); // MAF
    maf["v"] = snapshot.engine_maf;
  }
  if (snapshot.engine_map != 0) {
    JsonObject map_s = s["0x0B"].to<JsonObject>(); // MAP
    map_s["v"] = snapshot.engine_map;
  }

  // === FUEL ===
  if (snapshot.fuel_level != 0) {
    JsonObject fuel_lvl = s["0x2F"].to<JsonObject>(); // Fuel Level
    fuel_lvl["v"] = snapshot.fuel_level;
  }
  if (snapshot.fuel_rate != 0) {
    JsonObject fuel_rate = s["0x5E"].to<JsonObject>(); // Fuel Rate
    fuel_rate["v"] = snapshot.fuel_rate;
  }
  if (snapshot.fuel_total != 0) {
    JsonObject fuel_total =
        s["fuel_total"]
            .to<JsonObject>(); // Calculated (No PID standard, keep name)
    fuel_total["v"] = snapshot.fuel_total;
  }

  // === BATTERY ===
  if (snapshot.battery_voltage != 0) {
    JsonObject batt =
        s["BAT"].to<JsonObject>(); // Control Module Voltage pid 0x42
    batt["v"] = snapshot.battery_voltage;
  }

  // === SUSPENSION ===
  if (snapshot.susp_fl != 0 || snapshot.susp_fr != 0) {
    JsonObject susp_fl = s["susp_fl"].to<JsonObject>();
    susp_fl["v"] = snapshot.susp_fl;

    JsonObject susp_fr = s["susp_fr"].to<JsonObject>();
    susp_fr["v"] = snapshot.susp_fr;

    JsonObject susp_rl = s["susp_rl"].to<JsonObject>();
    susp_rl["v"] = snapshot.susp_rl;

    JsonObject susp_rr = s["susp_rr"].to<JsonObject>();
    susp_rr["v"] = snapshot.susp_rr;
  }

  // === CUSTOM VALUES ===
  for (int i = 0; i < snapshot.custom_count; i++) {
    JsonObject custom = s[snapshot.custom_values[i].key].to<JsonObject>();
    custom["v"] = snapshot.custom_values[i].value;
  }

  // === META ===
  JsonObject wifi_rssi = s["wifi_rssi"].to<JsonObject>();
  wifi_rssi["v"] = snapshot.wifi_rssi;

  JsonObject heap = s["heap_free"].to<JsonObject>();
  heap["v"] = snapshot.heap_free;

  // === DTC Array ===
  doc["DTC"].to<JsonArray>();

  String output;
  serializeJson(doc, output);

  return output;
}

// Status section was here - removing duplicates

// ============================================================================
// STATUS
// ============================================================================

void CloudManager::printStatus() {
  Serial.println(F("\n========== CLOUD MANAGER STATUS =========="));
  Serial.printf("Network State: %s\n", networkStateToString(_networkState));
  Serial.printf("WiFi: %s (RSSI: %d dBm)\n",
                WiFi.isConnected() ? "CONNECTED" : "DISCONNECTED",
                WiFi.isConnected() ? WiFi.RSSI() : 0);
  Serial.printf("MQTT: %s\n",
                _mqttClient.connected() ? "CONNECTED" : "DISCONNECTED");
  Serial.printf("Success/Fail: %lu / %lu\n", _successCount, _failCount);
  Serial.printf("Offline saved/sent: %lu / %lu\n", _offlineSaved, _offlineSent);
  Serial.printf("Offline buffer: %d frames (%d%%)\n",
                OfflineBuffer::getInstance().count(),
                OfflineBuffer::getInstance().fillPercent());
  Serial.printf("WiFi retry count: %d (delay: %lu ms)\n", _wifiRetryCount,
                getWifiRetryDelay());
  Serial.printf("MQTT retry count: %d (delay: %lu ms)\n", _mqttRetryCount,
                getMqttRetryDelay());
  Serial.println(F("==========================================\n"));
}
