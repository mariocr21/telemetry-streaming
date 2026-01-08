/**
 * ESP32-C3 Módulo OBD2 Autónomo
 * Version 3.2 - Corregido (C3 + ELMduino no bloqueante)
 * Librería: ELMduino 3.4.1
 *
 * Cambios clave vs 3.1:
 *  - Escaneo de PIDs usando el patrón no bloqueante de ELMduino
 *  - Lectura secuencial de PIDs tipo “state machine”
 *  - No se mezcla lectura de PIDs con DTC/SCAN cuando ELM está ocupado
 *  - Se envían PIDs con valor 0 si son válidos (finito)
 */

#include <Arduino.h>
#include <ArduinoJson.h>
#include <ELMduino.h>
#include <WiFi.h>

// ==================== CONFIGURACIÓN HARDCODEADA ====================
// WiFi del ELM327
#define ELM_SSID "WiFi_OBDII"
#define ELM_PASS ""
#define ELM_IP IPAddress(192, 168, 0, 10)
#define ELM_PORT 35000

// UART hacia ESP32 Principal
#define UART_TX_PIN 20
#define UART_RX_PIN 21
#define UART_BAUD 460800
#define LED_STATUS_PIN 8 // LED Visual Status (SuperMini C3 / generic)

// Intervalos
#define SEND_INTERVAL_MS                                                       \
  100 // Enviar PIDs cada 100ms - Optimizado para tiempo real (antes 200ms)
#define DTC_INTERVAL_MS 300000 // Leer DTCs cada 5 minutos
#define SCAN_INTERVAL_MS                                                       \
  300000 // Re-escanear PIDs cada 5 minutos (reducido de 10min)
#define SCAN_AGGRESSIVE_MS                                                     \
  120000 // Escaneo agresivo cada 2 min (antes 30s - muy intrusivo)
#define AGGRESSIVE_PERIOD_MS                                                   \
  120000 // Período agresivo: 2 minutos desde arranque (antes 5min)
#define OPPORTUNISTIC_INTERVAL_MS                                              \
  10000 // Aumentado de 2s a 10s para no bloquear DATA
#define PID_FAIL_THRESHOLD                                                     \
  5 // Fallos consecutivos para desactivar PID temporalmente

// ==================== CONFIGURACIÓN DE FILTRO DE SUAVIZADO
// ====================
#define EMA_ALPHA 1.0f // 1.0 = SIN FILTRO (Raw data)
#define OUTLIER_THRESHOLD                                                      \
  10.0f // 1000% cambio permitido (permitir saltos bruscos de RPM)
#define MIN_VALID_READINGS 1 // Aceptar datos inmediatamente

// ==================== ESTRUCTURA DE PARÁMETROS OBD ====================
struct ParametroOBD {
  const char *pid;
  const char *nombre;
  float (ELM327::*funcion)();
  bool disponible;
  float valor;    // Valor filtrado (suavizado)
  float valorRaw; // Último valor crudo leído
  float valorEMA; // Valor EMA acumulado
  unsigned long ultimaLectura;
  uint8_t lecturasValidas; // Contador de lecturas válidas consecutivas
};

// Lista de parámetros a monitorear
// Formato: {pid, nombre, funcion, disponible, valor, valorRaw, valorEMA,
// ultimaLectura, lecturasValidas}
ParametroOBD parametros[] = {
    {"0x0C", "RPM", (float (ELM327::*)())&ELM327::rpm, true, 0, 0, 0, 0, 0},
    {"BAT", "BATT_V", (float (ELM327::*)())&ELM327::batteryVoltage, true, 0, 0,
     0, 0, 0},
    {"0x05", "COOLANT", (float (ELM327::*)())&ELM327::engineCoolantTemp, true,
     0, 0, 0, 0, 0},
    {"0x04", "LOAD", (float (ELM327::*)())&ELM327::engineLoad, true, 0, 0, 0, 0,
     0},
    {"0x0F", "IAT", (float (ELM327::*)())&ELM327::intakeAirTemp, true, 0, 0, 0,
     0, 0},
    {"0x0B", "MAP", (float (ELM327::*)())&ELM327::manifoldPressure, true, 0, 0,
     0, 0, 0},
    {"0x10", "MAF", (float (ELM327::*)())&ELM327::mafRate, true, 0, 0, 0, 0, 0},
    {"0x11", "THROTTLE", (float (ELM327::*)())&ELM327::throttle, true, 0, 0, 0,
     0, 0},
    {"0x0D", "SPEED", (float (ELM327::*)())&ELM327::kph, true, 0, 0, 0, 0, 0},

    // PIDs adicionales
    {"0x5E", "FUEL_RATE", (float (ELM327::*)())&ELM327::fuelRate, true, 0, 0, 0,
     0, 0},
    {"0x2F", "FUEL_LEVEL", (float (ELM327::*)())&ELM327::fuelLevel, true, 0, 0,
     0, 0, 0},
    {"0x51", "FUEL_PRESSURE", (float (ELM327::*)())&ELM327::fuelPressure, true,
     0, 0, 0, 0, 0},
    {"0x5C", "OIL_TEMP", (float (ELM327::*)())&ELM327::oilTemp, true, 0, 0, 0,
     0, 0},
    {"0x3C", "CAT_TEMP_B1S1", (float (ELM327::*)())&ELM327::catTempB1S1, true,
     0, 0, 0, 0, 0},
};

const int NUM_PARAMETROS = sizeof(parametros) / sizeof(parametros[0]);

// Forward declarations
bool esPIDCombustible(const ParametroOBD &p);
bool esPIDBase(const ParametroOBD &p);

