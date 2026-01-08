/**
 * @file source_obd_direct.h
 * @brief Fuente de datos OBD2 via ELM327 WiFi (conexión directa)
 *
 * Conecta directamente al ELM327 por WiFi y lee PIDs OBD2.
 * Basado en la librería ELMduino con patrón no bloqueante.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef SOURCE_OBD_DIRECT_H
#define SOURCE_OBD_DIRECT_H

#include "data_source.h"
#include <ELMduino.h>
#include <WiFi.h>

// Número máximo de PIDs a monitorear
#define MAX_OBD_PIDS 20

/**
 * @brief Estructura para un PID OBD2
 */
struct ObdPid {
  uint8_t pid;            ///< Código PID (ej: 0x0C para RPM)
  const char *name;       ///< Nombre legible
  float value;            ///< Último valor leído
  float valueFiltered;    ///< Valor con filtro EMA
  bool available;         ///< PID soportado por el vehículo
  bool enabled;           ///< Habilitado para lectura
  unsigned long lastRead; ///< Timestamp última lectura
};

/**
 * @class SourceOBDDirect
 * @brief Fuente de datos OBD2 via ELM327 WiFi
 */
class SourceOBDDirect : public BaseDataSource {
public:
  SourceOBDDirect();

  bool begin() override;
  void startTask() override;
  void stopTask() override;

  /**
   * @brief Verifica si está conectado al ELM327
   */
  bool isElmConnected() const { return _elmConnected; }

  /**
   * @brief Obtiene número de PIDs activos
   */
  uint8_t getActivePidCount() const { return _activePidCount; }

  /**
   * @brief Escanea PIDs soportados por el vehículo
   */
  void scanSupportedPids();

  /**
   * @brief Parsea lista de PIDs desde string (ej: "0x0C,0x0D,BAT")
   */
  void parsePidsFromString(const char *pidsStr);

private:
  static void taskFunction(void *param);
  void taskLoop();

  bool connectToElm327Wifi();
  bool connectToElmDevice();
  void pollNextPid();
  void processPidResult(uint8_t pidIndex);
  void publishToTelemetryBus();

  WiFiClient _elmClient;
  ELM327 _elm;

  bool _elmWifiConnected;
  bool _elmConnected;

  // PIDs configurados
  ObdPid _pids[MAX_OBD_PIDS];
  uint8_t _pidCount;
  uint8_t _activePidCount;

  // Estado del polling
  uint8_t _currentPidIndex;
  bool _waitingResponse;
  unsigned long _pollStartTime;

  // Config
  char _elmSsid[32];
  char _elmPassword[32];
  char _elmIp[16];
  uint16_t _elmPort;
  uint16_t _pollIntervalMs;

  // Filtro EMA
  static constexpr float EMA_ALPHA = 0.3f;

  // Timeout para respuesta OBD
  static constexpr uint16_t OBD_TIMEOUT_MS = 1000;
};

#endif // SOURCE_OBD_DIRECT_H
