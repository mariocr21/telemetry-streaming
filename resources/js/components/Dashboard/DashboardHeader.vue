<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface DeviceInventory {
    serial_number: string;
}

interface Device {
    id: number;
    device_name: string;
    device_inventory: DeviceInventory;
}

interface Vehicle {
    id: number;
    vin: string;
    make: string;
    model: string;
    nickname?: string;
}

interface DisplayConnectionStatus {
    text: string;
    color: string;
    icon: string;
    description: string;
}

defineProps<{
    devices: Device[];
    selectedDeviceId: string | number;
    selectedVehicle: Vehicle | null;
    isLoading: boolean;
    displayConnectionStatus: DisplayConnectionStatus;
    error: string | null;
    lastUpdateFormatted: string;
    activeSensorsCount: number;
    lastDataSource: string;
}>();

const emit = defineEmits<{
    'update:selectedDeviceId': [value: string | number];
}>();

// Current time state
const currentTime = ref(new Date());
let intervalId: NodeJS.Timeout | null = null;

// Update selected device
const updateSelectedDevice = (value: string | number) => {
    // Convertir a número si es string numérico
    const deviceId = typeof value === 'string' ? parseInt(value) : value;
    console.log('Device selected:', deviceId);
    emit('update:selectedDeviceId', deviceId);
};

// Formatted time
const formattedTime = computed(() => {
    return currentTime.value.toLocaleTimeString('es-MX', { hour12: false });
});

// Lifecycle
onMounted(() => {
    // Clock
    intervalId = setInterval(() => {
        currentTime.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});
</script>

<template>
    <!-- Header -->
    <div class="border-b border-cyan-500/20 bg-slate-900/95 px-6 py-4 backdrop-blur-xl">
        <div class="flex items-center justify-between">
            <!-- Brand -->
            <div>
                <p class="text-sm text-slate-400">Monitoreo en Tiempo Real</p>
            </div>

            <!-- Device Selector -->
            <div class="flex items-center gap-6">
                <div>
                    <label class="mb-1 block text-xs tracking-wide text-slate-400 uppercase"> Dispositivo </label>
                    <select
                        :value="selectedDeviceId"
                        @change="updateSelectedDevice($event.target.value)"
                        :disabled="isLoading"
                        class="min-w-[200px] rounded-lg border border-cyan-500/30 bg-slate-800/80 px-4 py-2 text-sm text-white focus:border-cyan-400 focus:outline-none disabled:opacity-50"
                    >
                        <option value="" disabled>Seleccionar dispositivo...</option>
                        <option v-for="device in devices" :key="device.id" :value="device.id">
                            {{ device.device_name || device.device_inventory.serial_number }}
                        </option>
                    </select>
                </div>

                <!-- Loading -->
                <div v-if="isLoading" class="flex items-center gap-2 text-cyan-400">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    <span class="text-sm">Cargando...</span>
                </div>

                <!-- Error -->
                <div v-if="error" class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-400">
                    {{ error }}
                </div>

                <!-- Vehicle Info -->
                <div v-if="selectedVehicle && !isLoading" class="rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-4 py-2">
                    <div class="text-xs tracking-wide text-slate-400 uppercase">Monitoreando</div>
                    <div class="text-sm font-semibold text-cyan-400">
                        {{ selectedVehicle.nickname || `${selectedVehicle.make} ${selectedVehicle.model}` }}
                    </div>
                    <div class="text-xs text-slate-500">VIN: {{ selectedVehicle.vin.slice(-6) }} | {{ activeSensorsCount }} sensores</div>
                </div>
            </div>

            <!-- Status -->
            <div class="flex items-center gap-6">
                <div class="font-mono text-lg font-semibold text-cyan-400">
                    {{ formattedTime }}
                </div>
                <div
                    class="flex items-center gap-2 rounded-full border px-3 py-2 transition-all duration-300"
                    :class="{
                        'border-green-500/30 bg-green-500/10': displayConnectionStatus.color === 'green',
                        'border-cyan-500/30 bg-cyan-500/10': displayConnectionStatus.color === 'cyan',
                        'border-orange-500/30 bg-orange-500/10': displayConnectionStatus.color === 'orange',
                        'border-red-500/30 bg-red-500/10': displayConnectionStatus.color === 'red',
                    }"
                >
                    <div
                        class="h-2 w-2 rounded-full transition-all duration-300"
                        :class="{
                            'animate-pulse bg-green-400': displayConnectionStatus.icon === 'live',
                            'bg-cyan-400': displayConnectionStatus.icon === 'online',
                            'bg-orange-400': displayConnectionStatus.icon === 'offline',
                            'bg-red-400': displayConnectionStatus.icon === 'disconnected',
                        }"
                    />
                    <span
                        class="text-sm font-medium"
                        :class="{
                            'text-green-400': displayConnectionStatus.color === 'green',
                            'text-cyan-400': displayConnectionStatus.color === 'cyan',
                            'text-orange-400': displayConnectionStatus.color === 'orange',
                            'text-red-400': displayConnectionStatus.color === 'red',
                        }"
                    >
                        {{ displayConnectionStatus.text }}
                    </span>
                </div>
                <div class="text-sm text-slate-400">
                    {{ lastUpdateFormatted }}
                </div>
                <!-- Data Source Indicator -->
                <div v-if="selectedVehicle" class="rounded bg-slate-800/50 px-2 py-1 text-xs text-slate-500">
                    {{
                        lastDataSource === 'realtime'
                            ? '📡 Tiempo Real'
                            : lastDataSource === 'cache'
                              ? '💾 Caché'
                              : lastDataSource === 'database'
                                ? '🗄️ Base de Datos'
                                : '🎲 Simulado'
                    }}
                </div>
            </div>
        </div>
    </div>
</template>
