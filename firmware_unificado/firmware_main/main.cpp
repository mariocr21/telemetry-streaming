/**
 * @file main.cpp
 * @brief Punto de entrada del Firmware Unificado Neurona Off Road Telemetry
 *
 * Este archivo orquesta la inicialización y ejecución de todos los
 * módulos del sistema según la configuración.
 *
 * @author Neurona Racing Development
 * @date 2024-12-19
 * @version 3.0
 */

#include <Arduino.h>
#include <esp_task_wdt.h>

// === Config ===
#include "config/config_manager.h"

// === Telemetry Bus ===
#include "telemetry/telemetry_bus.h"

// === Data Sources ===
#include "sources/source_can.h"
#include "sources/source_gps.h"
#include "sources/source_imu.h"
#include "sources/source_obd_bridge.h"
#include "sources/source_obd_direct.h"

// === Cloud ===
#include "cloud/cloud_manager.h"

// === Serial ===
#include "serial/serial_manager.h"

// === Status LEDs ===
#include "config/config_defaults.h"
#include "status_led.h"

// ============================================================================
// INSTANCIAS DE HARDWARE UTILITARIO
// ============================================================================

StatusLed ledWifi(LED_WIFI_PIN);
StatusLed ledCloud(LED_CLOUD_PIN);
StatusLed ledCan(LED_CAN_PIN);
StatusLed ledObd(LED_OBD_PIN);

// ============================================================================
// INSTANCIAS DE FUENTES DE DATOS
// ============================================================================

static SourceGPS *sourceGps = nullptr;
static SourceIMU *sourceImu = nullptr;
static SourceCAN *sourceCan = nullptr;
static SourceOBDDirect *sourceObdDirect = nullptr;
static SourceOBDBridge *sourceObdBridge = nullptr;

// ============================================================================
// PROTOTIPOS
// ============================================================================

void printBanner();
void initWatchdog();
void initSources();
void startSources();
void printSystemStatus();

// ============================================================================
// SETUP
// ============================================================================

void setup() {
  // === 1. Serial Manager (incluye Serial.begin) ===
  SerialManager::getInstance().begin(115200);
  printBanner();

  // === 1.1 Status LEDs Init ===
  ledWifi.begin();
  ledCloud.begin();
  ledCan.begin();
  ledObd.begin();

  // Test secuencial de LEDs al inicio (Knight Rider)
  ledWifi.setPattern(StatusLed::ON);
  delay(200);
  ledCloud.setPattern(StatusLed::ON);
  delay(200);
  ledCan.setPattern(StatusLed::ON); // Was ledGps
  delay(200);
  ledObd.setPattern(StatusLed::ON); // Was ledError
  delay(500);

  ledWifi.setPattern(StatusLed::OFF);
  ledCloud.setPattern(StatusLed::OFF);
  ledCan.setPattern(StatusLed::OFF);
  ledObd.setPattern(StatusLed::OFF);

  // === 2. Watchdog ===
  initWatchdog();

  // === 3. Config Manager ===
  Serial.println(F("[MAIN] Loading configuration..."));
  ConfigManager::getInstance().begin();
  ConfigManager::getInstance().printConfig();

  // === 4. Telemetry Bus ===
  Serial.println(F("[MAIN] Initializing TelemetryBus..."));
  TelemetryBus::getInstance().begin();

  // === 5. Inicializar fuentes de datos ===
  Serial.println(F("[MAIN] Initializing data sources..."));
  initSources();

  // === 6. Cloud Manager ===
  Serial.println(F("[MAIN] Initializing CloudManager..."));
  CloudManager::getInstance().begin();
  CloudManager::getInstance().setStatusLed(&ledCloud);

  // === 7. Iniciar tareas de fuentes ===
  Serial.println(F("[MAIN] Starting data source tasks..."));
  startSources();

  // === 8. Iniciar tarea cloud ===
  Serial.println(F("[MAIN] Starting CloudManager task..."));
  CloudManager::getInstance().startTask();

  // === 9. Status final ===
  printSystemStatus();

  Serial.println(F("\n[MAIN] ====== SYSTEM READY ======\n"));
}

// ============================================================================
// LOOP
// ============================================================================

