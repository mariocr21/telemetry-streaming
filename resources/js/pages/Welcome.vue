<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

// Props
defineProps<{
    auth?: {
        user?: any;
    };
}>();

const isLoaded = ref(false);

// Features based on Bitacora
const features = ref([
    {
        icon: '🏎️',
        title: 'Live Telemetry Dashboard',
        description: 'Visualización en tiempo real optimizada para tablets rugerizadas. Gauges D3.js de alta precisión y modo oscuro para visibilidad extrema.',
    },
    {
        icon: '🗺️',
        title: 'GPS & Live Mapping',
        description: 'Rastreo satelital preciso integrado con Leaflet. Monitoreo de trayectoria, velocidad y posición en terrenos off-road.',
    },
    {
        icon: '🔧',
        title: 'Custom Sensor Mapping',
        description: 'Vinculación dinámica de sensores (OBD2, CAN, Analógicos). Normalización de IDs y configuración avanzada por vehículo.',
    },
    {
        icon: '📼',
        title: 'Replay System & Analytics',
        description: 'Grabación y reproducción de sesiones de carrera. Analiza RPM, temperaturas, fuerzas G y más para mejorar el rendimiento.',
    },
]);

// Simulated Live Data for the Mockup
const liveData = ref({
    rpm: 6850,
    speed: 112,
    temp: 195,
    gear: 4,
});

// Animate values periodically
onMounted(() => {
    setTimeout(() => {
        isLoaded.value = true;
    }, 100);

    setInterval(() => {
        liveData.value.rpm = 6500 + Math.floor(Math.random() * 800);
        liveData.value.speed = 110 + Math.floor(Math.random() * 15);
        liveData.value.temp = 190 + Math.floor(Math.random() * 10);
    }, 1000);
});
</script>

