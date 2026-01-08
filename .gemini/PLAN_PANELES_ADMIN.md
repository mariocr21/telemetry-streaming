# 📋 PLAN: Paneles de Super Admin y Cliente [OBSOLETO]

> **Nota:** Este documento está obsoleto. Refiérase a `GUIA_FLUJO_ASIGNACION.md` para la información más reciente sobre la gestión de clientes y vehículos.


> **Proyecto:** Neurona Off Road Telemetry  
> **Módulo:** Paneles Administrativos  
> **Inicio:** 04 de Enero, 2026  
> **Estado Actual:** � En Desarrollo

---

## 📊 Estado Real del Proyecto (Tras Análisis)

### ✅ Ya Implementado (NO rehacer)

| Módulo | Estado | Notas |
|--------|--------|-------|
| **Gestión de Clientes** | ✅ 100% | CRUD completo, permisos por rol |
| **Gestión de Dispositivos** | ✅ 100% | CRUD, activar/desactivar, por cliente |
| **Inventario de Dispositivos** | ✅ 100% | Solo Super Admin |
| **Gestión de Vehículos** | ✅ 100% | CRUD anidado en dispositivos |
| **Dashboard de Telemetría** | ✅ 100% | Tiempo real con WebSocket |
| **Dashboard Dinámico** | ✅ 100% | Configurable por BD |
| **Sistema de Roles** | ✅ 100% | SA, CA, CU implementados |
| **Selector de Dispositivos** | ✅ 100% | Modal funcionando |

### ⚠️ Por Implementar (Esta Sesión)

| Módulo | Estado | Descripción |
|--------|--------|-------------|
| **Gestión de Sensores** | 🔴 0% | Controller vacío, sin UI, sin rutas |
| **Navegación Admin** | 🟡 50% | Falta menú lateral claro |

---

## 🎯 Objetivo de Esta Sesión

1. **Implementar CRUD completo de Sensores**
   - Backend: SensorController con toda la lógica
   - Frontend: Páginas Index, Create, Edit, Show
   - Rutas: Agregar a web.php

2. **Mejorar Navegación del Panel Admin**
   - Agregar links claros en la barra lateral
   - Acceso directo a: Clientes, Dispositivos, Inventario, Sensores

---

## 📁 Estructura de Archivos a Crear/Modificar

### Archivos a CREAR

```
📂 resources/js/Pages/Admin/
└── Sensors/
    ├── Index.vue          # Lista de sensores con filtros
    ├── Create.vue         # Formulario crear sensor
    ├── Edit.vue           # Formulario editar sensor
    └── Show.vue           # Detalle de sensor
```

### Archivos a MODIFICAR

```
📂 app/Http/Controllers/
└── SensorController.php   # Implementar métodos vacíos

📂 routes/
└── web.php                # Agregar rutas /admin/sensors

📂 resources/js/layouts/
└── AppLayout.vue          # Agregar navegación a sensores
```

---

## 🔧 Implementación

### Fase 1: Backend (SensorController)
- index() - Listar con filtros y paginación
- create() - Formulario de creación
- store() - Guardar nuevo sensor
- show() - Ver detalle
- edit() - Formulario de edición
- update() - Actualizar sensor
- destroy() - Eliminar sensor

### Fase 2: Rutas
```php
Route::resource('admin/sensors', SensorController::class)
    ->middleware(['auth', 'verified']);
```

### Fase 3: Frontend
- Index con tabla, búsqueda, filtros por categoría
- Create/Edit con formulario completo
- Show con detalle y vehículos relacionados

### Fase 4: Navegación
- Agregar en sidebar del AppLayout

---

*Documento actualizado: 04 de Enero, 2026*
