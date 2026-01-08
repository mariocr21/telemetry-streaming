/**
 * @file config_defaults.h
 * @brief Valores por defecto para la configuración del sistema
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 */

#ifndef CONFIG_DEFAULTS_H
#define CONFIG_DEFAULTS_H

#include "config_schema.h"

// ============================================================================
// PINES POR DEFECTO
// ============================================================================

// CAN Bus (MCP2515)
#define DEFAULT_CAN_CS_PIN 5
#define DEFAULT_CAN_INT_PIN 4
#define DEFAULT_CAN_BAUD_KBPS 500
#define DEFAULT_CAN_CRYSTAL_MHZ 8

// GPS (UART2)
#define DEFAULT_GPS_RX_PIN 16
#define DEFAULT_GPS_TX_PIN 17
#define DEFAULT_GPS_BAUD 9600

// IMU (I2C)
#define DEFAULT_IMU_SDA_PIN 21
#define DEFAULT_IMU_SCL_PIN 22

// OBD Bridge UART (UART1)
#define DEFAULT_OBD_UART_RX 32
#define DEFAULT_OBD_UART_TX 33
#define DEFAULT_OBD_UART_BAUD 460800

// ELM327 WiFi
#define DEFAULT_ELM_IP "192.168.0.10"
#define DEFAULT_ELM_PORT 35000

// ============================================================================
// STATUS LEDS
// ============================================================================

// ============================================================================
// STATUS LEDS (DIAGNÓSTICO VISUAL)
// ============================================================================

// LED 1: Estado WiFi (Capa Física) -> GPIO 25
#define LED_WIFI_PIN 25
// LED 2: Estado Cloud/MQTT (Transporte) -> GPIO 2 (Built-in LED / D2)
#define LED_CLOUD_PIN 2
// LED 3: Actividad CAN Bus (MoTeC) -> GPIO 27
#define LED_CAN_PIN 27
// LED 4: Actividad OBD2 (ECU) -> GPIO 14
#define LED_OBD_PIN 14

// ============================================================================
// INTERVALOS POR DEFECTO (ms)
// ============================================================================

#define DEFAULT_CLOUD_INTERVAL_MS 100 // Optimizado para 10Hz (antes 1000)
#define DEFAULT_SERIAL_INTERVAL_MS 30
#define DEFAULT_OBD_POLL_INTERVAL 100

// ============================================================================
// MQTT POR DEFECTO
// ============================================================================

#define DEFAULT_MQTT_PORT 1883
#define DEFAULT_MQTT_TOPIC "vehicles/telemetry"

// ============================================================================
// COMBUSTIBLE POR DEFECTO
// ============================================================================

#define DEFAULT_ENGINE_DISPLACEMENT 5.0f // 5L típico Trophy Truck
#define DEFAULT_VOLUMETRIC_EFF 0.85f
#define DEFAULT_AIR_FUEL_RATIO 14.7f

// ============================================================================
// PIDs OBD2 POR DEFECTO
// ============================================================================

#define DEFAULT_PIDS_ENABLED "0x0C,0x0D,0x04,0x05,0x10,0x0B,BAT"
// 0x0C = RPM
// 0x0D = Speed
// 0x04 = Engine Load
// 0x05 = Coolant Temp
// 0x10 = MAF
// 0x0B = Intake Manifold Pressure
// BAT  = Battery Voltage

// ============================================================================
// FUNCIÓN PARA OBTENER CONFIGURACIÓN POR DEFECTO
// ============================================================================

/**
 * @brief Obtiene una configuración con todos los valores por defecto
 * @return UnifiedConfig con valores default
 */
inline UnifiedConfig getDefaultConfig() {
  UnifiedConfig cfg = {};

  // Versión
  strncpy(cfg.version, CONFIG_VERSION, sizeof(cfg.version) - 1);

  // Identificación
  strncpy(cfg.device_id, "NEURONA_001", sizeof(cfg.device_id) - 1);
  strncpy(cfg.car_id, "TRUCK-2024-001", sizeof(cfg.car_id) - 1);

  // Fuente de datos (CAN por defecto para MoTeC)
  cfg.source = DataSource::CAN_ONLY;

  // WiFi (vacío, debe configurarse)
  cfg.wifi.ssid[0] = '\0';
  cfg.wifi.password[0] = '\0';

  // Cloud
  cfg.cloud_protocol = CloudProtocol::MQTT;
  strncpy(cfg.mqtt.server, "broker.neurona.mx", sizeof(cfg.mqtt.server) - 1);
  cfg.mqtt.port = DEFAULT_MQTT_PORT;
  strncpy(cfg.mqtt.user, "", sizeof(cfg.mqtt.user) - 1);
  strncpy(cfg.mqtt.password, "", sizeof(cfg.mqtt.password) - 1);
  strncpy(cfg.mqtt.topic, DEFAULT_MQTT_TOPIC, sizeof(cfg.mqtt.topic) - 1);
  strncpy(cfg.http.url, "https://api.neurona.mx/telemetry",
          sizeof(cfg.http.url) - 1);
  cfg.cloud_interval_ms = DEFAULT_CLOUD_INTERVAL_MS;
  cfg.debug_mode = false;

  // Serial
  cfg.serial_interval_ms = DEFAULT_SERIAL_INTERVAL_MS;

  // CAN
  cfg.can.enabled = true;
  cfg.can.cs_pin = DEFAULT_CAN_CS_PIN;
  cfg.can.int_pin = DEFAULT_CAN_INT_PIN;
  cfg.can.baud_kbps = DEFAULT_CAN_BAUD_KBPS;
  cfg.can.crystal_mhz = DEFAULT_CAN_CRYSTAL_MHZ;

  // OBD
  cfg.obd.enabled = false;
  strncpy(cfg.obd.mode, "direct", sizeof(cfg.obd.mode) - 1);
  cfg.obd.elm_ssid[0] = '\0';
  cfg.obd.elm_password[0] = '\0';
  strncpy(cfg.obd.elm_ip, DEFAULT_ELM_IP, sizeof(cfg.obd.elm_ip) - 1);
  cfg.obd.elm_port = DEFAULT_ELM_PORT;
  strncpy(cfg.obd.pids_enabled, DEFAULT_PIDS_ENABLED,
          sizeof(cfg.obd.pids_enabled) - 1);
  cfg.obd.poll_interval_ms = DEFAULT_OBD_POLL_INTERVAL;
  cfg.obd.uart_rx_pin = DEFAULT_OBD_UART_RX;
  cfg.obd.uart_tx_pin = DEFAULT_OBD_UART_TX;
  cfg.obd.uart_baud = DEFAULT_OBD_UART_BAUD;

  // GPS
  cfg.gps.enabled = true;
  cfg.gps.rx_pin = DEFAULT_GPS_RX_PIN;
  cfg.gps.tx_pin = DEFAULT_GPS_TX_PIN;
  cfg.gps.baud = DEFAULT_GPS_BAUD;

  // IMU
  cfg.imu.enabled = true;
  cfg.imu.sda_pin = DEFAULT_IMU_SDA_PIN;
  cfg.imu.scl_pin = DEFAULT_IMU_SCL_PIN;

  // Combustible
  cfg.fuel.method = FuelMethod::AUTO;
  cfg.fuel.displacement_l = DEFAULT_ENGINE_DISPLACEMENT;
  cfg.fuel.volumetric_efficiency = DEFAULT_VOLUMETRIC_EFF;
  cfg.fuel.air_fuel_ratio = DEFAULT_AIR_FUEL_RATIO;

  return cfg;
}

#endif // CONFIG_DEFAULTS_H
