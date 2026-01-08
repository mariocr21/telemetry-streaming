<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import DashboardHeader from '@/components/DashboardHeader.vue';
import DeviceSelectModal from '@/components/DeviceSelectModal.vue';
import MapWidget from '@/components/Dashboard/MapWidget.vue';
import { useI18n } from '@/i18n/useI18n';
import { Settings2, X, Check, Gauge as GaugeIcon, Activity, BarChart2, Layers } from 'lucide-vue-next';

/**
 * NEURONA RACING DASHBOARD - PRO v3.0 (COMPACT & CONFIGURABLE)
 */

const { t } = useI18n();

// --- STATE ---
const props = defineProps<{ devices: any }>();
const selectedDevice = ref<any>(null);
const selectedVehicle = ref<any>(null);
const showDeviceModal = ref(false);
const isRealTimeActive = ref(false);
const isConfigMode = ref(false);
const mapWidgetRef = ref<any>(null);

// Sensor data storage
const liveSensors = ref<Record<string, any>>({});
const gpsData = ref({ lat: 31.411774, lng: -115.728470, alt_m: 840.9, rumbo: 135, vel_kmh: 0 });

// User Preferences (PID -> widgetType) - Loaded from localStorage
const userPreferences = ref<Record<string, string>>({});

// --- WIDGET TYPES ---
const WIDGET_TYPES = [
    { id: 'gauge', icon: GaugeIcon, label: 'Analogico' },
    { id: 'bar', icon: BarChart2, label: 'Barra' },
    { id: 'box', icon: Activity, label: 'Numerico' },
    { id: 'thermometer', icon: Layers, label: 'Termometro' }
];

const loadPreferences = () => {
    const saved = localStorage.getItem('dash_pro_prefs');
    if (saved) userPreferences.value = JSON.parse(saved);
};

const savePreference = (pid: string, type: string) => {
    userPreferences.value[pid] = type;
    localStorage.setItem('dash_pro_prefs', JSON.stringify(userPreferences.value));
};

// Selection logic
const getWidgetType = (sensor: any) => {
    if (userPreferences.value[sensor.pid]) return userPreferences.value[sensor.pid];
    
    // Defaults por inferencia (v2 logic)
    const pid = sensor.pid;
    if (pid === '0x0C' || pid === '0x0D' || pid === 'vel_kmh') return 'gauge';
    if (pid === 'GEAR') return 'gear';
    
    const unit = (sensor.unit || '').toLowerCase();
    if (unit === '%' || unit === 'pos' || unit === 'psi' || unit === 'bar') return 'bar';
    if (unit === 'f' || unit === 'c' || unit === '°f' || unit === '°c') return 'thermometer';
    
    return 'box';
};

// --- DATA PROCESSING ---
const handleTelemetryUpdate = (payload: any) => {
    if (payload.vehicle_id !== selectedVehicle.value?.id) return;
    isRealTimeActive.value = true;

    if (payload.data) {
        Object.entries(payload.data).forEach(([pid, data]: [string, any]) => {
            const value = data.processed_value ?? data.value ?? 0;
            if (['lat', 'lng', 'alt_m', 'rumbo', 'vel_kmh'].includes(pid)) {
                gpsData.value[pid] = value;
                return;
            }
            liveSensors.value[pid] = {
                pid, value, name: data.name || pid, unit: data.unit || '',
                min: data.min_value ?? 0, max: data.max_value ?? 100
            };
        });
        nextTick(() => mapWidgetRef.value?.updateGpsData?.(gpsData.value));
    }
};

