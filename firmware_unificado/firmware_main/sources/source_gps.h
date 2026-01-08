/**
 * @file source_gps.h
 * @brief Fuente de datos GPS
 *
 * Lee datos GPS via UART usando TinyGPSPlus y los publica al TelemetryBus.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef SOURCE_GPS_H
#define SOURCE_GPS_H

#include "data_source.h"
#include <HardwareSerial.h>
#include <TinyGPSPlus.h>


/**
 * @class SourceGPS
 * @brief Fuente de datos GPS
 */
class SourceGPS : public BaseDataSource {
public:
  SourceGPS();

  bool begin() override;
  void startTask() override;
  void stopTask() override;

  // Getters para datos actuales
  float getLatitude() const { return _lat; }
  float getLongitude() const { return _lng; }
  float getAltitude() const { return _alt; }
  float getSpeed() const { return _speed; }
  float getCourse() const { return _course; }
  uint8_t getSatellites() const { return _sats; }
  bool hasFix() const { return _fix; }

private:
  static void taskFunction(void *param);
  void taskLoop();

  HardwareSerial *_serial;
  TinyGPSPlus _gps;

  // Datos actuales (volatile por acceso desde tarea)
  volatile float _lat;
  volatile float _lng;
  volatile float _alt;
  volatile float _speed;
  volatile float _course;
  volatile uint8_t _sats;
  volatile bool _fix;

  // Config
  int8_t _rxPin;
  int8_t _txPin;
  uint32_t _baud;
};

#endif // SOURCE_GPS_H
