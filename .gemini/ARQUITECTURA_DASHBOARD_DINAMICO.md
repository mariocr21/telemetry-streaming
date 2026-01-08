# 🏗️ Arquitectura Fullstack: Dashboard Dinámico Configurable

> **Autor:** Arquitecto de Software IA (Antigravity)  
> **Fecha:** 28 de Diciembre, 2025  
> **Versión:** 1.0  
> **Proyecto:** Neurona Off Road Telemetry - Dashboard Dinámico

---

## 📋 Índice

1. [Análisis del Stack Actual](#1-análisis-del-stack-actual)
2. [Diseño de Base de Datos](#2-diseño-de-base-de-datos)
3. [API JSON Response Specification](#3-api-json-response-specification)
4. [Arquitectura Frontend - Component Factory](#4-arquitectura-frontend---component-factory)
5. [Integración con Datos en Tiempo Real](#5-integración-con-datos-en-tiempo-real)
6. [Diagramas de Flujo](#6-diagramas-de-flujo)
7. [Plan de Implementación](#7-plan-de-implementación)
8. [Sistema de Selección de Widgets por Sensor](#8-sistema-de-selección-de-widgets-por-sensor)
9. [**Diagramas Visuales de Arquitectura**](#9-diagramas-visuales-de-arquitectura) ⭐ NEW

---

## 1. Análisis del Stack Actual

### 🔧 Stack Tecnológico Confirmado

| Capa | Tecnología | Versión | Notas |
|------|------------|---------|-------|
| **Backend** | Laravel | 11.x | PHP 8.2+, Eloquent ORM |
| **Frontend** | Vue 3 | 3.5.13 | Composition API, Script Setup |
| **Bridge** | Inertia.js | Latest | SSR-ready |
| **Estilos** | Tailwind CSS | 4.1.1 | CSS-first tokens |
| **Visualización** | D3.js | 7.9.0 | SVG Gauges customizados |
| **Mapas** | Leaflet | 1.9.4 | Mapas en vivo |
| **Iconos** | Lucide Vue Next | 0.468.0 | |
| **Utilidades** | VueUse | 12.8.2 | Resize observers, etc. |
| **Real-time** | Laravel Reverb | WebSocket | Via Laravel Echo |
| **Database** | SQLite/MySQL | - | Según ambiente |

### 📡 Sistema de Transmisión Actual (WebSocket vía Reverb)

Según el archivo `VehicleTelemetryEvent.php`, el sistema usa:

```php
// Canales de broadcasting
new Channel('telemetry'),                    // Canal público general
new PrivateChannel('vehicle.' . $vehicleId), // Canal privado por vehículo
new PrivateChannel('device.' . $deviceId),   // Canal privado por dispositivo
new Channel('dtc'),                          // Canal de códigos de error
```

**Evento emitido:** `telemetry.updated`

**Payload actual:**
```json
{
  "vehicle_id": 5,
  "device_id": "ESP32_001",
  "timestamp": "2024-12-28T03:45:00.000Z",
  "data": {
    "RPM": 5500,
    "Vehicle_Speed": 85,
    "Coolant_Temp": 92,
    "CAN_ID_0x1F_Throttle": 78.5
  },
  "dtc_codes": ["P0300", "P0171"],
  "has_dtc": true
}
```

### 📊 Modelo de Datos Actual

```
┌─────────────┐     ┌─────────────────┐     ┌───────────┐
│   clients   │────▶│    vehicles     │────▶│  sensors  │
└─────────────┘     └─────────────────┘     └───────────┘
                           │                      │
                           ▼                      │
                    ┌───────────────────┐         │
                    │  vehicle_sensors  │◀────────┘
                    │  (pivot table)    │
                    └───────────────────┘
```

**Tabla `sensors` (existente):**
- `pid` (ej: "0x0C", "0x0D", "CAN_0x1F")
- `name` (ej: "RPM", "Vehicle Speed")
- `category` (ej: "engine", "fuel", "tires")
- `unit` (ej: "°C", "RPM", "PSI")

---

## 2. Diseño de Base de Datos

### 2.1 Nuevas Tablas Propuestas

Para lograr un dashboard 100% dinámico y configurable, necesitamos las siguientes tablas:

```
┌────────────────────────────────────────────────────────────────┐
│                    NUEVAS TABLAS                                │
├────────────────────────────────────────────────────────────────┤
│  dashboard_layouts          (Layouts por vehículo)              │
│  widget_groups              (Grupos/Cards del layout)           │
│  widget_definitions         (Catálogo de tipos de widgets)      │
│  widget_instances           (Widgets configurados por grupo)    │
│  sensor_widget_bindings     (Vincula sensor → widget)           │
└────────────────────────────────────────────────────────────────┘
```

### 2.2 Migraciones Propuestas

#### **Tabla: `dashboard_layouts`**
Define qué layout tiene cada vehículo.

```php
Schema::create('dashboard_layouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
    
    $table->string('name')->default('Default Layout');
    $table->string('theme')->default('cyberpunk-dark'); // Para futuras variantes
    
    // Configuración global del layout
    $table->json('grid_config')->nullable(); 
    // Ej: {"columns": 12, "gap": 4, "breakpoints": {...}}
    
    $table->boolean('is_active')->default(true);
    $table->boolean('is_default')->default(false);
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->unique(['vehicle_id', 'is_active']); // Solo un layout activo por vehículo
});
```

#### **Tabla: `widget_groups`**
Los "Groups" (Cards) como "Engine Performance", "Tires", "Electrical".

```php
Schema::create('widget_groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('dashboard_layout_id')->constrained()->onDelete('cascade');
    
    $table->string('name');         // "Engine Performance"
    $table->string('slug');         // "engine-performance"
    $table->string('icon')->nullable(); // Lucide icon name: "gauge", "thermometer"
    
    // Posición en el grid (CSS Grid)
    $table->integer('grid_column_start')->default(1);  // col-start
    $table->integer('grid_column_span')->default(6);   // col-span
    $table->integer('grid_row_start')->nullable();     // row-start (auto si null)
    $table->integer('grid_row_span')->default(1);      // row-span
    
    // Orden de renderizado
    $table->integer('sort_order')->default(0);
    
    // Configuración visual
    $table->json('style_config')->nullable();
    // Ej: {"bgColor": "dash-card", "borderColor": "slate-700", "headerVariant": "compact"}
    
    $table->boolean('is_visible')->default(true);
    $table->boolean('is_collapsible')->default(false);
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['dashboard_layout_id', 'sort_order']);
});
```

#### **Tabla: `widget_definitions`**  
Catálogo maestro de tipos de widgets disponibles.

```php
Schema::create('widget_definitions', function (Blueprint $table) {
    $table->id();
    
    $table->string('type')->unique();           // "radial_gauge", "linear_bar", "text_grid", "digital_value"
    $table->string('name');                     // "Gauge Radial D3"
    $table->string('component_name');           // "RadialGaugeD3" (nombre Vue)
    $table->text('description')->nullable();
    
    // Props disponibles (schema JSON)
    $table->json('props_schema');
    // Ej: {"min": {"type": "number", "default": 0}, "max": {"type": "number", "required": true}, ...}
    
    // Categoría para UI de configuración
    $table->string('category')->default('visualization'); // visualization, text, special
    
    // Tamaño mínimo recomendado
    $table->integer('min_width')->default(1);   // En columnas del grid
    $table->integer('min_height')->default(1);  // En rows
    
    $table->boolean('is_active')->default(true);
    
    $table->timestamps();
});
```

#### **Tabla: `widget_instances`**
Instancias configuradas de widgets dentro de un grupo.

```php
Schema::create('widget_instances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('widget_group_id')->constrained()->onDelete('cascade');
    $table->foreignId('widget_definition_id')->constrained()->onDelete('restrict');
    
    // Configuración específica de esta instancia
    $table->json('props')->nullable();
    // Ej: {"min": 0, "max": 9000, "thresholds": [...], "arcWidth": 12}
    
    // Posición dentro del grupo (flex/grid interno)
    $table->integer('sort_order')->default(0);
    $table->string('size_class')->default('md'); // sm, md, lg, xl (para responsividad)
    
    // Estilo override
    $table->json('style_override')->nullable();
    
    $table->boolean('is_visible')->default(true);
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['widget_group_id', 'sort_order']);
});
```

#### **Tabla: `sensor_widget_bindings`**
**TABLA CRÍTICA:** Vincula sensores CAN/OBD con instancias de widgets.

```php
Schema::create('sensor_widget_bindings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('widget_instance_id')->constrained()->onDelete('cascade');
    $table->foreignId('vehicle_sensor_id')->constrained()->onDelete('cascade');
    
    // Clave del dato en el JSON de telemetría
    $table->string('telemetry_key');  // "RPM", "CAN_ID_0x1F", "Coolant_Temp"
    
    // Prop del widget al que se vincula
    $table->string('target_prop')->default('value');  // Generalmente "value"
    
    // Transformación opcional
    $table->json('transform')->nullable();
    // Ej: {"multiply": 0.1, "offset": -40, "round": 2}
    
    // Override de display
    $table->string('display_label')->nullable();  // Override del nombre del sensor
    $table->string('display_unit')->nullable();   // Override de unidad
    
    // Para widgets con múltiples valores (ej: Tires con 4 sensores)
    $table->string('slot')->nullable();  // "fl", "fr", "rl", "rr"
    
    $table->timestamps();
    
    $table->unique(['widget_instance_id', 'vehicle_sensor_id']); // Sin duplicados
    $table->index('telemetry_key'); // Para lookup rápido
});
```

### 2.3 Diagrama ER Completo

```
          ┌─────────────────┐
          │    vehicles     │
          │   (existente)   │
          └────────┬────────┘
                   │ 1:N
                   ▼
          ┌─────────────────┐
          │dashboard_layouts│
          └────────┬────────┘
                   │ 1:N
                   ▼
          ┌─────────────────┐
          │  widget_groups  │
          └────────┬────────┘
                   │ 1:N
                   ▼
          ┌─────────────────┐       ┌───────────────────┐
          │widget_instances │◀──────│widget_definitions │
          └────────┬────────┘  N:1  │   (catálogo)      │
                   │                └───────────────────┘
                   │ 1:N
                   ▼
        ┌──────────────────────┐
        │sensor_widget_bindings│
        └──────────┬───────────┘
                   │ N:1
                   ▼
          ┌─────────────────┐       ┌───────────┐
          │ vehicle_sensors │◀──────│  sensors  │
          │   (existente)   │  N:1  │(existente)│
          └─────────────────┘       └───────────┘
```

---

## 3. API JSON Response Specification

### 3.1 Endpoint Principal

```
GET /api/vehicles/{vehicleId}/dashboard
```

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

### 3.2 JSON Response que Genera el HTML del dash2.html

```json
{
  "success": true,
  "data": {
    "vehicle_id": 5,
    "layout": {
      "id": 1,
      "name": "Race Dashboard Pro",
      "theme": "cyberpunk-dark",
      "grid_config": {
        "columns": 12,
        "gap": 4,
        "responsive": {
          "lg": { "columns": 12 },
          "md": { "columns": 6 },
          "sm": { "columns": 1 }
        }
      }
    },
    "groups": [
      {
        "id": 1,
        "name": "Engine Performance",
        "slug": "engine-performance",
        "icon": "gauge",
        "grid": {
          "colStart": 1,
          "colSpan": 12,
          "rowSpan": 1
        },
        "style": {
          "bgColor": "bg-dash-card",
          "borderColor": "border-slate-700"
        },
        "widgets": [
          {
            "id": 101,
            "type": "radial_gauge",
            "component": "RadialGaugeD3",
            "size": "lg",
            "props": {
              "min": 0,
              "max": 9000,
              "label": "RPM",
              "unit": "",
              "thresholds": [
                { "value": 60, "color": "#00ff9d" },
                { "value": 85, "color": "#ff8a00" },
                { "value": 100, "color": "#ff003c" }
              ],
              "arcWidth": 12,
              "startAngle": -135,
              "endAngle": 135,
              "showTicks": true,
              "animated": true
            },
            "bindings": [
              {
                "telemetry_key": "RPM",
                "target_prop": "value",
                "transform": null
              }
            ]
          },
          {
            "id": 102,
            "type": "radial_gauge",
            "component": "RadialGaugeD3",
            "size": "lg",
            "props": {
              "min": 0,
              "max": 200,
              "label": "SPEED",
              "unit": "MPH",
              "thresholds": [
                { "value": 50, "color": "#00ff9d" },
                { "value": 80, "color": "#ff8a00" },
                { "value": 100, "color": "#ff003c" }
              ],
              "arcWidth": 12,
              "animated": true
            },
            "bindings": [
              {
                "telemetry_key": "Vehicle_Speed",
                "target_prop": "value",
                "transform": null
              }
            ]
          },
          {
            "id": 103,
            "type": "radial_gauge",
            "component": "RadialGaugeD3",
            "size": "md",
            "props": {
              "min": 0,
              "max": 100,
              "label": "TPS",
              "unit": "%",
              "thresholds": [
                { "value": 70, "color": "#10b981" },
                { "value": 100, "color": "#10b981" }
              ],
              "arcWidth": 10,
              "animated": true
            },
            "bindings": [
              {
                "telemetry_key": "Throttle_Position",
                "target_prop": "value",
                "transform": null
              }
            ]
          }
        ]
      },
      {
        "id": 2,
        "name": "Gear",
        "slug": "gear-indicator",
        "icon": "settings-2",
        "grid": {
          "colStart": 1,
          "colSpan": 4,
          "rowSpan": 1
        },
        "style": {
          "bgColor": "bg-dash-success",
          "borderColor": "border-green-600",
          "variant": "highlight"
        },
        "widgets": [
          {
            "id": 201,
            "type": "digital_value",
            "component": "DigitalValueWidget",
            "size": "xl",
            "props": {
              "label": "Gear",
              "fontSize": "6xl",
              "fontWeight": "black",
              "textColor": "white",
              "fallbackValue": "N"
            },
            "bindings": [
              {
                "telemetry_key": "Current_Gear",
                "target_prop": "value",
                "transform": null
              }
            ]
          }
        ]
      },
      {
        "id": 3,
        "name": "Oil & Fuel",
        "slug": "oil-fuel",
        "icon": "droplets",
        "grid": {
          "colStart": 5,
          "colSpan": 8,
          "rowSpan": 1
        },
        "widgets": [
          {
            "id": 301,
            "type": "linear_bar",
            "component": "LinearBarD3",
            "size": "full",
            "props": {
              "label": "Oil Temp",
              "min": 0,
              "max": 300,
              "unit": "°F",
              "variant": "default",
              "colorScheme": "temperature",
              "thresholds": {
                "warning": 220,
                "critical": 260
              }
            },
            "bindings": [
              {
                "telemetry_key": "Oil_Temperature",
                "target_prop": "value",
                "transform": null
              }
            ]
          },
          {
            "id": 302,
            "type": "linear_bar",
            "component": "LinearBarD3",
            "size": "full",
            "props": {
              "label": "Fuel Press",
              "min": 0,
              "max": 80,
              "unit": "PSI",
              "variant": "default",
              "colorScheme": "pressure"
            },
            "bindings": [
              {
                "telemetry_key": "Fuel_Pressure",
                "target_prop": "value",
                "transform": null
              }
            ]
          }
        ]
      },
      {
        "id": 4,
        "name": "Temperatures",
        "slug": "temperatures",
        "icon": "thermometer",
        "grid": {
          "colStart": 1,
          "colSpan": 12,
          "rowSpan": 1
        },
        "widgets": [
          {
            "id": 401,
            "type": "text_grid",
            "component": "TextGridWidget",
            "size": "full",
            "props": {
              "columns": 4,
              "gap": 2,
              "items": [
                { "label": "Coolant", "slot": "coolant", "unit": "°" },
                { "label": "Oil", "slot": "oil", "unit": "°" },
                { "label": "Trans", "slot": "trans", "unit": "°" },
                { "label": "Intake", "slot": "intake", "unit": "°" }
              ]
            },
            "bindings": [
              {
                "telemetry_key": "Coolant_Temp",
                "target_prop": "value",
                "slot": "coolant",
                "transform": null
              },
              {
                "telemetry_key": "Oil_Temperature",
                "target_prop": "value",
                "slot": "oil",
                "transform": null
              },
              {
                "telemetry_key": "Transmission_Temp",
                "target_prop": "value",
                "slot": "trans",
                "transform": null
              },
              {
                "telemetry_key": "Intake_Air_Temp",
                "target_prop": "value",
                "slot": "intake",
                "transform": null
              }
            ]
          }
        ]
      },
      {
        "id": 5,
        "name": "Tires",
        "slug": "tires",
        "icon": "circle-dot",
        "grid": {
          "colStart": 1,
          "colSpan": 6,
          "rowSpan": 1
        },
        "widgets": [
          {
            "id": 501,
            "type": "tire_grid",
            "component": "TireGridWidget",
            "size": "full",
            "props": {
              "layout": "2x2",
              "showPressure": true,
              "showTemperature": true,
              "pressureUnit": "PSI",
              "tempUnit": "°F"
            },
            "bindings": [
              {
                "telemetry_key": "Tire_FL_Pressure",
                "target_prop": "pressure",
                "slot": "fl"
              },
              {
                "telemetry_key": "Tire_FL_Temp",
                "target_prop": "temperature",
                "slot": "fl"
              },
              {
                "telemetry_key": "Tire_FR_Pressure",
                "target_prop": "pressure",
                "slot": "fr"
              },
              {
                "telemetry_key": "Tire_FR_Temp",
                "target_prop": "temperature",
                "slot": "fr"
              },
              {
                "telemetry_key": "Tire_RL_Pressure",
                "target_prop": "pressure",
                "slot": "rl"
              },
              {
                "telemetry_key": "Tire_RL_Temp",
                "target_prop": "temperature",
                "slot": "rl"
              },
              {
                "telemetry_key": "Tire_RR_Pressure",
                "target_prop": "pressure",
                "slot": "rr"
              },
              {
                "telemetry_key": "Tire_RR_Temp",
                "target_prop": "temperature",
                "slot": "rr"
              }
            ]
          }
        ]
      },
      {
        "id": 6,
        "name": "Electrical",
        "slug": "electrical",
        "icon": "zap",
        "grid": {
          "colStart": 7,
          "colSpan": 6,
          "rowSpan": 1
        },
        "widgets": [
          {
            "id": 601,
            "type": "text_grid",
            "component": "TextGridWidget",
            "size": "full",
            "props": {
              "columns": 2,
              "gap": 4,
              "items": [
                { "label": "Battery", "slot": "voltage", "unit": "V", "color": "yellow-400" },
                { "label": "Current", "slot": "current", "unit": "A", "color": "yellow-400" }
              ]
            },
            "bindings": [
              {
                "telemetry_key": "Battery_Voltage",
                "target_prop": "value",
                "slot": "voltage",
                "transform": { "round": 1 }
              },
              {
                "telemetry_key": "Alternator_Current",
                "target_prop": "value",
                "slot": "current",
                "transform": { "round": 1 }
              }
            ]
          }
        ]
      }
    ],
    "special_components": {
      "map": {
        "enabled": true,
        "grid": {
          "colStart": 1,
          "colSpan": 5,
          "rowStart": 1,
          "rowSpan": "full"
        },
        "bindings": {
          "latitude": "GPS_Latitude",
          "longitude": "GPS_Longitude",
          "heading": "GPS_Heading",
          "speed": "GPS_Speed"
        }
      },
      "shift_lights": {
        "enabled": true,
        "position": "top",
        "bindings": {
          "rpm": "RPM"
        },
        "config": {
          "totalLights": 10,
          "startRpm": 4000,
          "maxRpm": 9000,
          "shiftRpm": 8500
        }
      }
    },
    "meta": {
      "generated_at": "2024-12-28T03:45:00.000Z",
      "cache_ttl": 3600,
      "version": "1.0"
    }
  }
}
```

---

## 4. Arquitectura Frontend - Component Factory

### 4.1 Estructura de Carpetas Propuesta

```
resources/js/
├── components/
│   └── Dashboard/
│       ├── DynamicDashboard.vue        # 🎯 Componente principal "factory"
│       ├── GroupCard.vue               # Contenedor de grupo
│       ├── WidgetRenderer.vue          # Renderizador dinámico
│       │
│       ├── widgets/                    # Catálogo de widgets
│       │   ├── RadialGaugeD3.vue       ✅ (existente)
│       │   ├── LinearBarD3.vue         ✅ (existente)
│       │   ├── DigitalValueWidget.vue  📝 (crear)
│       │   ├── TextGridWidget.vue      📝 (crear)
│       │   ├── TireGridWidget.vue      📝 (crear)
│       │   └── index.ts                # Registro de widgets
│       │
│       └── composables/
│           ├── useDashboardConfig.ts   # Fetch de configuración
│           ├── useTelemetryBinding.ts  # 🎯 Vincula WebSocket → Widgets
│           └── useWidgetFactory.ts     # Resolución dinámica de componentes
│
├── pages/
│   └── TelemetryDashboard.vue          # Página principal
│
└── types/
    └── dashboard.d.ts                  # TypeScript interfaces
```

### 4.2 Component Factory: DynamicDashboard.vue

```vue
<script setup lang="ts">
/**
 * DynamicDashboard.vue - Component Factory
 * Renderiza el dashboard completamente desde configuración JSON
 */
import { computed, provide, ref, onMounted, onUnmounted } from 'vue';
import { useDashboardConfig } from '@/composables/useDashboardConfig';
import { useTelemetryBinding } from '@/composables/useTelemetryBinding';
import GroupCard from './GroupCard.vue';
import WidgetRenderer from './WidgetRenderer.vue';

// Props
const props = defineProps<{
    vehicleId: number;
    preloadedConfig?: DashboardConfig; // Opcional: para SSR con Inertia
}>();

// Fetch configuración del backend
const { 
    config, 
    loading, 
    error, 
    refresh 
} = useDashboardConfig(props.vehicleId, props.preloadedConfig);

// Sistema de telemetría en tiempo real
const { 
    telemetryData, 
    isConnected,
    connectionStatus,
    subscribe,
    unsubscribe 
} = useTelemetryBinding(props.vehicleId);

// Proveer datos de telemetría a todos los widgets hijos
provide('telemetryData', telemetryData);
provide('isConnected', isConnected);

// Computed: Grid CSS dinámico
const gridStyle = computed(() => {
    if (!config.value?.layout?.grid_config) return {};
    
    const { columns, gap } = config.value.layout.grid_config;
    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
        gap: `${gap * 0.25}rem` // Convertir a rem (Tailwind scale)
    };
});

// Lifecycle
onMounted(() => {
    subscribe();
});

onUnmounted(() => {
    unsubscribe();
});
</script>

<template>
    <div class="dynamic-dashboard">
        <!-- Loading State -->
        <div v-if="loading" class="loading-overlay">
            <LoadingSpinner />
        </div>
        
        <!-- Error State -->
        <div v-else-if="error" class="error-state">
            <AlertCircle class="w-12 h-12 text-red-500" />
            <p>Error cargando configuración</p>
            <button @click="refresh">Reintentar</button>
        </div>
        
        <!-- Dashboard Content -->
        <template v-else-if="config">
            <!-- Shift Lights (Special Component) -->
            <ShiftLightsBar 
                v-if="config.special_components?.shift_lights?.enabled"
                :config="config.special_components.shift_lights"
                :rpm="telemetryData[config.special_components.shift_lights.bindings.rpm] ?? 0"
            />
            
            <!-- Main Grid Layout -->
            <div class="dashboard-grid" :style="gridStyle">
                <!-- Map Widget (Special, fixed position) -->
                <div 
                    v-if="config.special_components?.map?.enabled"
                    class="map-container"
                    :style="{
                        gridColumn: `${config.special_components.map.grid.colStart} / span ${config.special_components.map.grid.colSpan}`,
                        gridRow: config.special_components.map.grid.rowSpan === 'full' ? '1 / -1' : 'auto'
                    }"
                >
                    <MapWidget 
                        :latitude="telemetryData[config.special_components.map.bindings.latitude]"
                        :longitude="telemetryData[config.special_components.map.bindings.longitude]"
                    />
                </div>
                
                <!-- Dynamic Groups -->
                <GroupCard
                    v-for="group in config.groups"
                    :key="group.id"
                    :group="group"
                    :style="{
                        gridColumn: `${group.grid.colStart} / span ${group.grid.colSpan}`,
                        gridRow: group.grid.rowSpan ? `span ${group.grid.rowSpan}` : 'auto'
                    }"
                >
                    <!-- Widgets dentro del grupo -->
                    <WidgetRenderer
                        v-for="widget in group.widgets"
                        :key="widget.id"
                        :widget="widget"
                        :telemetry-data="telemetryData"
                    />
                </GroupCard>
            </div>
        </template>
    </div>
</template>
```

### 4.3 WidgetRenderer.vue - El Resolutor Dinámico

```vue
<script setup lang="ts">
/**
 * WidgetRenderer.vue
 * Resuelve dinámicamente el componente correcto y le pasa los props con datos vivos
 */
import { computed, defineAsyncComponent } from 'vue';
import type { WidgetInstance, TelemetryData } from '@/types/dashboard';

// Registro de componentes disponibles
const WIDGET_COMPONENTS: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    'radial_gauge': defineAsyncComponent(() => import('./widgets/RadialGaugeD3.vue')),
    'linear_bar': defineAsyncComponent(() => import('./widgets/LinearBarD3.vue')),
    'digital_value': defineAsyncComponent(() => import('./widgets/DigitalValueWidget.vue')),
    'text_grid': defineAsyncComponent(() => import('./widgets/TextGridWidget.vue')),
    'tire_grid': defineAsyncComponent(() => import('./widgets/TireGridWidget.vue')),
};

// Props
const props = defineProps<{
    widget: WidgetInstance;
    telemetryData: TelemetryData;
}>();

// Resolver componente
const resolvedComponent = computed(() => {
    return WIDGET_COMPONENTS[props.widget.type] ?? null;
});

// Construir props con valores de telemetría inyectados
const resolvedProps = computed(() => {
    const baseProps = { ...props.widget.props };
    
    // Procesar bindings: inyectar valores de telemetría
    if (props.widget.bindings && props.widget.bindings.length > 0) {
        for (const binding of props.widget.bindings) {
            const rawValue = props.telemetryData[binding.telemetry_key];
            let value = rawValue ?? null;
            
            // Aplicar transformaciones si existen
            if (binding.transform && value !== null) {
                if (binding.transform.multiply) {
                    value = value * binding.transform.multiply;
                }
                if (binding.transform.offset) {
                    value = value + binding.transform.offset;
                }
                if (binding.transform.round !== undefined) {
                    value = Number(value.toFixed(binding.transform.round));
                }
            }
            
            // Asignar al prop target
            if (binding.slot) {
                // Para widgets con múltiples slots (Tires, TextGrid)
                if (!baseProps.values) baseProps.values = {};
                if (!baseProps.values[binding.slot]) baseProps.values[binding.slot] = {};
                baseProps.values[binding.slot][binding.target_prop] = value;
            } else {
                // Binding directo
                baseProps[binding.target_prop] = value;
            }
        }
    }
    
    return baseProps;
});

// Clases CSS por tamaño
const sizeClasses = computed(() => {
    const sizeMap: Record<string, string> = {
        'sm': 'w-20 h-20',
        'md': 'w-24 h-24',
        'lg': 'w-28 h-28',
        'xl': 'w-full',
        'full': 'w-full'
    };
    return sizeMap[props.widget.size] ?? 'w-24 h-24';
});
</script>

<template>
    <component 
        v-if="resolvedComponent"
        :is="resolvedComponent"
        v-bind="resolvedProps"
        :class="sizeClasses"
    />
    <div v-else class="widget-error">
        Widget "{{ widget.type }}" no encontrado
    </div>
</template>
```

### 4.4 Composable: useTelemetryBinding.ts

```typescript
/**
 * useTelemetryBinding.ts
 * Maneja la suscripción WebSocket y mantiene el estado reactivo de telemetría
 */
import { ref, computed, onUnmounted } from 'vue';
import Echo from 'laravel-echo';

// Tipos
export interface TelemetryData {
    [key: string]: number | string | null;
}

export function useTelemetryBinding(vehicleId: number) {
    // Estado reactivo
    const telemetryData = ref<TelemetryData>({});
    const isConnected = ref(false);
    const lastUpdate = ref<Date | null>(null);
    const connectionStatus = ref<'connecting' | 'connected' | 'disconnected' | 'error'>('disconnected');
    
    // Canal privado del vehículo
    let channel: any = null;
    
    // Suscribirse al canal de telemetría
    function subscribe() {
        if (!window.Echo) {
            console.error('Laravel Echo no está configurado');
            connectionStatus.value = 'error';
            return;
        }
        
        connectionStatus.value = 'connecting';
        
        // Canal privado para este vehículo específico
        channel = window.Echo.private(`vehicle.${vehicleId}`)
            .listen('.telemetry.updated', (event: any) => {
                // Actualizar datos de telemetría
                if (event.data) {
                    // Merge con datos existentes (para no perder datos de sensores que no reportan en cada paquete)
                    telemetryData.value = {
                        ...telemetryData.value,
                        ...event.data
                    };
                    lastUpdate.value = new Date();
                }
            })
            .subscribed(() => {
                isConnected.value = true;
                connectionStatus.value = 'connected';
                console.log(`🟢 Conectado a telemetría del vehículo ${vehicleId}`);
            })
            .error((error: any) => {
                console.error('Error en canal de telemetría:', error);
                connectionStatus.value = 'error';
                isConnected.value = false;
            });
    }
    
    // Desuscribirse
    function unsubscribe() {
        if (channel) {
            window.Echo.leave(`vehicle.${vehicleId}`);
            channel = null;
            isConnected.value = false;
            connectionStatus.value = 'disconnected';
        }
    }
    
    // Helper: obtener valor específico con fallback
    function getValue(key: string, fallback: number | string = 0): number | string {
        return telemetryData.value[key] ?? fallback;
    }
    
    // Cleanup automático
    onUnmounted(() => {
        unsubscribe();
    });
    
    return {
        telemetryData,
        isConnected,
        lastUpdate,
        connectionStatus,
        subscribe,
        unsubscribe,
        getValue
    };
}
```

---

## 5. Integración con Datos en Tiempo Real

### 5.1 Flujo de Datos End-to-End

```
┌─────────────────┐     ┌─────────────┐     ┌─────────────────┐
│  ESP32 Device   │────▶│   MQTT      │────▶│  Laravel API    │
│  (CAN/OBD Data) │     │  Broker     │     │  (Ingest)       │
└─────────────────┘     └─────────────┘     └────────┬────────┘
                                                      │
                                                      ▼
                                            ┌─────────────────┐
                                            │ VehicleTelemetry │
                                            │    Event.php    │
                                            └────────┬────────┘
                                                     │ broadcast()
                                                     ▼
┌─────────────────┐     ┌─────────────┐     ┌─────────────────┐
│   Vue Frontend  │◀────│   Laravel   │◀────│  Laravel Echo   │
│ DynamicDashboard│     │   Reverb    │     │   (WebSocket)   │
└─────────────────┘     │ (WS Server) │     └─────────────────┘
                        └─────────────┘
```

### 5.2 Mapeo Dinámico: CAN_ID → Widget

El dato crudo del CAN bus (ej: `CAN_ID_0x1F`) se mapea al widget mediante el `telemetry_key` en `sensor_widget_bindings`:

**Ejemplo:**

1. **Sensor en BD:**
   ```sql
   -- sensors table
   id: 15
   pid: "CAN_0x1F"
   name: "Throttle Position"
   category: "engine"
   unit: "%"
   ```

2. **Vehículo tiene ese sensor:**
   ```sql
   -- vehicle_sensors table
   id: 42
   vehicle_id: 5
   sensor_id: 15
   is_active: true
   ```

3. **Widget vinculado:**
   ```sql
   -- sensor_widget_bindings table
   widget_instance_id: 103  -- El gauge TPS
   vehicle_sensor_id: 42
   telemetry_key: "Throttle_Position"  -- Clave en JSON de telemetría
   target_prop: "value"
   ```

4. **Dato recibido por WebSocket:**
   ```json
   {
     "data": {
       "Throttle_Position": 78.5
     }
   }
   ```

5. **El `WidgetRenderer` resuelve:**
   ```typescript
   // binding.telemetry_key = "Throttle_Position"
   const value = telemetryData["Throttle_Position"]; // 78.5
   // Se pasa como prop :value="78.5" al RadialGaugeD3
   ```

### 5.3 Manejo de Sensores Ausentes

Si un sensor no reporta, el widget muestra estado "offline":

```vue
<RadialGaugeD3 
    :value="telemetryData['RPM'] ?? null"
    :is-offline="telemetryData['RPM'] === undefined"
/>
```

---

## 6. Diagramas de Flujo

### 6.1 Flujo de Renderizado

```
┌───────────────────────────────────────────────────────────────┐
│                    FLUJO DE RENDERIZADO                       │
├───────────────────────────────────────────────────────────────┤
│                                                               │
│  1. TelemetryDashboard.vue monta                              │
│         │                                                     │
│         ▼                                                     │
│  2. useDashboardConfig(vehicleId)                             │
│         │ → GET /api/vehicles/5/dashboard                     │
│         ▼                                                     │
│  3. Recibe JSON de configuración                              │
│         │                                                     │
│         ▼                                                     │
│  4. useTelemetryBinding.subscribe()                           │
│         │ → WebSocket: vehicle.5                              │
│         ▼                                                     │
│  5. DynamicDashboard renderiza:                               │
│         │                                                     │
│         ├── v-for group in config.groups                      │
│         │       │                                             │
│         │       └── <GroupCard :group="group">                │
│         │               │                                     │
│         │               ├── v-for widget in group.widgets     │
│         │               │       │                             │
│         │               │       └── <WidgetRenderer           │
│         │               │               :widget="widget"      │
│         │               │               :telemetry="data"     │
│         │               │           />                        │
│         │               │           │                         │
│         │               │           ▼                         │
│         │               │   WIDGET_COMPONENTS[widget.type]    │
│         │               │           │                         │
│         │               │           ▼                         │
│         │               │   <RadialGaugeD3 :value="78.5" />   │
│         │               │                                     │
│         │               └── </GroupCard>                      │
│         │                                                     │
│         └── </DynamicDashboard>                               │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

### 6.2 Diagrama de Secuencia: Actualización en Tiempo Real

```
                 ┌─────────┐   ┌────────┐   ┌─────────┐   ┌──────────┐   ┌───────────┐
                 │ ESP32   │   │  MQTT  │   │ Laravel │   │  Reverb  │   │   Vue     │
                 │ Device  │   │ Broker │   │  API    │   │ WS Server│   │ Dashboard │
                 └────┬────┘   └───┬────┘   └────┬────┘   └────┬─────┘   └─────┬─────┘
                      │            │             │              │               │
   CAN Data Read      │            │             │              │               │
   RPM = 5500    ────▶│            │             │              │               │
                      │   PUBLISH  │             │              │               │
                      │──────────▶ │             │              │               │
                      │   topic:   │             │              │               │
                      │   v/5/telem│             │              │               │
                      │            │  HTTP POST  │              │               │
                      │            │────────────▶│              │               │
                      │            │  /api/ingest│              │               │
                      │            │             │              │               │
                      │            │             │  broadcast   │               │
                      │            │             │    event     │               │
                      │            │             │─────────────▶│               │
                      │            │             │  VehicleTelem│               │
                      │            │             │  etryEvent   │               │
                      │            │             │              │  WS Message   │
                      │            │             │              │──────────────▶│
                      │            │             │              │ {data: {...}} │
                      │            │             │              │               │
                      │            │             │              │         telemetryData.value
                      │            │             │              │         = { RPM: 5500 }
                      │            │             │              │               │
                      │            │             │              │         RadialGaugeD3
                      │            │             │              │         :value="5500"
                      │            │             │              │         → Gauge moves!
                      │            │             │              │               │
```

---

## 7. Plan de Implementación

### Fase 1: Base de Datos (Semana 1)
- [ ] Crear migraciones para las 5 nuevas tablas
- [ ] Crear modelos Eloquent con relaciones
- [ ] Seeders con configuración de ejemplo
- [ ] Crear `DashboardLayoutController` con endpoint `/api/vehicles/{id}/dashboard`

### Fase 2: API Backend (Semana 1-2)
- [ ] Implementar `DashboardLayoutResource` para serialización JSON
- [ ] Crear policy de autorización (solo owner del vehículo)
- [ ] Cache de configuración (Redis/File) con invalidación

### Fase 3: Component Factory Frontend (Semana 2)
- [ ] Crear `DynamicDashboard.vue`
- [ ] Crear `GroupCard.vue`
- [ ] Crear `WidgetRenderer.vue`
- [ ] Crear `useDashboardConfig.ts`
- [ ] Crear `useTelemetryBinding.ts`

### Fase 4: Nuevos Widgets (Semana 2-3)
- [ ] `DigitalValueWidget.vue`
- [ ] `TextGridWidget.vue`
- [ ] `TireGridWidget.vue`
- [ ] Actualizar `widgets/index.ts` con registro

### Fase 5: Admin UI para Configuración (Semana 3-4)
- [ ] CRUD de layouts
- [ ] Drag & drop de grupos
- [ ] Selector de widgets
- [ ] Binding de sensores

### Fase 6: Testing & QA (Semana 4)
- [ ] Tests unitarios de composables
- [ ] Tests de integración API
- [ ] Testing en tablets reales
- [ ] Performance profiling (60 FPS target)

---

## 📎 Apéndice: TypeScript Interfaces

```typescript
// types/dashboard.d.ts

export interface DashboardConfig {
    vehicle_id: number;
    layout: DashboardLayout;
    groups: WidgetGroup[];
    special_components: SpecialComponents;
    meta: ConfigMeta;
}

export interface DashboardLayout {
    id: number;
    name: string;
    theme: string;
    grid_config: GridConfig;
}

export interface GridConfig {
    columns: number;
    gap: number;
    responsive?: Record<string, { columns: number }>;
}

export interface WidgetGroup {
    id: number;
    name: string;
    slug: string;
    icon?: string;
    grid: GroupGrid;
    style?: GroupStyle;
    widgets: WidgetInstance[];
}

export interface GroupGrid {
    colStart: number;
    colSpan: number;
    rowSpan?: number;
}

export interface WidgetInstance {
    id: number;
    type: string;
    component: string;
    size: 'sm' | 'md' | 'lg' | 'xl' | 'full';
    props: Record<string, any>;
    bindings: SensorBinding[];
}

export interface SensorBinding {
    telemetry_key: string;
    target_prop: string;
    slot?: string;
    transform?: {
        multiply?: number;
        offset?: number;
        round?: number;
    };
}

export interface TelemetryData {
    [key: string]: number | string | null;
}
```

---

## 8. Selección de Widgets por Sensor (Guía Práctica)

Esta sección explica cómo el sistema permite elegir **qué tipo de widget usar para cada sensor**, garantizando total flexibilidad en la configuración visual del dashboard.

### 8.1 Catálogo de Widgets Disponibles

La tabla `widget_definitions` actúa como un **menú de opciones**. Cada tipo de widget tiene sus características:

| id | type | name | component | Uso Recomendado |
|----|------|------|-----------|-----------------|
| 1 | `radial_gauge` | 🔘 Tacómetro D3 | RadialGaugeD3 | RPM, Velocidad, TPS |
| 2 | `linear_bar` | 📊 Barra Lineal | LinearBarD3 | Temperaturas, Presiones, Throttle |
| 3 | `digital_value` | 🔢 Valor Digital | DigitalValueWidget | Gear, Voltaje, valores simples |
| 4 | `text_grid` | 📋 Grilla de Texto | TextGridWidget | Múltiples valores en grid (4 temps) |
| 5 | `tire_grid` | 🚗 Diagrama de Llantas | TireGridWidget | Presión/Temp de 4 ruedas |
| 6 | `speedometer` | 🎯 Velocímetro Pro | SpeedometerWidget | Velocidad con needle estilo racing |
| 7 | `thermometer` | 🌡️ Termómetro | ThermometerWidget | Temperaturas con escala vertical |

### 8.2 Ejemplo Práctico: Configurando el Vehículo #5

Supongamos que para un **Ford Raptor (Vehículo ID 5)** deseas la siguiente configuración:

| Sensor | Widget Elegido | ¿Por qué? |
|--------|----------------|-----------|
| RPM | 🔘 Tacómetro (Radial) | Visualización clásica para revoluciones |
| Velocidad | 🔘 Tacómetro (Radial) | Lectura rápida en carreras |
| Throttle Position | 📊 Barra Lineal | Muestra progreso 0-100% intuitivamente |
| Oil Temp | 📊 Barra Lineal | Fácil de ver umbrales de warning |
| Coolant, Trans, Intake | 📋 Grilla de Texto | Compacto, 4 valores en un widget |
| Gear | 🔢 Valor Digital Grande | Número visible incluso en movimiento |
| Llantas (4) | 🚗 Diagrama de Llantas | Layout 2x2 que representa el vehículo |

### 8.3 Cómo se Guarda en la Base de Datos

```sql
-- ═══════════════════════════════════════════════════════════════════
-- PASO 1: Crear el Layout del vehículo
-- ═══════════════════════════════════════════════════════════════════
INSERT INTO dashboard_layouts (vehicle_id, name, is_active) 
VALUES (5, 'Raptor Race Setup', true);
-- Resultado: layout_id = 1

-- ═══════════════════════════════════════════════════════════════════
-- PASO 2: Crear el Grupo "Engine Performance"
-- ═══════════════════════════════════════════════════════════════════
INSERT INTO widget_groups (dashboard_layout_id, name, slug, grid_column_span, sort_order)
VALUES (1, 'Engine Performance', 'engine', 12, 1);
-- Resultado: group_id = 1

-- ═══════════════════════════════════════════════════════════════════
-- PASO 3: RPM como TACÓMETRO RADIAL
-- ═══════════════════════════════════════════════════════════════════
INSERT INTO widget_instances (widget_group_id, widget_definition_id, props, sort_order)
VALUES (
    1,                                      -- Grupo: Engine Performance
    1,                                      -- Widget: radial_gauge (tacómetro) ◀️ AQUÍ ELIGES EL WIDGET
    '{"min": 0, "max": 9000, "label": "RPM", "thresholds": [
        {"value": 60, "color": "#00ff9d"},
        {"value": 85, "color": "#ff8a00"},
        {"value": 100, "color": "#ff003c"}
    ]}',
    1
);
-- Resultado: widget_instance_id = 101

-- Vincular sensor RPM al widget
INSERT INTO sensor_widget_bindings (widget_instance_id, vehicle_sensor_id, telemetry_key)
VALUES (101, 42, 'RPM');

-- ═══════════════════════════════════════════════════════════════════
-- PASO 4: Throttle Position como BARRA LINEAL (no tacómetro)
-- ═══════════════════════════════════════════════════════════════════
INSERT INTO widget_instances (widget_group_id, widget_definition_id, props, sort_order)
VALUES (
    1,                                      -- Mismo grupo: Engine Performance
    2,                                      -- Widget: linear_bar ◀️ DIFERENTE WIDGET
    '{"min": 0, "max": 100, "label": "TPS", "unit": "%", "colorScheme": "success"}',
    3
);
-- Resultado: widget_instance_id = 103

-- Vincular sensor Throttle al widget de barra
INSERT INTO sensor_widget_bindings (widget_instance_id, vehicle_sensor_id, telemetry_key)
VALUES (103, 45, 'Throttle_Position');

-- ═══════════════════════════════════════════════════════════════════
-- PASO 5: Temperatures como GRILLA DE TEXTO (4 valores en 1 widget)
-- ═══════════════════════════════════════════════════════════════════
INSERT INTO widget_instances (widget_group_id, widget_definition_id, props, sort_order)
VALUES (
    4,                                      -- Grupo: Temperatures
    4,                                      -- Widget: text_grid ◀️ WIDGET PARA MÚLTIPLES VALORES
    '{"columns": 4, "items": [
        {"label": "Coolant", "slot": "coolant", "unit": "°"},
        {"label": "Oil", "slot": "oil", "unit": "°"},
        {"label": "Trans", "slot": "trans", "unit": "°"},
        {"label": "Intake", "slot": "intake", "unit": "°"}
    ]}',
    1
);
-- Resultado: widget_instance_id = 401

-- Vincular LOS 4 SENSORES al mismo widget usando slots
INSERT INTO sensor_widget_bindings (widget_instance_id, vehicle_sensor_id, telemetry_key, slot)
VALUES 
    (401, 50, 'Coolant_Temp', 'coolant'),
    (401, 51, 'Oil_Temperature', 'oil'),
    (401, 52, 'Transmission_Temp', 'trans'),
    (401, 53, 'Intake_Air_Temp', 'intake');
```

### 8.4 Interfaz de Administración (UI Propuesta)

Para evitar escribir SQL manualmente, se creará una **UI de configuración visual**:

```
╔══════════════════════════════════════════════════════════════════════════════╗
║  📊 CONFIGURAR DASHBOARD - Ford Raptor (#5)                                  ║
╠══════════════════════════════════════════════════════════════════════════════╣
║                                                                              ║
║  ┌─ Grupo: Engine Performance ──────────────────────────────────────────┐   ║
║  │                                                                       │   ║
║  │  SENSOR              TIPO DE WIDGET              ACCIONES             │   ║
║  │  ─────────────────────────────────────────────────────────────────    │   ║
║  │  ⚙️ RPM              [🔘 Tacómetro Radial   ▼]   [⚙️ Props] [🗑️]     │   ║
║  │  ⚙️ Vehicle Speed    [🔘 Tacómetro Radial   ▼]   [⚙️ Props] [🗑️]     │   ║
║  │  ⚙️ Throttle Pos.    [📊 Barra Lineal      ▼]   [⚙️ Props] [🗑️]     │   ║
║  │                                                                       │   ║
║  │  [+ Agregar Sensor al Grupo]                                          │   ║
║  └───────────────────────────────────────────────────────────────────────┘   ║
║                                                                              ║
║  ┌─ Grupo: Temperatures ────────────────────────────────────────────────┐   ║
║  │                                                                       │   ║
║  │  SENSOR              TIPO DE WIDGET              ACCIONES             │   ║
║  │  ─────────────────────────────────────────────────────────────────    │   ║
║  │  🌡️ Oil Temp         [📊 Barra Lineal      ▼]   [⚙️ Props] [🗑️]     │   ║
║  │  🌡️ Coolant Temp     [📋 Grilla de Texto   ▼]   ────────────────     │   ║
║  │  🌡️ Trans Temp       [📋 Grilla de Texto   ▼]   (mismo widget)       │   ║
║  │  🌡️ Intake Temp      [📋 Grilla de Texto   ▼]   ────────────────     │   ║
║  │                                                                       │   ║
║  └───────────────────────────────────────────────────────────────────────┘   ║
║                                                                              ║
║  ┌─ Grupo: Electrical ──────────────────────────────────────────────────┐   ║
║  │                                                                       │   ║
║  │  ⚡ Battery Voltage   [🔢 Valor Digital     ▼]   [⚙️ Props] [🗑️]     │   ║
║  │  ⚡ Alternator Amps   [🔢 Valor Digital     ▼]   [⚙️ Props] [🗑️]     │   ║
║  │                                                                       │   ║
║  └───────────────────────────────────────────────────────────────────────┘   ║
║                                                                              ║
║  [+ Nuevo Grupo]                        [👁️ Vista Previa] [💾 Guardar]     ║
╚══════════════════════════════════════════════════════════════════════════════╝
```

### 8.5 El Dropdown de Selección de Widget

Al hacer clic en el dropdown, el usuario ve todas las opciones disponibles:

```
┌────────────────────────────────────┐
│  Seleccionar tipo de widget:       │
├────────────────────────────────────┤
│  🔘 Tacómetro Radial (D3)          │  ← Ideal para RPM, Speed
│  📊 Barra Lineal                   │  ← Ideal para Temps, Throttle  
│  🔢 Valor Digital Grande           │  ← Ideal para Gear, Voltage
│  📋 Grilla de Texto                │  ← Para múltiples valores
│  🚗 Diagrama de Llantas            │  ← Para presión/temp ruedas
│  🎯 Velocímetro Pro                │  ← Alternativa para Speed
│  🌡️ Termómetro Vertical            │  ← Alternativa para Temps
└────────────────────────────────────┘
```

### 8.6 Casos de Uso: Flexibilidad Total

#### Caso 1: Cambiar RPM de Tacómetro a Barra

Si después de probar decides que prefieres ver el RPM como barra lineal:

```sql
-- Solo cambias el widget_definition_id
UPDATE widget_instances 
SET widget_definition_id = 2  -- Ahora es linear_bar
WHERE id = 101;               -- El widget de RPM
```

**Resultado:** Sin tocar código, el RPM ahora se muestra como barra.

#### Caso 2: Vehículos Diferentes, Configuraciones Diferentes

| Vehículo | RPM Widget | Temps Widget | Gear Widget |
|----------|------------|--------------|-------------|
| Ford Raptor | 🔘 Tacómetro D3 | 📊 Barra Lineal | 🔢 Digital |
| Polaris RZR | 🎯 Speedometer Pro | 📋 Grilla Texto | 🔘 Tacómetro |
| Trophy Truck | 📊 Barra Lineal | 🌡️ Termómetro | 🔢 Digital XL |

**Cada vehículo tiene su propio `dashboard_layout_id`**, por lo que las configuraciones son completamente independientes.

#### Caso 3: Agregar un Nuevo Tipo de Widget

Si en el futuro creas un nuevo componente Vue (ej: `HalfCircleGauge.vue`):

1. **Creas el componente Vue:**
   ```
   resources/js/components/Dashboard/widgets/HalfCircleGauge.vue
   ```

2. **Lo registras en el catálogo:**
   ```sql
   INSERT INTO widget_definitions (type, name, component_name, props_schema)
   VALUES ('half_circle', 'Gauge Semicircular', 'HalfCircleGauge', 
           '{"min": {"type": "number"}, "max": {"type": "number"}}');
   ```

3. **Lo agregas al registro de Vue:**
   ```typescript
   // WidgetRenderer.vue
   const WIDGET_COMPONENTS = {
       // ... existentes
       'half_circle': defineAsyncComponent(() => import('./widgets/HalfCircleGauge.vue')),
   };
   ```

4. **¡Listo!** Ahora aparece en el dropdown para seleccionar.

### 8.7 Tabla de Capacidades por Widget

| Widget | Valor Único | Múltiples Slots | Thresholds | Animación | Mejor Para |
|--------|:-----------:|:---------------:|:----------:|:---------:|------------|
| radial_gauge | ✅ | ❌ | ✅ | ✅ | RPM, Speed, Presiones |
| linear_bar | ✅ | ❌ | ✅ | ✅ | Temps, Throttle, Fuel |
| digital_value | ✅ | ❌ | ❌ | ❌ | Gear, Voltage, contadores |
| text_grid | ❌ | ✅ (4+) | ❌ | ❌ | Múltiples temps, stats |
| tire_grid | ❌ | ✅ (4 fijo) | ✅ | ❌ | Presión/Temp llantas |
| speedometer | ✅ | ❌ | ✅ | ✅ | Velocidad estilo racing |

### 8.8 Resumen: ¿Qué Puedes Hacer?

| Acción | ¿Soportado? |
|--------|:-----------:|
| Elegir tacómetro para RPM | ✅ |
| Elegir barra para Throttle | ✅ |
| Usar grilla para múltiples temps | ✅ |
| Cambiar widget sin tocar código | ✅ |
| Configuración diferente por vehículo | ✅ |
| Agregar nuevos tipos de widgets | ✅ |
| Reordenar widgets visualmente | ✅ |
| Configurar props (min, max, colors) | ✅ |
| Ocultar sensores sin eliminarlos | ✅ |

---

## 9. Diagramas Visuales de Arquitectura

Esta sección contiene diagramas ASCII que muestran la arquitectura y flujo de datos del sistema de forma visual.

### 9.1 Arquitectura del Component Factory

```
┌────────────────────────────────────────────────────────────────┐
│                      DashboardDynamic.vue (Page)               │
│                              │                                 │
│              ┌───────────────┴───────────────┐                 │
│              │    DynamicDashboard.vue       │                 │
│              │  (Main Orchestrator)          │                 │
│              │  - useDashboardConfig()       │                 │
│              │  - useTelemetryBinding()      │                 │
│              │  - provide('telemetryData')   │                 │
│              └───────────────┬───────────────┘                 │
│                              │                                 │
│       ┌──────────────────────┼──────────────────────┐          │
│       │                      │                      │          │
│  ShiftLightsBar        GroupCard × N          MapWidget        │
│       │                      │                                 │
│       │            ┌─────────┴─────────┐                       │
│       │            │  WidgetRenderer   │                       │
│       │            │  (Component Factory)                      │
│       │            └─────────┬─────────┘                       │
│       │                      │                                 │
│       └──────────┬───────────┼───────────┬─────────────────────┤
│                  │           │           │                     │
│            RadialGaugeD3  LinearBarD3  DigitalValue  TextGrid  │
│                                                     TireGrid   │
└────────────────────────────────────────────────────────────────┘
```

**Descripción:**
- `DashboardDynamic.vue` es la página Inertia que recibe props del servidor
- `DynamicDashboard.vue` orquesta todo: fetches config, suscribe WebSocket, provee datos
- `GroupCard` renderiza cada grupo (card con header)
- `WidgetRenderer` es el **Component Factory** que resuelve dinámicamente el componente Vue

---

### 9.2 Arquitectura del Admin UI de Configuración

```
┌─────────────────────────────────────────────────────────────────┐
│                    /dashboard-config (Index)                     │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Vehículos Configurados        Vehículos Sin Configurar   │  │
│  │  ┌─────────┐ ┌─────────┐      ┌─────────┐ ┌─────────┐    │  │
│  │  │ Raptor  │ │ F-150   │      │ Tacoma  │ │ Bronco  │    │  │
│  │  │ ✓Active │ │ ✓Active │      │ [Auto]  │ │ [Manual]│    │  │
│  │  │[Edit] [👁]│[Edit] [👁]│     │         │ │         │    │  │
│  │  └─────────┘ └─────────┘      └─────────┘ └─────────┘    │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                        Click "Edit"
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│              /dashboard-config/1/edit (Editor)                   │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  [← Volver]  📊 Configurar Dashboard - Ford Raptor          ││
│  │                                    [👁 Vista Previa] [💾]   ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  Configuración General                                       ││
│  │  Nombre: [ Baja Race Dashboard  ]  Tema: [Cyberpunk Dark ▾] ││
│  │                                  3 grupos  8 widgets        ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  ≡ Engine Performance              [12 cols ▾]  [🗑]  [▼]   ││
│  │  ├─ RadialGaugeD3 [lg ▾] RPM ×  [+ Vincular Sensor]         ││
│  │  ├─ LinearBarD3  [md ▾]  TPS ×  Speed ×                     ││
│  │  └─ [+ Agregar Widget]                                       ││
│  ├──────────────────────────────────────────────────────────────┤│
│  │  ≡ Temperatures                    [6 cols ▾]   [🗑]  [▼]   ││
│  │  └─ TextGridWidget [full ▾] Coolant × Oil × Trans ×         ││
│  └─────────────────────────────────────────────────────────────┘│
│           [+ Agregar Grupo]                                      │
└─────────────────────────────────────────────────────────────────┘
```

**Flujo del Editor:**
1. **Index** muestra todos los vehículos con su estado de configuración
2. Click "Edit" abre el **Editor visual**
3. El editor permite:
   - Cambiar nombre y tema del layout
   - Agregar/eliminar grupos
   - Agregar widgets desde el catálogo
   - Vincular sensores a cada widget
   - Guardar vía API PUT

---

### 9.3 Flujo de Datos de Sensores

```
┌──────────────────────────────────────────────────────────────────┐
│                         sensors (catálogo)                        │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│  │ RPM      │ │ Speed    │ │ Coolant  │ │ Oil_Temp │  ...       │
│  │ sensor_1 │ │ sensor_2 │ │ sensor_3 │ │ sensor_4 │            │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘            │
└───────│────────────│────────────│────────────│───────────────────┘
        │            │            │            │
        │       vehicle_sensors (relación)     │
        │     ┌──────┴──────┬──────┴──────┐    │
        │     │             │             │    │
┌───────▼─────▼─────────────▼─────────────▼────▼───────────────────┐
│                     vehicle_sensors                               │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐   │
│  │ vehicle_id: 1   │  │ vehicle_id: 1   │  │ vehicle_id: 1   │   │
│  │ sensor_id: 1    │  │ sensor_id: 2    │  │ sensor_id: 3    │   │
│  │ custom_label:   │  │ custom_label:   │  │ custom_label:   │   │
│  │ "Tacómetro"     │  │ "Velocidad"     │  │ "Temp Motor"    │   │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘   │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    Dashboard Config Editor
                     (lista de sensores)
```

**Descripción:**
- `sensors` es el catálogo maestro de todos los sensores posibles
- `vehicle_sensors` es la tabla pivot que vincula sensores a vehículos
- Cada vehículo solo ve SUS sensores en el editor de configuración

---

### 9.4 Flujo Completo: Configuración → Dashboard → Tiempo Real

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           FASE DE CONFIGURACIÓN                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Admin UI                    API                         Database       │
│  ┌──────────┐               ┌─────┐                    ┌──────────┐    │
│  │ Editor   │───PUT JSON───▶│ API │───────────────────▶│ Layouts  │    │
│  │ Vue.js   │               │     │                    │ Groups   │    │
│  └──────────┘               └─────┘                    │ Widgets  │    │
│                                                         │ Bindings │    │
│                                                         └──────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
                                       │
                                       │ GET config
                                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           FASE DE RENDERIZADO                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                      DynamicDashboard.vue                         │  │
│  │                                                                   │  │
│  │    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐     │  │
│  │    │  GroupCard   │    │  GroupCard   │    │  GroupCard   │     │  │
│  │    │  ┌────────┐  │    │  ┌────────┐  │    │  ┌────────┐  │     │  │
│  │    │  │ Widget │  │    │  │ Widget │  │    │  │ Widget │  │     │  │
│  │    │  │ RPM    │  │    │  │ Speed  │  │    │  │ Temps  │  │     │  │
│  │    │  └────────┘  │    │  └────────┘  │    │  └────────┘  │     │  │
│  │    └──────────────┘    └──────────────┘    └──────────────┘     │  │
│  │                                                                   │  │
│  └───────────────────────────────┬───────────────────────────────────┘  │
│                                  │                                      │
└──────────────────────────────────│──────────────────────────────────────┘
                                   │
                                   │ WebSocket subscription
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        FASE DE TIEMPO REAL                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   ESP32 Device ──MQTT──▶ Laravel Backend ──Reverb──▶ Vue Dashboard     │
│                                                                         │
│   ┌─────────────────────────────────────────────────────────────────┐  │
│   │  WebSocket Event: telemetry.updated                              │  │
│   │                                                                  │  │
│   │  {                                                               │  │
│   │    "vehicle_id": 1,                                              │  │
│   │    "data": {                                                     │  │
│   │      "RPM": 5500,        ────────▶  RadialGaugeD3 (value)       │  │
│   │      "Speed": 85,        ────────▶  RadialGaugeD3 (value)       │  │
│   │      "Coolant_Temp": 92  ────────▶  TextGrid (slot: coolant)    │  │
│   │    }                                                             │  │
│   │  }                                                               │  │
│   └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 9.5 WidgetRenderer: Resolución Dinámica de Componentes

```
┌───────────────────────────────────────────────────────────────────┐
│                      WidgetRenderer.vue                           │
│                    (Component Factory)                            │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│   Input: widget.component = "RadialGaugeD3"                       │
│                                                                   │
│   ┌─────────────────────────────────────────────────────────────┐ │
│   │               componentRegistry (Object)                     │ │
│   │                                                              │ │
│   │   'RadialGaugeD3' ─────▶ defineAsyncComponent(...)           │ │
│   │   'LinearBarD3' ───────▶ defineAsyncComponent(...)           │ │
│   │   'DigitalValueWidget' ▶ defineAsyncComponent(...)           │ │
│   │   'TextGridWidget' ────▶ defineAsyncComponent(...)           │ │
│   │   'TireGridWidget' ────▶ defineAsyncComponent(...)           │ │
│   │   'ShiftLightsBar' ────▶ defineAsyncComponent(...)           │ │
│   │                                                              │ │
│   └───────────────────────────┬─────────────────────────────────┘ │
│                               │                                   │
│                               ▼                                   │
│   ┌─────────────────────────────────────────────────────────────┐ │
│   │   resolvedComponent = componentRegistry['RadialGaugeD3']     │ │
│   └───────────────────────────┬─────────────────────────────────┘ │
│                               │                                   │
│                               ▼                                   │
│   ┌─────────────────────────────────────────────────────────────┐ │
│   │   <component                                                 │ │
│   │       :is="resolvedComponent"                                │ │
│   │       v-bind="mergedProps"                                   │ │
│   │   />                                                         │ │
│   └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│   Output: Componente Vue renderizado con props                    │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

**Cómo funciona:**
1. `widget.component` contiene el nombre del componente Vue
2. `componentRegistry` mapea nombres a componentes lazy-loaded
3. `<component :is="...">` renderiza dinámicamente el componente correcto
4. `mergedProps` combina props de DB + valores de telemetría en tiempo real

---

### 9.6 Flujo de Bindings: Sensor → Widget

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    sensor_widget_bindings                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   Base de Datos:                                                        │
│   ┌───────────────────────────────────────────────────────────────┐    │
│   │  widget_instance_id: 101                                       │    │
│   │  vehicle_sensor_id: 5                                          │    │
│   │  telemetry_key: "RPM"        ◀── Key en JSON de telemetría     │    │
│   │  target_prop: "value"        ◀── Prop del componente Vue       │    │
│   │  slot: null                  ◀── Para widgets multi-valor      │    │
│   │  transform: { round: 0 }     ◀── Transformación opcional       │    │
│   └───────────────────────────────────────────────────────────────┘    │
│                                                                         │
└──────────────────────────────────────┬──────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       useTelemetryBinding()                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   1. WebSocket recibe:                                                  │
│      { "data": { "RPM": 5500, "Speed": 85, ... } }                      │
│                                                                         │
│   2. Para cada binding:                                                 │
│      - Lee telemetryData["RPM"] = 5500                                  │
│      - Aplica transform: round(5500) = 5500                             │
│      - Asigna a boundValues.value = 5500                                │
│                                                                         │
│   3. Pasa al widget:                                                    │
│      <RadialGaugeD3 :value="5500" :min="0" :max="9000" />               │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 9.7 Widgets Multi-Slot (Tire Grid, Text Grid)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    TireGridWidget (4 slots)                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   Bindings en BD:                                                       │
│   ┌─────────────────────────────────────────────────────────────────┐  │
│   │  telemetry_key: "Tire_FL_Pressure", slot: "fl"                   │  │
│   │  telemetry_key: "Tire_FR_Pressure", slot: "fr"                   │  │
│   │  telemetry_key: "Tire_RL_Pressure", slot: "rl"                   │  │
│   │  telemetry_key: "Tire_RR_Pressure", slot: "rr"                   │  │
│   └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│   Renderizado:                                                          │
│   ┌───────────────────────────────────────────────────────┐            │
│   │                                                       │            │
│   │       🛞 FL: 32 PSI         🛞 FR: 31 PSI            │            │
│   │                                                       │            │
│   │              [  🚗 CAR DIAGRAM  ]                     │            │
│   │                                                       │            │
│   │       🛞 RL: 30 PSI         🛞 RR: 31 PSI            │            │
│   │                                                       │            │
│   └───────────────────────────────────────────────────────┘            │
│                                                                         │
│   Props recibidos:                                                      │
│   {                                                                     │
│     slots: {                                                            │
│       fl: { value: 32, label: "FL", unit: "PSI" },                      │
│       fr: { value: 31, label: "FR", unit: "PSI" },                      │
│       rl: { value: 30, label: "RL", unit: "PSI" },                      │
│       rr: { value: 31, label: "RR", unit: "PSI" }                       │
│     }                                                                   │
│   }                                                                     │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 9.8 Jerarquía de Componentes Completa

```
App.vue
└── AppLayout.vue
    └── DashboardDynamic.vue (Page - Inertia)
        └── DynamicDashboard.vue (Orchestrator)
            ├── ShiftLightsBar.vue (Special Component)
            │   └── props: { rpm, config }
            │
            ├── MapWidget.vue (Special Component)
            │   └── props: { latitude, longitude, heading }
            │
            └── GroupCard.vue (× N grupos)
                ├── Header (name, icon, collapse toggle)
                │
                └── WidgetRenderer.vue (× N widgets por grupo)
                    │
                    ├── RadialGaugeD3.vue
                    │   └── props: { value, min, max, label, thresholds }
                    │
                    ├── LinearBarD3.vue
                    │   └── props: { value, min, max, label, colorScheme }
                    │
                    ├── DigitalValueWidget.vue
                    │   └── props: { value, label, fontSize, unit }
                    │
                    ├── TextGridWidget.vue
                    │   └── props: { items[], slots{} }
                    │
                    └── TireGridWidget.vue
                        └── props: { slots{ fl, fr, rl, rr }, unit }
```

---

### 9.9 Tabla de URLs y Rutas

| URL | Nombre de Ruta | Controlador/Vista | Descripción |
|-----|----------------|-------------------|-------------|
| `/dashboard-config` | `dashboard.config.index` | `DashboardConfigController@index` | Lista de vehículos |
| `/dashboard-config/{id}/edit` | `dashboard.config.edit` | `DashboardConfigController@edit` | Editor de dashboard |
| `/dashboard-dynamic/{id?}` | `dashboard.dynamic` | Inline (Inertia) | Dashboard en vivo |
| `/api/vehicles/{id}/dashboard` | - | `DashboardLayoutController@show` | GET config JSON |
| `/api/vehicles/{id}/dashboard` | - | `DashboardLayoutController@update` | PUT guardar config |
| `/api/vehicles/{id}/dashboard/generate` | - | `DashboardLayoutController@generate` | POST auto-generar |
| `/api/dashboard/widgets` | - | `DashboardLayoutController@getWidgetDefinitions` | GET catálogo widgets |

---

**Documento actualizado:** 29 de Diciembre, 2025  
**Versión:** 1.1 (con diagramas visuales)  
**Próximos pasos:** Fase 6 - Testing & QA
