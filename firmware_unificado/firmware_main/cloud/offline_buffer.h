/**
 * @file offline_buffer.h
 * @brief Buffer offline para telemetría cuando MQTT no está disponible
 *
 * Implementa un RingBuffer en RAM para almacenar frames de telemetría
 * durante cortes de red. Sin allocación dinámica, estructura fija.
 *
 * PART OF: Plan Safety-Critical P0.1
 * RISK MITIGATED: Pérdida total de telemetría en dropouts Starlink
 *
 * @author Neurona Racing Development
 * @date 2024-12-20
 */

#ifndef OFFLINE_BUFFER_H
#define OFFLINE_BUFFER_H

#include <Arduino.h>
#include <freertos/FreeRTOS.h>
#include <freertos/semphr.h>

// ============================================================================
// CONFIGURACIÓN DEL BUFFER
// ============================================================================

// Tamaño máximo del payload JSON (bytes)
// Tamaño máximo del payload JSON (bytes) - Reducido para ahorrar RAM
#define MAX_PAYLOAD_SIZE 512

// Número de frames en el buffer (RAM)
// 50 * 512 + overhead ~= 26KB de RAM (Mucho más seguro que 300KB)
#define OFFLINE_BUFFER_SIZE 50

// ============================================================================
// ESTRUCTURAS
// ============================================================================

/**
 * @struct TelemetryFrame
 * @brief Frame de telemetría con estructura fija (sin alloc dinámico)
 */
struct TelemetryFrame {
  char payload[MAX_PAYLOAD_SIZE]; ///< JSON payload
  uint32_t timestamp_ms;          ///< Timestamp de captura (millis)
  uint16_t payload_len;           ///< Longitud real del payload
  bool valid;                     ///< Frame válido para envío
};

/**
 * @class OfflineBuffer
 * @brief RingBuffer thread-safe para frames de telemetría
 *
 * Implementación FIFO:
 * - Si mqtt.publish() falla → push al buffer
 * - Al reconectar MQTT → enviar FIFO
 * - Si buffer lleno → descartar más antiguo (overwrite)
 */
class OfflineBuffer {
public:
  static OfflineBuffer &getInstance() {
    static OfflineBuffer instance;
    return instance;
  }

  OfflineBuffer(const OfflineBuffer &) = delete;
  OfflineBuffer &operator=(const OfflineBuffer &) = delete;

  /**
   * @brief Inicializa el buffer (crear mutex)
   */
  void begin();

  /**
   * @brief Agrega un frame al buffer
   * @param payload JSON string
   * @return true si se agregó, false si hubo error
   */
  bool push(const String &payload);

  /**
   * @brief Extrae el frame más antiguo (FIFO)
   * @param payload Output: payload JSON
   * @return true si hay frame disponible
   */
  bool pop(String &payload);

  /**
   * @brief Peek al frame más antiguo sin extraerlo
   * @param payload Output: payload JSON
   * @return true si hay frame disponible
   */
  bool peek(String &payload);

  /**
   * @brief Retorna el número de frames en buffer
   */
  size_t count() const { return _count; }

  /**
   * @brief Retorna si el buffer está vacío
   */
  bool isEmpty() const { return _count == 0; }

  /**
   * @brief Retorna si el buffer está lleno
   */
  bool isFull() const { return _count >= OFFLINE_BUFFER_SIZE; }

  /**
   * @brief Retorna el porcentaje de ocupación (0-100)
   */
  uint8_t fillPercent() const {
    return (uint8_t)((_count * 100) / OFFLINE_BUFFER_SIZE);
  }

  /**
   * @brief Limpia todo el buffer
   */
  void clear();

  /**
   * @brief Estadísticas
   */
  uint32_t getTotalPushed() const { return _totalPushed; }
  uint32_t getTotalOverwritten() const { return _totalOverwritten; }
  uint32_t getTotalPopped() const { return _totalPopped; }

  /**
   * @brief Imprime estado del buffer
   */
  void printStatus();

private:
  OfflineBuffer();

  TelemetryFrame _buffer[OFFLINE_BUFFER_SIZE];
  size_t _head;  ///< Índice de escritura (próximo push)
  size_t _tail;  ///< Índice de lectura (próximo pop)
  size_t _count; ///< Número de elementos en buffer

  // Estadísticas
  uint32_t _totalPushed;
  uint32_t _totalOverwritten;
  uint32_t _totalPopped;

  SemaphoreHandle_t _mutex;

  bool takeMutex(TickType_t timeout = pdMS_TO_TICKS(10));
  void giveMutex();
};

#endif // OFFLINE_BUFFER_H