// ==================== FUNCIONES DE FILTRADO ====================
// Aplica filtro EMA con rechazo de outliers
float aplicarFiltro(ParametroOBD &p, float nuevoValor) {
  // Si el valor no es válido, mantener el anterior
  if (!isfinite(nuevoValor)) {
    return p.valor;
  }

  // Primera lectura válida: inicializar EMA
  if (p.lecturasValidas == 0) {
    p.valorEMA = nuevoValor;
    p.valorRaw = nuevoValor;
    p.valor = nuevoValor;
    p.lecturasValidas = 1;
    return nuevoValor;
  }

  // Calcular cambio porcentual respecto al valor EMA anterior
  float cambio = 0;
  if (p.valorEMA != 0) {
    cambio = fabs(nuevoValor - p.valorEMA) / fabs(p.valorEMA);
  } else if (nuevoValor != 0) {
    cambio = 1.0f; // Si el anterior era 0 y el nuevo no, es un cambio del 100%
  }

  // Guardar valor crudo
  p.valorRaw = nuevoValor;

  // Rechazo de outliers: si el cambio es mayor al umbral Y ya tenemos lecturas
  // estables EXCEPCIÓN: Si es un PID de combustible y el valor cae a 0 (o
  // cerca), confiamos más en el sensor (cut-off)
  bool esCombustible = esPIDCombustible(p);
  bool posibleCorteInyeccion = esCombustible && (nuevoValor < 0.1f);

  if (cambio > OUTLIER_THRESHOLD && p.lecturasValidas >= MIN_VALID_READINGS &&
      !posibleCorteInyeccion) {
    // Posible outlier - aplicar EMA más suave (menos peso al nuevo valor)
    float alphaReducido = EMA_ALPHA * 0.3f; // Reducir influencia del outlier
    p.valorEMA =
        (alphaReducido * nuevoValor) + ((1.0f - alphaReducido) * p.valorEMA);

    // Log opcional para debug
    // Serial.printf("[FILTRO] %s: Outlier detectado (%.1f -> %.1f,
    // cambio=%.0f%%)\n",
    //               p.nombre, p.valor, nuevoValor, cambio * 100);
  } else {
    // Valor normal - aplicar EMA estándar
    p.valorEMA = (EMA_ALPHA * nuevoValor) + ((1.0f - EMA_ALPHA) * p.valorEMA);

    // Incrementar contador de lecturas válidas (máximo 255)
    if (p.lecturasValidas < 255) {
      p.lecturasValidas++;
    }
  }

  // El valor final es el EMA suavizado
  p.valor = p.valorEMA;

  return p.valor;
}

// Resetea el filtro de un parámetro (útil al reconectar)
void resetearFiltro(ParametroOBD &p) {
  p.valor = 0;
  p.valorRaw = 0;
  p.valorEMA = 0;
  p.lecturasValidas = 0;
}

bool esPIDBase(const ParametroOBD &p) {
  // PIDs que SIEMPRE queremos intentar
  if (strcmp(p.pid, "0x0C") == 0)
    return true; // RPM
  if (strcmp(p.pid, "0x04") == 0)
    return true; // Carga
  if (strcmp(p.pid, "0x05") == 0)
    return true; // Temp refrigerante
  if (strcmp(p.pid, "BAT") == 0)
    return true; // Batería

  return false; // El resto son "extras"
}

bool esPIDCombustible(const ParametroOBD &p) {
  // PIDs relacionados con combustible que pueden ser 0 legítimamente
  if (strcmp(p.nombre, "FUEL_RATE") == 0)
    return true;
  if (strcmp(p.nombre, "FUEL_LEVEL") == 0)
    return true;
  if (strcmp(p.nombre, "THROTTLE") == 0)
    return true;
  if (strcmp(p.nombre, "LOAD") == 0)
    return true;
  if (strcmp(p.nombre, "MAF") == 0)
    return true;
  if (strcmp(p.nombre, "MAP") == 0)
    return true;
  return false;
}

// ==================== VARIABLES GLOBALES ====================
WiFiClient elmClient;
ELM327 elm;
HardwareSerial MainSerial(0);

// Estados
bool wifiConectado = false;
bool elmConectado = false;
bool obdEnabled = true; // NUEVO: permite pausar lectura/envío OBD por comando
                        // UART del Principal
int parametrosDisponibles = 0;
String dtcActivos[10];
int numDTCs = 0;

// Control de lectura secuencial
uint8_t idxParametro = 0;

// Temporizadores
unsigned long ultimoEnvio = 0;
unsigned long ultimoDTC = 0;
unsigned long ultimoScan = 0;

// Buffer UART
char uartBuffer[512];
int uartBufferIndex = 0;

// ==================== ESCANEO ADAPTATIVO ====================
unsigned long startupTime = 0;       // Tiempo de arranque para período agresivo
unsigned long ultimoOportunista = 0; // Última prueba oportunista
int idxOportunista = 0;              // Índice para escaneo oportunista
float ultimoRPM = 0;                 // Para detectar encendido de motor
bool motorRecienEncendido = false;   // Flag de motor recién encendido
uint8_t fallosConsecutivos[14] = {
    0}; // Contador de fallos por PID (max 14 PIDs)

// ==================== INTEGRIDAD DE DATOS (P1.1) ====================
uint8_t calcularChecksum(const String &s) {
  uint8_t checksum = 0;
  for (size_t i = 0; i < s.length(); i++) {
    checksum ^= (uint8_t)s[i];
  }
  return checksum;
}

// ==================== FUNCIONES DE INICIALIZACIÓN (Forward Declarations)
// ====================
void enviarMensaje(const String &tipo, const String &datos);

