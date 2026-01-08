<script setup lang="ts">
/**
 * DashboardV2Config.vue - Sensor Mapping Configuration
 * 
 * Simple interface to map vehicle sensors to fixed dashboard slots.
 */
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import { 
    ArrowLeft, 
    Save, 
    Eye, 
    Gauge, 
    Thermometer, 
    Fuel, 
    Battery, 
    CircleDot,
    Loader2,
    Map,
    Video,
    Plus,
    Trash2
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

// Props
interface Sensor {
    id: number;
    sensor_key: string;
    label: string;
    unit: string;
}

interface Props {
    vehicleId: number;
    vehicle: {
        id: number;
        make: string;
        model: string;
        nickname?: string;
    };
    sensors: Sensor[];
    currentMapping: Record<string, string | null>;
    shiftLightsConfig: {
        enabled: boolean;
        maxRpm: number;
        shiftRpm: number;
        startRpm: number;
    };
    cameraConfig?: {
        streamBaseUrl: string;
        cameras: Array<{ channelId: string; label: string }>;
    };
}

const props = defineProps<Props>();

// Slot definitions with categories
const slotDefinitions = [
    { 
        category: 'Engine Performance', 
        icon: Gauge,
        slots: [
            { key: 'rpm', label: 'RPM', description: 'Engine revolutions per minute' },
            { key: 'speed', label: 'Vehicle Speed', description: 'Current speed in MPH/KPH' },
            { key: 'throttle', label: 'Throttle Position', description: 'Accelerator pedal position %' },
            { key: 'gear', label: 'Current Gear', description: 'Transmission gear' },
        ]
    },
    {
        category: 'Fluids',
        icon: Fuel,
        slots: [
            { key: 'oil_temp', label: 'Oil Temperature', description: 'Engine oil temp' },
            { key: 'fuel_press', label: 'Fuel Pressure', description: 'Fuel system pressure PSI' },
        ]
    },
    {
        category: 'Temperatures',
        icon: Thermometer,
        slots: [
            { key: 'coolant_temp', label: 'Coolant Temperature', description: 'Engine coolant temp' },
            { key: 'trans_temp', label: 'Transmission Temperature', description: 'Transmission fluid temp' },
            { key: 'intake_temp', label: 'Intake Air Temperature', description: 'Air intake temp' },
        ]
    },
    {
        category: 'Electrical',
        icon: Battery,
        slots: [
            { key: 'voltage', label: 'Battery Voltage', description: 'System voltage' },
            { key: 'amps', label: 'Current Draw', description: 'Electrical current' },
        ]
    },
    {
        category: 'Tires',
        icon: CircleDot,
        slots: [
            { key: 'tire_fl_psi', label: 'Front Left PSI', description: 'FL tire pressure' },
            { key: 'tire_fl_temp', label: 'Front Left Temp', description: 'FL tire temperature' },
            { key: 'tire_fr_psi', label: 'Front Right PSI', description: 'FR tire pressure' },
            { key: 'tire_fr_temp', label: 'Front Right Temp', description: 'FR tire temperature' },
            { key: 'tire_rl_psi', label: 'Rear Left PSI', description: 'RL tire pressure' },
            { key: 'tire_rl_temp', label: 'Rear Left Temp', description: 'RL tire temperature' },
            { key: 'tire_rr_psi', label: 'Rear Right PSI', description: 'RR tire pressure' },
            { key: 'tire_rr_temp', label: 'Rear Right Temp', description: 'RR tire temperature' },
        ]
    },
    {
        category: 'GPS',
        icon: Map,
        slots: [
            { key: 'gps_lat', label: 'Latitude', description: 'GPS latitude coordinate' },
            { key: 'gps_lon', label: 'Longitude', description: 'GPS longitude coordinate' },
            { key: 'gps_heading', label: 'Heading', description: 'Direction of travel (degrees)' },
        ]
    },
];

// State
const mapping = ref<Record<string, string | null>>({ ...props.currentMapping });
const shiftConfig = ref({ ...props.shiftLightsConfig });
const cameraConfig = ref({
    streamBaseUrl: props.cameraConfig?.streamBaseUrl || 'https://stream.neurona.xyz',
    cameras: props.cameraConfig?.cameras || [],
});
const saving = ref(false);
const hasChanges = ref(false);

// Computed
const vehicleName = computed(() => props.vehicle.nickname || `${props.vehicle.make} ${props.vehicle.model}`);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Dashboard V2', href: `/dashboard-v2/${props.vehicleId}` },
    { title: 'Configuración', href: '#' },
];

const mappedCount = computed(() => {
    return Object.values(mapping.value).filter(v => v !== null && v !== '').length;
});

const totalSlots = computed(() => {
    return slotDefinitions.reduce((sum, cat) => sum + cat.slots.length, 0);
});

// Methods
function updateMapping(slotKey: string, sensorKey: string | null) {
    mapping.value[slotKey] = sensorKey === '' ? null : sensorKey;
    hasChanges.value = true;
}

function addCamera() {
    cameraConfig.value.cameras.push({ channelId: '', label: `Cámara ${cameraConfig.value.cameras.length + 1}` });
    hasChanges.value = true;
}

function removeCamera(index: number) {
    cameraConfig.value.cameras.splice(index, 1);
    hasChanges.value = true;
}

