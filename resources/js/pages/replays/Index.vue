<script setup lang="ts">
import AllSensorsWidget from '@/components/Dashboard/AllSensorsWidget.vue';
import ConnectionStatusWidget from '@/components/Dashboard/ConnectionStatusWidget.vue';
import MapWidget from '@/components/Dashboard/MapWidget.vue';
import PrimarySensorsWidget from '@/components/Dashboard/PrimarySensorsWidget.vue';
import SecondarySensorsWidget from '@/components/Dashboard/SecondarySensorsWidget.vue';
import ThrottleWidget from '@/components/Dashboard/ThrottleWidget.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, ref, watch } from 'vue';

// ---- Tipos (copiados/adaptados de tu Dashboard.vue) ----
interface Sensor {
    id: number;
    pid: string;
    name: string;
    description: string;
    category: string;
    unit: string;
    data_type: string;
    min_value?: number;
    max_value?: number;
    requires_calculation?: boolean;
    calculation_formula?: string;
}

interface VehicleSensor {
    id: number;
    vehicle_id: number;
    sensor_id: number;
    is_active: boolean;
    frequency_seconds: number;
    min_value?: number;
    max_value?: number;
    last_reading_at?: string;
    sensor: Sensor;
}

interface Vehicle {
    id: number;
    vin: string;
    make: string;
    model: string;
    year: number;
    nickname?: string | null;
    plate?: string | null;
    vehicle_sensors?: VehicleSensor[];
}

interface ConnectionStatus {
    is_online: boolean;
    status: string;
    last_seen: string | null;
    minutes_since_last_reading: number | null;
    seconds_since_last_reading?: number | null;
    human_readable_last_seen: string;
    formatted_inactivity?: string;
}

// Estructura del frame que regresa el backend de replay
interface ReplaySensorFrameInfo {
    pid: string;
    name: string;
    unit: string;
    raw_value: number;
    processed_value: number;
}

interface ReplayFrame {
    timestamp: string;
    sensors: Record<string, ReplaySensorFrameInfo>;
}

interface ReplayResponse {
    vehicle_id: number;
    from: string;
    to: string;
    frames: ReplayFrame[];
    gps_path: Array<{
        timestamp: string;
        lat: number | null;
        lng: number | null;
        speed: number | null;
        alt: number | null;
        heading: number | null;
    }>;
    summary?: {
        total_frames: number;
        total_points: number;
        duration_minutes: number;
        formatted_duration?: string;
    };
}

// ---- Props de Inertia ----
const props = defineProps<{
    vehicles: Vehicle[];
}>();

// ---- Estado base ----
const selectedVehicleId = ref<number | null>(props.vehicles[0]?.id ?? null);
const selectedVehicle = computed<Vehicle | null>(() => props.vehicles.find((v) => v.id === selectedVehicleId.value) ?? null);

const from = ref('');
const to = ref('');

const isLoadingReplay = ref(false);
const errorMessage = ref<string | null>(null);

const replayData = ref<ReplayResponse | null>(null);

// Lecturas actuales de sensores (igual que en Dashboard.vue)
const sensorReadings = ref<Record<string, number>>({});
const lastUpdate = ref<Date | null>(null);

// ---- Map widget ref (igual que en Dashboard.vue) ----
const mapWidgetRef = ref<InstanceType<typeof MapWidget> | null>(null);
const gpsPids = ['lat', 'lng', 'vel_kmh', 'alt_m', 'rumbo'];

const updateMapGpsData = () => {
    if (mapWidgetRef.value && mapWidgetRef.value.updateGpsData) {
        mapWidgetRef.value.updateGpsData(sensorReadings.value);
    }
};

// ---- Computed de sensores (igual lógica que Dashboard.vue) ----
const activeSensors = computed<VehicleSensor[]>(() => {
    return selectedVehicle.value?.vehicle_sensors?.filter((vs) => vs.is_active) ?? [];
});

const hasSensors = computed(() => activeSensors.value.length > 0);

const sensorMap = computed<Record<string, VehicleSensor>>(() => {
    const map: Record<string, VehicleSensor> = {};
    activeSensors.value.forEach((sensor) => {
        map[sensor.sensor.pid] = sensor;
    });
    return map;
});