// ==================== HEARTBEAT SERVICE ====================
// Se define aquí arriba para ser visible por funciones bloqueantes
void serviceHeartbeat() {
  static unsigned long lastLinkMsg = 0;
  if (millis() - lastLinkMsg > 1000) {
    lastLinkMsg = millis();
    if (elmConectado && obdEnabled) {
      enviarMensaje("OBD_STATUS", "CONNECTED");
    } else {
      enviarMensaje("OBD_STATUS", "DISCONNECTED");
    }
  }
}

// ==================== HELPERS ELM ====================

// ¿ELM está ocupado con un mensaje pendiente?
inline bool elmOcupado() { return (elm.nb_rx_state == ELM_GETTING_MSG); }

// Consulta “bloqueante” UN SOLO PID usando el patrón no bloqueante interno de
// ELMduino. Se usa SOLO en el escaneo inicial y re-escanear, no en la lectura
// continua.
bool queryPIDBlocking(ParametroOBD &p, uint16_t timeoutMs = 500) {
  unsigned long start = millis();

  while (millis() - start < timeoutMs) {
    float valor = (elm.*(p.funcion))();

    if (elm.nb_rx_state == ELM_SUCCESS) {
      if (isfinite(valor)) {
        p.valor = valor;
        p.ultimaLectura = millis();
        return true;
      }
      return false;
    } else if (elm.nb_rx_state != ELM_GETTING_MSG) {
      // Algún error: NO DATA, TIMEOUT, etc.
      elm.printError();
      return false;
    }

    // Sigue esperando respuesta - yield más frecuente
    delay(5);
    serviceHeartbeat(); // Keep link alive during blocking query
  }

  // Timeout
  Serial.print(" (timeout)");
  return false;
}

// ==================== FUNCIONES DE INICIALIZACIÓN ====================
void conectarWiFi();
void conectarELM();
void escanearPIDs();
void leerPIDs();
void leerDTCs();
void borrarDTCs();
void enviarDatos();
void enviarMensaje(const String &tipo, const String &datos);
void procesarUART();
void procesarComando(const String &comando);
void procesarUART();
void procesarComando(const String &comando);
void verificarConexiones();

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n===== ESP32-C3 OBD2 Auto v3.2 =====");
  Serial.println("[SYS] Iniciando...");

  // Inicializar LED
  pinMode(LED_STATUS_PIN, OUTPUT);
  digitalWrite(LED_STATUS_PIN, LOW);

  // Inicializar UART hacia ESP32 Principal
  MainSerial.begin(UART_BAUD, SERIAL_8N1, UART_RX_PIN, UART_TX_PIN);
  MainSerial.setRxBufferSize(2048); // <-- NUEVO
  Serial.println("[UART] ✓ Iniciado (ESP32 Principal)");

  // Conectar WiFi
  conectarWiFi();

  // Conectar ELM327
  if (wifiConectado) {
    conectarELM();

    if (elmConectado) {
      escanearPIDs();
    }
  }

  // Inicializar sistema de escaneo adaptativo
  startupTime = millis();
  Serial.println("[SYS] ✓ Escaneo adaptativo activado:");
  Serial.printf("      - Agresivo: primeros %d segundos (cada %ds)\n",
                AGGRESSIVE_PERIOD_MS / 1000, SCAN_AGGRESSIVE_MS / 1000);
  Serial.printf("      - Normal: cada %d segundos\n", SCAN_INTERVAL_MS / 1000);
  Serial.println("      - Detección de encendido de motor: SI");
}

// ==================== CONEXIÓN WiFi ====================
void conectarWiFi() {
  Serial.print("[WiFi] Conectando a ");
  Serial.print(ELM_SSID);

  // Reset completo del módulo WiFi antes de conectar
  WiFi.disconnect(true);
  WiFi.mode(WIFI_OFF);
  delay(100);

  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true); // Habilitar auto-reconexión nativa
  WiFi.begin(ELM_SSID, ELM_PASS);

  int intentos = 0;
  const int MAX_INTENTOS = 30; // 15 segundos máximo (aumentado de 10s)

  while (WiFi.status() != WL_CONNECTED && intentos < MAX_INTENTOS) {
    delay(500);
    serviceHeartbeat(); // Keep link alive during WiFi connection
    Serial.print(".");
    intentos++;

    // Cada 10 intentos, hacer diagnóstico
    if (intentos % 10 == 0) {
      Serial.printf(" [Status:%d]", WiFi.status());
    }
  }

  if (WiFi.status() == WL_CONNECTED) {
    wifiConectado = true;
    Serial.print(" ✓ IP: ");
    Serial.print(WiFi.localIP());
    Serial.printf(" RSSI: %ddBm\n", WiFi.RSSI());
  } else {
    Serial.printf(" ✗ Fallo (Status: %d)\n", WiFi.status());
    wifiConectado = false;
  }
}

