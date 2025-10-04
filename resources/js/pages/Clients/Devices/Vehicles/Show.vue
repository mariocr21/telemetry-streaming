<script setup lang="ts">
import ExportSensorDataModal from '@/components/ExportSensorDataModal.vue';
import Badge from '@/components/ui/Badge.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import SimpleDropdown from '@/components/ui/SimpleDropdown.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    Calendar,
    Car,
    CheckCircle2,
    Clock,
    Copy,
    Cpu,
    Download,
    Edit,
    ExternalLink,
    FileText,
    Filter,
    Gauge,
    Info,
    MapPin,
    MoreVertical,
    Palette,
    Power,
    PowerOff,
    RefreshCw,
    Settings,
    Trash2,
    TrendingUp,
    WifiOff,
    Wrench,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Sensor {
    id: number;
    pid: string;
    name: string;
    description?: string;
    category: string;
    unit: string;
    data_type: string;
    is_standard: boolean;
}

interface VehicleSensor {
    id: number;
    is_active: boolean;
    frequency_seconds: number;
    min_value?: number;
    max_value?: number;
    last_reading_at?: string;
    sensor: Sensor;
    recent_registers: Array<{
        id: number;
        value: number;
        recorded_at: string;
        recorded_at_human: string;
    }>;
    stats?: {
        min: number;
        max: number;
        avg: number;
        current: number;
        count: number;
    };
}

interface Vehicle {
    id: number;
    make?: string;
    model?: string;
    year?: number;
    license_plate?: string;
    color?: string;
    nickname?: string;
    vin?: string;
    protocol?: string;
    status: boolean;
    auto_detected: boolean;
    is_configured: boolean;
    first_reading_at?: string;
    last_reading_at?: string;
    created_at: string;
    supported_pids?: any;
}

interface Device {
    id: number;
    device_name: string;
    mac_address: string;
    status: string;
    device_inventory?: {
        model: string;
        serial_number: string;
    };
}

interface Client {
    id: number;
    full_name: string;
    email: string;
}

interface RecentActivity {
    id: number;
    value: number;
    recorded_at: string;
    recorded_at_human: string;
    sensor_name: string;
    sensor_unit: string;
    sensor_category: string;
}

interface Props {
    client: Client;
    device: Device;
    vehicle: Vehicle;
    sensors: VehicleSensor[];
    sensors_by_category: Record<string, VehicleSensor[]>;
    vehicle_stats: {
        total_sensors: number;
        active_sensors: number;
        sensors_with_recent_data: number;
        configuration_progress: number;
    };
    recent_activity: RecentActivity[];
    can: {
        view: boolean;
        update: boolean;
        delete: boolean;
        configure_sensors: boolean;
    };
}

const props = defineProps<Props>();
const page = usePage();

// Estado reactivo
const copied = ref('');
const selectedCategory = ref('all');
const isRefreshing = ref(false);
const showSensorConfig = ref(false);
const selectedSensor = ref<VehicleSensor | null>(null);

// Computadas
const flashMessage = computed(() => {
    const flash = page.props.flash as any;
    return flash?.message;
});

const vehicleDisplayName = computed(() => {
    const parts = [];
    if (props.vehicle.nickname) {
        return `"${props.vehicle.nickname}"`;
    }
    if (props.vehicle.make) parts.push(props.vehicle.make);
    if (props.vehicle.model) parts.push(props.vehicle.model);
    if (props.vehicle.year) parts.push(props.vehicle.year.toString());
    return parts.length > 0 ? parts.join(' ') : 'Vehículo';
});

const isOnline = computed(() => {
    if (!props.vehicle.last_reading_at) return false;

    const lastReading = new Date(props.vehicle.last_reading_at);
    const now = new Date();
    const diffMinutes = (now.getTime() - lastReading.getTime()) / (1000 * 60);

    return diffMinutes < 30; // Consideramos online si la última lectura fue hace menos de 30 minutos
});

const filteredSensors = computed(() => {
    if (selectedCategory.value === 'all') {
        return props.sensors;
    }
    return props.sensors.filter((sensor) => sensor.sensor.category === selectedCategory.value);
});

const categories = computed(() => {
    const cats = new Set(props.sensors.map((sensor) => sensor.sensor.category));
    return Array.from(cats).sort();
});