void loop() {
  // Procesar comandos serial
  SerialManager::getInstance().process();

  // === LED Logic Update ===
  ledWifi.update();
  ledCloud.update();
  ledCan.update();
  ledObd.update();

  // === System LED Logic (Activity Monitor - using WIFI/SYSTEM LED) ===
  // Si hay datos nuevos en TelemetryBus, parpadear CAN LED (Activity)
  if (TelemetryBus::getInstance().countUpdated() > 0) {
    ledCan.flash(); // Flash en cada ciclo con datos nuevos
    TelemetryBus::getInstance()
        .clearUpdatedFlags(); // Limpiar flags para detectar nuevos prox ciclo
  } else {
    // Si no hay datos, heartbeat lento en System LED
    ledWifi.setPattern(StatusLed::HEARTBEAT);
  }

  // === Cloud LED Logic ===
  if (WiFi.isConnected()) {
    if (CloudManager::getInstance().isFullyConnected()) {
      ledCloud.setPattern(StatusLed::ON);
    } else {
      ledCloud.setPattern(StatusLed::FAST_BLINK); // WiFi OK, MQTT conectando
    }
  } else {
    ledCloud.setPattern(StatusLed::SLOW_BLINK); // Buscando WiFi
  }

  // === GPS/OBD LED Logic ===
  TelemetrySnapshot snap;
  TelemetryBus::getInstance().getSnapshot(snap);

  // Usamos el LED OBD (Pin 14) para indicar GPS Fix si no tenemos OBD activo
  // O podemos alternar si tenemos OBD. Por simplicidad, GPS Fix ON.
  if (snap.gps_fix) {
    ledObd.setPattern(StatusLed::ON);
  } else {
    ledObd.setPattern(StatusLed::OFF);
  }

  // Pequeño delay para no saturar (las tareas corren en FreeRTOS)
  vTaskDelay(pdMS_TO_TICKS(10)); // 10ms es suficiente para LEDs y Serial
}

// ============================================================================
// FUNCIONES
// ============================================================================

