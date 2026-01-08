/**
 * @file source_can.h
 * @brief Fuente de datos CAN Bus (MoTeC/MCP2515)
 *
 * Lee tramas CAN del MCP2515 y decodifica señales según configuración.
 * Soporta byte order Big Endian (MoTeC) y Little Endian.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef SOURCE_CAN_H
#define SOURCE_CAN_H

#include "../config/config_schema.h"
#include "data_source.h"
#include <SPI.h>
#include <mcp_can.h>
#include <vector>

/**
 * @class SourceCAN
 * @brief Fuente de datos CAN Bus
 */
class SourceCAN : public BaseDataSource {
public:
  SourceCAN();
  ~SourceCAN();

  bool begin() override;
  void startTask() override;
  void stopTask() override;

  /**
   * @brief Verifica si el bus CAN está activo
   */
  bool isBusActive() const { return _busActive; }

  /**
   * @brief Obtiene el número de tramas procesadas
   */
  uint32_t getFrameCount() const { return _frameCount; }

  /**
   * @brief Estadísticas de CAN flood (P1.3)
   */
  uint32_t getFramesDiscarded() const { return _framesDiscarded; }
  uint32_t getErrorCount() const { return _errorCount; }
  uint32_t getMaxFramesPerCycle() const { return _maxFramesPerCycle; }

private:
  static void taskFunction(void *param);
  void taskLoop();

  /**
   * @brief Procesa una trama CAN recibida
   */
  void processFrame(uint32_t canId, uint8_t len, uint8_t *data);

  /**
   * @brief Decodifica un sensor de la trama
   */
  float decodeSensor(const SensorConfig &sensor, uint8_t *data, uint8_t len);

  /**
   * @brief Mapea nombre de sensor a setter del TelemetryBus
   */
  void publishToTelemetryBus(const SensorConfig &sensor, float value);

  MCP_CAN *_can;
  bool _busActive;

  // Config
  int8_t _csPin;
  int8_t _intPin;
  uint16_t _baudKbps;
  uint8_t _crystalMhz;

  // Stats (P1.3)
  volatile uint32_t _frameCount;
  volatile uint32_t _framesDiscarded;   ///< Frames descartados por flood
  volatile uint32_t _errorCount;        ///< Errores de bus CAN
  volatile uint32_t _maxFramesPerCycle; ///< Max frames procesados en un ciclo

  // Referencia a sensores configurados
  std::vector<SensorConfig> *_sensors;

  // Mutex para acceso a sensores (heredamos del global para compatibilidad)
  SemaphoreHandle_t _sensorMutex;
};

#endif // SOURCE_CAN_H
