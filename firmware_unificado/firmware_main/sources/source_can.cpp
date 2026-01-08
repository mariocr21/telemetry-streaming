/**
 * @file source_can.cpp
 * @brief Implementación de SourceCAN
 *
 * Basado en el código probado de Motec/firmware task_can.cpp
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#include "source_can.h"
#include "../config/config_manager.h"
#include "../telemetry/telemetry_bus.h"
#include <esp_task_wdt.h>

// Máximo de tramas a procesar por iteración (evita bloqueo pero permite
// ráfagas)
#define MAX_FRAMES_PER_LOOP 40

// ============================================================================
// CONSTRUCTOR / DESTRUCTOR
// ============================================================================

SourceCAN::SourceCAN()
    : BaseDataSource("CAN"), _can(nullptr), _busActive(false), _csPin(-1),
      _intPin(-1), _baudKbps(500), _crystalMhz(8), _frameCount(0),
      _framesDiscarded(0), _errorCount(0), _maxFramesPerCycle(0),
      _sensors(nullptr), _sensorMutex(nullptr) {}

SourceCAN::~SourceCAN() {
  if (_can != nullptr) {
    delete _can;
    _can = nullptr;
  }
  if (_sensorMutex != nullptr) {
    vSemaphoreDelete(_sensorMutex);
    _sensorMutex = nullptr;
  }
}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

bool SourceCAN::begin() {
  Serial.println(F("[CAN] Initializing MCP2515..."));
  setState(SourceState::INITIALIZING);

  // Obtener configuración
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (!cfg.can.enabled) {
    Serial.println(F("[CAN] Disabled in configuration"));
    setState(SourceState::SOURCE_DISABLED);
    return false;
  }

  _csPin = cfg.can.cs_pin;
  _intPin = cfg.can.int_pin;
  _baudKbps = cfg.can.baud_kbps;
  _crystalMhz = cfg.can.crystal_mhz;

  // Referencia a sensores
  _sensors = &ConfigManager::getInstance().getSensors();

  // Crear mutex para sensores
  _sensorMutex = xSemaphoreCreateMutex();
  if (_sensorMutex == nullptr) {
    Serial.println(F("[CAN] ERROR: Failed to create mutex"));
    setState(SourceState::ERROR_STATE);
    return false;
  }

  // Configurar pin de interrupción
  pinMode(_intPin, INPUT);

  // Crear instancia MCP_CAN
  _can = new MCP_CAN(_csPin);

  // Determinar velocidad CAN
  uint8_t canSpeed;
  switch (_baudKbps) {
  case 250:
    canSpeed = (_crystalMhz == 8) ? CAN_250KBPS : CAN_250KBPS;
    break;
  case 500:
    canSpeed = (_crystalMhz == 8) ? CAN_500KBPS : CAN_500KBPS;
    break;
  case 1000:
    canSpeed = (_crystalMhz == 8) ? CAN_1000KBPS : CAN_1000KBPS;
    break;
  default:
    canSpeed = CAN_500KBPS;
  }

  // Frecuencia del cristal
  uint8_t clockSet = (_crystalMhz == 16) ? MCP_16MHZ : MCP_8MHZ;

  Serial.printf("[CAN] CS=%d, INT=%d, %dkbps, %dMHz crystal\n", _csPin, _intPin,
                _baudKbps, _crystalMhz);

  // Inicializar MCP2515
  if (_can->begin(MCP_ANY, canSpeed, clockSet) != CAN_OK) {
    Serial.println(F("[CAN] ERROR: MCP2515 initialization failed!"));
    setState(SourceState::ERROR_STATE);
    _busActive = false;
    return false;
  }

  // Modo normal
  _can->setMode(MCP_NORMAL);

  _busActive = true;
  setState(SourceState::READY);

  Serial.printf("[CAN] MCP2515 ready, %d sensors configured\n",
                _sensors->size());

  return true;
}

// ============================================================================
// TAREA FREERTOS
// ============================================================================

void SourceCAN::startTask() {
  if (getState() != SourceState::READY) {
    Serial.println(F("[CAN] Cannot start task, not ready"));
    return;
  }

  TaskHandle_t handle = nullptr;

  xTaskCreatePinnedToCore(taskFunction, // Función
                          "CanTask",    // Nombre
                          8192, // Stack (8KB - más grande por decodificación)
                          this, // Parámetro
                          2,    // Prioridad ALTA
                          &handle, // Handle
                          0 // Core 0 (Pro CPU - menos interrupciones WiFi)
  );

  if (handle != nullptr) {
    setTaskHandle(handle);
    setState(SourceState::RUNNING);
    Serial.println(F("[CAN] Task started on Core 0 (high priority)"));
  } else {
    Serial.println(F("[CAN] Failed to create task!"));
    setState(SourceState::ERROR_STATE);
  }
}

void SourceCAN::stopTask() {
  TaskHandle_t handle = getTaskHandle();
  if (handle != nullptr) {
    vTaskDelete(handle);
    setTaskHandle(nullptr);
    setState(SourceState::READY);
    Serial.println(F("[CAN] Task stopped"));
  }
}

void SourceCAN::taskFunction(void *param) {
  SourceCAN *self = static_cast<SourceCAN *>(param);

  Serial.printf("[CAN] Task running on core %d\n", xPortGetCoreID());

  // Registrar en watchdog
  esp_task_wdt_add(NULL);

  while (true) {
    self->taskLoop();
  }
}

void SourceCAN::taskLoop() {
  // Reset watchdog
  esp_task_wdt_reset();

  // Yield mínimo para no monopolizar CPU
  vTaskDelay(1);

  if (!_busActive || _can == nullptr) {
    vTaskDelay(pdMS_TO_TICKS(100));
    return;
  }

  // Buffer local
  static uint8_t rxBuf[8];
  uint32_t rxId;
  uint8_t len = 0;

  // Verificar si hay datos (INT pin LOW)
  if (digitalRead(_intPin) == LOW) {
    int framesProcessed = 0;

    // Procesar ráfaga (P1.3)
    while (digitalRead(_intPin) == LOW &&
           framesProcessed < MAX_FRAMES_PER_LOOP) {
      if (_can->readMsgBuf((unsigned long *)&rxId, &len, rxBuf) == CAN_OK) {
        processFrame(rxId, len, rxBuf);
        _frameCount++;
        framesProcessed++;
      } else {
        _errorCount++;
      }
    }

    // Diagnóstico de saturación: verificar flags de overflow
    uint8_t errFlags = _can->checkError(); // Usar checkError estándar

    if (errFlags & 0xC0) { // RX0OVR o RX1OVR (Overflows)
      _framesDiscarded++;
      // Intentar limpiar flags si la librería lo permite
      // (algunas implementaciones de readMsgBuf ya lo hacen)
    }

    // Actualizar max frames por ciclo (para diagnóstico de flood)
    if (framesProcessed > (int)_maxFramesPerCycle) {
      _maxFramesPerCycle = framesProcessed;
    }

    // P1.3: Yield después del lote para dar tiempo a otras tareas (WDT, WiFi)
    if (framesProcessed > (int)(MAX_FRAMES_PER_LOOP / 2)) {
      vTaskDelay(1);
    } else {
      taskYIELD();
    }
  }
}

// ============================================================================
// PROCESAMIENTO DE TRAMAS
// ============================================================================

void SourceCAN::processFrame(uint32_t canId, uint8_t len, uint8_t *data) {
  if (len > 8)
    len = 8; // Seguridad

  // Tomar mutex
  if (xSemaphoreTake(_sensorMutex, pdMS_TO_TICKS(5)) != pdTRUE) {
    return; // No pudimos tomar el mutex, saltamos este frame
  }

  // Buscar sensores que coincidan con este CAN ID
  for (auto &sensor : *_sensors) {
    if (!sensor.enabled)
      continue;
    if (sensor.can_id != canId)
      continue;

    // Decodificar valor
    float value = decodeSensor(sensor, data, len);

    // Actualizar sensor
    sensor.value = value;
    sensor.updated = true;

    incrementReadCount();

    // Publicar al TelemetryBus
    publishToTelemetryBus(sensor, value);
  }

  xSemaphoreGive(_sensorMutex);
}

float SourceCAN::decodeSensor(const SensorConfig &sensor, uint8_t *data,
                              uint8_t len) {
  uint64_t raw = 0;

  if (sensor.big_endian) {
    // === BIG ENDIAN (MoTeC) ===
    int startByte = sensor.start_byte;
    int numBytes = sensor.length / 8;
    if (numBytes == 0)
      numBytes = 1;

    if (startByte + numBytes <= len) {
      if (numBytes == 1) {
        raw = data[startByte];
      } else if (numBytes == 2) {
        raw = ((uint16_t)data[startByte] << 8) | data[startByte + 1];
      } else if (numBytes == 4) {
        raw = ((uint32_t)data[startByte] << 24) |
              ((uint32_t)data[startByte + 1] << 16) |
              ((uint32_t)data[startByte + 2] << 8) | data[startByte + 3];
      }
    }
  } else {
    // === LITTLE ENDIAN (Intel) ===
    // Convertir buffer a uint64
    uint64_t fullData = 0;
    for (int i = 0; i < len; i++) {
      fullData |= ((uint64_t)data[i] << (i * 8));
    }

    // Aplicar máscara y shift
    uint64_t mask = 0;
    if (sensor.length >= 64) {
      mask = ~0ULL;
    } else {
      mask = (1ULL << sensor.length) - 1;
    }

    if (sensor.start_bit < 64) {
      raw = (fullData >> sensor.start_bit) & mask;
    }
  }

  // Extensión de signo si es necesario
  int64_t signedRaw = (int64_t)raw;
  if (sensor.signed_val && sensor.length > 0 && sensor.length < 64) {
    int shift = 64 - sensor.length;
    signedRaw = ((int64_t)raw << shift) >> shift;
  }

  // Aplicar escala y offset
  float value = (signedRaw * sensor.multiplier) + sensor.offset;
  return value;
}

void SourceCAN::publishToTelemetryBus(const SensorConfig &sensor, float value) {
  TelemetryBus &bus = TelemetryBus::getInstance();

  // P1.5 Optimization: Usar mapeo pre-calculado (O(1)) en lugar de strcmp
  // (O(N))
  switch (sensor.map_type) {
  case SensorConfig::MappingType::ENGINE_RPM:
    bus.setEngineRpm(value);
    break;
  case SensorConfig::MappingType::ENGINE_SPEED:
    bus.setEngineSpeed(value);
    break;
  case SensorConfig::MappingType::ENGINE_COOLANT:
    bus.setEngineCoolantTemp(value);
    break;
  case SensorConfig::MappingType::ENGINE_OIL_TEMP:
    bus.setEngineOilTemp(value);
    break;
  case SensorConfig::MappingType::ENGINE_THROTTLE:
    bus.setEngineThrottle(value);
    break;
  case SensorConfig::MappingType::ENGINE_LOAD:
    bus.setEngineLoad(value);
    break;
  case SensorConfig::MappingType::ENGINE_MAF:
    bus.setEngineMaf(value);
    break;
  case SensorConfig::MappingType::ENGINE_MAP:
    bus.setEngineMap(value);
    break;
  case SensorConfig::MappingType::FUEL_LEVEL:
    bus.setFuelLevel(value);
    break;
  case SensorConfig::MappingType::FUEL_RATE:
    bus.setFuelRate(value);
    break;
  case SensorConfig::MappingType::BATTERY_VOLT:
    bus.setBatteryVoltage(value);
    break;
  default:
    // Fallback para custom values
    bus.setCustomValue(sensor.cloud_id, value);
    break;
  }
}
