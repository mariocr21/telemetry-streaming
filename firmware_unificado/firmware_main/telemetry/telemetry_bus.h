/**
 * @file telemetry_bus.h
 * @brief Bus de telemetría compartido thread-safe
 *
 * TelemetryBus es el buffer central donde todas las fuentes de datos
 * escriben y de donde CloudManager/SerialManager leen.
 *
 * Implementado como Singleton con mutex FreeRTOS para acceso seguro
 * desde múltiples tareas.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef TELEMETRY_BUS_H
#define TELEMETRY_BUS_H

#include <Arduino.h>
#include <freertos/FreeRTOS.h>
#include <freertos/semphr.h>
#include <map>
#include <vector>

// Tiempo máximo de espera para mutex (ms)
#define TELEMETRY_MUTEX_TIMEOUT_MS 10

// Límites para evitar fragmentación (P1.1)
#define MAX_CUSTOM_VALUES 64
#define MAX_KEY_LEN 24

/**
 * @struct TelemetryValue
 * @brief Valor de telemetría con metadata
 */
struct TelemetryValue {
  float value;        ///< Valor numérico
  uint32_t timestamp; ///< Timestamp en millis()
  bool updated;       ///< Flag de actualización desde última lectura
  char unit[8];       ///< Unidad de medida (ej: "km/h", "°C")
  char source[16];    ///< Fuente del dato (ej: "CAN", "OBD", "GPS")
  bool valid;         ///< Slot ocupado/válido
};

/**
 * @struct CustomValue
 * @brief Valor de telemetría personalizado con clave estática
 */
struct CustomValue {
  char key[MAX_KEY_LEN];
  float value;
  bool updated;
};

/**
 * @struct TelemetrySnapshot
 * @brief Snapshot completo de telemetría para serialización
 */
struct TelemetrySnapshot {
  // GPS
  float gps_lat = 0;
  float gps_lng = 0;
  float gps_alt = 0;
  float gps_speed = 0;
  float gps_course = 0;
  uint8_t gps_sats = 0;
  bool gps_fix = false;

  // IMU
  float imu_accel_x = 0;
  float imu_accel_y = 0;
  float imu_accel_z = 0;
  float imu_gyro_x = 0;
  float imu_gyro_y = 0;
  float imu_gyro_z = 0;

  // Engine (from CAN or OBD)
  float engine_rpm = 0;
  float engine_speed = 0;
  float engine_coolant_temp = 0;
  float engine_oil_temp = 0;
  float engine_throttle = 0;
  float engine_load = 0;
  float engine_maf = 0;
  float engine_map = 0;
  float engine_intake_temp = 0;

  // Fuel
  float fuel_level = 0;
  float fuel_rate = 0;
  float fuel_total = 0;

  // Battery
  float battery_voltage = 0;

  // Suspensión (MoTeC)
  float susp_fl = 0;
  float susp_fr = 0;
  float susp_rl = 0;
  float susp_rr = 0;

  // Metadata
  uint32_t uptime_ms = 0;
  int8_t wifi_rssi = 0;
  uint32_t heap_free = 0;

  // Custom sensors (CAN)
  CustomValue custom_values[MAX_CUSTOM_VALUES];
  uint8_t custom_count = 0;

  // ================================================================
  // TIMESTAMPS POR FUENTE (P1.1 - Detección de datos stale)
  // ================================================================
  uint32_t ts_gps = 0;     ///< Último update GPS (millis)
  uint32_t ts_imu = 0;     ///< Último update IMU (millis)
  uint32_t ts_engine = 0;  ///< Último update motor CAN/OBD (millis)
  uint32_t ts_fuel = 0;    ///< Último update combustible (millis)
  uint32_t ts_battery = 0; ///< Último update batería (millis)

  // Flags de validez (P1.1)
  bool gps_valid = false;    ///< GPS data is fresh (<2s old)
  bool engine_valid = false; ///< Engine data is fresh (<2s old)
};

/**
 * @class TelemetryBus
 * @brief Singleton para bus de telemetría thread-safe
 */
class TelemetryBus {
public:
  /**
   * @brief Obtiene la instancia única
   * @return Referencia al TelemetryBus
   */
  static TelemetryBus &getInstance() {
    static TelemetryBus instance;
    return instance;
  }

  // Eliminar copy constructor y assignment
  TelemetryBus(const TelemetryBus &) = delete;
  TelemetryBus &operator=(const TelemetryBus &) = delete;

  /**
   * @brief Inicializa el bus (crear mutex)
   */
  void begin();

  // ========================================================================
  // MÉTODOS DE ESCRITURA (para Sources)
  // ========================================================================

  /**
   * @brief Establece un valor en el bus (thread-safe)
   * @param key Clave del valor (ej: "engine.rpm")
   * @param value Valor numérico
   * @param unit Unidad (opcional)
   * @param source Fuente del dato (opcional)
   * @return true si se estableció correctamente
   */
  bool setValue(const String &key, float value, const char *unit = "",
                const char *source = "");

  /**
   * @brief Establece múltiples valores de una vez (más eficiente)
   * @param keys Array de claves
   * @param values Array de valores
   * @param count Número de elementos
   * @param source Fuente común
   */
  void setValues(const String keys[], const float values[], size_t count,
                 const char *source);

