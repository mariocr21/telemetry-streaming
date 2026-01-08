/**
 * @file source_imu.cpp
 * @brief Implementación de SourceIMU
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#include "source_imu.h"
#include "../config/config_manager.h"
#include "../telemetry/telemetry_bus.h"
#include <esp_task_wdt.h>

// Intervalo de lectura del IMU (ms)
#define IMU_READ_INTERVAL_MS 20

// ============================================================================
// CONSTRUCTOR
// ============================================================================

SourceIMU::SourceIMU()
    : BaseDataSource("IMU"), _mpuAvailable(false), _accelX(0), _accelY(0),
      _accelZ(0), _gyroX(0), _gyroY(0), _gyroZ(0), _temp(0), _sdaPin(-1),
      _sclPin(-1), _intervalMs(IMU_READ_INTERVAL_MS) {}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

bool SourceIMU::begin() {
  Serial.println(F("[IMU] Initializing MPU6050..."));
  setState(SourceState::INITIALIZING);

  // Obtener configuración
  auto &cfg = ConfigManager::getInstance().getConfig();

  if (!cfg.imu.enabled) {
    Serial.println(F("[IMU] Disabled in configuration"));
    setState(SourceState::SOURCE_DISABLED);
    return false;
  }

  _sdaPin = cfg.imu.sda_pin;
  _sclPin = cfg.imu.scl_pin;

  // Inicializar I2C
  Wire.begin(_sdaPin, _sclPin);

  // Intentar inicializar MPU6050
  if (!_mpu.begin()) {
    Serial.println(F("[IMU] ERROR: MPU6050 not found!"));
    setState(SourceState::ERROR_STATE);
    _mpuAvailable = false;
    return false;
  }

  _mpuAvailable = true;

  // Configurar rangos
  _mpu.setAccelerometerRange(MPU6050_RANGE_8_G); // ±8g para off-road
  _mpu.setGyroRange(MPU6050_RANGE_500_DEG);      // ±500°/s
  _mpu.setFilterBandwidth(MPU6050_BAND_21_HZ);   // Filtro paso bajo

  Serial.println(F("[IMU] MPU6050 configured:"));
  Serial.printf("  - Accelerometer: ±8G\n");
  Serial.printf("  - Gyroscope: ±500°/s\n");
  Serial.printf("  - Filter: 21Hz\n");

  setState(SourceState::READY);
  return true;
}

// ============================================================================
// TAREA FREERTOS
// ============================================================================

void SourceIMU::startTask() {
  if (getState() != SourceState::READY) {
    Serial.println(F("[IMU] Cannot start task, not ready"));
    return;
  }

  TaskHandle_t handle = nullptr;

  xTaskCreatePinnedToCore(taskFunction, // Función
                          "ImuTask",    // Nombre
                          4096,         // Stack (4KB)
                          this,         // Parámetro
                          1,            // Prioridad (baja)
                          &handle,      // Handle
                          1             // Core 1
  );

  if (handle != nullptr) {
    setTaskHandle(handle);
    setState(SourceState::RUNNING);
    Serial.println(F("[IMU] Task started on Core 1"));
  } else {
    Serial.println(F("[IMU] Failed to create task!"));
    setState(SourceState::ERROR_STATE);
  }
}

void SourceIMU::stopTask() {
  TaskHandle_t handle = getTaskHandle();
  if (handle != nullptr) {
    vTaskDelete(handle);
    setTaskHandle(nullptr);
    setState(SourceState::READY);
    Serial.println(F("[IMU] Task stopped"));
  }
}

void SourceIMU::taskFunction(void *param) {
  SourceIMU *self = static_cast<SourceIMU *>(param);

  Serial.printf("[IMU] Task running on core %d\n", xPortGetCoreID());

  // Registrar en watchdog
  esp_task_wdt_add(NULL);

  while (true) {
    self->taskLoop();
  }
}

void SourceIMU::taskLoop() {
  // Reset watchdog
  esp_task_wdt_reset();

  if (!_mpuAvailable) {
    // Intentar re-init proactivo cada 5 segundos si el sensor no está
    // disponible
    if (millis() - _lastRetryTime > 5000) {
      _lastRetryTime = millis();
      if (begin()) {
        Serial.println(F("[IMU] MPU6050 re-acquired successfully"));
      }
    }
    vTaskDelay(pdMS_TO_TICKS(1000));
    return;
  }

  sensors_event_t accel, gyro, temp;

  if (_mpu.getEvent(&accel, &gyro, &temp)) {
    _consecutiveErrors = 0;
    // Actualizar datos
    _accelX = accel.acceleration.x;
    _accelY = accel.acceleration.y;
    _accelZ = accel.acceleration.z;

    _gyroX = gyro.gyro.x;
    _gyroY = gyro.gyro.y;
    _gyroZ = gyro.gyro.z;

    _temp = temp.temperature;

    incrementReadCount();

    // Publicar al TelemetryBus
    TelemetryBus &bus = TelemetryBus::getInstance();
    bus.setImuAccel(_accelX, _accelY, _accelZ);
    bus.setImuGyro(_gyroX, _gyroY, _gyroZ);
  } else {
    // Error de lectura (Resiliencia P1.1)
    _consecutiveErrors++;
    incrementErrorCount();

    if (_consecutiveErrors > 15) {
      Serial.println(F("[IMU] Bus lock or sensor hang detected! Performing I2C "
                       "recovery..."));
      // Ejecutar secuencia de disparos de reloj 9 veces para liberar esclavos
      performBusRecovery();

      // Re-init bus I2C
      Wire.begin(_sdaPin, _sclPin);
      if (_mpu.begin()) {
        _consecutiveErrors = 0;
        Serial.println(F("[IMU] Recovery successful"));
      }
    }
  }

  // Esperar hasta próxima lectura
  vTaskDelay(pdMS_TO_TICKS(_intervalMs));
}

void SourceIMU::performBusRecovery() {
  // Manual bit-banging para liberar bus I2C bloqueado (9 clocks)
  // SDA alto, clockeamos SCL para que el esclavo suelte SDA

  pinMode(_sdaPin, INPUT_PULLUP); // Dejar SDA flotando (pullup externo)
  pinMode(_sclPin, OUTPUT);

  for (int i = 0; i < 9; i++) {
    digitalWrite(_sclPin, LOW);
    delayMicroseconds(5);
    digitalWrite(_sclPin, HIGH);
    delayMicroseconds(5);
  }

  // Generar condición de STOP
  pinMode(_sdaPin, OUTPUT);
  digitalWrite(_sdaPin, LOW);
  delayMicroseconds(5);
  digitalWrite(_sclPin, HIGH);
  delayMicroseconds(5);
  digitalWrite(_sdaPin, HIGH);
  delayMicroseconds(5);

  // Restaurar pines para Wire.begin()
  pinMode(_sdaPin, INPUT_PULLUP);
  pinMode(_sclPin, INPUT_PULLUP);
}
