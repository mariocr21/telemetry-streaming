<script setup lang="ts">
/**
 * TelemetryDashboardPro.vue - Live Telemetry Dashboard
 * Neurona Off Road Telemetry - Professional Racing Interface
 * 
 * Designed for: Rugged tablets in off-road racing vehicles (Baja 1000, Dakar)
 * Features:
 * - Maximum readability in motion
 * - Dark Mode optimized
 * - D3.js-powered gauges
 * - Real-time data streaming
 * - Responsive grid layout
 */

import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { throttle } from 'lodash';
import { useResizeObserver, useWindowSize } from '@vueuse/core';
import * as d3 from 'd3';

// Layout Components
import AppLayout from '@/layouts/AppLayout.vue';
import TelemetryGridLayout from '@/components/Dashboard/TelemetryGridLayout.vue';
import TelemetryWidget from '@/components/Dashboard/TelemetryWidget.vue';

// D3 Gauge Components
import RadialGaugeD3 from '@/components/Dashboard/RadialGaugeD3.vue';
import LinearBarD3 from '@/components/Dashboard/LinearBarD3.vue';

// Existing Components
import MapWidget from '@/components/Dashboard/MapWidget.vue';
import DashboardHeader from '@/components/DashboardHeader.vue';
import DeviceSelectModal from '@/components/DeviceSelectModal.vue';

// Icons
import { 
    Activity, 
    Gauge, 
    Thermometer, 
    Fuel, 
    Zap, 
    Navigation, 
    Radio,
    Settings2,
    Maximize2,
    AlertTriangle
} from 'lucide-vue-next';

// Props
const props = defineProps<{ devices: any }>();

// === STATE ===
const selectedDevice = ref<any>(null);
const selectedVehicle = ref<any>(null);
const showDeviceModal = ref(false);
const isRealTimeActive = ref(false);
const isFullscreen = ref(false);
const mapWidgetRef = ref<any>(null);

// Telemetry Data
const liveSensors = ref<Record<string, any>>({});
const gpsData = ref({
    lat: 31.411774,
    lng: -115.728470,
    alt_m: 840.9,
    rumbo: 135,
    vel_kmh: 0
});

// === COMPUTED ===
const connectionStatus = computed(() => ({
    text: isRealTimeActive.value ? 'LIVE' : 'OFFLINE',
    color: isRealTimeActive.value ? 'green' : 'red',
    status: isRealTimeActive.value ? 'normal' as const : 'offline' as const
}));

// Primary gauges
const rpmValue = computed(() => liveSensors.value['0x0C']?.value ?? 0);
const speedValue = computed(() => liveSensors.value['0x0D']?.value ?? liveSensors.value['vel_kmh']?.value ?? gpsData.value.vel_kmh ?? 0);
const throttleValue = computed(() => liveSensors.value['0x11']?.value ?? 0);
const gearValue = computed(() => liveSensors.value['GEAR']?.value ?? 'N');

// Temperatures
const coolantTemp = computed(() => liveSensors.value['0x05']?.value ?? 0);
const oilTemp = computed(() => liveSensors.value['0x0B']?.value ?? 0);
const transTemp = computed(() => liveSensors.value['0x06']?.value ?? 0);

// Other sensors
const fuelLevel = computed(() => liveSensors.value['0x2F']?.value ?? 0);
const batteryVoltage = computed(() => liveSensors.value['0x42']?.value ?? 0);
const intakeAirTemp = computed(() => liveSensors.value['0x0F']?.value ?? 0);

// Status helpers
const getTemperatureStatus = (value: number, warnAt: number, critAt: number) => {
    if (value >= critAt) return 'critical' as const;
    if (value >= warnAt) return 'warning' as const;
    return 'normal' as const;
};

// RPM threshold configuration
const rpmThresholds = [
    { value: 65, color: '#00ff9d' },  // Green zone 0-65%
    { value: 85, color: '#ff8a00' },  // Orange zone 65-85%
    { value: 100, color: '#ff003c' }  // Red zone 85-100%
];

// Speed thresholds (KM/H)
const speedThresholds = [
    { value: 70, color: '#00f0ff' },
    { value: 90, color: '#ff8a00' },
    { value: 100, color: '#ff003c' }
];

// Temperature thresholds (Fahrenheit)
const tempThresholds = [
    { value: 60, color: '#00ff9d' },
    { value: 80, color: '#ff8a00' },
    { value: 100, color: '#ff003c' }
];