<template>
    <Head title="Neurona Off Road Telemetry">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet" />
    </Head>

    <div class="flex min-h-screen flex-col bg-[#050505] text-gray-300 font-sans overflow-x-hidden selection:bg-[#00e1ff] selection:text-black">
        
        <!-- Header -->
        <header class="fixed top-0 w-full z-50 border-b border-[#ffffff10] bg-[#050505]/90 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center">
                         <!-- Using the provided logo style: Text-based with specific colors from the image -->
                        <div class="text-3xl font-bold tracking-tight flex items-center gap-1 select-none">
                             <!-- App Logo or Icon representation could go here, but focusing on the text logo -->
                             <span class="text-[#00e1ff]">NEUR</span>
                             <!-- Brain/Chip Icon Placeholder if needed, for now just text or the AppLogoIcon -->
                             <AppLogoIcon class="w-8 h-8 text-[#00e1ff]" />
                             <span class="text-[#00e1ff]">NA</span>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="flex items-center gap-4">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="hidden sm:block text-sm font-medium text-gray-400 hover:text-[#00e1ff] transition-colors"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-if="!$page.props.auth?.user"
                        :href="route('login')"
                         class="group relative inline-flex items-center justify-center px-6 py-2 overflow-hidden font-bold text-[#050505] transition-all duration-300 bg-[#00e1ff] rounded-lg hover:bg-[#33e7ff] focus:outline-none focus:ring-2 focus:ring-[#00e1ff] focus:ring-offset-2 focus:ring-offset-gray-900 shadow-[0_0_15px_rgba(0,225,255,0.3)]"
                    >
                        <span class="relative">INICIAR SESIÓN</span>
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="flex-1 flex items-center justify-center pt-20 relative">
            <!-- Background Elements -->
             <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-0 left-1/4 w-[500px] h-[500px] bg-[#00e1ff] opacity-[0.03] rounded-full blur-[100px]"></div>
                <div class="absolute bottom-0 right-1/4 w-[600px] h-[600px] bg-[#00f8ff] opacity-[0.02] rounded-full blur-[100px]"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-transparent via-[#050505]/50 to-[#050505]"></div>
            </div>

            <div class="w-full max-w-7xl px-6 grid lg:grid-cols-2 gap-12 items-center relative z-10 py-12 lg:py-20">
                
                <!-- Text Content -->
                <div 
                    class="space-y-8 transition-all duration-1000 transform"
                    :class="{ 'translate-y-0 opacity-100': isLoaded, 'translate-y-10 opacity-0': !isLoaded }"
                >
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#ffffff05] border border-[#ffffff10]">
                        <span class="w-2 h-2 rounded-full bg-[#00e1ff] animate-pulse"></span>
                        <span class="text-xs font-mono text-[#00e1ff]">SYSTEM ONLINE v2.0</span>
                    </div>

                    <h1 class="text-5xl lg:text-7xl font-bold text-white leading-tight">
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-500">PRECISIÓN</span> EN <br/>
                        <span class="text-[#00e1ff] text-glow">TIEMPO REAL</span>
                    </h1>
                    
                    <p class="text-lg text-gray-400 max-w-xl leading-relaxed">
                        Plataforma avanzada de telemetría para competición Off-Road. 
                        Monitoreo crítico de motor, suspensión y GPS diseñado para la rudeza de la Baja 1000.
                    </p>

                    <div class="flex flex-wrap gap-4">
                        <Link
                            :href="$page.props.auth?.user ? route('dashboard') : route('login')"
                            class="px-8 py-4 bg-[#00e1ff] text-[#050505] font-bold rounded hover:bg-[#33e7ff] transition-all transform hover:-translate-y-1 shadow-[0_10px_20px_-10px_rgba(0,225,255,0.4)] clip-corner"
                        >
                            ACCEDER AL SISTEMA
                        </Link>
                         <button class="px-8 py-4 bg-transparent border border-[#ffffff20] text-white font-bold rounded hover:bg-[#ffffff05] transition-all clip-corner group">
                            <span class="group-hover:text-[#00e1ff] transition-colors">VER DEMO</span>
                        </button>
                    </div>

                    <!-- Features Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-12">
                        <div v-for="(feature, idx) in features" :key="idx" class="p-4 rounded-xl bg-[#0a0c10] border border-[#ffffff08] hover:border-[#00e1ff]/30 transition-colors group">
                            <div class="text-3xl mb-3 grayscale group-hover:grayscale-0 transition-all duration-300 drop-shadow-[0_0_10px_rgba(0,225,255,0.2)]">{{ feature.icon }}</div>
                            <h3 class="text-white font-bold mb-1">{{ feature.title }}</h3>
                            <p class="text-sm text-gray-500 leading-snug">{{ feature.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Visual Mockup (Simulated Dashboard) -->
                <div 
                    class="relative lg:h-[600px] flex items-center justify-center transition-all duration-1000 delay-300 transform"
                    :class="{ 'translate-x-0 opacity-100': isLoaded, 'translate-x-10 opacity-0': !isLoaded }"
                >
                    <!-- Tablet Frame -->
                    <div class="relative w-full aspect-video max-w-2xl bg-[#0a0c10] rounded-2xl border-4 border-[#1a1c20] shadow-2xl overflow-hidden ring-1 ring-white/10 group">
                        
                        <!-- Screen Content -->
                        <div class="absolute inset-0 bg-[#050505] p-6 flex flex-col justify-between">
                             <!-- Top Bar -->
                            <div class="flex justify-between items-center border-b border-white/10 pb-4">
                                <div class="font-mono text-[#00e1ff] text-lg">LIVE FEED • <span class="text-white">VEHICLE-01</span></div>
                                <div class="flex gap-4 text-xs font-mono text-gray-400">
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-[#00e1ff]"></span> GPS FIX</span>
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-[#00e1ff]"></span> TELEMETRY OK</span>
                                </div>
                            </div>

                            <!-- Gauges Mockup -->
                            <div class="flex-1 grid grid-cols-2 gap-6 mt-6 items-center">
                                <!-- RPM Box -->
                                <div class="relative flex flex-col items-center justify-center py-8 rounded-xl bg-[#0f1115] border border-white/5 shadow-[0_0_30px_rgba(0,225,255,0.05)]">
                                    <span class="text-gray-500 text-xs font-mono tracking-widest uppercase mb-2">Engine RPM</span>
                                    <div class="text-5xl font-mono font-bold text-white tabular-nums tracking-tighter">
                                        {{ liveData.rpm }}
                                    </div>
                                    <div class="w-3/4 h-2 bg-gray-800 rounded-full mt-4 overflow-hidden">
                                        <div 
                                            class="h-full bg-gradient-to-r from-[#00e1ff] via-[#00f0ff] to-[#ffffff]" 
                                            :style="{ width: `${(liveData.rpm / 9000) * 100}%` }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Speed Box -->
                                <div class="relative flex flex-col items-center justify-center py-8 rounded-xl bg-[#0f1115] border border-white/5">
                                    <span class="text-gray-500 text-xs font-mono tracking-widest uppercase mb-2">Speed (KM/H)</span>
                                    <div class="text-5xl font-mono font-bold text-[#00e1ff] tabular-nums tracking-tighter shadow-cyan">
                                        {{ liveData.speed }}
                                    </div>
                                </div>
                                
                                <!-- Temps Row -->
                                <div class="col-span-2 grid grid-cols-3 gap-4">
                                    <div class="bg-[#0f1115] rounded border border-white/5 p-3 text-center">
                                        <div class="text-[10px] text-gray-500 uppercase">Coolant</div>
                                        <div class="text-xl font-bold text-white font-mono">{{ liveData.temp }}°F</div>
                                    </div>
                                     <div class="bg-[#0f1115] rounded border border-white/5 p-3 text-center">
                                        <div class="text-[10px] text-gray-500 uppercase">Oil</div>
                                        <div class="text-xl font-bold text-white font-mono">210°F</div>
                                    </div>
                                     <div class="bg-[#0f1115] rounded border border-white/5 p-3 text-center">
                                        <div class="text-[10px] text-gray-500 uppercase">Voltage</div>
                                        <div class="text-xl font-bold text-[#ffee00] font-mono">14.2V</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                         <!-- Glare Effect -->
                        <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/5 to-transparent pointer-events-none"></div>
                    </div>

                    <!-- Decorative Circle behind -->
                    <div class="absolute -z-10 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[120%] border border-white/5 rounded-full animate-[spin_60s_linear_infinite]"></div>
                    <div class="absolute -z-10 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[140%] h-[140%] border border-dashed border-white/5 rounded-full animate-[spin_40s_linear_infinite_reverse]"></div>
                </div>

            </div>
        </main>

        <footer class="border-t border-[#ffffff08] py-8 bg-[#020202]">
            <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-gray-600 text-sm">
                    &copy; 2026 Neurona Off Road Telemetry. All rights reserved.
                </div>
                <!-- Socials or links could go here -->
                <div class="flex gap-6 text-sm text-gray-500">
                    <a href="#" class="hover:text-white transition-colors">Privacy</a>
                    <a href="#" class="hover:text-white transition-colors">Terms</a>
                    <a href="#" class="hover:text-white transition-colors">Support</a>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.text-glow {
    text-shadow: 0 0 20px rgba(0, 225, 255, 0.5);
}

.shadow-cyan {
    text-shadow: 0 0 15px rgba(0, 240, 255, 0.4);
}

.clip-corner {
    clip-path: polygon(0 0, 100% 0, 100% 85%, 95% 100%, 0 100%);
}

.glow-text {
    text-shadow: 0 0 5px rgba(0, 225, 255, 0.8);
}
</style>