// ==================== CONEXIÓN ELM327 ====================
void conectarELM() {
  const uint8_t MAX_INTENTOS = 5; // Reducido de 10 para fallar rápido
  uint8_t intento = 0;
  bool conectado = false;

  while (!conectado && intento < MAX_INTENTOS) {
    intento++;
    serviceHeartbeat(); // Mantener link vivo ANTES de cada intento
    Serial.printf("[ELM] Intento %d/%d... ", intento, MAX_INTENTOS);

    if (elmClient.connected()) {
      elmClient.stop();
      delay(200); // Reducido de 500ms
      serviceHeartbeat();
    }

    if (!elmClient.connect(ELM_IP, ELM_PORT)) {
      Serial.println("✗ Socket");
      delay(500); // Reducido de 2000ms
      serviceHeartbeat();
      continue;
    }

    Serial.print("Socket OK, init... ");
    serviceHeartbeat();

    // CRÍTICO: Reducido timeout de 2500ms a 1500ms para no exceder margen de
    // heartbeat
    if (!elm.begin(elmClient, false, 1500)) {
      Serial.println("✗ Init");
      elmClient.stop();
      delay(500); // Reducido de 2000ms
      serviceHeartbeat();
      continue;
    }

    Serial.println("✓ Conectado");
    conectado = true;
  }

  if (!conectado) {
    // NO reiniciar - dejar que el loop principal maneje reconexión gradual
    Serial.println("[ELM] Fallo conexión - se reintentará en próximo ciclo");
    elmConectado = false;
    return;
  }

  // Configuración del ELM327 - delays reducidos con heartbeat intercalado
  Serial.println("[ELM] Configurando...");
  serviceHeartbeat();
  delay(300); // Reducido de 1000ms

  elm.sendCommand("AT Z"); // Reset
  serviceHeartbeat();
  delay(800); // Reducido de 2000ms - ELM327 necesita tiempo para reset
  serviceHeartbeat();

  elm.sendCommand("AT E0");    // Echo off
  delay(50);                   // Reducido de 100ms
  elm.sendCommand("AT ST 12"); // Timeout 50ms (12 * 4ms approx) - AGRESIVO
  delay(50);                   // Reducido de 100ms
  elm.sendCommand("AT SP 0");  // Auto protocol
  delay(50);                   // Reducido de 100ms
  serviceHeartbeat();

  // Test rápido de voltaje de batería (no crítico, solo log)
  Serial.print("[ELM] Test batería... ");
  float voltage = elm.batteryVoltage();
  if (elm.nb_rx_state == ELM_SUCCESS && voltage > 0) {
    elmConectado = true;
    Serial.printf("✓ %.2fV\n", voltage);
  } else {
    Serial.println("⚠ Sin respuesta (continuando)");
    elmConectado = true;
  }
}

// ==================== ESCANEO DE PIDs ====================
void escanearPIDs() {
  Serial.println("[SCAN] Detectando sensores disponibles...");
  parametrosDisponibles = 0;

  // Reiniciar valores pero respetar quién es base
  for (int i = 0; i < NUM_PARAMETROS; i++) {
    ParametroOBD &p = parametros[i];
    bool base = esPIDBase(p);

    // Resetear filtro y valores
    resetearFiltro(p);
    p.ultimaLectura = 0;
    // Los base se quedan habilitados para que el loop siempre los intente
    p.disponible = base;
  }

  // ========= FASE 1: SOLO PIDs BASE (arranque rápido) =========
  Serial.println("[SCAN] Fase 1: PIDs base...");
  for (int i = 0; i < NUM_PARAMETROS; i++) {
    ParametroOBD &p = parametros[i];

    // Primera pasada: solo PIDs base (arranque rápido)
    if (!esPIDBase(p))
      continue;

    serviceHeartbeat(); // Heartbeat ANTES de cada PID
    Serial.printf("  Probando %s (%s)... ", p.pid, p.nombre);

    bool ok = queryPIDBlocking(p, 400); // Reducido de 700ms

    if (ok && isfinite(p.valor)) {
      p.disponible = true;
      parametrosDisponibles++;
      Serial.printf("✓ %.2f\n", p.valor);
    } else {
      // Los base siguen marcados como disponibles aunque fallen
      Serial.println("⚠ Falló, se reintentará en bucle");
    }

    delay(30); // Reducido de 50ms
  }
  serviceHeartbeat();

  Serial.printf("[SCAN] PIDs base: %d confirmados\n", parametrosDisponibles);

  // ========= FASE 2: PIDs EXTRA (solo si hay al menos 1 base) =========
  if (parametrosDisponibles >= 1) {
    Serial.println("[SCAN] Fase 2: PIDs extra...");

    for (int i = 0; i < NUM_PARAMETROS; i++) {
      ParametroOBD &p = parametros[i];

      // Segunda pasada: PIDs no-base
      if (esPIDBase(p) || p.disponible)
        continue;

      serviceHeartbeat(); // Heartbeat ANTES de cada PID
      Serial.printf("  Probando %s (%s)... ", p.pid, p.nombre);

      bool ok = queryPIDBlocking(p, 400); // Reducido de 700ms

      if (ok && isfinite(p.valor)) {
        p.disponible = true;
        parametrosDisponibles++;
        Serial.printf("✓ %.2f\n", p.valor);
      } else {
        p.disponible = false;
        Serial.println("✗");
      }

      delay(30); // Reducido de 50ms

      // Cada 3 PIDs, hacer yield extra
      if (i % 3 == 0) {
        serviceHeartbeat();
      }
    }
  } else {
    Serial.println(
        "[SCAN] Sin PIDs base - extras se probarán oportunistamente");
  }
  serviceHeartbeat();

  Serial.printf("[SCAN] Total PIDs confirmados: %d de %d\n",
                parametrosDisponibles, NUM_PARAMETROS);
}

// ==================== LECTURA SECUENCIAL DE PIDs ====================

