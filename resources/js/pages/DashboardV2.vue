<script setup lang="ts">
/**
 * DashboardV2.vue - Fixed Layout Dashboard (Slate Pro Edition)
 * 
 * A fixed-layout dashboard based on dash2.html design.
 * Uses predefined slots mapped to vehicle sensors.
 */
import { computed, provide, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useTelemetryBinding } from '@/composables/useTelemetryBinding';
import MapWidget from '@/components/Dashboard/MapWidget.vue';
import VehicleSelectorFloat from '@/components/Dashboard/VehicleSelectorFloat.vue';
import VideoStreamWidget from '@/components/Dashboard/widgets/VideoStreamWidget.vue';
import { Settings, Play, Pause, AlertCircle, Video, ChevronDown, ChevronUp } from 'lucide-vue-next';

// Props
interface Props {
    vehicleId: number;
    vehicle: {
        id: number;
        make: string;
        model: string;
        nickname?: string;
    };
    mapping: Record<string, string | null>;
    shiftLightsConfig?: {
        enabled: boolean;
        maxRpm: number;
        shiftRpm: number;
        startRpm: number;
    };
    availableVehicles?: any[];
    isSuperAdmin?: boolean;
    cameraConfig?: {
        streamBaseUrl: string;
        cameras: Array<{ channelId: string; label: string }>;
    };
}

const props = withDefaults(defineProps<Props>(), {
    mapping: () => ({}),
    shiftLightsConfig: () => ({
        enabled: true,
        maxRpm: 9000,
        shiftRpm: 8500,
        startRpm: 4000,
    }),
});

// Telemetry
const { 
    telemetryData, 
    isConnected,
    connectionStatus,
    subscribe,
    unsubscribe,
    getValue,
    setDemoMode,
    isDemoMode
} = useTelemetryBinding(props.vehicleId);

provide('telemetryData', telemetryData);
provide('getValue', getValue);

// Helper to get mapped sensor value
function getSensorValue<T = number>(slot: string, defaultValue: T): T {
    const sensorKey = props.mapping[slot];
    if (!sensorKey) return defaultValue;
    return getValue<T>(sensorKey, defaultValue);
}

// Computed values from mapped sensors
const rpm = computed(() => getSensorValue('rpm', 0));
const speed = computed(() => getSensorValue('speed', 0));
const throttle = computed(() => getSensorValue('throttle', 0));
const gear = computed(() => {
    const val = getSensorValue('gear', 0);
    if (val === 0) return 'N';
    return String(val);
});
const oilTemp = computed(() => getSensorValue('oil_temp', 0));
const fuelPress = computed(() => getSensorValue('fuel_press', 0));
const coolantTemp = computed(() => getSensorValue('coolant_temp', 0));
const transTemp = computed(() => getSensorValue('trans_temp', 0));
const intakeTemp = computed(() => getSensorValue('intake_temp', 0));
const voltage = computed(() => getSensorValue('voltage', 0));
const amps = computed(() => getSensorValue('amps', 0));

// Tire data
const tires = computed(() => ({
    fl: { psi: getSensorValue('tire_fl_psi', 0), temp: getSensorValue('tire_fl_temp', 0) },
    fr: { psi: getSensorValue('tire_fr_psi', 0), temp: getSensorValue('tire_fr_temp', 0) },
    rl: { psi: getSensorValue('tire_rl_psi', 0), temp: getSensorValue('tire_rl_temp', 0) },
    rr: { psi: getSensorValue('tire_rr_psi', 0), temp: getSensorValue('tire_rr_temp', 0) },
}));

// GPS
const latitude = computed(() => getSensorValue('gps_lat', 0));
const longitude = computed(() => getSensorValue('gps_lon', 0));
const heading = computed(() => getSensorValue('gps_heading', 0));
const gpsActive = computed(() => latitude.value !== 0 || longitude.value !== 0);

// Cameras
const showCameras = ref(true);
const activeCameras = computed(() => {
    const config = props.cameraConfig;
    if (!config || !config.cameras) return [];
    return config.cameras.filter(cam => cam.channelId && cam.channelId.trim() !== '');
});
const hasCameras = computed(() => activeCameras.value.length > 0);
const streamBaseUrl = computed(() => props.cameraConfig?.streamBaseUrl || 'https://stream.neurona.xyz');