void printBanner() {
  Serial.println(F(R"(
  _   _                                    
 | \ | | ___ _   _ _ __ ___  _ __   __ _  
 |  \| |/ _ \ | | | '__/ _ \| '_ \ / _` | 
 | |\  |  __/ |_| | | | (_) | | | | (_| | 
 |_| \_|\___|\__,_|_|  \___/|_| |_|\__,_| 
                                          
   OFF ROAD TELEMETRY - Unified Firmware
   Version 3.0 - December 2024
)"));
}

void initWatchdog() {
  // P0.3: Reducir WDT a 5s para reset rápido ante freeze
  Serial.println(F("[MAIN] Configuring Task Watchdog (5s)..."));

  // Configurar watchdog global - 5 segundos, panic on timeout
  // NOTA: Cloud task NO se registra en el WDT (puede bloquearse sin afectar
  // sistema)
  esp_task_wdt_init(5, true);

  // No agregamos la tarea de loop al WDT porque las tareas FreeRTOS
  // manejan su propio registro
}

void initSources() {
  auto &cfg = ConfigManager::getInstance().getConfig();

  // === GPS ===
  if (cfg.gps.enabled) {
    Serial.println(F("[MAIN] Creating SourceGPS..."));
    sourceGps = new SourceGPS();
    if (!sourceGps->begin()) {
      Serial.println(F("[MAIN] WARNING: GPS initialization failed"));
    }
  } else {
    Serial.println(F("[MAIN] GPS disabled"));
  }

  // === IMU ===
  if (cfg.imu.enabled) {
    Serial.println(F("[MAIN] Creating SourceIMU..."));
    sourceImu = new SourceIMU();
    if (!sourceImu->begin()) {
      Serial.println(F("[MAIN] WARNING: IMU initialization failed"));
    }
  } else {
    Serial.println(F("[MAIN] IMU disabled"));
  }

  // === CAN ===
  bool needCan =
      (cfg.source == DataSource::CAN_ONLY || cfg.source == DataSource::CAN_OBD);
  if (cfg.can.enabled && needCan) {
    Serial.println(F("[MAIN] Creating SourceCAN..."));
    sourceCan = new SourceCAN();
    if (!sourceCan->begin()) {
      Serial.println(F("[MAIN] WARNING: CAN initialization failed"));
    }
  } else {
    Serial.println(F("[MAIN] CAN disabled or not needed for current mode"));
  }

  // === OBD Logic (Strict Source of Truth) ===
  // Prioridad: cfg.source manda.
  // cfg.obd.mode solo se consulta en modo Híbrido (CAN_OBD) o como fallback.

  bool isObdDirect = (cfg.source == DataSource::OBD_DIRECT);
  bool isObdBridge = (cfg.source == DataSource::OBD_BRIDGE);
  bool isHybrid = (cfg.source == DataSource::CAN_OBD);

  // Determinar qué driver OBD usar
  bool useDirectDriver = isObdDirect;
  bool useBridgeDriver = isObdBridge;

  if (isHybrid) {
    // En modo híbrido, miramos el modo específico
    if (strcmp(cfg.obd.mode, "bridge") == 0) {
      useBridgeDriver = true;
    } else {
      useDirectDriver = true;
    }
  }

  // Debug
  Serial.printf("[MAIN] OBD Resolution -> Source: %d, Hybrid: %d -> UseDirect: "
                "%d, UseBridge: %d\n",
                (int)cfg.source, isHybrid, useDirectDriver, useBridgeDriver);

  // Instanciar
  if (useDirectDriver) {
    if (cfg.obd.enabled) {
      Serial.println(F("[MAIN] Creating SourceOBDDirect..."));
      sourceObdDirect = new SourceOBDDirect();
      if (!sourceObdDirect->begin()) {
        Serial.println(F("[MAIN] WARNING: OBD Direct initialization failed"));
      }
    } else {
      Serial.println(F("[MAIN] OBD Direct requested but 'obd.enabled' is false "
                       "(Check Config)"));
    }
  }

  if (useBridgeDriver) {
    if (cfg.obd.enabled) {
      Serial.println(F("[MAIN] Creating SourceOBDBridge..."));
      sourceObdBridge = new SourceOBDBridge();
      if (!sourceObdBridge->begin()) {
        Serial.println(F("[MAIN] WARNING: OBD Bridge initialization failed"));
      }
    } else {
      Serial.println(F("[MAIN] OBD Bridge requested but 'obd.enabled' is false "
                       "(Check Config)"));
    }
  }
}

void startSources() {
  // Iniciar tareas de fuentes que están listas

  if (sourceGps && sourceGps->isReady()) {
    sourceGps->startTask();
  }

  if (sourceImu && sourceImu->isReady()) {
    sourceImu->startTask();
  }

  if (sourceCan && sourceCan->isReady()) {
    sourceCan->startTask();
  }

  if (sourceObdDirect && sourceObdDirect->isReady()) {
    sourceObdDirect->startTask();
  }

  if (sourceObdBridge && sourceObdBridge->isReady()) {
    sourceObdBridge->startTask();
  }
}

void printSystemStatus() {
  Serial.println(F("\n========== SYSTEM STATUS =========="));

  auto &cfg = ConfigManager::getInstance().getConfig();

  Serial.printf("Device ID: %s\n", cfg.device_id);
  Serial.printf("Car ID: %s\n", cfg.car_id);
  Serial.printf("Data Source: %s\n", dataSourceToString(cfg.source));
  Serial.println(F("---"));

  // Sources
  Serial.println(F("Data Sources:"));
  if (sourceGps) {
    sourceGps->printStatus();
  } else {
    Serial.println(F("  [GPS] Not created"));
  }

  if (sourceImu) {
    sourceImu->printStatus();
  } else {
    Serial.println(F("  [IMU] Not created"));
  }

  if (sourceCan) {
    sourceCan->printStatus();
  } else {
    Serial.println(F("  [CAN] Not created"));
  }

  if (sourceObdDirect) {
    sourceObdDirect->printStatus();
  } else {
    Serial.println(F("  [OBD_DIRECT] Not created"));
  }

  if (sourceObdBridge) {
    sourceObdBridge->printStatus();
  } else {
    Serial.println(F("  [OBD_BRIDGE] Not created"));
  }

  Serial.println(F("---"));

  // Memory
  Serial.printf("Free Heap: %lu bytes\n", ESP.getFreeHeap());
  Serial.printf("Min Free Heap: %lu bytes\n", ESP.getMinFreeHeap());

  // FreeRTOS
  Serial.printf("FreeRTOS Tasks: %d\n", uxTaskGetNumberOfTasks());

  Serial.println(F("=====================================\n"));
}