  // ========================================================================
  // SETTERS RÁPIDOS (evitan overhead de strings)
  // ========================================================================

  // GPS
  void setGps(float lat, float lng, float alt, float speed, float course,
              uint8_t sats, bool fix);

  // IMU
  void setImuAccel(float x, float y, float z);
  void setImuGyro(float x, float y, float z);

  // Engine
  void setEngineRpm(float rpm);
  void setEngineSpeed(float speed);
  void setEngineCoolantTemp(float temp);
  void setEngineOilTemp(float temp);
  void setEngineThrottle(float throttle);
  void setEngineLoad(float load);
  void setEngineMaf(float maf);
  void setEngineMap(float map);

  // Fuel
  void setFuelLevel(float level);
  void setFuelRate(float rate);
  void setFuelTotal(float total);

  // Battery
  void setBatteryVoltage(float voltage);

  // Suspension
  void setSuspension(float fl, float fr, float rl, float rr);

  // Custom sensor by cloud_id
  void setCustomValue(const char *cloud_id, float value);

  // ========================================================================
  // MÉTODOS DE LECTURA (para CloudManager/SerialManager)
  // ========================================================================

  /**
   * @brief Obtiene un valor del bus
   * @param key Clave del valor
   * @param out Estructura de salida
   * @return true si el valor existe
   */
  bool getValue(const String &key, TelemetryValue &out);

  /**
   * @brief Obtiene un snapshot completo de todos los valores
   * @param snapshot Estructura de salida
   */
  void getSnapshot(TelemetrySnapshot &snapshot);

  /**
   * @brief Obtiene todos los valores (solo para debug/serialización controlada)
   */
  void getAllValues(TelemetryValue *outArray, char keys[][MAX_KEY_LEN],
                    size_t maxCount, size_t &actualCount);

  /**
   * @brief Resetea todos los flags "updated"
   */
  void clearUpdatedFlags();

  /**
   * @brief Cuenta valores actualizados desde última limpieza
   * @return Número de valores con updated=true
   */
  size_t countUpdated();

  /**
   * @brief Imprime estado del bus al Serial (debug)
   */
  void printStatus();

private:
  TelemetryBus() : _mutex(nullptr) {}

  SemaphoreHandle_t _mutex;

  // Datos del snapshot (acceso rápido)
  TelemetrySnapshot _snapshot;

  // Almacenamiento estático para valores genéricos (P1.1)
  TelemetryValue _generic_values[MAX_CUSTOM_VALUES];
  char _generic_keys[MAX_CUSTOM_VALUES][MAX_KEY_LEN];
  uint8_t _generic_count = 0;

  // Helper para tomar mutex
  bool
  takeMutex(TickType_t timeout = pdMS_TO_TICKS(TELEMETRY_MUTEX_TIMEOUT_MS));
  void giveMutex();
};

// ============================================================================
// CLAVES ESTÁNDAR DEL BUS
// ============================================================================

namespace TelemetryKeys {
// GPS
constexpr const char *GPS_LAT = "gps.lat";
constexpr const char *GPS_LNG = "gps.lng";
constexpr const char *GPS_ALT = "gps.alt";
constexpr const char *GPS_SPEED = "gps.speed";
constexpr const char *GPS_COURSE = "gps.course";
constexpr const char *GPS_SATS = "gps.sats";
constexpr const char *GPS_FIX = "gps.fix";

// IMU
constexpr const char *IMU_ACCEL_X = "imu.accel_x";
constexpr const char *IMU_ACCEL_Y = "imu.accel_y";
constexpr const char *IMU_ACCEL_Z = "imu.accel_z";
constexpr const char *IMU_GYRO_X = "imu.gyro_x";
constexpr const char *IMU_GYRO_Y = "imu.gyro_y";
constexpr const char *IMU_GYRO_Z = "imu.gyro_z";

// Engine
constexpr const char *ENGINE_RPM = "engine.rpm";
constexpr const char *ENGINE_SPEED = "engine.speed";
constexpr const char *ENGINE_COOLANT_TEMP = "engine.coolant_temp";
constexpr const char *ENGINE_OIL_TEMP = "engine.oil_temp";
constexpr const char *ENGINE_THROTTLE = "engine.throttle";
constexpr const char *ENGINE_LOAD = "engine.load";
constexpr const char *ENGINE_MAF = "engine.maf";
constexpr const char *ENGINE_MAP = "engine.map";
constexpr const char *ENGINE_INTAKE_TEMP = "engine.intake_temp";

// Fuel
constexpr const char *FUEL_LEVEL = "fuel.level";
constexpr const char *FUEL_RATE = "fuel.rate";
constexpr const char *FUEL_TOTAL = "fuel.total";

// Battery
constexpr const char *BATTERY_VOLTAGE = "battery.voltage";

// Suspension
constexpr const char *SUSP_FL = "suspension.fl";
constexpr const char *SUSP_FR = "suspension.fr";
constexpr const char *SUSP_RL = "suspension.rl";
constexpr const char *SUSP_RR = "suspension.rr";
} // namespace TelemetryKeys

#endif // TELEMETRY_BUS_H
