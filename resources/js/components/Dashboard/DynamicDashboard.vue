<script setup lang="ts">
/**
 * DynamicDashboard.vue (Strict Grid Edition)
 * 
 * Main component that renders a dashboard dynamically using a strict 12-column grid.
 * The Map acts as a fixed 'Hero' element (Span 8) at the start.
 */
import { computed, provide, onMounted, onUnmounted, watch } from 'vue';
import { useDashboardConfig } from '@/composables/useDashboardConfig';
import { useTelemetryBinding } from '@/composables/useTelemetryBinding';
import GroupCard from './GroupCard.vue';
import ShiftLightsBar from './widgets/ShiftLightsBar.vue';
import MapWidget from './MapWidget.vue'; // Keep map available if needed as a widget
import { AlertCircle, Loader2, RefreshCw, Play, Pause } from 'lucide-vue-next';
import type { TelemetryData, DashboardConfig } from '@/types/dashboard.d';

// Props
interface Props {
    vehicleId: number;
    preloadedConfig?: DashboardConfig;
}

const props = defineProps<Props>();

// Emits
const emit = defineEmits<{
    (e: 'configLoaded', config: DashboardConfig): void;
    (e: 'telemetryUpdate', data: TelemetryData): void;
    (e: 'connectionChange', connected: boolean): void;
}>();

// Fetch dashboard configuration
const { 
    config, 
    loading, 
    error, 
    isEmpty,
    refresh 
} = useDashboardConfig(props.vehicleId, {
    preloadedConfig: props.preloadedConfig,
});

// Telemetry Binding
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

// Provide telemetry
provide('telemetryData', telemetryData);
provide('isConnected', isConnected);
provide('connectionStatus', connectionStatus);
provide('getValue', getValue);

// Computed: Theme class
const themeClass = computed(() => {
    return config.value?.layout?.theme || 'cyberpunk-dark';
});

// Computed: Show shift lights
const showShiftLights = computed(() => {
    return config.value?.special_components?.shift_lights?.enabled ?? false;
});

const shiftLightsRpm = computed(() => {
    const slConfig = config.value?.special_components?.shift_lights;
    if (!showShiftLights.value || !slConfig) return 0;
    
    // Try to find the sensor key in bindings (legacy/backend mapped) or config (direct from DB)
    const rpmKey = slConfig.bindings?.rpm || slConfig.config?.rpmSensorKey || 'RPM';
    
    const value = getValue<number>(rpmKey, 0);
    // console.log(`[ShiftLights] Key: ${rpmKey}, Value: ${value}`, slConfig); // Debug log
    return value;
});

// Computed: All groups sorted by sort_order
const sortedGroups = computed(() => {
    if (!config.value?.groups) return [];
    // Ensure we sort by the explicit sort_order from DB
    return [...config.value.groups].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
});

// Computed: Connection visual states
const connectionStatusClass = computed(() => {
    if (isConnected.value) return 'bg-green-500/20 text-green-400 border border-green-500/30';
    if (connectionStatus.value === 'connecting') return 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30';
    return 'bg-red-500/20 text-red-400 border border-red-500/30';
});

const connectionDotClass = computed(() => {
    if (isConnected.value) return 'bg-green-400 animate-pulse';
    if (connectionStatus.value === 'connecting') return 'bg-yellow-400 animate-pulse';
    return 'bg-red-400';
});

const connectionLabel = computed(() => {
    if (isConnected.value) return 'Live';
    if (connectionStatus.value === 'connecting') return 'Connecting...';
    return 'Offline';
});

// Watchers
watch(config, (newConfig) => { if (newConfig) emit('configLoaded', newConfig); }, { immediate: true });
watch(isConnected, (connected) => emit('connectionChange', connected));
watch(telemetryData, (data) => emit('telemetryUpdate', data), { deep: true });

onMounted(() => subscribe());
onUnmounted(() => unsubscribe());
</script>