// Helper para filtrar valores absurdos
bool valorEnRango(const ParametroOBD &p, float v) {
  // RPM
  if (strcmp(p.nombre, "RPM") == 0) {
    return (v >= 0 && v <= 10000);
  }

  // Temperaturas
  if (strcmp(p.nombre, "COOLANT") == 0 || strcmp(p.nombre, "IAT") == 0 ||
      strcmp(p.nombre, "OIL_TEMP") == 0 ||
      strcmp(p.nombre, "CAT_TEMP_B1S1") == 0) {
    return (v > -50 && v < 1200);
  }

  // Porcentajes
  if (strcmp(p.nombre, "LOAD") == 0 || strcmp(p.nombre, "THROTTLE") == 0 ||
      strcmp(p.nombre, "FUEL_LEVEL") == 0) {
    return (v >= 0 && v <= 100);
  }

  // Velocidad
  if (strcmp(p.nombre, "SPEED") == 0) {
    return (v >= 0 && v <= 300);
  }

  // MAP kPa
  if (strcmp(p.nombre, "MAP") == 0) {
    return (v >= 0 && v <= 300);
  }

  // MAF g/s
  if (strcmp(p.nombre, "MAF") == 0) {
    return (v >= 0 && v <= 500);
  }

  // Fuel rate, presión, etc.
  if (strcmp(p.nombre, "FUEL_RATE") == 0) {
    return (v >= 0 && v <= 100);
  }
  if (strcmp(p.nombre, "FUEL_PRESSURE") == 0) {
    return (v >= 0 && v <= 2000);
  }

  // Batería
  if (strcmp(p.nombre, "BATT_V") == 0) {
    return (v > 5 && v < 20);
  }

  // Por defecto, aceptamos si es finito
  return isfinite(v);
}

void leerPIDs() {
  if (!elmConectado || !elmClient.connected()) {
    return;
  }

  // THROTTLE para NUEVOS comandos: No enviar comando nuevo si el anterior aún
  // no terminó PERO: Si el ELM está procesando (GETTING_MSG), DEBEMOS seguir
  // llamando para leer la respuesta
  static unsigned long ultimaPeticion = 0;
  static int8_t pidEnProceso =
      -1; // -1 = ninguno, 0+ = índice del PID esperando respuesta
  const unsigned long INTERVALO_MINIMO_PID =
      80; // 80ms mínimo entre NUEVOS comandos

  // Si hay un PID en proceso, debemos seguir llamando a su función hasta que
  // termine. No avanzamos idxParametro ni aplicamos throttle.
  if (pidEnProceso != -1) {
    // Asegurarse de que idxParametro apunte al PID que está en proceso
    // Esto es crucial si el loop principal avanza idxParametro por error o si
    // se reinicia. Aunque en este diseño, idxParametro solo avanza si el PID
    // actual termina. Para mayor robustez, podríamos hacer: idxParametro =
    // pidEnProceso; Pero el flujo actual ya lo mantiene.
  } else {
    // No hay PID en proceso, podemos buscar el siguiente o aplicar throttle
    // para uno nuevo. Verificar si ha pasado suficiente tiempo desde la última
    // petición exitosa o con error.
    if (millis() - ultimaPeticion < INTERVALO_MINIMO_PID) {
      return; // Demasiado pronto para enviar un NUEVO comando, esperar.
    }
  }

  // Secuencia circular de PIDs
  if (idxParametro >= NUM_PARAMETROS) {
    idxParametro = 0;
  }

  ParametroOBD &p = parametros[idxParametro];

  // Si no está marcado como disponible, saltar al siguiente
  if (!p.disponible) {
    idxParametro++;
    // Si el PID actual no está disponible, y no hay uno en proceso,
    // intentamos el siguiente inmediatamente sin throttle.
    // Esto es para evitar esperas innecesarias en PIDs no disponibles.
    if (pidEnProceso == -1) {
      ultimaPeticion = 0; // Resetear para que el próximo ciclo intente el
                          // siguiente PID disponible
    }
    return;
  }

  // Llamar a la función del PID (esto puede: enviar comando O procesar
  // respuesta pendiente)
  float valorCrudo = (elm.*(p.funcion))();

  if (elm.nb_rx_state == ELM_SUCCESS) {
    // ¡Respuesta recibida exitosamente!
    if (isfinite(valorCrudo) && valorEnRango(p, valorCrudo)) {
      aplicarFiltro(p, valorCrudo);
      p.ultimaLectura = millis();
    }
    // Pasamos al siguiente PID
    pidEnProceso = -1;
    idxParametro++;

    // Aplicar throttle: esperar antes de enviar el siguiente comando
    ultimaPeticion = millis();

  } else if (elm.nb_rx_state == ELM_GETTING_MSG) {
    // Aún esperando respuesta - marcar cuál PID está en proceso
    if (pidEnProceso ==
        -1) { // Solo marcar la primera vez que entra en GETTING_MSG
      pidEnProceso = idxParametro;
    }
    // NO avanzamos, seguiremos llamando a esta función en el próximo loop

  } else {
    // Error (NO_DATA, TIMEOUT, etc.) - mostrar y avanzar
    elm.printError();
    pidEnProceso = -1;
    idxParametro++;

    // Aplicar throttle también después de error
    ultimaPeticion = millis();
  }
}

// ==================== LECTURA DE DTCs ====================
void leerDTCs() {
  if (!elmConectado || elmOcupado()) {
    // No iniciar lectura de DTC si ELM sigue con otro PID
    return;
  }

  Serial.print("[DTC] Verificando códigos de falla... ");

  // 1) monitorStatus()
  bool ok = false;
  unsigned long start = millis();

  while (millis() - start < 1000) {
    elm.monitorStatus();

    if (elm.nb_rx_state == ELM_SUCCESS) {
      ok = true;
      break;
    } else if (elm.nb_rx_state != ELM_GETTING_MSG) {
      elm.printError();
      break;
    }

    delay(10);
  }

  if (!ok) {
    Serial.println("✗ Error monitorStatus");
    return;
  }

  // responseByte_2: bit7 = MIL, bits0-6 = num códigos
  uint8_t milStatus = (elm.responseByte_2 & 0x80);
  uint8_t numCodes = (elm.responseByte_2 & 0x7F);

  if (numCodes == 0) {
    Serial.println("✓ Sin códigos activos");
    numDTCs = 0;
    return;
  }

  Serial.printf("%d códigos detectados, MIL %s\n", numCodes,
                milStatus ? "ON" : "OFF");

  // 2) currentDTCCodes()
  ok = false;
  start = millis();

  while (millis() - start < 1500) {
    elm.currentDTCCodes();

    if (elm.nb_rx_state == ELM_SUCCESS) {
      ok = true;
      break;
    } else if (elm.nb_rx_state != ELM_GETTING_MSG) {
      elm.printError();
      break;
    }

    delay(10);
  }

  if (!ok) {
    Serial.println("[DTC] Error leyendo códigos detallados");
    return;
  }

  numDTCs = 0;
  Serial.print("[DTC] Códigos activos: ");

  for (int i = 0; i < elm.DTC_Response.codesFound && i < 10; i++) {
    dtcActivos[numDTCs++] = elm.DTC_Response.codes[i];
    Serial.print(elm.DTC_Response.codes[i]);
    Serial.print(" ");
  }

  Serial.println();
}

