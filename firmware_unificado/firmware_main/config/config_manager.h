/**
 * @file config_manager.h
 * @brief Gestor centralizado de configuración del sistema
 *
 * Singleton que maneja la carga, guardado y acceso a toda la configuración.
 * Soporta persistencia en Preferences y exportación/importación JSON.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef CONFIG_MANAGER_H
#define CONFIG_MANAGER_H

#include "config_defaults.h"
#include "config_schema.h"
#include <Arduino.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <vector>

// Namespace para Preferences
#define PREFS_NAMESPACE "neurona"
#define PREFS_KEY_CONFIG "config"
#define PREFS_KEY_SENSORS "sensors"

/**
 * @class ConfigManager
 * @brief Singleton para gestión de configuración
 */
class ConfigManager {
public:
  /**
   * @brief Obtiene la instancia única del ConfigManager
   * @return Referencia al ConfigManager
   */
  static ConfigManager &getInstance() {
    static ConfigManager instance;
    return instance;
  }

  // Eliminar copy constructor y assignment
  ConfigManager(const ConfigManager &) = delete;
  ConfigManager &operator=(const ConfigManager &) = delete;

  /**
   * @brief Inicializa el ConfigManager
   * @return true si se cargó configuración existente, false si se usaron
   * defaults
   */
  bool begin();

  /**
   * @brief Carga configuración desde Preferences
   * @return true si se cargó correctamente
   */
  bool loadFromPreferences();

  /**
   * @brief Guarda configuración en Preferences
   * @return true si se guardó correctamente
   */
  bool saveToPreferences();

  /**
   * @brief Carga configuración desde string JSON
   * @param json String JSON con la configuración
   * @return true si se parseó correctamente
   */
  bool loadFromJson(const String &json);

  /**
   * @brief Exporta configuración a string JSON
   * @param pretty Si true, formatea el JSON con indentación
   * @return String JSON con la configuración
   */
  String exportToJson(bool pretty = false);

  /**
   * @brief Resetea a valores por defecto (no guarda automáticamente)
   */
  void resetToDefaults();

  /**
   * @brief Obtiene referencia a la configuración actual
   * @return Referencia a UnifiedConfig
   */
  UnifiedConfig &getConfig() { return _config; }

  /**
   * @brief Obtiene referencia a los sensores CAN configurados
   * @return Vector de SensorConfig
   */
  std::vector<SensorConfig> &getSensors() { return _sensors; }

  /**
   * @brief Carga definición de sensores desde JSON
   * @param json JSON con array de sensores
   * @return true si se cargó correctamente
   */
  bool loadSensorsFromJson(const String &json);

  /**
   * @brief Exporta sensores a JSON
   * @param pretty Si true, formatea con indentación
   * @return String JSON con sensores
   */
  String exportSensorsToJson(bool pretty = false);

  /**
   * @brief Guarda sensores en Preferences
   * @return true si se guardó correctamente
   */
  bool saveSensorsToPreferences();

  /**
   * @brief Carga sensores desde Preferences
   * @return true si se cargó correctamente
   */
  bool loadSensorsFromPreferences();

  /**
   * @brief Verifica si es la primera ejecución (sin config guardada)
   * @return true si no hay configuración guardada
   */
  bool isFirstRun() const { return _firstRun; }

  /**
   * @brief Valida la configuración actual (P1.2 Anti-brick)
   * @param errors Opcional: string donde guardar errores encontrados
   * @return true si la configuración es válida
   */
  bool validateConfig(String *errors = nullptr);

  /**
   * @brief Normaliza la configuración forzando coherencia con source
   *
   * Elimina "estados zombie" asegurando que obd.enabled, can.enabled y
   * obd.mode sean coherentes con device.source (la Master Key).
   *
   * Debe llamarse después de loadFromJson() para asegurar consistencia.
   */
  void normalizeConfig();

  /**
   * @brief Imprime configuración actual al Serial (debug)
   */
  void printConfig();

private:
  ConfigManager() : _firstRun(true) {}

  UnifiedConfig _config;
  std::vector<SensorConfig> _sensors;
  Preferences _prefs;
  bool _firstRun;

  // Helpers internos
  void configToJson(JsonDocument &doc);
  void jsonToConfig(JsonDocument &doc);
  void sensorsToJson(JsonDocument &doc);
  void jsonToSensors(JsonDocument &doc);
};

#endif // CONFIG_MANAGER_H