// === TELEMETRY UPDATES ===
const handleTelemetryUpdate = (payload: any) => {
    if (payload.vehicle_id !== selectedVehicle.value?.id) return;
    isRealTimeActive.value = true;

    if (payload.data) {
        Object.entries(payload.data).forEach(([pid, data]: [string, any]) => {
            const value = data.processed_value ?? data.value ?? 0;
            
            // GPS data
            if (['lat', 'lng', 'alt_m', 'rumbo', 'vel_kmh'].includes(pid)) {
                (gpsData.value as any)[pid] = value;
                return;
            }
            
            // Sensor data
            liveSensors.value[pid] = {
                pid,
                value,
                name: data.name || pid,
                unit: data.unit || '',
                min: data.min_value ?? 0,
                max: data.max_value ?? 100
            };
        });

        nextTick(() => mapWidgetRef.value?.updateGpsData?.(gpsData.value));
    }
};

// === DEMO MODE ===
let demoInterval: any = null;

const startDemo = () => {
    // Initial values
    const mockData = [
        { pid: '0x0C', name: 'RPM', value: 3500, min: 0, max: 8000, unit: 'RPM' },
        { pid: '0x0D', name: 'Speed', value: 72, min: 0, max: 200, unit: 'MPH' },
        { pid: '0x11', name: 'Throttle', value: 45, min: 0, max: 100, unit: '%' },
        { pid: '0x05', name: 'Coolant Temp', value: 195, min: 100, max: 280, unit: '°F' },
        { pid: '0x0B', name: 'Oil Temp', value: 210, min: 100, max: 280, unit: '°F' },
        { pid: '0x06', name: 'Trans Temp', value: 185, min: 100, max: 280, unit: '°F' },
        { pid: '0x0F', name: 'Intake Air', value: 95, min: 32, max: 180, unit: '°F' },
        { pid: '0x2F', name: 'Fuel Level', value: 68, min: 0, max: 100, unit: '%' },
        { pid: '0x42', name: 'Battery', value: 14.2, min: 10, max: 16, unit: 'V' },
        { pid: 'GEAR', name: 'Gear', value: '3', min: 0, max: 6, unit: '' }
    ];

    mockData.forEach(sensor => {
        liveSensors.value[sensor.pid] = sensor;
    });

    isRealTimeActive.value = true;

    // Simulate live updates
    demoInterval = setInterval(() => {
        // RPM variation
        if (liveSensors.value['0x0C']) {
            liveSensors.value['0x0C'].value = Math.round(
                3000 + Math.sin(Date.now() / 1000) * 800 + Math.random() * 200
            );
        }
        
        // Speed variation
        if (liveSensors.value['0x0D']) {
            liveSensors.value['0x0D'].value = Math.round(
                65 + Math.sin(Date.now() / 2000) * 15 + Math.random() * 5
            );
        }

        // Throttle variation
        if (liveSensors.value['0x11']) {
            liveSensors.value['0x11'].value = Math.round(
                40 + Math.sin(Date.now() / 800) * 25 + Math.random() * 10
            );
        }

        // Slight temperature variations
        if (liveSensors.value['0x05']) {
            liveSensors.value['0x05'].value = Math.round(
                195 + Math.random() * 5 - 2.5
            );
        }

        // GPS velocity
        gpsData.value.vel_kmh = Math.round(
            100 + Math.sin(Date.now() / 2000) * 20
        );

    }, 150);
};

// === FULLSCREEN ===
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
        isFullscreen.value = true;
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
};

// === LIFECYCLE ===
onMounted(() => {
    startDemo();
});

onUnmounted(() => {
    if (demoInterval) clearInterval(demoInterval);
});

// === HANDLERS ===
const selectDeviceFromModal = (deviceId: number) => {
    selectedDevice.value = props.devices.data.find((d: any) => d.id === deviceId);
    showDeviceModal.value = false;
};
</script>

