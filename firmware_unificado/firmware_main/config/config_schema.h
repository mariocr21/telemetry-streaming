/**
 * @file config_schema.h
 * @brief Definiciones de estructuras de configuración unificadas
 *
 * Este archivo define todas las estructuras de datos para la configuración
 * del sistema Neurona Off Road Telemetry.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef CONFIG_SCHEMA_H
#define CONFIG_SCHEMA_H

#include <Arduino.h>

// ============================================================================
// CONSTANTES DE CONFIGURACIÓN
// ============================================================================

#define CONFIG_VERSION "3.0"
#define MAX_SENSORS 50
#define MAX_PID_COUNT 20
#define MAX_STRING_LEN 64
#define MAX_TOPIC_LEN 128
#define MAX_URL_LEN 256
#define MAX_PIDS_STRING 256

// ============================================================================
// ENUMERACIONES
// ============================================================================

/**
 * @brief Fuente de datos principal del sistema
 */
enum class DataSource : uint8_t {
  CAN_ONLY = 0,    ///< Solo CAN Bus (MoTeC)
  OBD_DIRECT = 1,  ///< OBD2 directo via WiFi (ELM327)
  OBD_BRIDGE = 2,  ///< OBD2 via ESP32-C3 bridge
  CAN_OBD = 3,     ///< Híbrido: CAN + OBD2
  SENSORS_ONLY = 4 ///< Solo GPS + IMU (tracking)
};

/**
 * @brief Protocolo de comunicación cloud
 */
enum class CloudProtocol : uint8_t { MQTT = 0, HTTP = 1 };

/**
 * @brief Método de cálculo de consumo de combustible
 */
enum class FuelMethod : uint8_t {
  AUTO = 0,  ///< Selección automática
  MAF = 1,   ///< Mass Air Flow
  MAP = 2,   ///< Manifold Absolute Pressure
  SPEED = 3, ///< Basado en velocidad
  ECU = 4    ///< Dato directo del ECU
};

// ============================================================================
// ESTRUCTURAS DE CONFIGURACIÓN
// ============================================================================

/**
 * @brief Configuración de un sensor CAN
 */
struct SensorConfig {
  char name[32];      ///< Nombre del sensor (ej: "RPM")
  char cloud_id[32];  ///< ID para cloud (ej: "engine.rpm")
  uint32_t can_id;    ///< ID de la trama CAN
  uint8_t start_byte; ///< Byte inicial (big endian)
  uint8_t start_bit;  ///< Bit inicial (little endian)
  uint8_t length;     ///< Longitud en bits
  bool signed_val;    ///< Valor con signo
  float multiplier;   ///< Multiplicador
  float offset;       ///< Offset (adder)
  bool big_endian;    ///< Byte order
  bool enabled;       ///< Habilitado para lectura

  // Optimization (P1.5 - String Removal)
  // Mapeo directo a TelemetryBus para evitar strcmp en cada frame
  enum class MappingType : uint8_t {
    CUSTOM = 0,
    ENGINE_RPM,
    ENGINE_SPEED,
    ENGINE_COOLANT,
    ENGINE_OIL_TEMP,
    ENGINE_THROTTLE,
    ENGINE_LOAD,
    ENGINE_MAF,
    ENGINE_MAP,
    FUEL_LEVEL,
    FUEL_RATE,
    BATTERY_VOLT,
    SUSP_FL,
    SUSP_FR,
    SUSP_RL,
    SUSP_RR
  } map_type;

  // Runtime (no persistente)
  volatile float value;  ///< Valor actual
  volatile bool updated; ///< Flag de actualización
};

/**
 * @brief Configuración WiFi
 */
struct WifiConfig {
  char ssid[MAX_STRING_LEN];
  char password[MAX_STRING_LEN];
};

/**
 * @brief Configuración MQTT
 */
struct MqttConfig {
  char server[MAX_STRING_LEN];
  uint16_t port;
  char user[MAX_STRING_LEN];
  char password[MAX_STRING_LEN];
  char topic[MAX_TOPIC_LEN];
};

/**
 * @brief Configuración HTTP
 */
struct HttpConfig {
  char url[MAX_URL_LEN];
};

/**
 * @brief Configuración CAN Bus
 */
struct CanConfig {
  bool enabled;
  int8_t cs_pin;       ///< Chip Select pin (default: 5)
  int8_t int_pin;      ///< Interrupt pin (default: 4)
  uint16_t baud_kbps;  ///< Velocidad: 250, 500, 1000
  uint8_t crystal_mhz; ///< Cristal MCP2515: 8 o 16
};

/**
 * @brief Configuración OBD2
 */
