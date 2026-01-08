/**
 * @file status_led.h
 * @brief Controlador visual de estado (LEDs)
 *
 * Maneja los patrones de parpadeo sin bloquear (non-blocking)
 * para diagnóstico visual rápido en carrera.
 *
 * @author Neurona Racing Development
 * @date 2024-12-20
 */

#ifndef STATUS_LED_H
#define STATUS_LED_H

#include <Arduino.h>

class StatusLed {
public:
  // Patrones de parpadeo
  enum Pattern {
    OFF,
    ON,
    SLOW_BLINK, // 1Hz (Buscando red)
    FAST_BLINK, // 5Hz (Actividad intensa)
    HEARTBEAT,  // Doble pulso (Sistema OK)
    FLASH       // Pulso único invertido (Tráfico)
  };

  StatusLed(uint8_t pin, bool invertLogic = false)
      : _pin(pin), _inverted(invertLogic), _pattern(OFF), _state(false),
        _lastToggle(0) {}

  void begin() {
    pinMode(_pin, OUTPUT);
    updateOutput();
  }

  void setPattern(Pattern p) { _pattern = p; }

  // Llama a esto periódicamente en el loop
  void update() {
    uint32_t now = millis();

    switch (_pattern) {
    case OFF:
      _state = false;
      break;
    case ON:
      _state = true;
      break;
    case SLOW_BLINK:
      if (now - _lastToggle >= 500) {
        _state = !_state;
        _lastToggle = now;
      }
      break;
    case FAST_BLINK:
      if (now - _lastToggle >= 100) {
        _state = !_state;
        _lastToggle = now;
      }
      break;
    case HEARTBEAT:
      // Patrón latido: pum-pum... pum-pum
      {
        uint32_t cycle = now % 1500;
        if (cycle < 100)
          _state = true;
        else if (cycle < 200)
          _state = false;
        else if (cycle < 300)
          _state = true;
        else
          _state = false;
      }
      break;
    }

    // Flash Override (Higher Priority)
    if (_flashActive) {
      if (now - _flashStart > 50) {
        _flashActive = false;
        // _state will naturally update to current pattern in next cycle
        // For this cycle, we let the switch result stand (restoring state)
      } else {
        _state = !_prevFlashState; // Invertir estado momentáneamente
      }
    }

    updateOutput();
  }

  // Dispara un flash momentáneo (ej: para indicar paquete enviado)
  void flash() {
    if (!_flashActive) {
      _flashActive = true;
      _flashStart = millis();
      _prevFlashState = _state;
    }
  }

private:
  uint8_t _pin;
  bool _inverted;
  Pattern _pattern;
  bool _state;
  uint32_t _lastToggle;

  // Flash logic
  bool _flashActive = false;
  uint32_t _flashStart = 0;
  bool _prevFlashState = false;

  void updateOutput() { digitalWrite(_pin, _inverted ? !_state : _state); }
};

#endif // STATUS_LED_H