<template>
    <AppLayout title="Live Telemetry">
        <Head title="Live Telemetry" />

        <div class="telemetry-dashboard">
            <!-- === HEADER BAR === -->
            <header class="dashboard-header-bar">
                <div class="header-left">
                    <DashboardHeader
                        :selected-device="selectedDevice"
                        :display-connection-status="connectionStatus"
                        @open-modal="showDeviceModal = true"
                    />
                </div>
                
                <div class="header-right">
                    <button 
                        class="header-btn"
                        @click="toggleFullscreen"
                        :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'"
                    >
                        <Maximize2 :size="18" />
                    </button>
                    <button class="header-btn">
                        <Settings2 :size="18" />
                    </button>
                </div>
            </header>

            <!-- === MAIN DASHBOARD GRID === -->
            <TelemetryGridLayout :columns="12" gap="0.75rem" padding="1rem">
                
                <!-- === PRIMARY GAUGES ROW === -->
                
                <!-- RPM Gauge -->
                <TelemetryWidget 
                    title="RPM" 
                    :col-span="3" 
                    :status="rpmValue > 6000 ? 'critical' : rpmValue > 5000 ? 'warning' : 'normal'"
                    accent="#ff003c"
                >
                    <RadialGaugeD3
                        :value="rpmValue"
                        :min="0"
                        :max="8000"
                        label="ENGINE RPM"
                        unit="RPM"
                        :thresholds="rpmThresholds"
                        :arc-width="14"
                        :tick-count="9"
                    />
                </TelemetryWidget>

                <!-- Speed Gauge -->
                <TelemetryWidget 
                    title="SPEED" 
                    :col-span="3"
                    :status="speedValue > 160 ? 'critical' : speedValue > 120 ? 'warning' : 'normal'"
                    accent="#00f0ff"
                >
                    <RadialGaugeD3
                        :value="speedValue"
                        :min="0"
                        :max="200"
                        label="VELOCITY"
                        unit="MPH"
                        :thresholds="speedThresholds"
                        :arc-width="14"
                        :tick-count="9"
                    />
                </TelemetryWidget>

                <!-- Gear Display -->
                <TelemetryWidget 
                    title="TRANSMISSION" 
                    :col-span="2"
                    accent="#00f0ff"
                >
                    <div class="gear-display">
                        <span class="gear-value" :class="{ 'is-neutral': gearValue === 'N' }">
                            {{ gearValue }}
                        </span>
                        <span class="gear-label">GEAR</span>
                    </div>
                </TelemetryWidget>

                <!-- Throttle Bar -->
                <TelemetryWidget 
                    title="THROTTLE" 
                    :col-span="4"
                    accent="#00ff9d"
                >
                    <LinearBarD3
                        :value="throttleValue"
                        :min="0"
                        :max="100"
                        label="THROTTLE POSITION"
                        unit="%"
                        :height="24"
                        :thresholds="[
                            { value: 70, color: '#00ff9d' },
                            { value: 90, color: '#ff8a00' },
                            { value: 100, color: '#ff003c' }
                        ]"
                    />
                    
                    <!-- Brake Indicator -->
                    <div class="brake-indicator mt-3">
                        <span class="brake-label">BRAKE</span>
                        <div class="brake-bar">
                            <div class="brake-fill" :style="{ width: '0%' }" />
                        </div>
                    </div>
                </TelemetryWidget>

                <!-- === MAP SECTION === -->
                <TelemetryWidget 
                    title="LIVE POSITION" 
                    :col-span="8"
                    :row-span="2"
                    accent="#00f0ff"
                    :status="connectionStatus.status"
                >
                    <template #header-right>
                        <div class="live-badge">
                            <span class="live-dot" />
                            {{ connectionStatus.text }}
                        </div>
                    </template>
                    
                    <div class="map-container">
                        <MapWidget ref="mapWidgetRef" :selected-vehicle="selectedVehicle" />
                        
                        <!-- GPS Overlay -->
                        <div class="gps-overlay">
                            <div class="gps-stat">
                                <Navigation :size="14" class="gps-icon" />
                                <span class="gps-coords">
                                    {{ gpsData.lat.toFixed(5) }}, {{ gpsData.lng.toFixed(5) }}
                                </span>
                            </div>
                            <div class="gps-stat">
                                <span class="gps-label">ALT</span>
                                <span class="gps-value">{{ gpsData.alt_m.toFixed(0) }}m</span>
                            </div>
                            <div class="gps-stat">
                                <span class="gps-label">GPS SPD</span>
                                <span class="gps-value highlight">{{ gpsData.vel_kmh }} km/h</span>
                            </div>
                        </div>
                    </div>
                </TelemetryWidget>

                <!-- === TEMPERATURES COLUMN === -->
                <TelemetryWidget 
                    title="TEMPERATURES" 
                    :col-span="4"
                    :row-span="2"
                    accent="#ff8a00"
                    :status="getTemperatureStatus(Math.max(coolantTemp, oilTemp, transTemp), 220, 250)"
                >
                    <div class="temps-container">
                        <LinearBarD3
                            :value="coolantTemp"
                            :min="100"
                            :max="280"
                            label="COOLANT"
                            unit="°F"
                            :height="18"
                            variant="thermometer"
                            :thresholds="[
                                { value: 55, color: '#00ff9d' },
                                { value: 75, color: '#ff8a00' },
                                { value: 100, color: '#ff003c' }
                            ]"
                        />
                        
                        <LinearBarD3
                            :value="oilTemp"
                            :min="100"
                            :max="280"
                            label="OIL"
                            unit="°F"
                            :height="18"
                            variant="thermometer"
                            :thresholds="[
                                { value: 55, color: '#00ff9d' },
                                { value: 75, color: '#ff8a00' },
                                { value: 100, color: '#ff003c' }
                            ]"
                        />
                        
                        <LinearBarD3
                            :value="transTemp"
                            :min="100"
                            :max="280"
                            label="TRANS"
                            unit="°F"
                            :height="18"
                            variant="thermometer"
                            :thresholds="[
                                { value: 55, color: '#00ff9d' },
                                { value: 75, color: '#ff8a00' },
                                { value: 100, color: '#ff003c' }
                            ]"
                        />

                        <LinearBarD3
                            :value="intakeAirTemp"
                            :min="32"
                            :max="180"
                            label="INTAKE AIR"
                            unit="°F"
                            :height="18"
                            variant="compact"
                            :thresholds="[
                                { value: 60, color: '#00ff9d' },
                                { value: 80, color: '#ff8a00' },
                                { value: 100, color: '#ff003c' }
                            ]"
                        />
                    </div>
                </TelemetryWidget>

                <!-- === FUEL & BATTERY === -->
                <TelemetryWidget 
                    title="FUEL" 
                    :col-span="3"
                    accent="#cc00ff"
                    :status="fuelLevel < 15 ? 'critical' : fuelLevel < 25 ? 'warning' : 'normal'"
                >
                    <div class="fuel-display">
                        <Fuel :size="28" class="fuel-icon" />
                        <div class="fuel-info">
                            <span class="fuel-value">{{ fuelLevel }}%</span>
                            <LinearBarD3
                                :value="fuelLevel"
                                :min="0"
                                :max="100"
                                :height="10"
                                :show-scale="false"
                                variant="compact"
                                :thresholds="[
                                    { value: 25, color: '#ff003c' },
                                    { value: 50, color: '#ff8a00' },
                                    { value: 100, color: '#cc00ff' }
                                ]"
                            />
                        </div>
                    </div>
                </TelemetryWidget>

                <TelemetryWidget 
                    title="BATTERY" 
                    :col-span="3"
                    accent="#ffee00"
                    :status="batteryVoltage < 12 ? 'critical' : batteryVoltage < 12.5 ? 'warning' : 'normal'"
                >
                    <div class="battery-display">
                        <Zap :size="28" class="battery-icon" />
                        <div class="battery-info">
                            <span class="battery-value">{{ batteryVoltage.toFixed(1) }}V</span>
                            <span class="battery-status">{{ batteryVoltage >= 13.5 ? 'CHARGING' : 'NORMAL' }}</span>
                        </div>
                    </div>
                </TelemetryWidget>

                <!-- === TIRES GRID === -->
                <TelemetryWidget 
                    title="TIRES" 
                    :col-span="6"
                    accent="#00f0ff"
                >
                    <div class="tires-grid">
                        <div v-for="pos in ['FL', 'FR', 'RL', 'RR']" :key="pos" class="tire-card">
                            <span class="tire-pos">{{ pos }}</span>
                            <div class="tire-data">
                                <span class="tire-pressure">28.7</span>
                                <span class="tire-unit">PSI</span>
                            </div>
                            <span class="tire-temp">102°F</span>
                        </div>
                    </div>
                </TelemetryWidget>

            </TelemetryGridLayout>
        </div>

        <!-- === MODALS === -->
        <DeviceSelectModal 
            :show="showDeviceModal" 
            :devices="props.devices"
            @close="showDeviceModal = false"
            @select="selectDeviceFromModal"
        />
    </AppLayout>
