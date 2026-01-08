/**
 * @file source_imu.h
 * @brief Fuente de datos IMU (MPU6050)
 *
 * Lee datos del acelerómetro y giroscopio MPU6050 via I2C.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef SOURCE_IMU_H
#define SOURCE_IMU_H

#include "data_source.h"
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <Wire.h>

/**
 * @class SourceIMU
 * @brief Fuente de datos IMU (MPU6050)
 */
class SourceIMU : public BaseDataSource {
public:
  SourceIMU();

  bool begin() override;
  void startTask() override;
  void stopTask() override;

  // Getters
  float getAccelX() const { return _accelX; }
  float getAccelY() const { return _accelY; }
  float getAccelZ() const { return _accelZ; }
  float getGyroX() const { return _gyroX; }
  float getGyroY() const { return _gyroY; }
  float getGyroZ() const { return _gyroZ; }
  float getTemperature() const { return _temp; }

private:
  static void taskFunction(void *param);
  void taskLoop();
  void performBusRecovery();

  Adafruit_MPU6050 _mpu;
  bool _mpuAvailable;

  // Datos actuales
  volatile float _accelX, _accelY, _accelZ;
  volatile float _gyroX, _gyroY, _gyroZ;
  volatile float _temp;

  // Config
  int8_t _sdaPin;
  int8_t _sclPin;
  uint32_t _intervalMs;

  // Resiliencia (P1.1)
  uint32_t _lastRetryTime = 0;
  uint8_t _consecutiveErrors = 0;
};

#endif // SOURCE_IMU_H