// ==================== BORRAR DTCs ====================
void borrarDTCs() {
  if (!elmConectado || elmOcupado()) {
    Serial.println("[DTC] No se puede borrar ahora, ELM ocupado");
    enviarMensaje("DTC_CLEARED", "BUSY");
    return;
  }

  Serial.print("[DTC] Borrando códigos de falla... ");

  // resetDTC() en versiones recientes ya devuelve bool directo
  if (elm.resetDTC()) {
    Serial.println("✓ Códigos borrados exitosamente");
    numDTCs = 0;
    enviarMensaje("DTC_CLEARED", "SUCCESS");
  } else {
    Serial.println("✗ Error al borrar códigos");
    enviarMensaje("DTC_CLEARED", "FAILED");
  }
}

// ==================== ENVÍO DE DATOS ====================
void enviarDatos() {
  JsonDocument doc;

  doc["t"] = "DATA";
  doc["ts"] = millis();

  JsonObject pids = doc["pids"].to<JsonObject>();
  int validPids = 0;

  // --- Construcción de LOG en UNA sola línea ---
  String logLine = "[DATA] ";
  logLine += "PIDs: ";

  bool firstField = true;

  for (int i = 0; i < NUM_PARAMETROS; i++) {
    // Solo PIDs marcados como disponibles
    if (!parametros[i].disponible)
      continue;

    float valor = parametros[i].valor;

    // Evitar NaN / infinito (esto sí rompe JSON)
    if (!isfinite(valor))
      continue;

    // --- Agregar al JSON (aunque sea 0) ---
    pids[parametros[i].pid] = valor;
    validPids++;

    // --- Agregar al log en texto ---
    if (!firstField) {
      logLine += " ";
    } else {
      firstField = false;
    }

    logLine += parametros[i].nombre;
    logLine += "=";

    // Formato: enteros para RPM / SPEED / LOAD, decimales para el resto
    if (strcmp(parametros[i].nombre, "RPM") == 0 ||
        strcmp(parametros[i].nombre, "SPEED") == 0 ||
        strcmp(parametros[i].nombre, "LOAD") == 0) {
      logLine += String(valor, 0);
    } else {
      logLine += String(valor, 2);
    }
  }

  logLine += " (";
  logLine += String(validPids);
  logLine += " total)";

  // Agregar DTCs al JSON y al log si existen
  if (numDTCs > 0) {
    JsonArray dtc = doc["dtc"].to<JsonArray>();

    logLine += " | DTC:";
    for (int i = 0; i < numDTCs; i++) {
      dtc.add(dtcActivos[i]);
      logLine += " ";
      logLine += dtcActivos[i];
    }
  }

  // Marcamos que se envió correctamente
  logLine += " | TX→ESP32 OK";

  // --- Log en UNA sola línea ---
  Serial.println(logLine);

  // --- Envío real al ESP32 Principal (UNA línea JSON puro) ---
  // NOTA: Checksum removido para compatibilidad con source_obd_bridge.cpp
  //       que espera JSON puro sin sufijo #XX
  String output;
  serializeJson(doc, output);
  MainSerial.println(output);
}

// ==================== ENVÍO DE MENSAJES ====================
void enviarMensaje(const String &tipo, const String &datos) {
  JsonDocument doc;
  doc["t"] = tipo;
  doc["data"] = datos;
  doc["ts"] = millis();

  String output;
  serializeJson(doc, output);
  MainSerial.println(output); // JSON puro sin checksum

  Serial.printf("[TX→] Mensaje tipo '%s' enviado\n", tipo.c_str());
}

// ==================== PROCESAMIENTO UART ====================
void procesarUART() {
  while (MainSerial.available()) {
    char c = MainSerial.read();

    if (c == '\n' || c == '\r') {
      if (uartBufferIndex > 0) {
        uartBuffer[uartBufferIndex] = '\0';
        procesarComando(String(uartBuffer));
        uartBufferIndex = 0;
      }
    } else if (uartBufferIndex < (int)sizeof(uartBuffer) - 1) {
      uartBuffer[uartBufferIndex++] = c;
    }
  }
}

