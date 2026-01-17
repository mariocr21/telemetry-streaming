# 🏎️ Neurona Off Road Telemetry - VMC Frontend

Este repositorio contiene la plataforma web de visualización y gestión de telemetría para **Neurona**, enfocada en vehículos de competición Off-Road (Baja, Dakar, etc.). El sistema permite el monitoreo en tiempo real de sensores, GPS y estado general de la flota.

## 📋 Descripción del Proyecto

El proyecto **VMC (Vehicle Mission Control)** es una aplicación monolítica moderna construida sobre **Laravel 12** y **Vue 3** (via Inertia.js). Su objetivo principal es recibir flujos de datos de telemetría (vía MQTT/WebSockets), procesarlos y mostrarlos en interfaces optimizadas tanto para ingenieros de pista (escritorio) como para pilotos/copilotos (tablets rugerizadas).

### Características Clave
- **Dashboard Pro:** Interface de visualización de alta frecuencia visual (60fps) usando D3.js y WebSockets.
- **Mapeo GPS en Vivo:** Integración con Leaflet para seguimiento de posición en tiempo real.
- **Gestión Dinámica de Sensores:** Capacidad de mapear IDs de hardware (Cloud IDs) a sensores lógicos del sistema.
- **Sistema de Alertas:** Monitoreo visual de estados Normal, Warning y Critical para variables vitales (RPM, Temp, Presión).

---

## 🚀 Stack Tecnológico

La aplicación utiliza un stack robusto y moderno para garantizar rendimiento y mantenibilidad:

### Backend (API & Lógica)
- **Framework:** Laravel 12.x
- **Real-time:** Laravel Reverb (WebSockets nativos)
- **Ingesta de Datos:** `php-mqtt/client`
- **Base de Datos:** MySQL / MariaDB

### Frontend (User Interface)
- **Core:** Vue 3.5 (Composition API, Script Setup)
- **Routing/Glue:** Inertia.js 2.0
- **Estilos:** Tailwind CSS 4.1 (Motor Oxide)
- **Visualización:** D3.js v7 (Gauges vectoriales personalizados)
- **Mapas:** Leaflet
- **Iconografía:** Lucide Vue

---

## 🛠️ Guía de Inicio Rápido

Sigue estos pasos para levantar el entorno de desarrollo local:

### 1. Requisitos Previos
Asegúrate de tener instalado:
- PHP >= 8.2
- Composer
- Node.js (LTS) & NPM
- Servidor MySQL o MariaDB

### 2. Instalación

```bash
# 1. Clonar el repositorio
git clone <url-del-repo>
cd vmc

# 2. Instalar dependencias de Backend
composer install

# 3. Instalar dependencias de Frontend
npm install

# 4. Configurar variables de entorno
cp .env.example .env
# (EDITA el archivo .env con tus credenciales de base de datos y configuración de Reverb/MQTT)

# 5. Generar clave de aplicación
php artisan key:generate

# 6. Ejecutar migraciones de base de datos
php artisan migrate

# 7. (Opcional) Poblar base de datos con datos de prueba
php artisan db:seed
```

### 3. Ejecución en Desarrollo

Para trabajar, necesitas correr los procesos simultáneamente (puedes usar terminales separadas o el script `dev` que usa `concurrently`):

```bash
# Inicia Servidor Laravel, Vite, Cola y Reverb (si está configurado)
npm run dev
```

El sitio estará disponible típicamente en `http://localhost:8000`.

---

## 📂 Estructura del Proyecto

- **`app/Events/`**: Eventos de transmisión de telemetría (`VehicleTelemetryEvent`).
- **`app/Http/Controllers/`**: Lógica de negocio y gestión de vehículos.
- **`resources/js/Pages/`**: Vistas principales de Inertia (Vistas de página completa).
- **`resources/js/components/Dashboard/`**: Componentes visuales críticos (Gauges D3, Widgets).
- **`routes/web.php`**: Definición de rutas web y endpoints de Inertia.
- **`BITACORA.md`**: Registro histórico de cambios y decisiones de arquitectura.

---

## 📡 WebSockets y Telemetría

El dashboard "Live" depende de **Laravel Reverb**. Asegúrate de que el servidor de sockets esté corriendo y configurado correctamente en `.env`:

```env
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME="http"
```

El evento principal de escucha en el frontend es `VehicleTelemetryEvent`.

---

## 🤝 Contribución

1. Rigen las normas definidas en `BITACORA.md`.
2. Todo nuevo feature visual debe ser responsive y soportar **Dark Mode**.
3. Mantener el tipado estricto en los componentes Vue (TypeScript/JSDoc donde aplique).

---

## 📂 Firmware & Hardware

Este repositorio incluye también el código fuente para el hardware de telemetría en el directorio `firmware_unificado/`.

- **PlatformIO:** El proyecto de firmware está configurado para PlatformIO.
- **Estructura:**
  - `firmware_main/`: Código principal del MCU (ESP32/ESP32-S3).
  - `configurator/`: Herramientas de configuración web para el dispositivo físico.
- **Documentación Específica:** Consulta `firmware_unificado/README.md` para detalles de flasheo y compilación del hardware.

---

## ⚠️ Notas para Desarrolladores

### Código Deprecado
Se ha movido código antiguo a la carpeta `deprecated/` (ej. `DashboardOld.vue`). No edites estos archivos; existen solo como referencia histórica. El desarrollo activo debe centrarse en los componentes bajo `resources/js/components/Dashboard/`.

### Convenciones de Código
- **Commits:** Usar prefijos [FEAT], [FIX], [DOCS], [STYLE].
- **Bitácora:** Es **obligatorio** registrar cambios arquitectónicos o de funcionalidad mayor en `BITACORA.md`.
- **Estilos:** No usar CSS puro fuera de Tailwind a menos que sea estrictamente necesario (ej. animaciones complejas en `dashboard-pro.css`).

---

**Neurona Off Road Telemetry** © 2026
