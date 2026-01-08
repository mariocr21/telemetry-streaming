# PROPUESTA DE RE-ARQUITECTURA (SOLUCIÓN)
**Proyecto:** Neurona Off Road Telemetry
**Arquitecto:** Roo

## 1. Nueva Estructura Modular
Se propone una separación estricta de responsabilidades siguiendo un patrón **Component-Based Architecture** con un **Controller** central.

```text
configurator/
├── assets/                 # Estilos (QSS), Imágenes, Logos
├── core/                   # Lógica de Negocio (Pura)
│   ├── models.py           # Clases Sensor, Config, Device
│   ├── engine.py           # Generador de JSON, Lógica de Conversión
│   └── serial_service.py   # Wrapper de comunicación
├── ui/                     # Componentes de Presentación
│   ├── main_window.py      # Frame principal (Orquestador de UI)
│   ├── tabs/
│   │   ├── can_tab.py      # Vista de Sensores CAN
│   │   ├── obd_tab.py      # Vista de Configuración OBD
│   │   └── device_tab.py   # Vista de Identidad y Modo
│   └── widgets/
│       ├── live_cards.py   # Dashboard components
│       └── console.py      # Log interactivo
└── main.py                 # Punto de entrada (Boilerplate mínimo)
```

## 2. Estrategia de "UI Reactiva" (State Management)
Para solucionar la confusión entre OBD/CAN, implementaremos un sistema de visibilidad basada en estados:

- **Config State:** Un objeto único que mantiene el modo actual.
- **Observers:** Los tabs se suscriben a cambios en el modo de operación.
- **Behavior:**
  - Si `mode == "can_only"`: Se oculta/deshabilita el tab OBD.
  - Si `mode == "obd_direct"`: Se oculta el tab de Sensores CAN.
  - El sistema de validación solo recolecta datos de los componentes visibles/activos.

## 3. Patrón de Diseño: Controller-View
Eliminaremos la lógica de `main.py` delegándola a un `AppController`.

- **Model:** Clases dataclass para representar la configuración.
- **View:** Solo maneja eventos de usuario y dibujado.
- **Controller:** Escucha señales de la View, procesa datos mediante el Core, y actualiza la View.

## 4. Nuevo `main.py` (Esqueleto)
```python
import sys
from PySide6.QtWidgets import QApplication
from ui.main_window import MainWindow
from core.app_controller import AppController

def main():
    app = QApplication(sys.argv)
    app.setApplicationName("Neurona Telemetry Configurator")
    
    # Iniciar controlador (Lógica de negocio)
    controller = AppController()
    
    # Iniciar interfaz (Pasando el controlador)
    window = MainWindow(controller)
    window.show()
    
    sys.exit(app.exec())

if __name__ == "__main__":
    main()
```

## 5. Ejemplo de UI Reactiva (Pseudo-código en Controller)
```python
def on_source_mode_changed(self, new_mode):
    # 1. Actualizar modelo interno
    self.config.device.source = new_mode
    
    # 2. Notificar a la UI para adaptar visibilidad
    if new_mode == "CAN_ONLY":
        self.view.tabs.obd.set_visible(False)
        self.view.tabs.can.set_visible(True)
    elif new_mode == "OBD_DIRECT":
        self.view.tabs.can.set_visible(False)
        self.view.tabs.obd.set_visible(True)
```
