<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Moon, Sun } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Dark mode state
const isDark = ref(false);

// Check for saved theme preference or default to 'light'
onMounted(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        isDark.value = savedTheme === 'dark';
    } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    updateTheme();
});

const toggleTheme = () => {
    isDark.value = !isDark.value;
    updateTheme();
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

const updateTheme = () => {
    if (isDark.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login - N Racing">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    </Head>

    <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] flex flex-col lg:flex-row transition-colors duration-300">
        
        <!-- Left Side - Branding & Info -->
        <div class="lg:flex-1 bg-gradient-to-br from-[#00bcd4] to-[#0097a7] relative overflow-hidden flex items-center justify-center p-8 lg:p-16">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-20 left-10 w-32 h-32 border-2 border-white rounded-full"></div>
                <div class="absolute bottom-32 right-16 w-24 h-24 border-2 border-white rounded-full"></div>
                <div class="absolute top-1/2 left-1/4 w-16 h-16 border-2 border-white rounded-full"></div>
                <div class="absolute top-1/3 right-1/3 w-20 h-20 border-2 border-white rounded-full"></div>
            </div>

            <!-- Content -->
            <div class="relative z-10 text-center text-white max-w-md">
                <!-- Logo -->
                <div class="flex items-center justify-center gap-3 mb-8">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center text-white font-bold text-2xl">
                        <AppLogoIcon class="size-16" />
                    </div>
                    <div class="text-left">
                        <div class="text-3xl font-bold leading-none">Neurona</div>
                        <div class="text-sm opacity-90 font-medium tracking-[2px] uppercase">
                            Off Road Racing
                        </div>
                    </div>
                </div>

                <h1 class="text-3xl lg:text-4xl font-bold mb-4">
                    Monitoreo en Tiempo Real
                </h1>
                <p class="text-lg opacity-90 mb-8 leading-relaxed">
                    Accede al sistema de telemetría más avanzado para carreras off-road. 
                    Baja 1000, rally y competencias extremas.
                </p>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-6 text-center">
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="text-2xl font-bold">500+</div>
                        <div class="text-sm opacity-80">Carreras</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="text-2xl font-bold">24/7</div>
                        <div class="text-sm opacity-80">Tracking</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="text-2xl font-bold">99.9%</div>
                        <div class="text-sm opacity-80">Uptime</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="lg:flex-1 flex items-center justify-center p-8 lg:p-16 relative">
            
            <!-- Theme Toggle -->
            <button
                @click="toggleTheme"
                class="absolute top-6 right-6 w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 flex items-center justify-center transition-all duration-300 group"
            >
                <Sun v-if="isDark" class="h-5 w-5 text-gray-600 dark:text-gray-300 group-hover:scale-110 transition-transform" />
                <Moon v-else class="h-5 w-5 text-gray-600 dark:text-gray-300 group-hover:scale-110 transition-transform" />
            </button>

            <!-- Back to Home -->
            <Link 
                href="/"
                class="absolute top-6 left-6 text-sm text-gray-600 dark:text-gray-400 hover:text-[#00bcd4] dark:hover:text-[#00bcd4] transition-colors duration-300 flex items-center gap-2"
            >
                ← Volver al inicio
            </Link>

            <div class="w-full max-w-md">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-[#1b1b18] dark:text-white mb-2">
                        Iniciar Sesión
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Accede a tu panel de control de telemetría
                    </p>
                </div>

                <!-- Status Message -->
                <div v-if="status" class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </div>

                <!-- Login Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <!-- Email Field -->
                    <div class="space-y-2">
                        <Label for="email" class="text-[#1b1b18] dark:text-[#EDEDEC] font-medium">
                            Correo Electrónico
                        </Label>
                        <div class="relative">
                            <Input
                                id="email"
                                type="email"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="email"
                                v-model="form.email"
                                placeholder="tu@email.com"
                                class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-[#00bcd4] focus:border-transparent transition-all duration-300 text-[#1b1b18] dark:text-[#EDEDEC] placeholder-gray-500 dark:placeholder-gray-400"
                            />
                        </div>
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="password" class="text-[#1b1b18] dark:text-[#EDEDEC] font-medium">
                                Contraseña
                            </Label>
                            <TextLink 
                                v-if="canResetPassword" 
                                :href="route('password.request')" 
                                class="text-sm text-[#00bcd4] hover:text-[#0097a7] transition-colors duration-300" 
                                :tabindex="5"
                            >
                                ¿Olvidaste tu contraseña?
                            </TextLink>
                        </div>
                        <div class="relative">
                            <Input
                                id="password"
                                type="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                v-model="form.password"
                                placeholder="••••••••"
                                class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-[#00bcd4] focus:border-transparent transition-all duration-300 text-[#1b1b18] dark:text-[#EDEDEC] placeholder-gray-500 dark:placeholder-gray-400"
                            />
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <Label for="remember" class="flex items-center space-x-3 cursor-pointer">
                            <Checkbox 
                                id="remember" 
                                v-model="form.remember" 
                                :tabindex="3"
                                class="border-gray-300 dark:border-gray-600 text-[#00bcd4] focus:ring-[#00bcd4]"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Recordarme</span>
                        </Label>
                    </div>

                    <!-- Submit Button -->
                    <Button 
                        type="submit" 
                        class="w-full bg-gradient-to-r from-[#00bcd4] to-[#0097a7] hover:from-[#0097a7] hover:to-[#00838f] text-white py-3 px-6 rounded-lg font-semibold transition-all duration-300 hover:-translate-y-0.5 shadow-lg shadow-[rgba(0,188,212,0.25)] hover:shadow-xl hover:shadow-[rgba(0,188,212,0.35)] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none" 
                        :tabindex="4" 
                        :disabled="form.processing"
                    >
                        <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin mr-2" />
                        <span v-if="!form.processing">Iniciar Sesión</span>
                        <span v-else>Iniciando sesión...</span>
                    </Button>

                    <!-- Register Link (commented as in original) -->
                    <!-- <div class="text-center pt-6 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            ¿No tienes una cuenta?
                            <TextLink 
                                :href="route('register')" 
                                :tabindex="6"
                                class="text-[#00bcd4] hover:text-[#0097a7] font-medium transition-colors duration-300"
                            >
                                Regístrate aquí
                            </TextLink>
                        </p>
                    </div> -->
                </form>

                <!-- Footer Info -->
                <div class="mt-8 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Al iniciar sesión, aceptas nuestros términos y condiciones
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom animations */
@keyframes float {
    0%, 100% { 
        transform: translateY(0px) rotate(0deg); 
    }
    33% { 
        transform: translateY(-10px) rotate(3deg); 
    }
    66% { 
        transform: translateY(5px) rotate(-3deg); 
    }
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .lg\:flex-row {
        flex-direction: column;
    }
    
    .lg\:flex-1:first-child {
        min-height: 40vh;
    }
}

/* Dark mode transitions */
* {
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

/* Input focus styles */
.focus\:ring-2:focus {
    box-shadow: 0 0 0 2px rgba(0, 188, 212, 0.2);
}

/* Button hover animations */
.hover\:-translate-y-0\.5:hover {
    transform: translateY(-2px);
}
</style>