// PIDs que se muestran en widgets específicos (para excluir del widget general)
const displayedPids = ['0x0D', 'vel_kmh', '0x0C', '0x05', '0x2F', '0x0B', '0x42', '0x11', 'lat', 'lng', 'alt_m', 'rumbo'];

// Helper para obtener valor de un PID desde sensorReadings
const getSensorValue = (pid: string): number => {
    const val = sensorReadings.value[pid];
    if (val === undefined || val === null) return 0;
    return Number(2);
};

// Sensores principales (velocidad, RPM)
const primarySensors = computed(() => {
    const sensors: any[] = [];

    // Velocidad
    if (sensorMap.value['0x0D'] || sensorMap.value['vel_kmh']) {
        sensors.push({
            id: 'speed',
            title: 'Velocidad',
            sensor: sensorMap.value['0x0D'] || sensorMap.value['vel_kmh'],
            value: getSensorValue('0x0D') || getSensorValue('vel_kmh'),
            defaultValue: 0,
        });
    }

    // RPM
    if (sensorMap.value['0x0C']) {
        sensors.push({
            id: 'rpm',
            title: 'RPM',
            sensor: sensorMap.value['0x0C'],
            value: getSensorValue('0x0C'),
            defaultValue: 0,
        });
    }

    return sensors;
});

// Sensores secundarios (temp, fuel, presión, batería)
const secondarySensors = computed(() => {
    const sensors: any[] = [];

    // Temperatura
    if (sensorMap.value['0x05']) {
        sensors.push({
            id: 'coolantTemp',
            title: 'Temperatura',
            emoji: '🌡️',
            sensor: sensorMap.value['0x05'],
            value: getSensorValue('0x05'),
            defaultValue: 85,
        });
    }

    // Combustible
    if (sensorMap.value['0x2F']) {
        sensors.push({
            id: 'fuel',
            title: 'Combustible',
            emoji: '⛽',
            sensor: sensorMap.value['0x2F'],
            value: getSensorValue('0x2F'),
            defaultValue: 50,
        });
    }

    // Presión (ej. barométrica, intake)
    if (sensorMap.value['0x0B']) {
        sensors.push({
            id: 'pressure',
            title: 'Presión',
            emoji: '🎚️',
            sensor: sensorMap.value['0x0B'],
            value: getSensorValue('0x0B'),
            defaultValue: 100,
        });
    }

    // Batería
    if (sensorMap.value['0x42']) {
        sensors.push({
            id: 'battery',
            title: 'Batería',
            emoji: '🔋',
            sensor: sensorMap.value['0x42'],
            value: getSensorValue('0x42'),
            defaultValue: 12.4,
        });
    }

    return sensors;
});

// Mariposa / throttle
const throttleSensor = computed(() => {
    if (sensorMap.value['0x11']) {
        return {
            id: 'throttle',
            title: 'Posición Mariposa',
            emoji: '⚙️',
            sensor: sensorMap.value['0x11'],
            value: getSensorValue('0x11'),
            defaultValue: 0,
        };
    }
    return null;
});

// Sensores restantes para AllSensorsWidget
const remainingSensors = computed(() => {
    return activeSensors.value
        .filter((sensor) => !displayedPids.includes(sensor.sensor.pid))
        .map((sensor) => ({
            id: sensor.sensor.pid,
            title: sensor.sensor.name,
            sensor: sensor,
            value: getSensorValue(sensor.sensor.pid),
            defaultValue: 0,
        }));
});

// ---- Info de conexión “falsa” para modo replay ----
const connectionStatus = computed<ConnectionStatus | null>(() => {
    if (!replayData.value) return null;

    return {
        is_online: true,
        status: 'historical',
        last_seen: replayData.value.to,
        minutes_since_last_reading: null,
        human_readable_last_seen: 'Reproducción histórica',
    };
});

const displayConnectionStatus = computed(() => {
    if (replayData.value) {
        return {
            text: 'Replay',
            color: 'cyan',
            icon: 'history',
            description: 'Reproduciendo datos históricos de telemetría',
        };
    }

    return {
        text: 'Sin datos',
        color: 'gray',
        icon: 'offline',
        description: 'Selecciona un rango de fechas para iniciar el replay',
    };
});

