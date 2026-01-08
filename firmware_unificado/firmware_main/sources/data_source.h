/**
 * @file data_source.h
 * @brief Interfaz base para todas las fuentes de datos
 *
 * Define el contrato que deben cumplir todas las fuentes de datos
 * (CAN, OBD, GPS, IMU, etc.) para integrarse con el sistema.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef DATA_SOURCE_H
#define DATA_SOURCE_H

#include <Arduino.h>

/**
 * @brief Estado de una fuente de datos
 * Nota: Usamos nombres únicos para evitar conflictos con macros de ESP32
 */
enum class SourceState {
  UNINITIALIZED,  ///< No inicializada
  INITIALIZING,   ///< En proceso de inicialización
  READY,          ///< Lista para operar
  RUNNING,        ///< Tarea corriendo
  ERROR_STATE,    ///< Error (evita conflicto con macro ERROR)
  SOURCE_DISABLED ///< Deshabilitada (evita conflicto con macro DISABLED)
};

/**
 * @brief Convierte SourceState a string
 */
inline const char *sourceStateToString(SourceState state) {
  switch (state) {
  case SourceState::UNINITIALIZED:
    return "UNINITIALIZED";
  case SourceState::INITIALIZING:
    return "INITIALIZING";
  case SourceState::READY:
    return "READY";
  case SourceState::RUNNING:
    return "RUNNING";
  case SourceState::ERROR_STATE:
    return "ERROR";
  case SourceState::SOURCE_DISABLED:
    return "DISABLED";
  default:
    return "UNKNOWN";
  }
}

/**
 * @class IDataSource
 * @brief Interfaz abstracta para fuentes de datos
 *
 * Todas las fuentes de datos deben heredar de esta clase e implementar
 * los métodos virtuales puros.
 */
class IDataSource {
public:
  virtual ~IDataSource() = default;

  /**
   * @brief Inicializa la fuente de datos
   *
   * Debe configurar hardware, crear buffers, etc.
   * NO debe iniciar la tarea FreeRTOS.
   *
   * @return true si la inicialización fue exitosa
   */
  virtual bool begin() = 0;

  /**
   * @brief Inicia la tarea FreeRTOS de esta fuente
   *
   * Debe crear la tarea con xTaskCreatePinnedToCore.
   * Solo llamar después de begin() exitoso.
   */
  virtual void startTask() = 0;

  /**
   * @brief Detiene la tarea de esta fuente
   */
  virtual void stopTask() = 0;

  /**
   * @brief Obtiene el estado actual de la fuente
   * @return Estado actual
   */
  virtual SourceState getState() const = 0;

  /**
   * @brief Verifica si la fuente está operativa
   * @return true si está en estado READY o RUNNING
   */
  virtual bool isReady() const {
    SourceState state = getState();
    return state == SourceState::READY || state == SourceState::RUNNING;
  }

  /**
   * @brief Obtiene el nombre de la fuente (para logging)
   * @return Nombre de la fuente
   */
  virtual const char *getName() const = 0;

  /**
   * @brief Obtiene estadísticas de la fuente
   * @param readCount Número de lecturas exitosas
   * @param errorCount Número de errores
   * @param lastReadTime Timestamp de última lectura
   */
  virtual void getStats(uint32_t &readCount, uint32_t &errorCount,
                        uint32_t &lastReadTime) const {
    readCount = 0;
    errorCount = 0;
    lastReadTime = 0;
  }

  /**
   * @brief Imprime estado de la fuente al Serial (debug)
   */
  virtual void printStatus() const {
    Serial.printf("[%s] State: %s\n", getName(),
                  sourceStateToString(getState()));
  }
};

/**
 * @class BaseDataSource
 * @brief Clase base con implementación común para fuentes de datos
 *
 * Proporciona implementación base para estadísticas y estado.
 * Las clases concretas deben heredar de aquí.
 */
class BaseDataSource : public IDataSource {
public:
  BaseDataSource(const char *name)
      : _state(SourceState::UNINITIALIZED), _taskHandle(nullptr) {
    strncpy(_name, name, sizeof(_name) - 1);
  }

  SourceState getState() const override { return _state; }
  const char *getName() const override { return _name; }

  void getStats(uint32_t &readCount, uint32_t &errorCount,
                uint32_t &lastReadTime) const override {
    readCount = _stats.readCount;
    errorCount = _stats.errorCount;
    lastReadTime = _stats.lastReadTime;
  }

  void printStatus() const override {
    Serial.printf("[%s] State: %s, Reads: %lu, Errors: %lu, Last: %lu ms ago\n",
                  _name, sourceStateToString(_state), _stats.readCount,
                  _stats.errorCount, millis() - _stats.lastReadTime);
  }

protected:
  void setState(SourceState state) { _state = state; }

  void incrementReadCount() {
    _stats.readCount++;
    _stats.lastReadTime = millis();
  }

  void incrementErrorCount() { _stats.errorCount++; }

  TaskHandle_t getTaskHandle() const { return _taskHandle; }
  void setTaskHandle(TaskHandle_t handle) { _taskHandle = handle; }

  char _name[16];
  SourceState _state;
  TaskHandle_t _taskHandle;

  struct Stats {
    uint32_t readCount = 0;
    uint32_t errorCount = 0;
    uint32_t lastReadTime = 0;
  } _stats;
};

#endif // DATA_SOURCE_H