// Shift lights
const isRedline = computed(() => rpm.value >= props.shiftLightsConfig.shiftRpm);
const shiftLights = computed(() => {
    const totalLights = 10;
    const startRpm = props.shiftLightsConfig.startRpm;
    const maxRpm = props.shiftLightsConfig.maxRpm;
    const range = maxRpm - startRpm;
    const lights = [];

    for (let i = 0; i < totalLights; i++) {
        const threshold = startRpm + (range / totalLights) * (i + 1);
        const isActive = rpm.value >= threshold;
        
        let styleClass = 'bg-slate-800';
        
        if (isActive) {
            if (isRedline.value) {
                styleClass = 'bg-red-500 shadow-[0_0_10px_#ef4444] animate-pulse';
            } else {
                if (i < 4) styleClass = 'bg-green-500 shadow-[0_0_6px_#22c55e]';
                else if (i < 7) styleClass = 'bg-yellow-400 shadow-[0_0_6px_#facc15]';
                else styleClass = 'bg-red-500 shadow-[0_0_6px_#ef4444]';
            }
        }
        lights.push({ class: styleClass });
    }
    return lights;
});

// Gauge calculations
const rpmPercent = computed(() => (rpm.value / props.shiftLightsConfig.maxRpm) * 100);
const speedPercent = computed(() => (speed.value / 200) * 100);
const throttlePercent = computed(() => throttle.value);

// SVG gauge helper - calculates dashoffset for clockwise fill starting from bottom-left
// For a 270-degree arc gauge (typical car gauge sweep)
const GAUGE_CIRCUMFERENCE = 283; // 2 * PI * 45
const GAUGE_ARC = 0.75; // 270 degrees = 75% of full circle
const GAUGE_ARC_LENGTH = GAUGE_CIRCUMFERENCE * GAUGE_ARC; // ~212

function gaugeOffset(percent: number): number {
    const clampedPercent = Math.min(Math.max(percent, 0), 100);
    const fillLength = (GAUGE_ARC_LENGTH * clampedPercent) / 100;
    return GAUGE_ARC_LENGTH - fillLength;
}

onMounted(() => subscribe());
onUnmounted(() => unsubscribe());

function handleVehicleSelect(id: number) {
    router.visit(`/dashboard-v2/${id}`);
}
</script>

