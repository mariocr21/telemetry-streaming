/**
 * @file offline_buffer.cpp
 * @brief Implementación del OfflineBuffer
 *
 * PART OF: Plan Safety-Critical P0.1
 *
 * @author Neurona Racing Development
 * @date 2024-12-20
 */

#include "offline_buffer.h"

// ============================================================================
// CONSTRUCTOR
// ============================================================================

OfflineBuffer::OfflineBuffer()
    : _head(0), _tail(0), _count(0), _totalPushed(0), _totalOverwritten(0),
      _totalPopped(0), _mutex(nullptr) {
  // Inicializar buffer a ceros
  memset(_buffer, 0, sizeof(_buffer));
}

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

void OfflineBuffer::begin() {
  Serial.println(F("[OFFLINE_BUFFER] Initializing..."));

  _mutex = xSemaphoreCreateMutex();

  if (_mutex == nullptr) {
    Serial.println(F("[OFFLINE_BUFFER] ERROR: Failed to create mutex!"));
    return;
  }

  // Limpiar buffer
  clear();

  Serial.printf("[OFFLINE_BUFFER] Ready (capacity: %d frames, ~%d KB RAM)\n",
                OFFLINE_BUFFER_SIZE,
                (OFFLINE_BUFFER_SIZE * sizeof(TelemetryFrame)) / 1024);
}

// ============================================================================
// MUTEX HELPERS
// ============================================================================

bool OfflineBuffer::takeMutex(TickType_t timeout) {
  if (_mutex == nullptr)
    return false;
  return xSemaphoreTake(_mutex, timeout) == pdTRUE;
}

void OfflineBuffer::giveMutex() {
  if (_mutex != nullptr) {
    xSemaphoreGive(_mutex);
  }
}

// ============================================================================
// OPERACIONES DEL BUFFER
// ============================================================================

bool OfflineBuffer::push(const String &payload) {
  if (payload.length() == 0 || payload.length() >= MAX_PAYLOAD_SIZE) {
    Serial.printf("[OFFLINE_BUFFER] Push failed: invalid payload size (%d)\n",
                  payload.length());
    return false;
  }

  if (!takeMutex()) {
    Serial.println(F("[OFFLINE_BUFFER] Push failed: mutex timeout"));
    return false;
  }

  // Si el buffer está lleno, sobrescribimos el más antiguo
  bool overwrite = isFull();
  if (overwrite) {
    // Avanzar tail (descartamos el más antiguo)
    _tail = (_tail + 1) % OFFLINE_BUFFER_SIZE;
    _count--;
    _totalOverwritten++;
  }

  // Escribir en head
  TelemetryFrame &frame = _buffer[_head];
  strncpy(frame.payload, payload.c_str(), MAX_PAYLOAD_SIZE - 1);
  frame.payload[MAX_PAYLOAD_SIZE - 1] = '\0';
  frame.payload_len = payload.length();
  frame.timestamp_ms = millis();
  frame.valid = true;

  // Avanzar head
  _head = (_head + 1) % OFFLINE_BUFFER_SIZE;
  _count++;
  _totalPushed++;

  giveMutex();

  if (overwrite) {
    // Log solo cada 10 overwrites para no saturar serial
    if (_totalOverwritten % 10 == 1) {
      Serial.printf("[OFFLINE_BUFFER] Warning: buffer full, overwriting old "
                    "frames (total: %lu)\n",
                    _totalOverwritten);
    }
  }

  return true;
}

bool OfflineBuffer::pop(String &payload) {
  if (!takeMutex()) {
    return false;
  }

  if (_count == 0) {
    giveMutex();
    return false;
  }

  // Leer de tail
  TelemetryFrame &frame = _buffer[_tail];

  if (frame.valid) {
    payload = String(frame.payload);
    frame.valid = false;
    frame.payload[0] = '\0';
  } else {
    // Frame inválido, skipear
    giveMutex();
    _tail = (_tail + 1) % OFFLINE_BUFFER_SIZE;
    _count--;
    return false;
  }

  // Avanzar tail
  _tail = (_tail + 1) % OFFLINE_BUFFER_SIZE;
  _count--;
  _totalPopped++;

  giveMutex();

  return true;
}

bool OfflineBuffer::peek(String &payload) {
  if (!takeMutex()) {
    return false;
  }

  if (_count == 0) {
    giveMutex();
    return false;
  }

  TelemetryFrame &frame = _buffer[_tail];

  if (frame.valid) {
    payload = String(frame.payload);
  } else {
    giveMutex();
    return false;
  }

  giveMutex();
  return true;
}

void OfflineBuffer::clear() {
  if (!takeMutex()) {
    return;
  }

  _head = 0;
  _tail = 0;
  _count = 0;

  // Marcar todos los frames como inválidos
  for (size_t i = 0; i < OFFLINE_BUFFER_SIZE; i++) {
    _buffer[i].valid = false;
    _buffer[i].payload[0] = '\0';
  }

  giveMutex();

  Serial.println(F("[OFFLINE_BUFFER] Buffer cleared"));
}

// ============================================================================
// STATUS
// ============================================================================

void OfflineBuffer::printStatus() {
  Serial.println(F("\n========== OFFLINE BUFFER STATUS =========="));
  Serial.printf("Count: %d / %d (%d%%)\n", _count, OFFLINE_BUFFER_SIZE,
                fillPercent());
  Serial.printf("Total pushed: %lu\n", _totalPushed);
  Serial.printf("Total popped: %lu\n", _totalPopped);
  Serial.printf("Total overwritten: %lu\n", _totalOverwritten);
  Serial.printf("Memory used: %d bytes\n", _count * sizeof(TelemetryFrame));
  Serial.println(F("============================================\n"));
}