// ---- Playback state ----
const currentFrameIndex = ref(0);
const isPlaying = ref(false);
const playbackSpeed = ref(1); // 1x, 2x, etc.
const playbackTimer = ref<number | null>(null);

const frames = computed<ReplayFrame[]>(() => replayData.value?.frames ?? []);
const totalFrames = computed(() => frames.value.length);

const currentFrame = computed<ReplayFrame | null>(() => {
    if (!frames.value.length) return null;
    return frames.value[currentFrameIndex.value] ?? null;
});

const currentTimestampFormatted = computed(() => {
    if (!currentFrame.value) return '-';
    const date = new Date(currentFrame.value.timestamp);
    return date.toLocaleString('es-MX', { hour12: false });
});

const summaryInfo = computed(() => replayData.value?.summary ?? null);

// ---- Funciones de reproducción ----
const applyFrame = async (frame: ReplayFrame | null) => {
    if (!frame) return;

    // Reset y aplicar lecturas
    const newReadings: Record<string, number> = {};

    Object.keys(frame.sensors).forEach((pid) => {
        const sensorInfo = frame.sensors[pid];
        const value = typeof sensorInfo.processed_value === 'number' ? sensorInfo.processed_value : sensorInfo.raw_value;

        newReadings[pid] = value;
    });

    sensorReadings.value = newReadings;
    lastUpdate.value = new Date(frame.timestamp);

    // Actualizar mapa (lat/lng/vel/alt/rumbo se leen desde sensorReadings)
    await nextTick();
    updateMapGpsData();
};

const clearPlaybackTimer = () => {
    if (playbackTimer.value !== null) {
        clearTimeout(playbackTimer.value);
        playbackTimer.value = null;
    }
};

const scheduleNextFrame = () => {
    clearPlaybackTimer();
    if (!isPlaying.value || frames.value.length === 0) return;

    const interval = 1000 / playbackSpeed.value; // base: 1 frame/seg
    playbackTimer.value = window.setTimeout(() => {
        if (!isPlaying.value) return;

        if (currentFrameIndex.value < frames.value.length - 1) {
            currentFrameIndex.value += 1;
            applyFrame(currentFrame.value);
            scheduleNextFrame();
        } else {
            // Terminó el replay
            isPlaying.value = false;
            clearPlaybackTimer();
        }
    }, interval);
};

const play = () => {
    if (!frames.value.length) return;
    if (!currentFrame.value) {
        currentFrameIndex.value = 0;
    }
    isPlaying.value = true;
    applyFrame(currentFrame.value);
    scheduleNextFrame();
};

const pause = () => {
    isPlaying.value = false;
    clearPlaybackTimer();
};

const stop = () => {
    isPlaying.value = false;
    clearPlaybackTimer();
    currentFrameIndex.value = 0;
    applyFrame(currentFrame.value);
};

const onSeek = () => {
    // Cuando mueves el slider manualmente
    isPlaying.value = false;
    clearPlaybackTimer();
    applyFrame(currentFrame.value);
};

// Si cambia la velocidad de reproducción mientras está en play, reprogramar
watch(playbackSpeed, () => {
    if (isPlaying.value) {
        scheduleNextFrame();
    }
});

// Conectado si hay datos de replay cargados
const isConnected = computed(() => !!replayData.value);

// En modo replay nunca es tiempo real
const isRealTimeActive = ref(false);

// Podrías usar esto si quisieras reintentar carga de replay
const connectionRetries = ref(0);
const maxRetries = 0; // o 5 si quieres mostrar la UI igual que el dashboard

// Reutilizamos el timestamp del frame actual como "última actualización"
const lastUpdateFormatted = computed(() => currentTimestampFormatted.value);

// En el real-time usas: 'cache' | 'database' | 'realtime' | 'simulation'
// Aquí marcamos explícitamente que la fuente es "replay"
const lastDataSource = ref<'replay'>('replay');
// ---- Carga de datos de replay desde backend ----
const canLoadReplay = computed(() => !!selectedVehicleId.value && !!from.value && !!to.value);

