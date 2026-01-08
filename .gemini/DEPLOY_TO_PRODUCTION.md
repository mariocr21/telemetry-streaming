
# 🚀 Guía de Despliegue a Producción (MQTT + Websockets)

Esta guía detalla los pasos necesarios para activar la funcionalidad completa de Telemetría en Tiempo Real y Mapa GPS en el servidor de producción.

## 1. Habilitar Laravel Reverb (Websockets)

En desarrollo local (Windows) hemos mantenido `laravel/reverb` desactivado para evitar conflictos. En el servidor (Linux), debe activarse.

1.  **Editar `composer.json`:**
    Busca la sección `extra.laravel.dont-discover` y elimina `"laravel/reverb"`.
    ```json
    "extra": {
        "laravel": {
            "dont-discover": []  <-- Dejar vacío o eliminar la línea de reverb
        }
    }
    ```

2.  **Regenerar Autoloader:**
    Ejecuta en la terminal del servidor:
    ```bash
    composer dump-autoload
    php artisan package:discover
    ```

## 2. Configuración de Entorno (.env)

Asegúrate de que las variables de entorno en el servidor `.env` estén configuradas para usar Reverb y Redis (recomendado) o Array cache.

```ini
# Configuración Broadcast
BROADCAST_CONNECTION=reverb

# Configuración Reverb (Websockets)
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST="0.0.0.0"
REVERB_PORT=8080
REVERB_SCHEME=http

# Configuración Vite (Frontend)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## 3. Comandos de Arranque (Daemons)

Para que el sistema reciba datos y los envíe al dashboard, necesitas mantener corriendo dos procesos en segundo plano (usando Supervisor):

### A. Servidor de Websockets
```bash
php artisan reverb:start
```

### B. Listener MQTT (El puente con el ESP32)
Este comando se suscribe al broker MQTT y transforma los mensajes en eventos de Laravel.
```bash
php artisan mqtt:listen
```
*(Nota: Verifica el nombre exacto del comando en `app/Console/Commands/ListenTelemetryMqtt.php`. Si no existe el comando artisan, asegúrate de que el script esté registrado).*

### C. Worker de Colas (Opcional pero recomendado)
```bash
php artisan queue:work
```

## 4. Verificación

1.  Abre el Dashboard en el navegador.
2.  Abre las herramientas de desarrollador (F12) -> Red -> WS (Websockets).
3.  Deberías ver una conexión exitosa a `ws://tu-servidor:8080`.
4.  Al enviar un dato MQTT desde el ESP32, deberías ver el evento llegar por websocket instantáneamente.

---
**Nota sobre Mapas:** La configuración de capas (Oscuro/Claro/Satélite) ya está lista y persistente en base de datos, no requiere configuración extra en el servidor.