<template>
    <div 
        class="dynamic-dashboard dashboard-container"
        :data-theme="themeClass"
    >
        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center h-screen">
            <div class="text-center">
                <Loader2 class="w-12 h-12 animate-spin mx-auto mb-4 text-[var(--neurona-primary)]" />
                <p class="text-lg opacity-70">Cargando telemetría...</p>
            </div>
        </div>
        
        <!-- Error -->
        <div v-else-if="error" class="flex items-center justify-center h-screen">
            <div class="text-center max-w-md p-8 glass-card">
                <AlertCircle class="w-16 h-16 mx-auto mb-4 text-red-500" />
                <h2 class="text-xl font-semibold mb-2">Error de Configuración</h2>
                <p class="mb-6 opacity-70">{{ error }}</p>
                <button @click="refresh" class="btn-primary">Reintentar</button>
            </div>
        </div>
        
        <!-- Empty -->
        <div v-else-if="isEmpty" class="flex items-center justify-center h-screen">
            <div class="text-center max-w-md p-8 glass-card">
                <h2 class="text-xl font-semibold">Sin Configuración</h2>
                <p class="opacity-70">Este vehículo no tiene un dashboard configurado.</p>
            </div>
        </div>
        
        <!-- MAIN DASHBOARD CONTENT -->
        <div v-else-if="config" class="dashboard-wrapper">
            
            <!-- Top Bar: Shift Lights (Optional) -->
            <div v-if="showShiftLights" class="w-full mb-2 z-50 relative">
                <ShiftLightsBar :rpm="shiftLightsRpm" :config="config.special_components?.shift_lights?.config" />
            </div>
            
            <!-- THE GRID (12 Columns Strict) -->
            <div class="neurona-grid">
                
                <!-- 1. HERO MAP (Fixed Position: Top Left, 8 Cols, 2 Rows Height) -->
                <div 
                    v-if="config.special_components?.map?.enabled ?? true"
                    class="map-block bento-panel animate-fade-in"
                    style="grid-column: span 8; grid-row: span 2; min-height: 520px;"
                >
                    <div class="panel-header">
                        <div class="header-accent bg-[var(--neurona-accent)] shadow-[0_0_8px_var(--neurona-accent)]"></div>
                        <h3 class="panel-title">GPS TRACKING</h3>
                    </div>
                    <div class="panel-body p-0 relative h-full">
                        <MapWidget 
                            :selected-vehicle="{ id: vehicleId, make: 'Ford', model: 'Raptor' }"
                            :is-loading="false"
                            :is-real-time-active="isConnected"
                            :connection-status="connectionStatus"
                            :latitude="getValue('GPS_Latitude')" 
                            :longitude="getValue('GPS_Longitude')" 
                            :heading="getValue('GPS_Heading')"
                            :default-layer="config.special_components?.map?.config?.defaultLayer"
                        />
                    </div>
                </div>

                <!-- 2. Dynamic Groups (Will fill the remaining 4 cols on the right, then wrap below) -->
                <GroupCard
                    v-for="(group, index) in sortedGroups"
                    :key="group.id"
                    :group="group"
                    :telemetry-data="telemetryData"
                    class="animate-fade-in"
                    :style="{ animationDelay: `${index * 50}ms` }"
                />
            </div>

            <!-- Floating Status Bar -->
            <div class="fixed bottom-4 right-4 z-50 flex items-center gap-2">
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
                    :class="connectionStatusClass"
                >
                    <span class="w-2 h-2 rounded-full" :class="connectionDotClass"></span>
                    <span>{{ connectionLabel }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dashboard-wrapper {
    min-height: 100vh;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    background-color: var(--neurona-bg-deep);
    overflow-x: hidden;
}

/* 
 * STRICT 12-COLUMN GRID 
 */
.neurona-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr); /* Force equal width cols */
    grid-auto-rows: min-content;
    gap: 1rem; 
    width: 100%;
    max-width: 100%;
}

/* Re-use Bento Styles locally for the Map wrapper if needed */
.bento-panel {
    background: #0f1014;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.panel-header {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid rgba(255,255,255,0.03);
}

.header-accent {
    width: 3px;
    height: 14px;
    margin-right: 12px;
}

.panel-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.7); 
}

.panel-body {
    position: relative;
    flex: 1;
}

.btn-primary {
    padding: 0.5rem 1.5rem;
    background: var(--neurona-primary);
    color: black;
    font-weight: bold;
    border-radius: 0.5rem;
}

/* Animations */
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
    opacity: 0;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 1024px) {
    .neurona-grid {
        grid-template-columns: repeat(2, 1fr); /* 2 cols on tablet */
    }
}

@media (max-width: 768px) {
    .neurona-grid {
        display: flex;
        flex-direction: column;
    }
    .map-block {
        grid-column: span 12 !important; /* Force full width on mobile */
        min-height: 300px !important;
    }
}
</style>
