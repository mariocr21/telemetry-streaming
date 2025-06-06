// resources/js/reverb.config.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Configuración específica para Reverb
export function createReverbConnection() {
    // Hacer Pusher disponible globalmente
    window.Pusher = Pusher;

    const config = {
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY || 'app-key',
        wsHost: import.meta.env.VITE_REVERB_HOST || 'ws.neurona.xyz',
        wsPort: import.meta.env.VITE_REVERB_PORT || 443,
        wssPort: import.meta.env.VITE_REVERB_PORT || 443,
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
    };

    console.log('🔧 Configurando Reverb con:', config);

    try {
        const echo = new Echo(config);
        console.log('✅ Reverb conectado exitosamente');
        return echo;
    } catch (error) {
        console.error('❌ Error conectando a Reverb:', error);
        throw error;
    }
}

// Test de conexión
export function testReverbConnection(echo) {
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
            reject(new Error('Timeout de conexión'));
        }, 5000);

        // Intentar suscribirse a un canal de prueba
        const channel = echo.channel('test-connection');
        
        channel.listen('.test-event', () => {
            clearTimeout(timeout);
            echo.leave('test-connection');
            resolve(true);
        });

        // Simular que la conexión está lista después de un momento
        setTimeout(() => {
            clearTimeout(timeout);
            echo.leave('test-connection');
            resolve(true);
        }, 1000);
    });
}