// --- GROUPING LOGIC ---
const groups = computed(() => {
    const sensors = Object.values(liveSensors.value);
    return [
        {
            id: 'performance',
            title: 'Engine Performance',
            accent: 'var(--racing-green)',
            sensors: sensors.filter(s => ['0x0C', '0x0D', 'vel_kmh', '0x11'].includes(s.pid))
        },
        {
            id: 'temperatures',
            title: 'Temperatures',
            accent: 'var(--racing-orange)',
            sensors: sensors.filter(s => ['0x05', '0x0B', '0x06'].includes(s.pid))
        },
        {
            id: 'pressures',
            title: 'Pressures & Fluids',
            accent: 'var(--racing-cyan)',
            sensors: sensors.filter(s => ['0x0A', '0x2F', '0x43'].includes(s.pid))
        },
        {
            id: 'miscellaneous',
            title: 'Other Sensors',
            accent: 'var(--text-muted)',
            sensors: sensors.filter(s => !['0x0C', '0x0D', 'vel_kmh', '0x11', '0x05', '0x0B', '0x06', '0x0A', '0x2F', '0x43', 'GEAR'].includes(s.pid))
        }
    ].filter(g => g.sensors.length > 0);
});

const gearSensor = computed(() => liveSensors.value['GEAR']);

// --- SIMULACIÓN ---
let demoInterval: any = null;
const startDemo = () => {
    const mock = [
        { pid: '0x0C', name: 'RPM', value: 3500, min: 0, max: 8000, unit: 'RPM' },
        { pid: '0x0D', name: 'MPH', value: 45, min: 0, max: 180, unit: 'MPH' },
        { pid: '0x11', name: 'Throttle', value: 39, min: 0, max: 100, unit: '%' },
        { pid: '0x05', name: 'Coolant', value: 203, min: 100, max: 250, unit: '°F' },
        { pid: '0x0B', name: 'Oil Temp', value: 215, min: 100, max: 250, unit: '°F' },
        { pid: '0x06', name: 'Trans Temp', value: 205, min: 100, max: 250, unit: '°F' },
        { pid: '0x2F', name: 'Fuel', value: 20, min: 0, max: 100, unit: '%' },
        { pid: '0x42', name: 'Battery', value: 14.3, min: 10, max: 16, unit: 'V' },
        { pid: 'GEAR', name: 'Gear', value: 'N' }
    ];
    mock.forEach(s => liveSensors.value[s.pid] = s);
    demoInterval = setInterval(() => {
        if (liveSensors.value['0x0C']) 
            liveSensors.value['0x0C'].value = Math.round(3000 + Math.random() * 500);
        if (liveSensors.value['0x0D']) 
            liveSensors.value['0x0D'].value = Math.round(40 + Math.random() * 10);
    }, 500);
};

onMounted(() => {
    loadPreferences();
    startDemo();
});

onUnmounted(() => clearInterval(demoInterval));

// Utils
const getRotation = (s: any) => -45 + ((s.value - s.min) / (s.max - s.min)) * 180;
const getPercent = (s: any) => Math.min(100, Math.max(0, ((s.value - s.min) / (s.max - s.min)) * 100));

const selectDeviceFromModal = (deviceId: number) => {
    selectedDevice.value = props.devices.data.find((d: any) => d.id === deviceId);
    showDeviceModal.value = false;
};
</script>