struct ObdConfig {
  bool enabled;
  char mode[16]; ///< "direct" o "bridge"

  // WiFi del ELM327 (modo direct)
  char elm_ssid[32];
  char elm_password[32];
  char elm_ip[16];
  uint16_t elm_port;

  // PIDs habilitados (lista separada por comas)
  char pids_enabled[MAX_PIDS_STRING];
  uint16_t poll_interval_ms;

  // C3 Bridge (modo bridge)
  int8_t uart_rx_pin; ///< Pin RX para UART C3 (default: 32)
  int8_t uart_tx_pin; ///< Pin TX para UART C3 (default: 33)
  uint32_t uart_baud; ///< Velocidad UART (default: 460800)
};

/**
 * @brief Configuración de combustible
 */
struct FuelConfig {
  FuelMethod method;
  float displacement_l;        ///< Cilindrada en litros
  float volumetric_efficiency; ///< Eficiencia volumétrica (0-1)
  float air_fuel_ratio;        ///< Relación aire/combustible
};

/**
 * @brief Configuración GPS
 */
struct GpsConfig {
  bool enabled;
  int8_t rx_pin; ///< Pin RX (default: 16)
  int8_t tx_pin; ///< Pin TX (default: 17)
  uint32_t baud; ///< Velocidad (default: 9600)
};

/**
 * @brief Configuración IMU (MPU6050)
 */
struct ImuConfig {
  bool enabled;
  int8_t sda_pin; ///< Pin SDA (default: 21)
  int8_t scl_pin; ///< Pin SCL (default: 22)
};

/**
 * @brief Configuración unificada del sistema
 */
struct UnifiedConfig {
  // Metadatos
  char version[8];

  // Identificación
  char device_id[32];
  char car_id[32];

  // Fuente de datos principal
  DataSource source;

  // WiFi (para cloud)
  WifiConfig wifi;

  // Cloud
  CloudProtocol cloud_protocol;
  MqttConfig mqtt;
  HttpConfig http;
  uint32_t cloud_interval_ms;
  bool debug_mode; ///< true = no guarda en DB

  // Serial
  uint32_t serial_interval_ms;

  // Módulos
  CanConfig can;
  ObdConfig obd;
  GpsConfig gps;
  ImuConfig imu;
  FuelConfig fuel;
};

// ============================================================================
// FUNCIONES HELPER
// ============================================================================

/**
 * @brief Convierte DataSource a string
 */
inline const char *dataSourceToString(DataSource source) {
  switch (source) {
  case DataSource::CAN_ONLY:
    return "CAN_ONLY";
  case DataSource::OBD_DIRECT:
    return "OBD_DIRECT";
  case DataSource::OBD_BRIDGE:
    return "OBD_BRIDGE";
  case DataSource::CAN_OBD:
    return "CAN_OBD";
  case DataSource::SENSORS_ONLY:
    return "SENSORS_ONLY";
  default:
    return "UNKNOWN";
  }
}

/**
 * @brief Convierte string a DataSource
 */
inline DataSource stringToDataSource(const char *str) {
  if (strcmp(str, "CAN_ONLY") == 0)
    return DataSource::CAN_ONLY;
  if (strcmp(str, "OBD_DIRECT") == 0)
    return DataSource::OBD_DIRECT;
  if (strcmp(str, "OBD_BRIDGE") == 0)
    return DataSource::OBD_BRIDGE;
  if (strcmp(str, "CAN_OBD") == 0)
    return DataSource::CAN_OBD;
  if (strcmp(str, "SENSORS_ONLY") == 0)
    return DataSource::SENSORS_ONLY;
  return DataSource::CAN_ONLY; // Default
}

/**
 * @brief Convierte FuelMethod a string
 */
inline const char *fuelMethodToString(FuelMethod method) {
  switch (method) {
  case FuelMethod::AUTO:
    return "AUTO";
  case FuelMethod::MAF:
    return "MAF";
  case FuelMethod::MAP:
    return "MAP";
  case FuelMethod::SPEED:
    return "SPEED";
  case FuelMethod::ECU:
    return "ECU";
  default:
    return "AUTO";
  }
}

/**
 * @brief Convierte string a FuelMethod
 */
inline FuelMethod stringToFuelMethod(const char *str) {
  if (strcmp(str, "MAF") == 0)
    return FuelMethod::MAF;
  if (strcmp(str, "MAP") == 0)
    return FuelMethod::MAP;
  if (strcmp(str, "SPEED") == 0)
    return FuelMethod::SPEED;
  if (strcmp(str, "ECU") == 0)
    return FuelMethod::ECU;
  return FuelMethod::AUTO;
}

#endif // CONFIG_SCHEMA_H