</template>

<style scoped>
/* === DASHBOARD CONTAINER === */
.telemetry-dashboard {
    min-height: 100vh;
    background: linear-gradient(135deg, #050505 0%, #0a0c10 50%, #05070a 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* === HEADER BAR === */
.dashboard-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: rgba(5, 5, 5, 0.9);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(12px);
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-left {
    flex: 1;
}

.header-right {
    display: flex;
    gap: 0.5rem;
}

.header-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: all 0.2s ease;
}

.header-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #00f0ff;
    border-color: rgba(0, 240, 255, 0.3);
}

/* === GEAR DISPLAY === */
.gear-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    gap: 0.5rem;
}

.gear-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 4rem;
    font-weight: 900;
    color: #00f0ff;
    line-height: 1;
    text-shadow: 0 0 40px rgba(0, 240, 255, 0.5);
}

.gear-value.is-neutral {
    color: #ff8a00;
    text-shadow: 0 0 40px rgba(255, 138, 0, 0.5);
}

.gear-label {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    color: rgba(255, 255, 255, 0.4);
}

/* === LIVE BADGE === */
.live-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.6rem;
    background: rgba(0, 255, 157, 0.1);
    border: 1px solid rgba(0, 255, 157, 0.3);
    border-radius: 1rem;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #00ff9d;
}