const loadReplay = async () => {
    if (!canLoadReplay.value) {
        errorMessage.value = 'Selecciona un vehículo y un rango de fecha/hora válido.';
        return;
    }

    errorMessage.value = null;
    isLoadingReplay.value = true;
    replayData.value = null;
    sensorReadings.value = {};
    currentFrameIndex.value = 0;
    lastUpdate.value = null;
    stop();

    try {
        const response = await axios.get(`/api/vehicles/${selectedVehicleId.value}/replay`, {
            params: {
                from: from.value,
                to: to.value,
            },
        });

        replayData.value = response.data as ReplayResponse;

        if (frames.value.length > 0) {
            currentFrameIndex.value = 0;
            await applyFrame(frames.value[0]);
        } else {
            sensorReadings.value = {};
        }
    } catch (error: any) {
        console.error(error);
        errorMessage.value = error.response?.data?.error || 'Ocurrió un error al obtener los datos de replay.';
    } finally {
        isLoadingReplay.value = false;
    }
};
</script>

<template>
    <Head title="Replay de Telemetría" />

    <AppLayout>
        <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-950 text-white">
            <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 lg:px-8">
                <!-- Filtros de vehículo + rango -->
                <div class="space-y-4 rounded-xl border border-slate-700/60 bg-slate-900/60 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-lg font-semibold text-white">Replay de Telemetría</h1>
                            <p class="text-xs text-slate-400">
                                Selecciona un vehículo y un rango de fechas para reproducir el historial de sensores.
                            </p>
                        </div>

                        <ConnectionStatusWidget
                            :is-connected="isConnected"
                            :is-real-time-active="isRealTimeActive"
                            :last-update-formatted="lastUpdateFormatted"
                            :connection-retries="connectionRetries"
                            :max-retries="maxRetries"
                            :selected-vehicle="selectedVehicle"
                            :connection-status="connectionStatus"
                            :last-data-source="lastDataSource"
                            @reconnect="loadReplay"
                        />
                    </div>

                    <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-4">
                        <!-- Vehículo -->
                        <div class="md:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-slate-300"> Vehículo </label>
                            <select
                                v-model="selectedVehicleId"
                                class="block w-full rounded-md border border-slate-700 bg-slate-900/80 px-3 py-2 text-sm text-slate-100 focus:ring-2 focus:ring-cyan-500 focus:outline-none"
                            >
                                <option :value="null" disabled>Selecciona un vehículo</option>
                                <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                                    {{ vehicle.nickname ? `${vehicle.nickname} (${vehicle.plate ?? 'sin placas'})` : (vehicle.plate ?? vehicle.vin) }}
                                </option>
                            </select>
                        </div>

                        <!-- Desde -->
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-300"> Desde (fecha y hora) </label>
                            <input
                                v-model="from"
                                type="datetime-local"
                                class="block w-full rounded-md border border-slate-700 bg-slate-900/80 px-3 py-2 text-sm text-slate-100 focus:ring-2 focus:ring-cyan-500 focus:outline-none"
                            />
                        </div>

                        <!-- Hasta -->
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-300"> Hasta (fecha y hora) </label>
                            <input
                                v-model="to"
                                type="datetime-local"
                                class="block w-full rounded-md border border-slate-700 bg-slate-900/80 px-3 py-2 text-sm text-slate-100 focus:ring-2 focus:ring-cyan-500 focus:outline-none"
                            />
                        </div>

                        <!-- Botón -->
                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium shadow-lg shadow-cyan-500/30 hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-600"
                                :disabled="!canLoadReplay || isLoadingReplay"
                                @click="loadReplay"
                            >
                                <svg
                                    v-if="isLoadingReplay"
                                    class="mr-2 -ml-1 h-4 w-4 animate-spin text-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 1 4.373 1 10h3z" />
                                </svg>
                                {{ isLoadingReplay ? 'Cargando...' : 'Cargar replay' }}
                            </button>
                        </div>
                    </div>

                    <p v-if="errorMessage" class="mt-1 text-xs text-red-400">
                        {{ errorMessage }}
                    </p>

                    <!-- Resumen -->
                    <div v-if="replayData && summaryInfo" class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-300 md:grid-cols-4">
                        <div>
                            <span class="font-semibold text-slate-100">Frames:</span>
                            <span class="ml-1"> {{ summaryInfo.total_frames }} ({{ summaryInfo.total_points }} puntos) </span>
                        </div>
                        <div>
                            <span class="font-semibold text-slate-100">Duración:</span>
                            <span class="ml-1">
                                {{ summaryInfo.formatted_duration ?? summaryInfo.duration_minutes + ' min' }}
                            </span>
                        </div>
                        <div>
                            <span class="font-semibold text-slate-100">Inicio:</span>
                            <span class="ml-1">{{ replayData.from }}</span>
                        </div>
                        <div>
                            <span class="font-semibold text-slate-100">Fin:</span>
                            <span class="ml-1">{{ replayData.to }}</span>
                        </div>
                    </div>
                </div>

                <!-- Si ya hay data de replay, mostrar dashboard -->
                <div v-if="selectedVehicle && replayData && hasSensors" class="flex h-[calc(100vh-220px)]">
                    <!-- Mapa -->
                    <MapWidget
                        ref="mapWidgetRef"
                        :selected-vehicle="selectedVehicle"
                        :is-loading="isLoadingReplay"
                        :is-real-time-active="false"
                        :connection-status="connectionStatus"
                    />

                    <!-- Panel de widgets -->
                    <div class="w-[35%] overflow-y-auto border-l border-slate-800/60 bg-slate-900/40">
                        <div class="space-y-4 p-4">
                            <!-- Controles de reproducción -->
                            <div class="rounded-lg border border-slate-700/70 bg-slate-900/80 p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <div class="text-xs text-slate-300">
                                        <div class="font-semibold text-slate-100">Tiempo: {{ currentTimestampFormatted }}</div>
                                        <div>Frame {{ currentFrameIndex + 1 }} / {{ totalFrames || 0 }}</div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button
                                            class="rounded bg-slate-800 px-2 py-1 text-xs hover:bg-slate-700 disabled:bg-slate-700/60"
                                            :disabled="!frames.length"
                                            @click="stop"
                                        >
                                            ⏮
                                        </button>
                                        <button
                                            class="rounded bg-cyan-600 px-2 py-1 text-xs hover:bg-cyan-700 disabled:bg-slate-700/60"
                                            :disabled="!frames.length"
                                            @click="isPlaying ? pause() : play()"
                                        >
                                            {{ isPlaying ? '⏸ Pausa' : '▶ Reproducir' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Slider -->
                                <input
                                    v-if="frames.length"
                                    v-model.number="currentFrameIndex"
                                    type="range"
                                    min="0"
                                    :max="totalFrames - 1"
                                    class="w-full"
                                    @input="onSeek"
                                />

                                <!-- Velocidad -->
                                <div class="mt-2 flex items-center justify-between text-xs text-slate-300">
                                    <span>Velocidad: {{ playbackSpeed.toFixed(1) }}x</span>
                                    <div class="space-x-1">
                                        <button
                                            class="rounded bg-slate-800 px-2 py-0.5 hover:bg-slate-700"
                                            @click="playbackSpeed = Math.max(0.5, playbackSpeed - 0.5)"
                                        >
                                            -
                                        </button>
                                        <button
                                            class="rounded bg-slate-800 px-2 py-0.5 hover:bg-slate-700"
                                            @click="playbackSpeed = Math.min(4, playbackSpeed + 0.5)"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Widgets -->
                            <PrimarySensorsWidget v-if="primarySensors.length > 0" :sensors="primarySensors" />

                            <SecondarySensorsWidget v-if="secondarySensors.length > 0" :sensors="secondarySensors" :is-real-time-active="false" />

                            <ThrottleWidget v-if="throttleSensor" :sensor="throttleSensor" :is-real-time-active="false" />

                            <AllSensorsWidget v-if="remainingSensors.length > 0" :sensors="remainingSensors" :is-real-time-active="false" />
                        </div>
                    </div>
                </div>

                <!-- Mensaje cuando no hay datos / selección -->
                <div v-else class="pt-16 text-center text-xs text-slate-400">
                    Selecciona un vehículo, un rango de fechas y carga el replay para ver el dashboard.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
