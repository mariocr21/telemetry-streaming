/**
 * @file source_gps.cpp
 * @brief Implementación de SourceGPS
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#include "source_gps.h"
#include "../config/config_manager.h"
#include "../telemetry/telemetry_bus.h"
#include <esp_task_wdt.h>

// ============================================================================
// CONSTRUCTOR
// ============================================================================

SourceGPS::SourceGPS()
    : BaseDataSource("GPS"), _serial(nullptr), _lat(0), _lng(0), _alt(0),
      _speed(0), _course(0), _sats(0), _fix(false), _rxPin(-1), _txPin(-1),
      _baud(9600) {}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

bool SourceGPS::begin() {
  Serial.println(F("[GPS] Initializing..."));
  setState(SourceState::INITIALIZING);

  // Obtener configuración
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (!cfg.gps.enabled) {
    Serial.println(F("[GPS] Disabled in configuration"));
    setState(SourceState::SOURCE_DISABLED);
    return false;
  }

  _rxPin = cfg.gps.rx_pin;
  _txPin = cfg.gps.tx_pin;
  _baud = cfg.gps.baud;

  // Usar UART2
  _serial = new HardwareSerial(2);

  Serial.printf("[GPS] Starting UART2 on RX=%d, TX=%d @ %lu baud\n", _rxPin,
                _txPin, _baud);
  _serial->begin(_baud, SERIAL_8N1, _rxPin, _txPin);

  // Esperar un momento para que el GPS se estabilice
  delay(100);

  setState(SourceState::READY);
  Serial.println(F("[GPS] Ready, waiting for satellite fix..."));

  return true;
}

// ============================================================================
// TAREA FREERTOS
// ============================================================================

void SourceGPS::startTask() {
  if (getState() != SourceState::READY) {
    Serial.println(F("[GPS] Cannot start task, not ready"));
    return;
  }

  TaskHandle_t handle = nullptr;

  xTaskCreatePinnedToCore(taskFunction, // Función
                          "GpsTask",    // Nombre
                          4096,         // Stack (4KB)
                          this,         // Parámetro (this pointer)
                          0, // Prioridad (MINIMA - para no bloquear Cloud)
                          &handle, // Handle
                          1        // Core 1 (App Core)
  );

  if (handle != nullptr) {
    setTaskHandle(handle);
    setState(SourceState::RUNNING);
    Serial.println(F("[GPS] Task started on Core 1"));
  } else {
    Serial.println(F("[GPS] Failed to create task!"));
    setState(SourceState::ERROR_STATE);
  }
}

void SourceGPS::stopTask() {
  TaskHandle_t handle = getTaskHandle();
  if (handle != nullptr) {
    vTaskDelete(handle);
    setTaskHandle(nullptr);
    setState(SourceState::READY);
    Serial.println(F("[GPS] Task stopped"));
  }
}

void SourceGPS::taskFunction(void *param) {
  SourceGPS *self = static_cast<SourceGPS *>(param);

  Serial.printf("[GPS] Task running on core %d\n", xPortGetCoreID());

  // Registrar en watchdog
  esp_task_wdt_add(NULL);

  while (true) {
    self->taskLoop();
  }
}

void SourceGPS::taskLoop() {
  // Reset watchdog
  esp_task_wdt_reset();

  // Leer datos del GPS (Limitado a 64 bytes para evitar hogging)
  int bytesRead = 0;
  while (_serial->available() > 0 && bytesRead < 64) {
    char c = _serial->read();
    bytesRead++;

    if (_gps.encode(c)) {
      // Se procesó una sentencia completa
      incrementReadCount();
    }
  }

  // Actualizar datos si hay cambios
  bool updated = false;

  if (_gps.location.isUpdated()) {
    _lat = _gps.location.lat();
    _lng = _gps.location.lng();
    _fix = _gps.location.isValid();
    updated = true;
  }

  if (_gps.altitude.isUpdated()) {
    _alt = _gps.altitude.meters();
    updated = true;
  }

  if (_gps.speed.isUpdated()) {
    _speed = _gps.speed.kmph();
    updated = true;
  }

  if (_gps.course.isUpdated()) {
    _course = _gps.course.deg();
    updated = true;
  }

  if (_gps.satellites.isUpdated()) {
    _sats = _gps.satellites.value();
    updated = true;
  }

  // Publicar al TelemetryBus
  if (updated) {
    TelemetryBus::getInstance().setGps(_lat, _lng, _alt, _speed, _course, _sats,
                                       _fix);
  }

  // Pequeño delay para no saturar
  vTaskDelay(pdMS_TO_TICKS(10));
}