.live-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #00ff9d;
    box-shadow: 0 0 8px #00ff9d;
    animation: dot-blink 1.5s ease-in-out infinite;
}

@keyframes dot-blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* === MAP CONTAINER === */
.map-container {
    position: relative;
    height: 100%;
    min-height: 300px;
    border-radius: 0.5rem;
    overflow: hidden;
}

.gps-overlay {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    z-index: 10;
}

.gps-stat {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.6rem;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    border-radius: 0.35rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.gps-icon {
    color: #00f0ff;
}

.gps-coords {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.65rem;
    color: rgba(255, 255, 255, 0.7);
}

.gps-label {
    font-size: 0.55rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.4);
}

.gps-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.7rem;
    font-weight: 700;
    color: white;
}

.gps-value.highlight {
    color: #00f0ff;
    text-shadow: 0 0 8px rgba(0, 240, 255, 0.4);
}

/* === TEMPERATURES === */
.temps-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    height: 100%;
}

/* === BRAKE INDICATOR === */
.brake-indicator {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.brake-label {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.4);
    min-width: 40px;
}

.brake-bar {
    flex: 1;
    height: 8px;
    background: rgba(255, 0, 60, 0.15);
    border-radius: 4px;
    overflow: hidden;
}

.brake-fill {
    height: 100%;
    background: #ff003c;
    box-shadow: 0 0 12px rgba(255, 0, 60, 0.6);
    transition: width 0.1s ease;
}

/* === FUEL DISPLAY === */
.fuel-display {
    display: flex;
    align-items: center;
    gap: 1rem;
    height: 100%;
}

.fuel-icon {
    color: #cc00ff;
    filter: drop-shadow(0 0 8px rgba(204, 0, 255, 0.4));
}

.fuel-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.fuel-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.8rem;
    font-weight: 900;
    color: #cc00ff;
}

/* === BATTERY DISPLAY === */
.battery-display {
    display: flex;
    align-items: center;
    gap: 1rem;
    height: 100%;
}

.battery-icon {
    color: #ffee00;
    filter: drop-shadow(0 0 8px rgba(255, 238, 0, 0.4));
}

.battery-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.battery-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.8rem;
    font-weight: 900;
    color: #ffee00;
}

.battery-status {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.4);
}

/* === TIRES GRID === */
.tires-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    height: 100%;
}

.tire-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.75rem 0.5rem;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    position: relative;
    overflow: hidden;
}

.tire-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, #00f0ff, transparent);
}

.tire-pos {
    font-size: 0.55rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.4);
}

.tire-data {
    display: flex;
    align-items: baseline;
    gap: 0.15rem;
}

.tire-pressure {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.25rem;
    font-weight: 900;
    color: #00f0ff;
}

.tire-unit {
    font-size: 0.5rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
}

.tire-temp {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.7rem;
    font-weight: 700;
    color: #ff8a00;
}

/* === RESPONSIVE === */
@media (max-width: 1024px) {
    .tires-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .gear-value {
        font-size: 3rem;
    }
}

@media (max-width: 768px) {
    .dashboard-header-bar {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .header-left {
        width: 100%;
    }
    
    .header-right {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
