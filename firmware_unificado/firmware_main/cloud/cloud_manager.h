/**
 * @file cloud_manager.h
 * @brief Gestor de comunicación cloud (MQTT/HTTP) - VERSION RESILIENTE
 *
 * MODIFICADO: Plan Safety-Critical P0.2
 * - Estado de red como State Machine no bloqueante
 * - Backoff exponencial
 * - Integración con OfflineBuffer P0.1
 * - Timeouts agresivos P0.4
 *
 * @author Neurona Racing Development
 * @date 2024-12-20
 */

#ifndef CLOUD_MANAGER_H
#define CLOUD_MANAGER_H

#include "../config/config_schema.h"
#include "offline_buffer.h"
#include <Arduino.h>
#include <HTTPClient.h>
#include <PubSubClient.h>
#include <WiFi.h>

// Forward declaration to avoid include loop
class StatusLed;

// ============================================================================
// ESTADOS DE CONEXIÓN (P0.2)
// ============================================================================

/**
 * @enum NetworkState
 * @brief Estados de la máquina de estados de red
 */
enum class NetworkState : uint8_t {
  DISCONNECTED = 0, ///< Sin conexión
  CONNECTING_WIFI,  ///< Intentando conectar WiFi
  WIFI_OK,          ///< WiFi conectado, MQTT no
  CONNECTING_MQTT,  ///< Intentando conectar MQTT
  MQTT_OK           ///< Todo conectado
};

/**
 * @brief Convierte NetworkState a string para debug
 */
inline const char *networkStateToString(NetworkState state) {
  switch (state) {
  case NetworkState::DISCONNECTED:
    return "DISCONNECTED";
  case NetworkState::CONNECTING_WIFI:
    return "CONNECTING_WIFI";
  case NetworkState::WIFI_OK:
    return "WIFI_OK";
  case NetworkState::CONNECTING_MQTT:
    return "CONNECTING_MQTT";
  case NetworkState::MQTT_OK:
    return "MQTT_OK";
  default:
    return "UNKNOWN";
  }
}

// ============================================================================
// CONSTANTES DE RESILIENCIA
// ============================================================================

// Timeouts agresivos (P0.4)
#define WIFI_CONNECT_TIMEOUT_MS                                                \
  10000 // 10s máximo para WiFi connect (Aumentado de 3s)
#define MQTT_CONNECT_TIMEOUT_MS                                                \
  10000                      // 10s máximo para MQTT connect (Aumentado de 2s)
#define HTTP_TIMEOUT_MS 2000 // 2s máximo para HTTP

// Backoff exponencial (P0.2)
#define WIFI_RETRY_BASE_MS 2000 // Retry inicial 2s
#define WIFI_RETRY_MAX_MS 60000 // Retry máximo 60s
#define MQTT_RETRY_BASE_MS 1000 // Retry inicial 1s
#define MQTT_RETRY_MAX_MS 30000 // Retry máximo 30s
#define BACKOFF_MULTIPLIER 2    // Factor de multiplicación

// Buffer offline (P0.1)
#define OFFLINE_DRAIN_BATCH_SIZE 5 // Enviar X mensajes por ciclo al reconectar
#define OFFLINE_DRAIN_DELAY_MS 50  // Delay entre mensajes

/**
 * @class CloudManager
 * @brief Singleton para gestión de comunicación cloud - RESILIENTE
 */
class CloudManager {
public:
  static CloudManager &getInstance() {
    static CloudManager instance;
    return instance;
  }

  CloudManager(const CloudManager &) = delete;
  CloudManager &operator=(const CloudManager &) = delete;

  /**
   * @brief Inicializa el CloudManager
   */
  bool begin();

  /**
   * @brief Inicia la tarea de envío cloud
   */
  void startTask();

  /**
   * @brief Detiene la tarea
   */
  void stopTask();

  /**
   * @brief Estado actual de la red
   */
  NetworkState getNetworkState() const { return _networkState; }

  /**
   * @brief Verifica estado de conexiones
   */
  bool isWifiConnected() const { return WiFi.isConnected(); }
  bool isMqttConnected() { return _mqttClient.connected(); }
  bool isFullyConnected() { return _networkState == NetworkState::MQTT_OK; }

  /**
   * @brief Estadísticas
   */
  uint32_t getSuccessCount() const { return _successCount; }
  uint32_t getFailCount() const { return _failCount; }
  uint32_t getOfflineBufferCount() const {
    return OfflineBuffer::getInstance().count();
  }
  int8_t getWifiRssi() const { return WiFi.isConnected() ? WiFi.RSSI() : 0; }

  /**
   * @brief Imprime estado
   */
  void printStatus();

  // ========================================================================
  // FAST PATH: Publicación por evento (baja latencia)
  // ========================================================================

  /**
   * @brief Solicita un envío cloud lo antes posible (sin bloquear).
   *
   * Se usa cuando llega telemetría crítica (ej: DATA del ESP32-C3) y queremos
   * que el siguiente publish ocurra tan pronto como lo permita el throttle
   * (cfg.cloud_interval_ms) y el estado de red.
   *
   * Thread-safe: puede llamarse desde otras tareas (OBD/CAN/GPS).
   */
  void requestImmediatePublish();

  /**
   * @brief Métricas de latencia (para diagnóstico)
   */
  uint32_t getLastPublishMs() const { return _lastPublishMs; }
  uint32_t getLastPublishLatencyMs() const { return _lastPublishLatencyMs; }

private:
  CloudManager();

  static void taskFunction(void *param);
  void taskLoop();

  StatusLed *_statusLed = nullptr;

public:
  void setStatusLed(StatusLed *led) { _statusLed = led; }

private:
  // === State Machine (P0.2) ===
  void updateNetworkState();
  bool startWifiConnection(); // No bloqueante
  bool checkWifiConnection(); // Verifica progreso
  bool startMqttConnection(); // No bloqueante
  bool checkMqttConnection(); // Verifica progreso

  // === Backoff ===
  void resetWifiBackoff();
  void resetMqttBackoff();
  unsigned long getWifiRetryDelay();
  unsigned long getMqttRetryDelay();

  // === Envío ===
  String buildPayload();
  bool sendMqtt(const String &payload);
  bool sendHttp(const String &payload);
  void drainOfflineBuffer(); // P0.1: enviar buffer acumulado

  // === Clientes ===
  WiFiClient _wifiClient;
  PubSubClient _mqttClient;

  TaskHandle_t _taskHandle;

  // === Estado de red (P0.2) ===
  NetworkState _networkState;
  unsigned long _stateEnteredAt; // Cuando entramos al estado actual
  unsigned long _lastWifiAttempt;
  unsigned long _lastMqttAttempt;
  uint8_t _wifiRetryCount;
  uint8_t _mqttRetryCount;

  // === Estadísticas ===
  uint32_t _successCount;
  uint32_t _failCount;
  uint32_t _offlineSaved;
  uint32_t _offlineSent;

  unsigned long _lastSendTime;

  // === Fast publish trigger (event-driven) ===
  volatile bool _immediatePublishPending = false;
  portMUX_TYPE _immediateMux = portMUX_INITIALIZER_UNLOCKED;
  uint32_t _lastPublishMs = 0;
  uint32_t _lastPublishLatencyMs = 0;

  // === Timeouts (P0.4) ===
  static constexpr unsigned long WIFI_CHECK_INTERVAL =
      100; // Check WiFi cada 100ms
};

#endif // CLOUD_MANAGER_H