<template>
    <AppLayout title="Pro Dashboard">
        <Head title="Pro Dashboard" />

        <div class="dashboard-pro">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <DashboardHeader
                    :selected-device="selectedDevice"
                    :display-connection-status="{ text: isRealTimeActive ? 'LIVE' : 'OFFLINE', color: isRealTimeActive ? 'green' : 'red' }"
                    @open-modal="showDeviceModal = true"
                    class="w-full md:w-auto"
                />
                
                <button 
                    @click="isConfigMode = !isConfigMode"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
                    :class="isConfigMode ? 'bg-racing-cyan text-black font-bold shadow-[0_0_15px_rgba(0,240,255,0.5)]' : 'bg-white/5 text-white hover:bg-white/10'"
                >
                    <Settings2 v-if="!isConfigMode" size="18" />
                    <Check v-else size="18" />
                    {{ isConfigMode ? 'Finalizar Config' : 'Configurar Dash' }}
                </button>
            </div>

            <!-- Dashboard Content -->
            <div class="gauges-grid-pro">
                
                <!-- MAP SECTION (Maintains high visibility) -->
                <div class="gps-section-pro span-full lg:span-2">
                    <div class="gps-header-pro">
                        <div class="live-badge-pro" :class="{ 'inactive': !isRealTimeActive }">
                            <span class="live-dot-pro"></span>
                            {{ isRealTimeActive ? 'EN VIVO' : 'OFFLINE' }}
                        </div>
                        <div class="gps-coords-pro font-mono text-xs opacity-70">
                            {{ gpsData.lat.toFixed(5) }}, {{ gpsData.lng.toFixed(5) }}
                        </div>
                    </div>
                    <div class="map-container relative group" style="height: 380px; border-radius: 0.75rem; overflow: hidden;">
                        <MapWidget ref="mapWidgetRef" :selected-vehicle="selectedVehicle" />
                        <div class="absolute top-4 left-4 flex gap-2 pointer-events-none">
                            <div class="bg-black/80 backdrop-blur px-3 py-1.5 rounded border border-white/10 flex flex-col">
                                <span class="text-[10px] text-text-muted uppercase font-bold">Velocidad GPS</span>
                                <span class="text-xl font-black text-racing-cyan font-mono">{{ gpsData.vel_kmh }} <span class="text-[10px]">KM/H</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT BLOCK: Principal Performance -->
                <div class="widget-group-pro span-full lg:span-2">
                    <div class="group-header-pro">
                        <span class="flex items-center gap-2">
                            <div class="w-1 h-3 bg-racing-green rounded-full"></div>
                            Engine Performance
                        </span>
                        <span class="text-[10px] opacity-40">REAL-TIME TELEMETRY</span>
                    </div>
                    <div class="group-content-pro">
                        <template v-for="sensor in groups.find(g => g.id === 'performance')?.sensors" :key="sensor.pid">
                            <div class="relative group">
                                <!-- Widget Picker Overlay -->
                                <div v-if="isConfigMode" class="absolute inset-0 z-10 bg-black/80 backdrop-blur flex flex-col items-center justify-center gap-2 rounded-lg border border-racing-cyan/50">
                                    <span class="text-[8px] text-racing-cyan font-bold uppercase">{{ sensor.name }}</span>
                                    <div class="flex gap-1.5">
                                        <button 
                                            v-for="type in WIDGET_TYPES" :key="type.id"
                                            @click="savePreference(sensor.pid, type.id)"
                                            class="p-1.5 rounded bg-white/5 hover:bg-racing-cyan hover:text-black transition-colors"
                                            :title="type.label"
                                            :class="{ 'bg-racing-cyan text-black': getWidgetType(sensor) === type.id }"
                                        >
                                            <component :is="type.icon" size="14" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Actual Widget content -->
                                <div class="flex flex-col items-center justify-center p-2 rounded-lg bg-white/[0.02]">
                                    <template v-if="getWidgetType(sensor) === 'gauge'">
                                        <div class="gauge-circular !w-24 !h-24">
                                            <div class="gauge-arc-bg-pro"></div>
                                            <div class="gauge-arc-fill-pro" :class="{ 'speed': sensor.pid !== '0x0C' }" :style="{ transform: `rotate(${getRotation(sensor)}deg)` }"></div>
                                            <div class="gauge-value-large-pro !text-lg">{{ Math.round(sensor.value) }}</div>
                                        </div>
                                        <span class="text-[10px] uppercase font-bold text-text-muted mt-1">{{ sensor.name }}</span>
                                    </template>
                                    <template v-else-if="getWidgetType(sensor) === 'box'">
                                        <div class="text-2xl font-black text-white font-mono">{{ sensor.value }}</div>
                                        <span class="text-[8px] text-racing-cyan uppercase font-bold">{{ sensor.unit }}</span>
                                        <span class="text-[10px] font-bold text-text-muted mt-1">{{ sensor.name }}</span>
                                    </template>
                                    <template v-else>
                                        <div class="w-full space-y-2">
                                            <div class="flex justify-between items-center px-1">
                                                <span class="text-[9px] font-bold text-text-secondary">{{ sensor.name }}</span>
                                                <span class="text-[10px] font-bold text-white">{{ sensor.value }}{{ sensor.unit }}</span>
                                            </div>
                                            <div class="pressure-bar-pro h-2">
                                                <div class="pressure-bar-fill-pro" :style="{ width: `${getPercent(sensor)}%` }"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- DYNAMIC BLOCKS: Temps, Pressures, etc. -->
                <div v-for="group in groups.filter(g => g.id !== 'performance')" :key="group.id" class="widget-group-pro">
                    <div class="group-header-pro">
                        <span class="flex items-center gap-2">
                            <div class="w-1 h-3 rounded-full" :style="{ backgroundColor: group.accent }"></div>
                            {{ group.title }}
                        </span>
                    </div>
                    <div class="space-y-3">
                        <div v-for="sensor in group.sensors" :key="sensor.pid" class="relative">
                            <!-- Config Mode inside group list -->
                            <div v-if="isConfigMode" class="absolute -right-1 -top-1 z-10 flex gap-1">
                                <button 
                                    v-for="type in WIDGET_TYPES" :key="type.id"
                                    @click="savePreference(sensor.pid, type.id)"
                                    class="p-1 rounded bg-black border border-white/10 hover:border-racing-cyan transition-colors"
                                    :class="{ 'text-racing-cyan border-racing-cyan': getWidgetType(sensor) === type.id }"
                                >
                                    <component :is="type.icon" size="10" />
                                </button>
                            </div>

                            <!-- List Item View -->
                            <div class="flex flex-col gap-1.5 p-2 rounded bg-white/[0.02]">
                                <div class="flex justify-between items-end">
                                    <span class="text-[10px] text-text-secondary font-bold uppercase">{{ sensor.name }}</span>
                                    <span class="text-sm font-black italic text-white font-mono">{{ sensor.value }} <span class="text-[8px] not-italic opacity-40">{{ sensor.unit }}</span></span>
                                </div>
                                <div v-if="getWidgetType(sensor) === 'bar' || getWidgetType(sensor) === 'thermometer'" class="pressure-bar-pro h-1.5">
                                    <div class="pressure-bar-fill-pro" :style="{ width: `${getPercent(sensor)}%`, backgroundColor: group.accent }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GEAR & BRAKES: Compact Fixed -->
                <div class="gauge-card-pro flex-row gap-4 items-center">
                    <div class="flex-1 bg-white/5 rounded-lg flex flex-col items-center justify-center p-3 border-l-4 border-racing-cyan">
                        <span class="text-[10px] text-text-muted font-bold uppercase mb-1">GEAR</span>
                        <span class="text-5xl font-black text-white font-mono leading-none drop-shadow-[0_0_15px_rgba(0,240,255,0.4)]">{{ gearSensor?.value || 'N' }}</span>
                    </div>
                    <div class="flex-1 bg-orange-500/10 rounded-lg flex flex-col items-center justify-center p-3 border-r-4 border-racing-orange">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 rounded bg-white/10 flex items-center justify-center">⛽</div>
                            <span class="text-xl font-black text-white">20%</span>
                        </div>
                        <div class="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                            <div class="bg-racing-orange h-full shadow-[0_0_10px_orange]" style="width: 20%"></div>
                        </div>
                    </div>
                </div>

                <!-- TIRES: Ultra Compact Grid -->
                <div class="widget-group-pro">
                    <div class="group-header-pro">Neumáticos (PSI / °F)</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div v-for="pos in ['FL', 'FR', 'RL', 'RR']" :key="pos" class="bg-black/40 p-2 rounded border border-white/5 relative overflow-hidden">
                            <div class="text-[8px] text-text-muted font-bold mb-1">{{ pos }}</div>
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-black text-racing-cyan font-mono leading-none">28.7</span>
                                <span class="text-[10px] font-bold text-racing-orange">102°</span>
                            </div>
                            <div class="absolute bottom-0 left-0 h-[2px] bg-racing-cyan" style="width: 70%"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modals -->
        <DeviceSelectModal :show="showDeviceModal" :devices="props.devices" />
    </AppLayout>
</template>

<style scoped>
@import '../../css/dashboard-pro.css';

/* Micro adjustments to make it "curious" and responsive */
.dashboard-pro {
    background: radial-gradient(circle at top left, #0d1117, #05070a);
}

.gauge-circular {
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.pressure-bar-fill-pro {
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.widget-group-pro:hover {
    border-color: rgba(255, 255, 255, 0.15);
}
</style>
