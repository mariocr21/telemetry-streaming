<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

// Props que puedes recibir del controlador
defineProps<{
    auth?: {
        user?: any;
    };
}>();

// Estado reactivo
const isLoaded = ref(false);

// Datos de ejemplo para los vehículos de carrera
const racingVehicles = ref([
    {
        id: 1,
        number: '101',
        driver: 'Carlos Mendoza',
        vehicle: 'Polaris RZR Pro R',
        category: 'UTV',
        position: 'CP-3 Ojos Negros',
        status: 'racing',
        statusText: 'En Carrera',
        speed: '78 km/h',
        lastUpdate: '2 min',
    },
    {
        id: 2,
        number: '205',
        driver: 'Ana Rodríguez',
        vehicle: 'Ford Raptor',
        category: 'Trophy Truck',
        position: 'CP-2 Valle de Trinidad',
        status: 'pit',
        statusText: 'En Pits',
        speed: '0 km/h',
        lastUpdate: '5 min',
    },
    {
        id: 3,
        number: '89',
        driver: 'Miguel Santos',
        vehicle: 'Honda Talon',
        category: 'UTV Sport',
        position: 'CP-4 San Felipe',
        status: 'racing',
        statusText: 'En Carrera',
        speed: '65 km/h',
        lastUpdate: '1 min',
    },
    {
        id: 4,
        number: '334',
        driver: 'Jessica López',
        vehicle: 'Can-Am X3',
        category: 'UTV',
        position: 'CP-1 Ensenada',
        status: 'dnf',
        statusText: 'Retirado',
        speed: '0 km/h',
        lastUpdate: '15 min',
    },
]);

// Características del sistema para racing
const features = ref([
    {
        icon: '🏁',
        title: 'Rastreo GPS de Alta Precisión',
        description: 'Monitoreo en tiempo real de posición, velocidad y trayectoria en terrenos extremos de Baja California',
    },
    {
        icon: '📡',
        title: 'Telemetría de Sensores',
        description: 'Motor, temperatura, combustible, presión de llantas y más de 20 sensores críticos para la carrera',
    },
    {
        icon: '⚡',
        title: 'Alertas de Seguridad',
        description: 'Notificaciones instantáneas de emergencias, volcaduras, paradas prolongadas y zonas de peligro',
    },
]);

// Lifecycle
onMounted(() => {
    setTimeout(() => {
        isLoaded.value = true;
    }, 100);
});
</script>

