# 📋 BITÁCORA DE DESARROLLO
## Neurona Off Road Telemetry - Frontend VMC

> Este documento registra todos los cambios, mejoras y decisiones de desarrollo realizadas en el proyecto.
> **Mantenerlo actualizado es responsabilidad de todo el equipo.**

---

## 📌 Índice
- [2024-12-27 - Dashboard de Telemetría Pro con D3.js](#2024-12-27---dashboard-de-telemetría-pro-con-d3js)
- [2026-01-02 - Sistema de Mapeo de Sensores Custom (ID Normalization)](#2026-01-02---sistema-de-mapeo-de-sensores-custom-id-normalization)

---

## 2024-12-27 - Dashboard de Telemetría Pro con D3.js

**Autor:** Asistente AI (Antigravity)  
**Fecha:** 27 de Diciembre, 2024 - 19:30 PST  
**Tipo:** Nueva funcionalidad  
**Rama:** (especificar rama de git)

### 🎯 Objetivo
Diseñar la interfaz de usuario (UI) para el "Live Telemetry Dashboard" optimizado para tablets rugerizadas en vehículos de carreras off-road (Baja 1000, Dakar). Requisitos clave: legibilidad en movimiento y Dark Mode obligatorio.

### 🛠️ Stack Tecnológico Utilizado
| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| Vue 3 | 3.5.13 | Framework UI (Composition API, Script Setup) |
| D3.js | 7.9.0 | Renderizado SVG de gauges (sin librerías pre-hechas) |
| Tailwind CSS | 4.1.1 | Sistema de estilos |
| Leaflet | 1.9.4 | Mapas interactivos |
| VueUse | 12.8.2 | Resize observers y utilidades |
| Lodash | 4.17.21 | Throttling y utilidades |
| Lucide Vue Next | 0.468.0 | Iconografía |
| tw-animate-css | 1.2.5 | Animaciones CSS |

### 📁 Archivos Creados

#### Componentes Vue

| Archivo | Ubicación | Descripción |
|---------|-----------|-------------|
| `RadialGaugeD3.vue` | `resources/js/components/Dashboard/` | Gauge radial SVG con D3.js. Props: `min`, `max`, `value`, `thresholds`, `arcWidth`, `animated`. Efectos glow y animaciones suaves. |
| `LinearBarD3.vue` | `resources/js/components/Dashboard/` | Barra horizontal para temperaturas. Variantes: `default`, `compact`, `thermometer`. Colores dinámicos según umbrales. |
| `TelemetryGridLayout.vue` | `resources/js/components/Dashboard/` | Grid CSS responsivo de 12 columnas con breakpoints para tablets. |
| `TelemetryWidget.vue` | `resources/js/components/Dashboard/` | Contenedor base para widgets con estados (normal/warning/critical/offline) y efectos glassmorphism. |
| `TelemetryDashboardPro.vue` | `resources/js/pages/` | Layout principal integrando todos los componentes. Modo demo con datos simulados. |

#### Estilos CSS

| Archivo | Cambios Realizados |
|---------|-------------------|
| `resources/css/dashboard-pro.css` | Actualizado con sistema de tokens CSS, paleta de colores racing, animaciones críticas (`critical-pulse`, `warning-glow`, `live-pulse`), soporte D3, optimizaciones para tablet, tema oscuro Leaflet. |

#### Configuración

| Archivo | Cambios |
|---------|---------|
| `routes/web.php` | Nueva ruta: `GET /telemetry-live` → `telemetry.live` |
| `resources/views/app.blade.php` | Agregada fuente JetBrains Mono de Google Fonts |
| `package.json` | Agregado `@types/d3` como devDependency |

### 🎨 Sistema de Diseño

#### Paleta de Colores Racing
```css
--racing-red: #ff003c      /* Critical */
--racing-orange: #ff8a00   /* Warning */
--racing-cyan: #00f0ff     /* Velocidad/Accent */
--racing-green: #00ff9d    /* Normal/OK */
--racing-yellow: #ffee00   /* Batería */
--racing-purple: #cc00ff   /* Combustible */
```

#### Fondos (Dark Industrial)
```css
--bg-primary: #050505
--bg-secondary: #0a0c10
--bg-card: rgba(10, 12, 15, 0.85)
```

#### Tipografía
- **Sans:** Inter (UI general)
- **Mono:** JetBrains Mono (valores numéricos tabulares)

### 📐 Widgets Implementados

1. **Gauge RPM** - Radial D3.js con umbrales de redline
2. **Gauge Velocidad** - Radial D3.js en MPH
3. **Display de Marcha** - Número grande con glow
4. **Barra de Throttle** - Linear bar con indicador de freno
5. **Mapa en Vivo** - Leaflet con overlay de GPS
6. **Panel de Temperaturas** - 4 sensores (Coolant, Oil, Trans, Intake)
7. **Indicador de Combustible** - Con icono y barra
8. **Indicador de Batería** - Voltaje y estado de carga
9. **Grid de Neumáticos** - Presión PSI y temperatura por rueda

### 🔧 Características Técnicas

#### RadialGaugeD3.vue
- Renderizado SVG puro con D3.js (sin Canvas)
- Transiciones animadas con `d3.easeCubicOut`
- Filtro SVG para efecto glow
- Ticks de escala configurables
- Resize observer para responsividad
- Throttling de actualizaciones (50ms)

#### Estados de Alerta
- **Normal:** Glow verde sutil
- **Warning:** Glow naranja con animación `warning-glow`
- **Critical:** Parpadeo rojo con `critical-pulse`, borde animado

#### Optimizaciones Tablet
- Touch targets mínimos de 48px
- Texto aumentado para legibilidad en movimiento
- Contraste alto para luz solar directa (media query)

### 🚀 Cómo Acceder

```bash
# El servidor ya está corriendo
# Acceder a:
http://localhost:8000/telemetry-live
```

El dashboard inicia automáticamente en **modo demo** con valores simulados que varían en tiempo real.

### ⚠️ Notas Importantes

1. **Mapa:** El widget de mapa (`MapWidget.vue`) ya estaba integrado, solo se reutilizó.
2. **WebSocket:** Los datos reales de telemetría se conectan via Laravel Echo/Reverb. El dashboard está preparado para recibir eventos `VehicleTelemetryEvent`.
3. **Compilación:** `npm run build` exitoso ✅
4. **Error preexistente:** Existe un error en `tsconfig.json` referente a `vue/tsx` que no afecta la compilación de Vite.

### 📝 Pendientes / Siguientes Pasos

- [ ] Conectar con datos reales de WebSocket
- [ ] Agregar widget de DTCs (códigos de error OBD)
- [ ] Implementar modo de grabación/replay
- [ ] Agregar configuración de umbrales por usuario
- [ ] Optimizar para modo landscape forzado en tablets
- [ ] Agregar indicador de calidad de señal GPS

### 🔗 Referencias

- [D3.js Arc Generator](https://d3js.org/d3-shape/arc)
- [VueUse - useResizeObserver](https://vueuse.org/core/useResizeObserver/)
- [Tailwind CSS v4 Documentation](https://tailwindcss.com/docs)

---

## 📖 Guía para Contribuir a esta Bitácora

### Formato de Entrada

```markdown
## YYYY-MM-DD - Título del Cambio

**Autor:** Nombre  
**Fecha:** DD de Mes, YYYY - HH:MM Timezone  
**Tipo:** Nueva funcionalidad | Bugfix | Refactor | Documentación  
**Rama:** nombre-de-rama

### 🎯 Objetivo
Descripción breve del objetivo.

### 📁 Archivos Modificados
Lista de archivos con descripción de cambios.

### 🔧 Detalles Técnicos
Explicación técnica relevante.

### ⚠️ Notas Importantes
Advertencias o consideraciones.

### 📝 Pendientes
Lista de tareas derivadas.
```

### Convenciones
- Usar emojis para categorías (🎯 🛠️ 📁 🔧 ⚠️ 📝)
- Incluir tablas para listas de archivos/dependencias
- Especificar versiones de dependencias
- Documentar decisiones de diseño importantes
- Listar pendientes derivados del trabajo

---

*Última actualización: 02 de Enero, 2026*

---

## 2026-01-02 - Sistema de Mapeo de Sensores Custom (ID Normalization)

**Autor:** Asistente AI (Antigravity)
**Fecha:** 02 de Enero, 2026
**Tipo:** Nueva funcionalidad / Backend & Frontend

### 🎯 Objetivo
Permitir la vinculación dinámica entre los identificadores de sensores enviados por el firmware (ej: `motec_rpm`, `can_1f4`, `engine_temp_custom`) y los sensores definidos en la base de datos del vehículo. Esto desacopla la definición del sensor en el hardware de su representación en el Dashboard.

### 🛠️ Cambios Implementados

#### 1. Base de Datos
*   **Nueva Migración:** `2026_01_02_210000_add_mapping_fields_to_vehicle_sensors_table`
    *   `mapping_key` (string, nullable, index): Almacena el ID exacto que envía el firmware (Cloud ID).
    *   `source_type` (string, default 'OBD2'): Define el origen ('OBD2', 'CAN_CUSTOM', 'GPS', 'VIRTUAL').
    *   Índice compuesto `(vehicle_id, mapping_key)` para búsqueda rápida en tiempo real.

#### 2. Backend (Laravel)
*   **Modelo `VehicleSensor`:** Actualizado `$fillable` y `$casts`.
*   **Controlador `VehicleController`:** 
    *   Actualizado método `updateSensorConfig` para validar y guardar `mapping_key` y `source_type`.
    *   Validación de unicidad implícita por vehículo (aunque no forzada en DB para flexibilidad).

#### 3. Frontend (Vue.js)
*   **Nuevo Componente:** `SensorConfigModal.vue`
    *   Modal reutilizable para configuración avanzada de sensores.
    *   Campos: Activo, Frecuencia, **Cloud ID (Mapping Key)**, Origen de Datos, Min/Max alertas.
*   **Vista `Show.vue` (Detalle Vehículo):**
    *   Integración del modal.
    *   Activación del botón "Configurar" (icono de llave inglesa) en la lista de sensores.

### 🔧 Flujo de Trabajo
1.  **Firmware:** Envía JSON `{ "v": 123, "id": "my_custom_sensor" }`.
2.  **Ingesta (Backend):** Busca en `vehicle_sensors` donde `vehicle_id = X` AND `mapping_key = 'my_custom_sensor'`.
3.  **Resultado:** Encuentra el `sensor_id` interno y guarda el registro asociado a ese sensor lógico.

### ⚠️ Notas Importantes
*   **Entorno de Desarrollo:** Se requirió una reparación manual del entorno Laravel (`bootstrap/cache` corrupto) para aplicar las migraciones.
*   **Uso:** El usuario debe configurar manualmente el `mapping_key` en el Dashboard si su firmware usa IDs no estándar. Los PIDs OBD2 estándar siguen funcionando automáticamente si el firmware envía el PID hex.

### 📝 Pendientes
- [ ] Implementar la lógica de ingesta en `VehicleTelemetryEvent` o controlador MQTT para usar activamente este campo `mapping_key` (Actualmente preparado en BD, falta lógica de consumo si no existe ya).
- [ ] Agregar validación visual en el Dashboard si un sensor configurado no está recibiendo datos (timeout).
