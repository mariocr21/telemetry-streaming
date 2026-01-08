<script setup lang="ts">
/**
 * DashboardDynamic.vue
 * 
 * Page component that uses the new database-driven dynamic dashboard.
 * This replaces the hardcoded DashboardPro approach.
 */
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DynamicDashboard from '@/components/Dashboard/DynamicDashboard.vue';
import VehicleSelectorFloat from '@/components/Dashboard/VehicleSelectorFloat.vue';
import { ref, computed } from 'vue';
import type { DashboardConfig, TelemetryData } from '@/types/dashboard.d';

// Vehicle type
interface AvailableVehicle {
    id: number;
    name: string;
    make: string | null;
    model: string | null;
    year: number | null;
    nickname: string | null;
    license_plate: string | null;
    vin: string | null;
    client?: {
        id: number;
        full_name: string;
        company: string | null;
    } | null;
}

// Props from Inertia (server-side)
interface Props {
    vehicleId: number;
    preloadedConfig?: DashboardConfig;
    availableVehicles?: AvailableVehicle[];
    isSuperAdmin?: boolean;
}

const props = defineProps<Props>();

// Events from dashboard
const currentConfig = ref<DashboardConfig | null>(null);
const lastTelemetry = ref<TelemetryData>({});
const isConnected = ref(false);

// Current vehicle
const currentVehicle = computed(() => {
    return props.availableVehicles?.find(v => v.id === props.vehicleId) || null;
});

function handleConfigLoaded(config: DashboardConfig) {
    currentConfig.value = config;
    console.log('[DashboardDynamic] Config loaded:', config.layout.name);
}

function handleTelemetryUpdate(data: TelemetryData) {
    lastTelemetry.value = data;
}

function handleConnectionChange(connected: boolean) {
    isConnected.value = connected;
}

function handleVehicleSelect(vehicleId: number) {
    // Navigate to the new vehicle's dashboard
    router.visit(`/dashboard-dynamic/${vehicleId}`, {
        preserveState: false,
    });
}
</script>

<template>
    <Head :title="currentVehicle?.name || currentConfig?.layout?.name || 'Live Dashboard'" />

    <!-- Dashboard sin AppLayout para que el tema funcione correctamente -->
    <DynamicDashboard 
        :vehicle-id="vehicleId"
        :preloaded-config="preloadedConfig"
        @config-loaded="handleConfigLoaded"
        @telemetry-update="handleTelemetryUpdate"
        @connection-change="handleConnectionChange"
    />

    <!-- Floating Vehicle Selector - Always show to display current vehicle -->
    <VehicleSelectorFloat
        v-if="availableVehicles && availableVehicles.length >= 1"
        :vehicles="availableVehicles"
        :current-vehicle-id="vehicleId"
        :current-vehicle="currentVehicle"
        :is-super-admin="isSuperAdmin"
        :is-connected="isConnected"
        @select="handleVehicleSelect"
    />
</template>