void procesarComando(const String &comando) {
  Serial.print("[RX←] Comando recibido: ");
  Serial.println(comando);

  StaticJsonDocument<256> doc; // Deserialize needs size or dynamic
  // For deserializeJson, usually JsonDocument is fine too in v7, but let's
  // stick to simple replacement where appropriate or use JsonDocument
  JsonDocument docInput;
  DeserializationError error = deserializeJson(docInput, comando);

  if (error) {
    Serial.println("[RX] Error parseando JSON");
    return;
  }

  String tipo = docInput["t"] | "";

  if (tipo == "CLEAR_DTC") {
    Serial.println("[CMD] Solicitud de borrar DTCs");
    borrarDTCs();
  } else if (tipo == "SCAN") {
    Serial.println("[CMD] Solicitud de escaneo de PIDs");
    // Solo escanear si ELM no está ocupado
    if (!elmOcupado()) {
      escanearPIDs();
    } else {
      Serial.println("[SCAN] ELM ocupado, se omite este escaneo");
    }
  } else if (tipo == "OBD_ENABLE") {
    // Comando del Principal: habilitar/deshabilitar lectura y envío de DATA
    // Formato esperado (por compatibilidad con sendC3Message del Principal):
    // {"t":"OBD_ENABLE","data":"1"} o {"t":"OBD_ENABLE","data":"0"}
    String data = docInput["data"] | "";
    bool newValue = (data == "1" || data == "true" || data == "TRUE");

    if (newValue != obdEnabled) {
      obdEnabled = newValue;
      Serial.printf("[CMD] OBD_ENABLE -> %s\n", obdEnabled ? "ON" : "OFF");
    } else {
      Serial.printf("[CMD] OBD_ENABLE (sin cambio) -> %s\n",
                    obdEnabled ? "ON" : "OFF");
    }

    // Responder estado actual al Principal (opcional pero útil para
    // UI/diagnóstico)
    enviarMensaje("OBD_STATUS", obdEnabled ? "ON" : "OFF");
  } else if (tipo == "ACK") {
    Serial.println("[ACK] Confirmación recibida");
  }
}

// ==================== RECONEXIÓN ====================
void verificarConexiones() {
  static uint32_t ultimoChequeo = 0;
  static uint8_t fallosConsecutivos = 0;
  static uint8_t reconexionesWifi = 0;
  static uint8_t reconexionesElm = 0;
  const uint32_t INTERVALO_CHEQUEO =
      2000;                     // Reducido a 2s para detección más rápida
  const uint8_t MAX_FALLOS = 2; // Reducido de 3 a 2 para reconexión más rápida
  const uint16_t SOCKET_TIMEOUT_MS =
      1000; // Timeout para reconexión rápida de socket

  // Optimización: No interrumpir si estamos ocupados recibiendo datos
  if (elmOcupado())
    return;

  if (millis() - ultimoChequeo >= INTERVALO_CHEQUEO) {
    ultimoChequeo = millis();

    // Verificar WiFi
    if (!wifiConectado || WiFi.status() != WL_CONNECTED) {
      reconexionesWifi++;
      Serial.printf("[CHECK] WiFi desconectado (reconexión #%d)...\n",
                    reconexionesWifi);
      wifiConectado = false;
      elmConectado = false;
      conectarWiFi();
      if (wifiConectado) {
        conectarELM();
        if (elmConectado) {
          escanearPIDs();
        }
      }
      return;
    }

    // Verificar conexión ELM
    if (!elmClient.connected()) {
      fallosConsecutivos++;
      Serial.printf("[CHECK] ELM socket cerrado (%d/%d)\n", fallosConsecutivos,
                    MAX_FALLOS);

      if (fallosConsecutivos >= MAX_FALLOS) {
        reconexionesElm++;
        Serial.printf("[CHECK] Reconectando ELM327 (reconexión #%d)...\n",
                      reconexionesElm);
        fallosConsecutivos = 0;
        elmConectado = false;

        // Limpiar socket antes de reconectar
        elmClient.stop();
        delay(100);

        conectarELM();
        if (elmConectado) {
          escanearPIDs();
        }
      } else {
        // Intento rápido de reconexión con timeout
        Serial.print("[CHECK] Intento rápido de reconexión... ");
        elmClient.setTimeout(SOCKET_TIMEOUT_MS);

        if (elmClient.connect(ELM_IP, ELM_PORT)) {
          Serial.println("✓ OK");
          fallosConsecutivos = 0;
          elmConectado = true;
        } else {
          Serial.println("✗ Fallo");
        }
      }
    } else {
      // Conexión OK - resetear contadores de fallo
      if (fallosConsecutivos > 0) {
        Serial.println("[CHECK] ELM conexión restaurada");
        fallosConsecutivos = 0;
      }
    }
  }
}

