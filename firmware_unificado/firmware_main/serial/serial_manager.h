/**
 * @file serial_manager.h
 * @brief Gestor de comunicación serial con PC/Configurador
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef SERIAL_MANAGER_H
#define SERIAL_MANAGER_H

#include <Arduino.h>

/**
 * @class SerialManager
 * @brief Singleton para gestión de comandos serial
 */
class SerialManager {
public:
  static SerialManager &getInstance() {
    static SerialManager instance;
    return instance;
  }

  SerialManager(const SerialManager &) = delete;
  SerialManager &operator=(const SerialManager &) = delete;

  /**
   * @brief Inicializa el SerialManager
   * @param baud Velocidad (default: 115200)
   */
  void begin(uint32_t baud = 115200);

  /**
   * @brief Procesa comandos entrantes (llamar en loop)
   */
  void process();

  /**
   * @brief Envía telemetría periódica al serial
   */
  void sendTelemetry();

  /**
   * @brief Activa/desactiva envío periódico de telemetría
   */
  void setLiveMode(bool enabled) { _liveMode = enabled; }
  bool isLiveMode() const { return _liveMode; }

private:
  SerialManager();

  void processCommand(const String &cmd);

  // Handlers de comandos
  void handlePing();
  void handleGetConfig();
  void handleSetConfig(const String &json);
  void handleSaveConfig();
  void handleGetStatus();
  void handleGetTelemetry();
  void handleGetSensors();
  void handleSetSensors(const String &json);
  void handleGetDiag();
  void handleReboot();
  void handleFactoryReset();
  void handleHelp();

  void sendResponse(const char *type, bool success,
                    const char *message = nullptr);
  void sendJson(const char *type, const String &json);

  char _buffer[4096];
  int _bufferIndex;
  bool _liveMode;
  unsigned long _lastTelemetrySend;
};

#endif // SERIAL_MANAGER_H
