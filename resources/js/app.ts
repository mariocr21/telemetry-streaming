import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

// Importar configuración de Reverb
import { createReverbConnection, testReverbConnection } from './reverb.config.js';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_REVERB_APP_KEY: string;
        readonly VITE_REVERB_HOST?: string;
        readonly VITE_REVERB_PORT?: string;
        readonly VITE_REVERB_SCHEME?: string;
        readonly VITE_APP_ENV: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

// Extend Window interface for Echo
declare global {
    interface Window {
        Pusher: any;
        Echo: any;
        REVERB_APP_KEY?: string;
        REVERB_HOST?: string;
        APP_DEBUG?: boolean;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Configurar Echo para Laravel Reverb
async function setupEcho() {
    const isLocal = import.meta.env.VITE_APP_ENV === 'local';
    
    try {
        window.Echo = createReverbConnection();
        
        // Hacer variables disponibles globalmente
        window.REVERB_APP_KEY = import.meta.env.VITE_REVERB_APP_KEY;
        window.REVERB_HOST = import.meta.env.VITE_REVERB_HOST;
        window.APP_DEBUG = isLocal;

        console.log('✅ Echo con Reverb configurado exitosamente', {
            mode: isLocal ? 'development' : 'production',
            key: window.REVERB_APP_KEY,
            host: window.REVERB_HOST,
            debug: window.APP_DEBUG
        });

        // Test de conexión si estamos en desarrollo
        if (isLocal) {
            try {
                await testReverbConnection(window.Echo);
                console.log('🟢 Test de conexión Reverb: OK');
            } catch (error) {
                console.warn('⚠️ Test de conexión Reverb falló:', error);
            }
        }

    } catch (error) {
        console.error('❌ Error configurando Echo:', error);
        
        // Fallback: crear objeto Echo vacío para evitar errores
        window.Echo = {
            channel: () => ({
                listen: () => {},
                stopListening: () => {}
            }),
            private: () => ({
                listen: () => {},
                stopListening: () => {}
            }),
            leave: () => {},
            disconnect: () => {},
        };
        
        window.APP_DEBUG = isLocal;
    }
}

// Configurar axios (si lo usas)
function setupAxios() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        // Si usas axios, configurar headers globales
        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
            window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        }
    }
}

// Función para limpiar Echo al cerrar la app
function cleanupEcho() {
    if (window.Echo) {
        try {
            window.Echo.disconnect();
            console.log('🔧 Echo desconectado por cleanup');
        } catch (error) {
            console.warn('⚠️ Error en cleanup de Echo:', error);
        }
    }
}

// Configurar Echo al cargar la aplicación
await setupEcho();
setupAxios();

// Cleanup al cerrar/recargar la página
window.addEventListener('beforeunload', cleanupEcho);

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // Hacer Echo disponible en toda la aplicación Vue
        app.config.globalProperties.$echo = window.Echo;
        
        // Provide Echo para composables
        app.provide('echo', window.Echo);

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Exportar para uso en otros módulos si es necesario
export { setupEcho, cleanupEcho };