function updateCamera(index: number, field: 'channelId' | 'label', value: string) {
    cameraConfig.value.cameras[index][field] = value;
    hasChanges.value = true;
}

async function saveConfig() {
    saving.value = true;
    
    try {
        const response = await fetch(`/api/dashboard-v2/${props.vehicleId}/config`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                mapping: mapping.value,
                shiftLightsConfig: shiftConfig.value,
                cameraConfig: cameraConfig.value,
            }),
        });
        
        if (response.ok) {
            hasChanges.value = false;
        } else {
            alert('Error al guardar la configuración');
        }
    } catch (error) {
        console.error('Save error:', error);
        alert('Error de conexión');
    } finally {
        saving.value = false;
    }
}

function previewDashboard() {
    window.open(`/dashboard-v2/${props.vehicleId}`, '_blank');
}
</script>

<template>
    <Head :title="`Configurar Dashboard V2 - ${vehicleName}`" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header -->
        <div class="mb-6 px-4 pt-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="`/dashboard-v2/${vehicleId}`">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                    
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                            Configurar Dashboard V2
                        </h1>
                        <p class="text-sm text-gray-500">
                            {{ vehicleName }} • {{ mappedCount }}/{{ totalSlots }} sensores mapeados
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="previewDashboard" class="gap-2">
                        <Eye class="h-4 w-4" />
                        Vista Previa
                    </Button>
                    
                    <Button 
                        @click="saveConfig" 
                        :disabled="saving"
                        :class="hasChanges ? 'ring-2 ring-orange-500 ring-offset-2' : ''"
                        class="gap-2"
                    >
                        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        {{ saving ? 'Guardando...' : (hasChanges ? 'Guardar *' : 'Guardar') }}
                    </Button>
                </div>
            </div>
        </div>

        <div class="pb-6">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                
                <!-- Shift Lights Config -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Gauge class="h-5 w-5 text-yellow-500" />
                            Configuración Shift Lights
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Activar
                                </label>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input 
                                        v-model="shiftConfig.enabled" 
                                        type="checkbox" 
                                        class="sr-only peer"
                                        @change="hasChanges = true"
                                    />
                                    <div class="w-11 h-6 bg-gray-300 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                                </label>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    RPM Inicio
                                </label>
                                <input
                                    v-model.number="shiftConfig.startRpm"
                                    type="number"
                                    step="500"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    @change="hasChanges = true"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    RPM Cambio
                                </label>
                                <input
                                    v-model.number="shiftConfig.shiftRpm"
                                    type="number"
                                    step="500"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    @change="hasChanges = true"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    RPM Máximo
                                </label>
                                <input
                                    v-model.number="shiftConfig.maxRpm"
                                    type="number"
                                    step="500"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    @change="hasChanges = true"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Sensor Mapping by Category -->
                <Card v-for="category in slotDefinitions" :key="category.category">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <component :is="category.icon" class="h-5 w-5 text-cyan-500" />
                            {{ category.category }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div 
                            v-for="slot in category.slots" 
                            :key="slot.key"
                            class="flex items-center justify-between gap-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50"
                        >
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ slot.label }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ slot.description }}
                                </div>
                            </div>
                            <div class="w-64">
                                <select
                                    :value="mapping[slot.key] || ''"
                                    @change="updateMapping(slot.key, ($event.target as HTMLSelectElement).value)"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                >
                                    <option value="">-- Sin asignar --</option>
                                    <option 
                                        v-for="sensor in sensors" 
                                        :key="sensor.sensor_key" 
                                        :value="sensor.sensor_key"
                                    >
                                        {{ sensor.label }} ({{ sensor.unit }})
                                    </option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Cameras Configuration -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Video class="h-5 w-5 text-cyan-500" />
                            Live Cameras
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Stream Base URL -->
                        <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        URL Base del Stream
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Servidor MediaMTX (ej: https://stream.neurona.xyz)
                                    </div>
                                </div>
                                <div class="w-64">
                                    <input
                                        v-model="cameraConfig.streamBaseUrl"
                                        type="text"
                                        placeholder="https://stream.neurona.xyz"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                        @change="hasChanges = true"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Camera List -->
                        <div 
                            v-for="(camera, index) in cameraConfig.cameras" 
                            :key="index"
                            class="flex items-center gap-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50"
                        >
                            <div class="flex-1 grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Etiqueta
                                    </label>
                                    <input
                                        :value="camera.label"
                                        @input="updateCamera(index, 'label', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        placeholder="Cámara 1"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        ID Canal
                                    </label>
                                    <input
                                        :value="camera.channelId"
                                        @input="updateCamera(index, 'channelId', ($event.target as HTMLInputElement).value)"
                                        type="text"
                                        placeholder="movil1"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                            </div>
                            <button
                                @click="removeCamera(index)"
                                class="p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Add Camera Button -->
                        <button
                            @click="addCamera"
                            class="w-full flex items-center justify-center gap-2 p-3 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-500 hover:border-cyan-500 hover:text-cyan-500 transition-colors"
                        >
                            <Plus class="h-4 w-4" />
                            Agregar Cámara
                        </button>

                        <!-- Helper Text -->
                        <p class="text-xs text-gray-500 text-center">
                            Las cámaras aparecerán en la sección inferior del dashboard. Máximo recomendado: 4 cámaras.
                        </p>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>
</template>