const getStatusBadge = (status: boolean) => {
    return status
        ? { text: 'Activo', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }
        : { text: 'Inactivo', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' };
};

const getCategoryIcon = (category: string) => {
    const icons: Record<string, any> = {
        engine: Cpu,
        fuel: Zap,
        diagnostics: AlertTriangle,
        vehicle: Car,
        default: Gauge,
    };
    return icons[category] || icons.default;
};

const getCategoryColor = (category: string) => {
    const colors: Record<string, string> = {
        engine: 'text-blue-600',
        fuel: 'text-yellow-600',
        diagnostics: 'text-red-600',
        vehicle: 'text-green-600',
        default: 'text-gray-600',
    };
    return colors[category] || colors.default;
};

const formatValue = (value: number, unit: string) => {
    return `${value}${unit ? ` ${unit}` : ''}`;
};

const getHealthStatus = (sensor: VehicleSensor) => {
    if (!sensor.stats) return { status: 'unknown', color: 'text-gray-400' };

    const { current, min: sensorMin, max: sensorMax } = sensor.stats;
    const configMin = sensor.min_value;
    const configMax = sensor.max_value;

    // Verificar límites configurados
    if (configMin !== null && current < configMin) {
        return { status: 'low', color: 'text-red-500' };
    }
    if (configMax !== null && current > configMax) {
        return { status: 'high', color: 'text-red-500' };
    }

    // Verificar actividad reciente
    if (!sensor.last_reading_at) {
        return { status: 'no_data', color: 'text-gray-400' };
    }

    const lastReading = new Date(sensor.last_reading_at);
    const hoursSinceReading = (Date.now() - lastReading.getTime()) / (1000 * 60 * 60);

    if (hoursSinceReading > 2) {
        return { status: 'stale', color: 'text-yellow-500' };
    }

    return { status: 'good', color: 'text-green-500' };
};

// Métodos
const copyToClipboard = async (text: string, type: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = type;
        setTimeout(() => {
            copied.value = '';
        }, 2000);
    } catch (err) {
        console.error('Error al copiar:', err);
    }
};

const toggleSensor = async (vehicleSensor: VehicleSensor) => {
    try {
        const response = await fetch(route('clients.devices.vehicles.toggle-sensor', [props.client.id, props.device.id, props.vehicle.id]), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                vehicle_sensor_id: vehicleSensor.id,
            }),
        });

        if (response.ok) {
            // Actualizar el estado local
            vehicleSensor.is_active = !vehicleSensor.is_active;
        }
    } catch (error) {
        console.error('Error toggling sensor:', error);
    }
};

const openSensorConfig = (sensor: VehicleSensor) => {
    selectedSensor.value = sensor;
    showSensorConfig.value = true;
};

