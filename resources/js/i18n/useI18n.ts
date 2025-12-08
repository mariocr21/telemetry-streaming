import { computed, onMounted, ref } from 'vue';
import { en } from './en';
import { es } from './es';

// Define el tipo de clave basado en 'es' (asumiendo que tiene todas las claves)
export type I18nKey = keyof typeof es;

const messages = { es, en };
export type Locale = 'es' | 'en';

// Define el estado reactivo del idioma
const locale = ref<Locale>('es');

// Función de traducción
const t = (key: I18nKey) => {
    return messages[locale.value][key] || key;
};

// Hook/Función de composición para la internacionalización
export function useI18n() {
    onMounted(() => {
        // 1. Prioridad: LocalStorage (Persistencia)
        const savedLocale = localStorage.getItem('user-locale');
        if (savedLocale && (savedLocale === 'es' || savedLocale === 'en')) {
            locale.value = savedLocale as Locale;
        } else {
            // 2. Fallback: Idioma del Navegador
            const browserLang = navigator.language.substring(0, 2);
            if (browserLang === 'en' || browserLang === 'es') {
                locale.value = browserLang as Locale;
                // Si deseas que el idioma del navegador sea persistente inmediatamente, descomenta:
                localStorage.setItem('user-locale', browserLang);
            }
        }
    });

    return {
        t,
        locale: computed(() => locale.value), // Expone el idioma actual (read-only)
        // Puedes añadir aquí una función setLocale si deseas permitir al usuario cambiar el idioma
    };
}