<template>
    <Head title="Welcome - NEURONA Racing">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    </Head>

    <div class="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18]">
        <!-- Header -->
        <header class="border-b border-[rgba(25,20,0,0.1)] bg-[#FDFDFC]">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br bg-black/85 text-white transition-all duration-300 hover:scale-105"
                    >
                        <AppLogoIcon class="size-8" />
                    </div>
                    <div class="flex flex-col">
                        <div class="text-2xl leading-none font-bold text-[#00bcd4]">NEURONA</div>
                        <div class="text-xs font-medium tracking-[2px] text-gray-500 uppercase">Off Road Racing Telemetry</div>
                    </div>
                </div>

                <!-- Navigation Actions -->
                <nav class="flex items-center gap-4">
                    <Link
                        href="#"
                        class="rounded-lg border border-[rgba(25,20,0,0.2)] px-6 py-3 text-sm font-medium text-[#1b1b18] transition-all duration-300 hover:border-[rgba(25,20,0,0.3)] hover:bg-[rgba(25,20,0,0.05)]"
                    >
                        Documentación
                    </Link>
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-gradient-to-r from-[#00bcd4] to-[#0097a7] px-6 py-3 text-sm font-medium text-white shadow-lg shadow-[rgba(0,188,212,0.2)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[rgba(0,188,212,0.3)]"
                    >
                        Ir al Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-lg bg-gradient-to-r from-[#00bcd4] to-[#0097a7] px-6 py-3 text-sm font-medium text-white shadow-lg shadow-[rgba(0,188,212,0.2)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[rgba(0,188,212,0.3)]"
                        >
                            Iniciar Sesión
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex flex-1 items-center justify-center p-8">
            <div class="grid w-full max-w-6xl items-center gap-16 lg:grid-cols-2">
                <!-- Content Section -->
                <div
                    class="relative rounded-xl bg-white p-12 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] transition-all duration-800"
                    :class="{ 'translate-y-0 opacity-100': isLoaded, 'translate-y-5 opacity-0': !isLoaded }"
                >
                    <!-- Top Border -->
                    <div class="absolute top-0 right-0 left-0 h-1 rounded-t-xl bg-gradient-to-r from-[#00bcd4] to-[#0097a7]"></div>

                    <h1 class="mb-2 text-3xl font-semibold text-[#1b1b18]">Bienvenido a NeuronaWireless Racing</h1>
                    <p class="mb-8 leading-relaxed text-[#706f6c]">
                        Tu sistema de telemetría para carreras off-road está listo. Monitorea pilotos, vehículos y sensores en tiempo real durante
                        competencias como la Baja 1000.
                    </p>

                    <!-- Features List -->
                    <ul class="mb-8 space-y-0">
                        <li
                            v-for="(feature, index) in features"
                            :key="index"
                            class="flex items-center gap-4 rounded-lg border-b border-[#e3e3e0] py-4 transition-all duration-300 last:border-b-0 hover:bg-gray-50 hover:px-2"
                        >
                            <div
                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[rgba(0,188,212,0.1)] text-xl text-[#00bcd4]"
                            >
                                {{ feature.icon }}
                            </div>
                            <div class="flex-1">
                                <h3 class="mb-1 font-semibold text-[#1b1b18]">
                                    {{ feature.title }}
                                </h3>
                                <p class="text-sm leading-snug text-gray-600">
                                    {{ feature.description }}
                                </p>
                            </div>
                        </li>
                    </ul>

                    <!-- CTA Buttons -->
                    <div class="flex flex-wrap gap-4">
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('dashboard')"
                            class="rounded-lg bg-gradient-to-r from-[#00bcd4] to-[#0097a7] px-6 py-3 font-medium text-white shadow-lg shadow-[rgba(0,188,212,0.2)] transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-[rgba(0,188,212,0.3)]"
                        >
                            Ver Carrera en Vivo
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-lg bg-gradient-to-r from-[#00bcd4] to-[#0097a7] px-6 py-3 font-medium text-white shadow-lg shadow-[rgba(0,188,212,0.2)] transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-[rgba(0,188,212,0.3)]"
                            >
                                Acceder al Sistema
                            </Link>
                        </template>
                        <Link
                            href="#"
                            class="rounded-lg border border-[rgba(25,20,0,0.2)] px-6 py-3 font-medium text-[#1b1b18] transition-all duration-300 hover:border-[rgba(25,20,0,0.3)] hover:bg-[rgba(25,20,0,0.05)]"
                        >
                            Guía de Uso
                        </Link>
                    </div>
                </div>

                <!-- Visual Section -->
                <div
                    class="relative flex items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-[rgba(0,188,212,0.05)] to-[rgba(0,151,167,0.1)] p-8 transition-all delay-200 duration-1000"
                    :class="{ 'translate-y-0 opacity-100': isLoaded, 'translate-y-5 opacity-0': !isLoaded }"
                >
                    <!-- Racing Flag Pattern Background -->
                    <div class="pointer-events-none absolute inset-0 overflow-hidden opacity-5">
                        <div class="absolute top-4 right-8 h-8 w-8 rotate-45 transform bg-black"></div>
                        <div class="absolute bottom-12 left-6 h-6 w-6 rotate-45 transform bg-black"></div>
                        <div class="absolute top-1/2 right-1/4 h-4 w-4 rotate-45 transform bg-black"></div>
                    </div>

                    <!-- Floating Elements -->
                    <div class="pointer-events-none absolute inset-0 overflow-hidden">
                        <div
                            class="absolute top-[20%] right-[15%] h-15 w-15 animate-[float_6s_ease-in-out_infinite] rounded-full bg-[rgba(0,188,212,0.1)]"
                        ></div>
                        <div
                            class="absolute bottom-[30%] left-[10%] h-10 w-10 animate-[float_6s_ease-in-out_infinite_2s] rounded-full bg-[rgba(0,188,212,0.1)]"
                        ></div>
                        <div
                            class="absolute top-[60%] right-[25%] h-20 w-20 animate-[float_6s_ease-in-out_infinite_4s] rounded-full bg-[rgba(0,188,212,0.1)]"
                        ></div>
                    </div>

                    <!-- Racing Dashboard Mockup -->
                    <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                        <!-- Header -->
                        <div class="mb-6 flex items-center justify-between border-b border-[#e3e3e0] pb-4">
                            <div class="flex items-center gap-2">
                                <div class="text-lg font-semibold text-[#1b1b18]">Baja 1000 - Live</div>
                                <div class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white">LIVE</div>
                            </div>
                            <div class="flex items-center gap-2 text-sm font-medium text-[#00bcd4]">
                                <div class="h-2 w-2 animate-pulse rounded-full bg-[#00bcd4]"></div>
                                Tracking
                            </div>
                        </div>

                        <!-- Racing Vehicles List -->
                        <div class="space-y-3">
                            <div
                                v-for="vehicle in racingVehicles"
                                :key="vehicle.id"
                                class="group cursor-pointer rounded-xl bg-gray-50 p-3 transition-all duration-300 hover:translate-x-1 hover:bg-blue-50"
                            >
                                <div class="mb-2 flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-r from-orange-400 to-red-500 text-xs font-bold text-white"
                                        >
                                            {{ vehicle.number }}
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-semibold text-[#1b1b18] transition-colors group-hover:text-[#00bcd4]">
                                                {{ vehicle.driver }}
                                            </h4>
                                            <p class="text-xs text-gray-500">{{ vehicle.vehicle }} • {{ vehicle.category }}</p>
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-full px-2 py-1 text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-700': vehicle.status === 'racing',
                                            'bg-yellow-100 text-yellow-700': vehicle.status === 'pit',
                                            'bg-red-100 text-red-700': vehicle.status === 'dnf',
                                        }"
                                    >
                                        {{ vehicle.statusText }}
                                    </div>
                                </div>
                                <div class="flex items-center justify-between text-xs text-gray-600">
                                    <span>{{ vehicle.position }}</span>
                                    <div class="flex gap-3">
                                        <span class="font-medium">{{ vehicle.speed }}</span>
                                        <span>{{ vehicle.lastUpdate }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="mt-6 grid grid-cols-3 gap-4 border-t border-gray-200 pt-4 text-center">
                            <div>
                                <div class="text-lg font-bold text-[#00bcd4]">156</div>
                                <div class="text-xs text-gray-500">Participantes</div>
                            </div>
                            <div>
                                <div class="text-lg font-bold text-green-600">134</div>
                                <div class="text-xs text-gray-500">En Carrera</div>
                            </div>
                            <div>
                                <div class="text-lg font-bold text-orange-500">22</div>
                                <div class="text-xs text-gray-500">DNF</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
@keyframes float {
    0%,
    100% {
        transform: translateY(0px) rotate(0deg);
    }
    33% {
        transform: translateY(-20px) rotate(120deg);
    }
    66% {
        transform: translateY(10px) rotate(240deg);
    }
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}

@media (max-width: 640px) {
    .bg-white {
        padding: 2rem;
    }

    .flex-wrap {
        flex-direction: column;
    }

    .px-6 {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}
</style>