// ==================== LOOP PRINCIPAL ====================
void loop() {
  unsigned long ahora = millis();

  // === VISUAL FEEDBACK (LED) ===
  // Parpadeo: 500ms (1Hz) si ELM conectado, OFF si desconectado
  static unsigned long lastBlink = 0;
  static bool ledState = false;

  if (elmConectado) {
    if (ahora - lastBlink > 500) {
      lastBlink = ahora;
      ledState = !ledState;
      digitalWrite(LED_STATUS_PIN, ledState);
    }
  } else {
    digitalWrite(LED_STATUS_PIN, LOW); // Apagado si no hay conexión
  }

  // Procesar comandos UART del Principal
  procesarUART();

  // Verificar conexiones periódicamente (cada 2 segundos)
  static unsigned long ultimaVerificacion = 0;
  if (ahora - ultimaVerificacion > 2000) {
    ultimaVerificacion = ahora;
    verificarConexiones();
  }

  // HEARTBEAT: Enviar status al ESP32 Principal (Manejado por
  // serviceHeartbeat)
  serviceHeartbeat();

  // === RESCATE: Si no hay sensores detectados, re-escanear periódicamente
  // ===
  if (elmConectado && parametrosDisponibles == 0) {
    if (ahora - ultimoScan > 5000) { // Reintentar cada 5s si estamos "ciegos"
      Serial.println("[SCAN] 0 sensores detectados. Reintentando escaneo...");
      if (!elmOcupado()) {
        escanearPIDs();
        ultimoScan = ahora;
      }
    }
  }

  if (elmConectado) {
    if (obdEnabled) {
      // Lectura secuencial de PIDs (uno por iteración) siguiendo el patrón de
      // ELMduino
      leerPIDs();

      // ========== DETECCIÓN DE ENCENDIDO DE MOTOR ==========
      // Si RPM pasa de 0 a >0, el motor acaba de encender → re-escanear
      // Si RPM pasa de 0 a >0, el motor acaba de encender → re-escanear
      float rpmActual = parametros[0].valor; // RPM es el primer PID

      // Condición: Si RPM sube de 0 a >300 (encendido real) O si voltaje sube
      // bruscamente (alternador)
      if (ultimoRPM == 0 && rpmActual > 300) {
        motorRecienEncendido = true;
        Serial.println(
            "[SCAN] 🚗 ¡Motor encendido detectado! Re-escaneando PIDs...");
        if (!elmOcupado()) {
          escanearPIDs();
          ultimoScan = ahora;
        }
      } else if (rpmActual == 0 && ultimoRPM > 100) {
        // Motor apagado, resetear flag
        motorRecienEncendido = false;
        Serial.println("[SCAN] Motor apagado detectado");
      }
      ultimoRPM = rpmActual;

      // Enviar datos según intervalo - SIEMPRE, independientemente del estado
      // de lectura Esto garantiza que nunca se pierdan más de 100ms de DATA
      if (ahora - ultimoEnvio >= SEND_INTERVAL_MS) {
        ultimoEnvio = ahora;
        enviarDatos();
      }

      // Advertencia si el ELM está ocupado por mucho tiempo (solo 1 vez por
      // bloqueo)
      static uint32_t tiempoElmOcupado = 0;
      static bool warnEmitido = false;
      if (elmOcupado()) {
        if (tiempoElmOcupado == 0)
          tiempoElmOcupado = ahora;
        uint32_t duracion = ahora - tiempoElmOcupado;
        if (duracion > 500 && !warnEmitido) {
          Serial.printf("[WARN] ELM ocupado por >500ms (posible timeout)\n");
          warnEmitido = true;
        }
      } else {
        tiempoElmOcupado = 0;
        warnEmitido = false;
      }

      // Leer DTCs (sin interrumpir PIDs en curso)
      if (ahora - ultimoDTC >= DTC_INTERVAL_MS) {
        ultimoDTC = ahora;
        if (!elmOcupado()) {
          leerDTCs();
        }
      }

      // ========== ESCANEO OPORTUNISTA NO BLOQUEANTE ==========
      // Cada OPPORTUNISTIC_INTERVAL_MS, probar UN PID no-disponible
      // PERO solo si el ELM no está ocupado (no queremos bloquear lectura
      // normal)
      if (ahora - ultimoOportunista >= OPPORTUNISTIC_INTERVAL_MS) {
        ultimoOportunista = ahora;

        // Solo intentar si ELM está libre
        if (!elmOcupado()) {
          // Buscar el siguiente PID no-disponible que no sea base
          for (int i = 0; i < NUM_PARAMETROS; i++) {
            int idx = (idxOportunista + i) % NUM_PARAMETROS;
            ParametroOBD &p = parametros[idx];

            if (!p.disponible && !esPIDBase(p)) {
              Serial.printf("[SCAN] Probando PID oportunista: %s...\n",
                            p.nombre);

              // Usar queryPIDBlocking con timeout MUY corto para no bloquear
              bool ok = queryPIDBlocking(p, 300); // Max 300ms

              if (ok && isfinite(p.valor) && valorEnRango(p, p.valor)) {
                p.disponible = true;
                parametrosDisponibles++;
                Serial.printf("[SCAN] ✓ PID %s ahora disponible! Valor: %.1f\n",
                              p.nombre, p.valor);
              } else {
                Serial.printf("[SCAN] ✗ PID %s no disponible\n", p.nombre);
              }

              idxOportunista = (idx + 1) % NUM_PARAMETROS;
              break; // Solo probar uno por ciclo
            }
          }
        }
      }

      // ========== RE-ESCANEO ADAPTATIVO ==========
      // Período agresivo (primeros 2 min): escanear cada 2 min
      // Después: escanear cada 5 min
      // PERO: Si ya tenemos >= 4 PIDs, saltar el re-escaneo agresivo (no
      // interrumpir)
      unsigned long tiempoDesdeArranque = ahora - startupTime;
      bool enPeriodoAgresivo = (tiempoDesdeArranque < AGGRESSIVE_PERIOD_MS);

      // Si ya tenemos suficientes PIDs, no necesitamos escaneo agresivo
      if (enPeriodoAgresivo && parametrosDisponibles >= 4) {
        enPeriodoAgresivo = false; // Desactivar agresividad si ya tenemos PIDs
      }

      unsigned long intervaloActual =
          enPeriodoAgresivo ? SCAN_AGGRESSIVE_MS : SCAN_INTERVAL_MS;

      if (ahora - ultimoScan >= intervaloActual) {
        if (!elmOcupado()) {
          ultimoScan = ahora;
          if (enPeriodoAgresivo) {
            Serial.printf("[SCAN] Re-escaneo agresivo (%ds restantes)...\n",
                          (AGGRESSIVE_PERIOD_MS - tiempoDesdeArranque) / 1000);
          } else {
            Serial.println("[SCAN] Re-escaneo normal...");
          }
          escanearPIDs();
        }
      }
    }
    // Si obdEnabled == false: se mantiene el sistema vivo (UART +
    // reconexiones), pero se pausa lectura/envío OBD para ahorrar recursos.
  } else {
    // Si no hay conexión, intentar reconectar cada 5 segundos
    static unsigned long ultimoIntento = 0;
    if (ahora - ultimoIntento > 5000) {
      ultimoIntento = ahora;
      Serial.println("[LOOP] Sin conexión ELM, verificando...");
      verificarConexiones();
    }
  }

  delay(10);
}
