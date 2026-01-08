/**
 * @file source_obd_bridge.h
 * @brief Fuente de datos OBD2 via ESP32-C3 Bridge (UART)
 *
 * Recibe datos OBD2 del ESP32-C3 que está conectado al ELM327.
 * El C3 actúa como puente WiFi<->UART.
 *
 * Protocolo de entrada (desde C3):
 * - {"t":"DATA", "pids":{"0x0C":5000, "0x0D":120, ...}, "dtc":["P0301"]}
 * - {"t":"OBD_STATUS", "data":"CONNECTED"}
 * - {"t":"DTC_CLEARED", "data":"OK"}
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef SOURCE_OBD_BRIDGE_H
#define SOURCE_OBD_BRIDGE_H

#include "data_source.h"
#include <ArduinoJson.h>
#include <HardwareSerial.h>
#include <vector>

// Configuración del buffer
#define OBD_BRIDGE_BUFFER_SIZE 1024
#define OBD_BRIDGE_TIMEOUT_MS                                                  \
  4000 // Aumentado a 4s para dar margen durante escaneo PIDs del C3

/**
 * @brief Código DTC almacenado
 */
struct DtcCode {
  char code[8]; // Ej: "P0301"
};

/**
 * @class SourceOBDBridge
 * @brief Fuente de datos OBD2 via UART bridge con ESP32-C3
 */
class SourceOBDBridge : public BaseDataSource {
public:
  SourceOBDBridge();

  bool begin() override;
  void startTask() override;
  void stopTask() override;

  /**
   * @brief Verifica si el C3 está respondiendo
   */
  bool isC3Connected() const { return _c3Connected; }

  /**
   * @brief Obtiene el tiempo desde última recepción
   */
  uint32_t getTimeSinceLastData() const { return millis() - _lastReceiveTime; }

  /**
   * @brief Habilita/deshabilita OBD en el C3
   */
  void setOBDEnabled(bool enabled);

  /**
   * @brief Solicita borrado de DTCs al C3
   */
  void clearDTCs();

  /**
   * @brief Obtiene códigos DTC actuales
   */
  const std::vector<DtcCode> &getDTCs() const { return _dtcCodes; }

  /**
   * @brief Número de PIDs activos
   */
  uint8_t getActivePidCount() const { return _pidCount; }

private:
  static void taskFunction(void *param);
  void taskLoop();

  /**
   * @brief Lee y procesa datos del C3
   */
  void processC3Data();

  /**
   * @brief Procesa un mensaje JSON completo del C3
   */
  void processC3Message(const String &json);

  /**
   * @brief Procesa mensaje tipo DATA con PIDs
   */
  void processDataMessage(JsonDocument &doc);

  /**
   * @brief Envía mensaje al C3
   */
  void sendToC3(const char *type, const char *data);

  /**
   * @brief Publica datos al TelemetryBus
   */
  void publishToTelemetryBus();

  HardwareSerial *_serial;

  // Buffer de recepción
  char _buffer[OBD_BRIDGE_BUFFER_SIZE];
  int _bufferIndex;

  // Estado
  bool _c3Connected;
  bool _obdEnabled;
  unsigned long _lastReceiveTime;
  uint8_t _pidCount;

  // Datos OBD recibidos
  float _rpm;
  float _speed;
  float _coolant;
  float _throttle;
  float _load;
  float _maf;
  float _map;
  float _intakeTemp;
  float _oilTemp;
  float _fuelLevel;
  float _fuelRate;
  float _batteryVoltage;

  // DTCs
  std::vector<DtcCode> _dtcCodes;

  // Config
  int8_t _rxPin;
  int8_t _txPin;
  uint32_t _baud;
};

#endif // SOURCE_OBD_BRIDGE_H