const refreshData = () => {
    isRefreshing.value = true;
    router.reload({
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clientes', href: '/clients' },
    { title: props.client.full_name, href: `/clients/${props.client.id}` },
    { title: 'Dispositivos', href: `/clients/${props.client.id}/devices` },
    { title: props.device.device_name, href: `/clients/${props.client.id}/devices/${props.device.id}` },
    { title: vehicleDisplayName.value, href: `/clients/${props.client.id}/devices/${props.device.id}/vehicles/${props.vehicle.id}` },
];
const showExportModal = ref(false);
</script>

<template>
    <Head :title="`${vehicleDisplayName} - ${device.device_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header -->
        <template #header>
            <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
                <div class="flex items-center space-x-4">
                    <Link :href="route('clients.devices.show', [client.id, device.id])">
                        <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Volver al Dispositivo
                        </Button>
                    </Link>

                    <div class="flex items-center space-x-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 shadow-lg">
                            <Car class="h-8 w-8 text-white" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ vehicleDisplayName }}
                            </h1>
                            <div class="mt-2 flex items-center space-x-4">
                                <Badge :class="getStatusBadge(vehicle.status).class">
                                    {{ getStatusBadge(vehicle.status).text }}
                                </Badge>
                                <span v-if="vehicle.license_plate" class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ vehicle.license_plate }}
                                </span>
                                <div v-if="isOnline" class="flex items-center space-x-1 text-green-600">
                                    <Activity class="h-4 w-4" />
                                    <span class="text-sm">Datos recientes</span>
                                </div>
                                <div v-else class="flex items-center space-x-1 text-gray-400">
                                    <WifiOff class="h-4 w-4" />
                                    <span class="text-sm">Sin datos recientes</span>
                                </div>
                                <div class="flex items-center space-x-1 text-blue-600">
                                    <Gauge class="h-4 w-4" />
                                    <span class="text-sm">{{ vehicle_stats.active_sensors }}/{{ vehicle_stats.total_sensors }} sensores</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button @click="refreshData" :disabled="isRefreshing" variant="outline" size="sm">
                        <RefreshCw :class="['mr-2 h-4 w-4', { 'animate-spin': isRefreshing }]" />
                        Actualizar
                    </Button>

                    <Link v-if="can?.update" :href="route('clients.devices.vehicles.edit', [client.id, device.id, vehicle.id])">
                        <Button variant="outline" size="sm">
                            <Edit class="mr-2 h-4 w-4" />
                            Editar
                        </Button>
                    </Link>

                    <SimpleDropdown align="right">
                        <template #trigger>
                            <Button variant="outline" size="sm">
                                <MoreVertical class="h-4 w-4" />
                            </Button>
                        </template>

                        <Link
                            v-if="can?.update"
                            :href="route('clients.devices.vehicles.edit', [client.id, device.id, vehicle.id])"
                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <Edit class="mr-2 h-4 w-4" />
                            Editar Vehículo
                        </Link>

                        <button
                            v-if="vehicle.vin"
                            @click="copyToClipboard(vehicle.vin, 'vin')"
                            class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <Copy class="mr-2 h-4 w-4" />
                            Copiar VIN
                        </button>

                        <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                        <button
                            v-if="can?.configure_sensors"
                            class="flex w-full items-center px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                        >
                            <Settings class="mr-2 h-4 w-4" />
                            Configurar Sensores
                        </button>

                        <button class="flex w-full items-center px-4 py-2 text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20">
                            <Download class="mr-2 h-4 w-4" />
                            Exportar Datos
                        </button>

                        <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                        <button
                            v-if="can?.delete"
                            class="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Eliminar Vehículo
                        </button>
                    </SimpleDropdown>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Mensajes Flash -->
                <div
                    v-if="flashMessage"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm dark:border-green-800 dark:bg-green-900/20"
                >
                    <div class="flex items-center">
                        <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-green-400" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                {{ flashMessage }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Notificación de copiado -->
                <div v-if="copied" class="rounded-lg border border-blue-200 bg-blue-50 p-4 shadow-sm dark:border-blue-800 dark:bg-blue-900/20">
                    <div class="flex items-center">
                        <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-blue-400" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                {{ copied === 'vin' ? 'VIN copiado al portapapeles' : 'Información copiada' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas Generales -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div class="flex items-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                                <Gauge class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sensores</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ vehicle_stats.total_sensors }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div class="flex items-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/20">
                                <CheckCircle2 class="h-6 w-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sensores Activos</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ vehicle_stats.active_sensors }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div class="flex items-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/20">
                                <Activity class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Con Datos Recientes</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ vehicle_stats.sensors_with_recent_data }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div class="flex items-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/20">
                                <TrendingUp class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Configuración</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ vehicle_stats.configuration_progress }}%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido principal -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Columna principal -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Información del Vehículo -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center text-lg">
                                    <Car class="mr-2 h-5 w-5 text-orange-600" />
                                    Información del Vehículo
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div class="space-y-4">
                                        <div v-if="vehicle.make || vehicle.model">
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Marca y Modelo</h4>
                                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                {{ vehicle.make }} {{ vehicle.model }}
                                                <span v-if="vehicle.year" class="text-gray-500">({{ vehicle.year }})</span>
                                            </p>
                                        </div>

                                        <div v-if="vehicle.license_plate">
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Placa</h4>
                                            <div class="flex items-center space-x-2">
                                                <MapPin class="h-4 w-4 text-gray-400" />
                                                <span class="font-mono font-medium text-gray-900 dark:text-gray-100">
                                                    {{ vehicle.license_plate }}
                                                </span>
                                            </div>
                                        </div>

                                        <div v-if="vehicle.color">
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Color</h4>
                                            <div class="flex items-center space-x-2">
                                                <Palette class="h-4 w-4 text-gray-400" />
                                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ vehicle.color }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div v-if="vehicle.vin">
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">VIN</h4>
                                            <div class="flex items-center space-x-2">
                                                <FileText class="h-4 w-4 text-gray-400" />
                                                <span class="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ vehicle.vin }}
                                                </span>
                                                <button
                                                    @click="copyToClipboard(vehicle.vin, 'vin')"
                                                    class="rounded p-1 text-gray-400 hover:text-gray-600"
                                                    title="Copiar VIN"
                                                >
                                                    <Copy class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>

                                        <div>
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Estado de Configuración</h4>
                                            <div class="flex items-center space-x-2">
                                                <CheckCircle2 v-if="vehicle.is_configured" class="h-5 w-5 text-green-500" />
                                                <AlertCircle v-else class="h-5 w-5 text-yellow-500" />
                                                <span class="font-medium">
                                                    {{ vehicle.is_configured ? 'Configurado' : 'Pendiente' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div v-if="vehicle.protocol">
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Protocolo OBD2</h4>
                                            <div class="flex items-center space-x-2">
                                                <Badge variant="secondary">{{ vehicle.protocol }}</Badge>
                                                <span v-if="vehicle.auto_detected" class="flex items-center space-x-1 text-green-600">
                                                    <CheckCircle2 class="h-3 w-3" />
                                                    <span class="text-xs">Auto-detectado</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div>
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Dispositivo</h4>
                                            <Link
                                                :href="route('clients.devices.show', [client.id, device.id])"
                                                class="flex items-center space-x-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                <span class="font-medium">{{ device.device_name }}</span>
                                                <ExternalLink class="h-3 w-3" />
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Filtros de Sensores -->
                        <Card>
                            <CardHeader>
                                <div class="flex items-center justify-between">
                                    <CardTitle class="flex items-center text-lg">
                                        <Gauge class="mr-2 h-5 w-5 text-blue-600" />
                                        Sensores ({{ filteredSensors.length }})

                                        <button
                                            @click="showExportModal = true"
                                            class="flex w-full items-center px-4 py-2 text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20"
                                        >
                                            <Download class="mr-2 h-4 w-4" />
                                            Exportar Datos
                                        </button>
                                    </CardTitle>

                                    <div class="flex items-center space-x-2">
                                        <Filter class="h-4 w-4 text-gray-400" />
                                        <select
                                            v-model="selectedCategory"
                                            class="rounded border border-gray-300 px-3 py-1 text-sm dark:border-gray-600 dark:bg-gray-700"
                                        >
                                            <option value="all">Todas las categorías</option>
                                            <option v-for="category in categories" :key="category" :value="category">
                                                {{ category.charAt(0).toUpperCase() + category.slice(1) }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="filteredSensors.length === 0" class="py-8 text-center">
                                    <Gauge class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No hay sensores en esta categoría</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Los sensores aparecerán aquí cuando el vehículo sea detectado.
                                    </p>
                                </div>

                                <div v-else class="space-y-4">
                                    <div
                                        v-for="vehicleSensor in filteredSensors"
                                        :key="vehicleSensor.id"
                                        class="rounded-lg border border-gray-200 p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50"
                                    >
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3">
                                                    <div
                                                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-400 to-blue-600"
                                                    >
                                                        <component :is="getCategoryIcon(vehicleSensor.sensor.category)" class="h-5 w-5 text-white" />
                                                    </div>
                                                    <div>
                                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                                            {{ vehicleSensor.sensor.name }}
                                                            <Badge variant="secondary" class="ml-2 text-xs">{{ vehicleSensor.sensor.pid }}</Badge>
                                                        </h3>
                                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                                            {{ vehicleSensor.sensor.description || 'Sin descripción' }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="mt-3 grid grid-cols-2 gap-4 md:grid-cols-4">
                                                    <div>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Estado</p>
                                                        <div class="mt-1 flex items-center space-x-2">
                                                            <div
                                                                :class="[
                                                                    'h-2 w-2 rounded-full',
                                                                    vehicleSensor.is_active ? 'bg-green-500' : 'bg-red-500',
                                                                ]"
                                                            ></div>
                                                            <span class="text-sm">
                                                                {{ vehicleSensor.is_active ? 'Activo' : 'Inactivo' }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div v-if="vehicleSensor.stats">
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Valor Actual</p>
                                                        <div class="mt-1 flex items-center space-x-1">
                                                            <span :class="['text-sm font-medium', getHealthStatus(vehicleSensor).color]">
                                                                {{ formatValue(vehicleSensor.stats.current, vehicleSensor.sensor.unit) }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div v-if="vehicleSensor.stats">
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Promedio</p>
                                                        <span class="text-sm">
                                                            {{ formatValue(vehicleSensor.stats.avg, vehicleSensor.sensor.unit) }}
                                                        </span>
                                                    </div>

                                                    <div>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Frecuencia</p>
                                                        <span class="text-sm">{{ vehicleSensor.frequency_seconds }}s</span>
                                                    </div>
                                                </div>

                                                <div v-if="vehicleSensor.last_reading_at" class="mt-2 text-xs text-gray-500">
                                                    Última lectura: {{ new Date(vehicleSensor.last_reading_at).toLocaleString('es-ES') }}
                                                </div>
                                            </div>

                                            <div class="flex items-center space-x-2">
                                                <Button
                                                    @click="toggleSensor(vehicleSensor)"
                                                    size="sm"
                                                    :variant="vehicleSensor.is_active ? 'outline' : 'default'"
                                                    :class="
                                                        vehicleSensor.is_active ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'
                                                    "
                                                >
                                                    <Power v-if="!vehicleSensor.is_active" class="h-4 w-4" />
                                                    <PowerOff v-else class="h-4 w-4" />
                                                </Button>

                                                <Button @click="openSensorConfig(vehicleSensor)" size="sm" variant="ghost">
                                                    <Wrench class="h-4 w-4" />
                                                </Button>

                                                <SimpleDropdown align="right">
                                                    <template #trigger>
                                                        <Button size="sm" variant="ghost">
                                                            <MoreVertical class="h-4 w-4" />
                                                        </Button>
                                                    </template>

                                                    <button
                                                        class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                    >
                                                        <BarChart3 class="mr-2 h-4 w-4" />
                                                        Ver Gráfico
                                                    </button>

                                                    <button
                                                        class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                    >
                                                        <Download class="mr-2 h-4 w-4" />
                                                        Exportar Datos
                                                    </button>

                                                    <button
                                                        @click="openSensorConfig(vehicleSensor)"
                                                        class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                    >
                                                        <Settings class="mr-2 h-4 w-4" />
                                                        Configurar
                                                    </button>

                                                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                                                    <button
                                                        @click="toggleSensor(vehicleSensor)"
                                                        :class="[
                                                            'flex w-full items-center px-4 py-2 text-sm',
                                                            vehicleSensor.is_active
                                                                ? 'text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20'
                                                                : 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20',
                                                        ]"
                                                    >
                                                        <PowerOff v-if="vehicleSensor.is_active" class="mr-2 h-4 w-4" />
                                                        <Power v-else class="mr-2 h-4 w-4" />
                                                        {{ vehicleSensor.is_active ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                </SimpleDropdown>
                                            </div>
                                        </div>

                                        <!-- Datos recientes del sensor -->
                                        <div
                                            v-if="vehicleSensor.recent_registers.length > 0"
                                            class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700"
                                        >
                                            <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Lecturas Recientes</h4>
                                            <div class="flex space-x-2 overflow-x-auto pb-2">
                                                <div
                                                    v-for="register in vehicleSensor.recent_registers.slice(0, 5)"
                                                    :key="register.id"
                                                    class="flex-shrink-0 rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800"
                                                >
                                                    <div class="font-mono">{{ formatValue(register.value, vehicleSensor.sensor.unit) }}</div>
                                                    <div class="text-gray-500">{{ register.recorded_at_human }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Actividad Reciente -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center text-lg">
                                    <Activity class="mr-2 h-5 w-5 text-purple-600" />
                                    Actividad Reciente
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <template v-for="activity in recent_activity.slice(0, 10)" :key="activity.id">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/20">
                                                <component
                                                    :is="getCategoryIcon(activity.sensor_category)"
                                                    class="h-4 w-4 text-purple-600 dark:text-purple-400"
                                                />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ activity.sensor_name }}
                                                </p>
                                                <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                                                    <span class="font-mono">{{ formatValue(activity.value, activity.sensor_unit) }}</span>
                                                    <span>•</span>
                                                    <span>{{ activity.recorded_at_human }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <div v-if="recent_activity.length === 0" class="py-4 text-center">
                                        <Activity class="mx-auto h-8 w-8 text-gray-400" />
                                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No hay actividad reciente</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Información del Sistema -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center text-lg">
                                    <Info class="mr-2 h-5 w-5 text-gray-600" />
                                    Información del Sistema
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">ID del Vehículo</h4>
                                    <p class="rounded bg-gray-100 px-2 py-1 font-mono text-sm dark:bg-gray-800">#{{ vehicle.id }}</p>
                                </div>

                                <div v-if="vehicle.first_reading_at">
                                    <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Primera Lectura</h4>
                                    <div class="flex items-center space-x-2">
                                        <Calendar class="h-4 w-4 text-gray-400" />
                                        <div>
                                            <p class="font-medium">
                                                {{
                                                    new Date(vehicle.first_reading_at).toLocaleDateString('es-ES', {
                                                        day: '2-digit',
                                                        month: '2-digit',
                                                        year: 'numeric',
                                                    })
                                                }}
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{
                                                    new Date(vehicle.first_reading_at).toLocaleTimeString('es-ES', {
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="vehicle.last_reading_at">
                                    <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Última Lectura</h4>
                                    <div class="flex items-center space-x-2">
                                        <Clock class="h-4 w-4 text-gray-400" />
                                        <div>
                                            <p class="font-medium">
                                                {{
                                                    new Date(vehicle.last_reading_at).toLocaleDateString('es-ES', {
                                                        day: '2-digit',
                                                        month: '2-digit',
                                                        year: 'numeric',
                                                    })
                                                }}
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{
                                                    new Date(vehicle.last_reading_at).toLocaleTimeString('es-ES', {
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-1 text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Registro</h4>
                                    <div class="flex items-center space-x-2">
                                        <Calendar class="h-4 w-4 text-gray-400" />
                                        <p class="font-medium">
                                            {{
                                                new Date(vehicle.created_at).toLocaleDateString('es-ES', {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    year: 'numeric',
                                                })
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- PIDs Soportados -->
                                <div v-if="vehicle.supported_pids && Object.keys(vehicle.supported_pids).length > 0">
                                    <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">PIDs Soportados</h4>
                                    <div class="max-h-32 overflow-y-auto">
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="(supported, pid) in vehicle.supported_pids"
                                                :key="pid"
                                                :variant="supported ? 'default' : 'secondary'"
                                                class="text-xs"
                                            >
                                                {{ pid }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Estado de Salud -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Estado de Salud</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Configuración básica</span>
                                        <CheckCircle2 v-if="vehicle.is_configured" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-yellow-500" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Sensores configurados</span>
                                        <div class="flex items-center space-x-2">
                                            <CheckCircle2 v-if="vehicle_stats.active_sensors > 0" class="h-4 w-4 text-green-500" />
                                            <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                                            <span class="text-xs text-gray-500">
                                                {{ vehicle_stats.active_sensors }}/{{ vehicle_stats.total_sensors }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Datos recientes</span>
                                        <CheckCircle2 v-if="isOnline" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Protocolo detectado</span>
                                        <CheckCircle2 v-if="vehicle.protocol" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Auto-detección</span>
                                        <CheckCircle2 v-if="vehicle.auto_detected" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-gray-400" />
                                    </div>

                                    <!-- Barra de progreso -->
                                    <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                                        <div class="mb-2 flex items-center justify-between text-sm">
                                            <span class="text-gray-500">Estado general</span>
                                            <span class="font-medium"> {{ vehicle_stats.configuration_progress }}% </span>
                                        </div>
                                        <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div
                                                class="h-2 rounded-full bg-gradient-to-r from-blue-500 to-green-500 transition-all duration-300"
                                                :style="{ width: `${vehicle_stats.configuration_progress}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
        <ExportSensorDataModal
            v-model:open="showExportModal"
            :sensors="sensors"
            :vehicle-id="vehicle.id"
            :client-id="client.id"
            :device-id="device.id"
        />
    </AppLayout>
</template>
