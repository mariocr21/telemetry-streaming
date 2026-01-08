# AUDITORÍA DE ESTADO ACTUAL (DIAGNÓSTICO)
**Proyecto:** Neurona Off Road Telemetry - Configurator  
**Fecha:** 23 de Diciembre, 2025  
**Auditor:** Roo (Senior Software Architect)

## 1. Análisis de Arquitectura Actual
El proyecto se encuentra en una etapa de "Crecimiento Orgánico Descontrolado". Aunque utiliza módulos externos para tareas específicas (`dbc_parser.py`, `serial_manager.py`), el núcleo de la aplicación (`main.py`) ha colapsado bajo su propio peso.

- **Patrón de Diseño:** Inexistente. Es un enfoque procedimental dentro de una clase `MainWindow`. La lógica de negocio (cómo se procesan los datos CAN), la lógica de comunicación (serial) y la lógica de presentación (estilos QSS, creación de widgets) están entrelazadas.
- **Acoplamiento:** Extremadamente alto. `MainWindow` conoce detalles íntimos de cómo se formatean los JSON para el firmware y cómo se parsean las tramas seriales.
- **Gestión de Estado:** El estado de la aplicación reside en los widgets de la UI (ej. leer `checkState()` de una tabla para saber qué sensores enviar). No existe un modelo de datos único (Single Source of Truth).

## 2. "Puntos de Dolor" (Pain Points) en la UX
1. **Sobrecarga Cognitiva:** La interfaz presenta todas las opciones de configuración (CAN, OBD, GPS, IMU, Nube) simultáneamente, independientemente del modo seleccionado.
2. **Fricción en la Selección de Modo:** El usuario elige un "Modo de Operación" en la pestaña Dispositivo, pero esto no adapta el resto de la interfaz. Por ejemplo, en modo `sensors_only`, las pestañas CAN y OBD siguen activas y visibles, confundiendo al usuario sobre qué parámetros son relevantes.
3. **Jerarquía Visual Débil:** Botones críticos como "Enviar Config" están mezclados con botones de "Simulación" o "Ver Payload" en una barra inferior saturada.
4. **Feedback Inconsistente:** El log de la consola mezcla mensajes de sistema con datos de telemetría, dificultando la depuración para el usuario final.

## 3. Escalabilidad de `main.py`
- **Calificación:** **3/10** (Inestable para producción a largo plazo).
- **Justificación:** Con más de 2300 líneas, `main.py` ha superado el límite de mantenibilidad. Añadir un nuevo sensor o un nuevo protocolo de comunicación requiere modificar métodos de cientos de líneas que ya manejan múltiples responsabilidades. El riesgo de efectos colaterales es del 100%.

## 4. Limpieza del Código
- **Calificación:** **4/10**
- **Justificación:**
  - **Positivo:** Uso de clases constantes para índices de columnas (`Col`), manejo de temas con QSS.
  - **Negativo:** Métodos de configuración de UI de más de 100 líneas (`setup_settings_tab`, `setup_obd_tab`). Mezcla de idiomas en variables y comentarios. Lógica de cálculo de combustible y conversión de bits incrustada directamente en métodos de la UI.

## 5. Riesgos Identificados
- **Bugs Silenciosos:** Al enviar la configuración, se recolectan datos de todas las pestañas. Si un usuario configuró OBD previamente pero ahora está en modo `can_only`, podría estar enviando parámetros OBD basura o inconsistentes que el firmware del ESP32 podría interpretar erróneamente.
- **Race Conditions:** La lectura serial y la actualización de la UI ocurren en el hilo principal o dependen de un `QTimer` que procesa JSONs pesados, lo que puede causar "congelamientos" (UI Lag) durante ráfagas de telemetría.
- **Imposibilidad de Tests:** No se pueden realizar pruebas unitarias sobre la lógica de generación de configuración sin instanciar toda la interfaz gráfica.