<template>
    <Head :title="`Dashboard V2 - ${vehicle.nickname || vehicle.make}`" />
    
    <div class="h-screen w-screen overflow-hidden flex flex-col bg-slate-900 relative">
        <!-- Vehicle Selector (Top Right) -->
        <div class="absolute top-4 right-4 z-[100]">
            <VehicleSelectorFloat 
                :current-vehicle-id="vehicleId"
                :vehicles="availableVehicles || []"
                :is-super-admin="isSuperAdmin || false"
                @select="handleVehicleSelect"
            />
        </div>

        <div class="flex-1 flex flex-col p-2 md:p-4 gap-4 h-full overflow-y-auto">
            
            <!-- Shift Lights Bar -->
            <div v-if="shiftLightsConfig.enabled" class="h-4 w-full flex gap-1 shrink-0">
                <div 
                    v-for="(light, index) in shiftLights" 
                    :key="index" 
                    class="flex-1 rounded-full transition-all duration-75"
                    :class="light.class"
                ></div>
            </div>

            <!-- Main Grid -->
            <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-4 min-h-0">
                
                <!-- Map Section (5 cols) -->
                <div class="lg:col-span-5 flex flex-col relative group rounded-2xl overflow-hidden border border-slate-700 shadow-2xl min-h-[300px]">
                    <!-- GPS Inactive Overlay -->
                    <div v-if="!gpsActive" class="absolute inset-0 z-[35] bg-slate-900/90 backdrop-blur-sm flex flex-col items-center justify-center text-center p-6">
                        <div class="bg-sky-500/20 p-4 rounded-full mb-4">
                            <AlertCircle class="w-12 h-12 text-sky-400" />
                        </div>
                        <h2 class="text-xl font-bold text-white mb-1">Sin Datos GPS</h2>
                        <p class="text-slate-400 text-sm">Esperando coordenadas del vehículo...</p>
                    </div>
                    
                    <MapWidget 
                        :selected-vehicle="vehicle"
                        :is-loading="false"
                        :is-real-time-active="isConnected"
                        :connection-status="connectionStatus"
                        :latitude="latitude" 
                        :longitude="longitude"
                        :heading="heading"
                        default-layer="dark"
                        class="w-full h-full"
                    />
                    
                    <!-- GPS Coords Overlay -->
                    <div class="absolute bottom-4 left-4 z-[35] flex gap-2">
                        <div class="bg-slate-900/80 backdrop-blur px-3 py-1 rounded border border-slate-700 text-xs font-mono text-sky-400">
                            LAT: {{ latitude.toFixed(4) }}
                        </div>
                        <div class="bg-slate-900/80 backdrop-blur px-3 py-1 rounded border border-slate-700 text-xs font-mono text-sky-400">
                            LON: {{ longitude.toFixed(4) }}
                        </div>
                    </div>
                </div>

                <!-- Widgets Section (7 cols) -->
                <div class="lg:col-span-7 grid grid-cols-1 md:grid-cols-2 gap-4 content-start overflow-y-auto pr-1">
                    
                    <!-- Engine Performance Group -->
                    <div class="md:col-span-2 bg-slate-800 rounded-xl border border-slate-700 p-4 shadow-lg">
                        <h3 class="text-xs font-bold text-slate-400 uppercase mb-4 tracking-wider">Engine Performance</h3>
                        <div class="flex justify-around items-center">
                            <!-- RPM Gauge -->
                            <div class="flex flex-col items-center relative w-28 h-28">
                                <svg class="w-full h-full" viewBox="0 0 100 100">
                                    <!-- Background track (270 degree arc) -->
                                    <circle 
                                        cx="50" cy="50" r="45" 
                                        class="stroke-slate-700 stroke-[8] fill-none opacity-30"
                                        :stroke-dasharray="`${GAUGE_ARC_LENGTH} ${GAUGE_CIRCUMFERENCE}`"
                                        stroke-dashoffset="0"
                                        transform="rotate(135 50 50)"
                                    />
                                    <!-- Value arc -->
                                    <circle 
                                        cx="50" cy="50" r="45" 
                                        class="stroke-[8] fill-none transition-all duration-100"
                                        :class="isRedline ? 'stroke-red-500' : 'stroke-emerald-500'"
                                        stroke-linecap="round"
                                        :stroke-dasharray="`${GAUGE_ARC_LENGTH} ${GAUGE_CIRCUMFERENCE}`"
                                        :stroke-dashoffset="gaugeOffset(rpmPercent)"
                                        transform="rotate(135 50 50)"
                                    />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-xl font-bold font-mono text-white">{{ rpm }}</span>
                                    <span class="text-[10px] text-slate-500 uppercase">RPM</span>
                                </div>
                            </div>
                            
                            <!-- Speed Gauge -->
                            <div class="flex flex-col items-center relative w-28 h-28">
                                <svg class="w-full h-full" viewBox="0 0 100 100">
                                    <!-- Background track -->
                                    <circle 
                                        cx="50" cy="50" r="45" 
                                        class="stroke-slate-700 stroke-[8] fill-none opacity-30"
                                        :stroke-dasharray="`${GAUGE_ARC_LENGTH} ${GAUGE_CIRCUMFERENCE}`"
                                        stroke-dashoffset="0"
                                        transform="rotate(135 50 50)"
                                    />
                                    <!-- Value arc -->
                                    <circle 
                                        cx="50" cy="50" r="45" 
                                        class="stroke-sky-400 stroke-[8] fill-none transition-all duration-100"
                                        stroke-linecap="round"
                                        :stroke-dasharray="`${GAUGE_ARC_LENGTH} ${GAUGE_CIRCUMFERENCE}`"
                                        :stroke-dashoffset="gaugeOffset(speedPercent)"
                                        transform="rotate(135 50 50)"
                                    />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-xl font-bold font-mono text-white">{{ speed }}</span>
                                    <span class="text-[10px] text-slate-500 uppercase">MPH</span>
                                </div>
                            </div>
                            
                            <!-- Throttle Gauge -->
                            <div class="flex flex-col items-center relative w-24 h-24">
                                <svg class="w-full h-full" viewBox="0 0 100 100">
                                    <!-- Background track -->
                                    <circle 
                                        cx="50" cy="50" r="45" 
                                        class="stroke-slate-700 stroke-[8] fill-none opacity-30"
                                        :stroke-dasharray="`${GAUGE_ARC_LENGTH} ${GAUGE_CIRCUMFERENCE}`"
                                        stroke-dashoffset="0"
                                        transform="rotate(135 50 50)"
                                    />
                                    <!-- Value arc -->
                                    <circle 
                                        cx="50" cy="50" r="45" 
                                        class="stroke-emerald-500 stroke-[8] fill-none transition-all duration-100"
                                        stroke-linecap="round"
                                        :stroke-dasharray="`${GAUGE_ARC_LENGTH} ${GAUGE_CIRCUMFERENCE}`"
                                        :stroke-dashoffset="gaugeOffset(throttlePercent)"
                                        transform="rotate(135 50 50)"
                                    />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-lg font-bold font-mono text-white">{{ throttle }}%</span>
                                    <span class="text-[10px] text-slate-500 uppercase">TPS</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gear + Oil/Fuel Row -->
                    <div class="md:col-span-2 grid grid-cols-12 gap-4">
                        <!-- Gear Display -->
                        <div class="col-span-4 bg-emerald-500 rounded-xl border border-emerald-600 p-2 flex flex-col items-center justify-center shadow-lg min-h-[120px]">
                            <span class="text-xs font-bold text-emerald-900 uppercase">Gear</span>
                            <span class="text-6xl font-black text-white">{{ gear }}</span>
                        </div>

                        <!-- Oil Temp + Fuel Press -->
                        <div class="col-span-8 bg-slate-800 rounded-xl border border-slate-700 p-4 flex flex-col justify-center gap-4 shadow-lg">
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-slate-400 font-bold">Oil Temp</span>
                                    <span class="font-mono text-amber-500">{{ oilTemp }} °F</span>
                                </div>
                                <div class="w-full h-2 bg-slate-700 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-amber-500 transition-all duration-300" 
                                        :style="{ width: Math.min(oilTemp / 300 * 100, 100) + '%' }"
                                    ></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-slate-400 font-bold">Fuel Press</span>
                                    <span class="font-mono text-sky-400">{{ fuelPress }} PSI</span>
                                </div>
                                <div class="w-full h-2 bg-slate-700 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-sky-400 transition-all duration-300" 
                                        :style="{ width: Math.min(fuelPress / 80 * 100, 100) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Temperatures Group -->
                    <div class="md:col-span-2 bg-slate-800 rounded-xl border border-slate-700 p-4 shadow-lg">
                        <h3 class="text-xs font-bold text-slate-400 uppercase mb-3 tracking-wider">Temperatures</h3>
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div class="bg-slate-700/50 rounded p-2">
                                <span class="text-[10px] text-slate-500 uppercase block">Coolant</span>
                                <span class="text-xl font-bold font-mono text-amber-500">{{ coolantTemp }}°</span>
                            </div>
                            <div class="bg-slate-700/50 rounded p-2">
                                <span class="text-[10px] text-slate-500 uppercase block">Oil</span>
                                <span class="text-xl font-bold font-mono text-amber-500">{{ oilTemp }}°</span>
                            </div>
                            <div class="bg-slate-700/50 rounded p-2">
                                <span class="text-[10px] text-slate-500 uppercase block">Trans</span>
                                <span class="text-xl font-bold font-mono text-amber-500">{{ transTemp }}°</span>
                            </div>
                            <div class="bg-slate-700/50 rounded p-2">
                                <span class="text-[10px] text-slate-500 uppercase block">Intake</span>
                                <span class="text-xl font-bold font-mono text-amber-500">{{ intakeTemp }}°</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tires Group -->
                    <div class="bg-slate-800 rounded-xl border border-slate-700 p-4 shadow-lg">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tires</h3>
                            <span class="text-[10px] text-slate-500">PSI / °F</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-slate-700/30 rounded p-2 border border-slate-600">
                                <div class="text-[10px] text-slate-500 font-bold mb-1">FL</div>
                                <div class="flex justify-between items-end">
                                    <span class="text-lg font-bold text-white">{{ tires.fl.psi }}</span>
                                    <span class="text-xs text-amber-500">{{ tires.fl.temp }}°</span>
                                </div>
                            </div>
                            <div class="bg-slate-700/30 rounded p-2 border border-slate-600">
                                <div class="text-[10px] text-slate-500 font-bold mb-1 text-right">FR</div>
                                <div class="flex justify-between items-end flex-row-reverse">
                                    <span class="text-lg font-bold text-white">{{ tires.fr.psi }}</span>
                                    <span class="text-xs text-amber-500">{{ tires.fr.temp }}°</span>
                                </div>
                            </div>
                            <div class="bg-slate-700/30 rounded p-2 border border-slate-600">
                                <div class="text-[10px] text-slate-500 font-bold mb-1">RL</div>
                                <div class="flex justify-between items-end">
                                    <span class="text-lg font-bold text-white">{{ tires.rl.psi }}</span>
                                    <span class="text-xs text-amber-500">{{ tires.rl.temp }}°</span>
                                </div>
                            </div>
                            <div class="bg-slate-700/30 rounded p-2 border border-slate-600">
                                <div class="text-[10px] text-slate-500 font-bold mb-1 text-right">RR</div>
                                <div class="flex justify-between items-end flex-row-reverse">
                                    <span class="text-lg font-bold text-white">{{ tires.rr.psi }}</span>
                                    <span class="text-xs text-amber-500">{{ tires.rr.temp }}°</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Electrical Group -->
                    <div class="bg-slate-800 rounded-xl border border-slate-700 p-4 shadow-lg">
                        <h3 class="text-xs font-bold text-slate-400 uppercase mb-3 tracking-wider">Electrical</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center bg-slate-700/50 rounded py-2">
                                <span class="text-[10px] text-slate-500 uppercase block">Battery</span>
                                <span class="text-2xl font-bold text-yellow-400">{{ voltage }} <span class="text-xs text-white">V</span></span>
                            </div>
                            <div class="text-center bg-slate-700/50 rounded py-2">
                                <span class="text-[10px] text-slate-500 uppercase block">Current</span>
                                <span class="text-2xl font-bold text-yellow-400">{{ amps }} <span class="text-xs text-white">A</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Cameras Section (Collapsible) -->
                    <div v-if="hasCameras" class="md:col-span-2 bg-slate-800 rounded-xl border border-slate-700 shadow-lg overflow-hidden">
                        <!-- Header (clickable to collapse) -->
                        <button 
                            @click="showCameras = !showCameras"
                            class="w-full flex items-center justify-between p-4 text-left hover:bg-slate-700/30 transition-colors"
                        >
                            <div class="flex items-center gap-2">
                                <Video class="w-4 h-4 text-cyan-500" />
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    Live Cameras ({{ activeCameras.length }})
                                </h3>
                            </div>
                            <ChevronUp v-if="showCameras" class="w-4 h-4 text-slate-400" />
                            <ChevronDown v-else class="w-4 h-4 text-slate-400" />
                        </button>
                        
                        <!-- Camera Grid (collapsible) -->
                        <Transition name="collapse">
                            <div v-if="showCameras" class="p-4 pt-0">
                                <div 
                                    class="grid gap-3"
                                    :class="[
                                        activeCameras.length === 1 ? 'grid-cols-1' : '',
                                        activeCameras.length === 2 ? 'grid-cols-2' : '',
                                        activeCameras.length >= 3 ? 'grid-cols-2' : '',
                                    ]"
                                >
                                    <div 
                                        v-for="(camera, index) in activeCameras" 
                                        :key="index"
                                        class="aspect-video rounded-lg overflow-hidden"
                                    >
                                        <VideoStreamWidget
                                            :stream-base-url="streamBaseUrl"
                                            :channel-id="camera.channelId"
                                            :label="camera.label || `Cámara ${index + 1}`"
                                            :autoplay="true"
                                        />
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>

                </div>
            </div>
        </div>

        <!-- Floating Controls -->
        <div class="fixed bottom-4 right-4 z-50 flex items-center gap-2">
            <Link :href="`/dashboard-v2/${vehicleId}/config`">
                <button class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold backdrop-blur-md transition-all border border-white/10 hover:bg-white/10 bg-black/40 text-white">
                    <Settings class="w-4 h-4" />
                    Config
                </button>
            </Link>
            
            <button
                @click="setDemoMode(!isDemoMode)"
                class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold backdrop-blur-md transition-all border border-white/10 hover:bg-white/5 bg-black/40"
            >
                <Play v-if="!isDemoMode" class="w-3.5 h-3.5" />
                <Pause v-else class="w-3.5 h-3.5" />
                {{ isDemoMode ? 'DEMO' : 'LIVE' }}
            </button>
            
            <div 
                class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium backdrop-blur-md transition-all"
                :class="isConnected 
                    ? 'bg-green-500/20 text-green-400 border border-green-500/30' 
                    : 'bg-red-500/20 text-red-400 border border-red-500/30'"
            >
                <span 
                    class="w-2 h-2 rounded-full" 
                    :class="isConnected ? 'bg-green-400 animate-pulse' : 'bg-red-400'"
                ></span>
                <span>{{ isConnected ? 'Live' : 'Offline' }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* JetBrains Mono for values */
.font-mono {
    font-family: 'JetBrains Mono', monospace;
}

/* Collapse transition */
.collapse-enter-active,
.collapse-leave-active {
    transition: all 0.3s ease;
    overflow: hidden;
}

.collapse-enter-from,
.collapse-leave-to {
    opacity: 0;
    max-height: 0;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}

.collapse-enter-to,
.collapse-leave-from {
    opacity: 1;
    max-height: 500px;
}
</style>
