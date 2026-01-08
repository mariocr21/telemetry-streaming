<script setup lang="ts">
/**
 * Admin/Clients/Show.vue
 * 
 * Client detail page with hierarchical view:
 * Client -> Devices -> Vehicles -> Sensors
 */
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    User, Smartphone, Car, Cpu, ChevronDown, ChevronRight, 
    Plus, Edit, Trash2, Eye, Settings, Play, ArrowLeft,
    Mail, Phone, Building2, MapPin, Calendar, Activity
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

// Types
interface Sensor {
    id: number;
    sensor_id: number;
    name: string;
    pid: string;
    unit: string;
    is_active: boolean;
}

interface Vehicle {
    id: number;
    make: string | null;
    model: string | null;
    year: number | null;
    nickname: string | null;
    license_plate: string | null;
    vin: string | null;
    status: boolean;
    sensors_count: number;
    has_dashboard: boolean;
    sensors: Sensor[];
}

interface Device {
    id: number;
    device_name: string;
    mac_address: string;
    status: string;
    last_ping: string | null;
    inventory: { serial_number: string; model: string } | null;
    vehicles: Vehicle[];
    vehicles_count: number;
}

interface ClientUser {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    last_login: string | null;
}

interface Client {
    id: number;
    first_name?: string;
    last_name?: string;
    full_name: string;
    email: string;
    phone?: string | null;
    company?: string | null;
    city?: string | null;
    country?: string | null;
    address?: string | null;
    created_at: string;
}

interface Stats {
    devices_count: number;
    vehicles_count: number;
    sensors_count: number;
    users_count: number;
}

// Props
const props = defineProps<{
    client: Client;
    devices: Device[];
    users: ClientUser[];
    availableInventory: { id: number; serial_number: string; model: string }[];
    stats: Stats;
    error?: string;
}>();

// State
const expandedDevices = ref<Set<number>>(new Set(props.devices?.map(d => d.id) || []));
const expandedVehicles = ref<Set<number>>(new Set());
const activeTab = ref<'overview' | 'users'>('overview');

// Toggle functions
const toggleDevice = (deviceId: number) => {
    if (expandedDevices.value.has(deviceId)) {
        expandedDevices.value.delete(deviceId);
    } else {
        expandedDevices.value.add(deviceId);
    }
};

const toggleVehicle = (vehicleId: number) => {
    if (expandedVehicles.value.has(vehicleId)) {
        expandedVehicles.value.delete(vehicleId);
    } else {
        expandedVehicles.value.add(vehicleId);
    }
};

// Get display name for vehicle
const getVehicleDisplayName = (vehicle: Vehicle): string => {
    if (vehicle.nickname) return vehicle.nickname;
    const parts = [vehicle.make, vehicle.model].filter(Boolean);
    if (parts.length) {
        return vehicle.year ? `${parts.join(' ')} (${vehicle.year})` : parts.join(' ');
    }
    return vehicle.license_plate || vehicle.vin || 'Vehículo sin nombre';
};

// Initials for avatar
const initials = computed(() => {
    const first = props.client.first_name?.[0] || props.client.full_name?.[0] || 'C';
    const last = props.client.last_name?.[0] || props.client.full_name?.[1] || 'L';
    return (first + last).toUpperCase();
});
</script>

<template>
    <AppLayout title="Detalle de Cliente">
        <Head :title="`Cliente: ${client.full_name}`" />

        <div class="min-h-screen bg-gray-50 py-6 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Breadcrumb -->
                <nav class="mb-6 flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link href="/admin/clients" class="flex items-center hover:text-gray-700 dark:hover:text-gray-200">
                        <ArrowLeft class="mr-1 h-4 w-4" />
                        Catálogo de Clientes
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-white">{{ client.full_name }}</span>
                </nav>

                <!-- Header Card -->
                <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <!-- Client Info -->
                        <div class="flex items-start space-x-4">
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-2xl font-bold text-white shadow-lg">
                                {{ initials }}
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ client.full_name }}
                                </h1>
                                <div class="mt-1 flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="flex items-center">
                                        <Mail class="mr-1 h-4 w-4" />
                                        {{ client.email }}
                                    </span>
                                    <span v-if="client.phone" class="flex items-center">
                                        <Phone class="mr-1 h-4 w-4" />
                                        {{ client.phone }}
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="client.company" class="flex items-center">
                                        <Building2 class="mr-1 h-4 w-4" />
                                        {{ client.company }}
                                    </span>
                                    <span v-if="client.city || client.country" class="flex items-center">
                                        <MapPin class="mr-1 h-4 w-4" />
                                        {{ [client.city, client.country].filter(Boolean).join(', ') }}
                                    </span>
                                    <span class="flex items-center">
                                        <Calendar class="mr-1 h-4 w-4" />
                                        Desde {{ client.created_at }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm">
                                <Edit class="mr-2 h-4 w-4" />
                                Editar
                            </Button>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="mt-6 grid grid-cols-2 gap-4 border-t border-gray-200 pt-6 dark:border-gray-700 sm:grid-cols-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600">{{ stats.devices_count }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Dispositivos</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ stats.vehicles_count }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Vehículos</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ stats.sensors_count }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Sensores</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ stats.users_count }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Usuarios</div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="mb-6 flex space-x-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                    <button
                        @click="activeTab = 'overview'"
                        class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-all"
                        :class="activeTab === 'overview' 
                            ? 'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white' 
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    >
                        <Smartphone class="mr-2 inline h-4 w-4" />
                        Dispositivos y Vehículos
                    </button>
                    <button
                        @click="activeTab = 'users'"
                        class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-all"
                        :class="activeTab === 'users' 
                            ? 'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white' 
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    >
                        <User class="mr-2 inline h-4 w-4" />
                        Usuarios ({{ stats.users_count }})
                    </button>
                </div>

                <!-- Overview Tab: Hierarchical Tree -->
                <div v-if="activeTab === 'overview'" class="space-y-4">
                    
                    <!-- Empty State -->
                    <div v-if="devices.length === 0" class="rounded-2xl border-2 border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-600 dark:bg-gray-800">
                        <Smartphone class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Sin dispositivos</h3>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">
                            Este cliente no tiene dispositivos asignados.
                        </p>
                        <Button class="mt-4 bg-orange-600 hover:bg-orange-700">
                            <Plus class="mr-2 h-4 w-4" />
                            Asignar Dispositivo
                        </Button>
                    </div>

                    <!-- Devices List -->
                    <div v-for="device in devices" :key="device.id" class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        
                        <!-- Device Header -->
                        <button
                            @click="toggleDevice(device.id)"
                            class="flex w-full items-center justify-between p-4 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        >
                            <div class="flex items-center space-x-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 shadow-lg">
                                    <Smartphone class="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">
                                            {{ device.device_name }}
                                        </h3>
                                        <span 
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="device.status === 'active' 
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' 
                                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                        >
                                            {{ device.status === 'active' ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-3 text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="device.inventory">{{ device.inventory.serial_number }}</span>
                                        <span class="font-mono text-xs">{{ device.mac_address }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ device.vehicles_count }} vehículo{{ device.vehicles_count !== 1 ? 's' : '' }}
                                </span>
                                <component 
                                    :is="expandedDevices.has(device.id) ? ChevronDown : ChevronRight" 
                                    class="h-5 w-5 text-gray-400 transition-transform"
                                />
                            </div>
                        </button>

                        <!-- Device Content (Vehicles) -->
                        <div v-if="expandedDevices.has(device.id)" class="border-t border-gray-200 dark:border-gray-700">
                            
                            <!-- Empty Vehicles -->
                            <div v-if="device.vehicles.length === 0" class="p-6 text-center">
                                <Car class="mx-auto h-8 w-8 text-gray-400" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Sin vehículos asignados</p>
                                <Button variant="outline" size="sm" class="mt-3">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Agregar Vehículo
                                </Button>
                            </div>

                            <!-- Vehicles List -->
                            <div v-for="(vehicle, vIndex) in device.vehicles" :key="vehicle.id" class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                                
                                <!-- Vehicle Header -->
                                <button
                                    @click="toggleVehicle(vehicle.id)"
                                    class="flex w-full items-center justify-between p-4 pl-8 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                >
                                    <div class="flex items-center space-x-4">
                                        <div class="relative">
                                            <div class="absolute -left-4 top-1/2 h-px w-4 bg-gray-300 dark:bg-gray-600"></div>
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 shadow">
                                                <Car class="h-5 w-5 text-white" />
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex items-center space-x-2">
                                                <h4 class="font-medium text-gray-900 dark:text-white">
                                                    {{ getVehicleDisplayName(vehicle) }}
                                                </h4>
                                                <span 
                                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                    :class="vehicle.status 
                                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' 
                                                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                                                >
                                                    {{ vehicle.status ? 'Activo' : 'Inactivo' }}
                                                </span>
                                                <span v-if="vehicle.has_dashboard" class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                    Dashboard ✓
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-3 text-sm text-gray-500 dark:text-gray-400">
                                                <span v-if="vehicle.license_plate">{{ vehicle.license_plate }}</span>
                                                <span v-if="vehicle.vin" class="font-mono text-xs">{{ vehicle.vin }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            {{ vehicle.sensors_count }} sensores
                                        </span>
                                        <Link 
                                            :href="`/dashboard-dynamic/${vehicle.id}`"
                                            class="rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-cyan-700"
                                            @click.stop
                                        >
                                            <Play class="mr-1 inline h-3 w-3" />
                                            Dashboard
                                        </Link>
                                        <component 
                                            :is="expandedVehicles.has(vehicle.id) ? ChevronDown : ChevronRight" 
                                            class="h-5 w-5 text-gray-400"
                                        />
                                    </div>
                                </button>

                                <!-- Sensors List -->
                                <div v-if="expandedVehicles.has(vehicle.id)" class="bg-gray-50 px-8 py-4 dark:bg-gray-900/50">
                                    <div class="mb-3 flex items-center justify-between">
                                        <h5 class="flex items-center text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            <Cpu class="mr-2 h-4 w-4" />
                                            Sensores Configurados
                                        </h5>
                                        <Link 
                                            :href="`/clients/${client.id}/devices/${device.id}/vehicles/${vehicle.id}`"
                                            class="text-xs text-cyan-600 hover:underline dark:text-cyan-400"
                                        >
                                            <Settings class="mr-1 inline h-3 w-3" />
                                            Configurar
                                        </Link>
                                    </div>

                                    <div v-if="vehicle.sensors.length === 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Sin sensores configurados
                                    </div>

                                    <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                                        <div 
                                            v-for="sensor in vehicle.sensors" 
                                            :key="sensor.id"
                                            class="rounded-lg border px-3 py-2 text-center text-xs"
                                            :class="sensor.is_active 
                                                ? 'border-green-200 bg-white dark:border-green-800 dark:bg-gray-800' 
                                                : 'border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/50'"
                                        >
                                            <div class="font-medium text-gray-900 dark:text-white">{{ sensor.name }}</div>
                                            <div class="font-mono text-gray-500 dark:text-gray-400">{{ sensor.pid }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Vehicle Button -->
                            <div class="p-4 pl-8">
                                <Button variant="outline" size="sm" class="border-dashed">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Agregar Vehículo a {{ device.device_name }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Add Device Button -->
                    <Button 
                        v-if="availableInventory.length > 0"
                        variant="outline" 
                        class="w-full border-dashed py-6"
                    >
                        <Plus class="mr-2 h-5 w-5" />
                        Asignar Dispositivo del Inventario ({{ availableInventory.length }} disponibles)
                    </Button>
                </div>

                <!-- Users Tab -->
                <div v-if="activeTab === 'users'" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Usuarios del Cliente</h3>
                        <Button size="sm" class="bg-purple-600 hover:bg-purple-700">
                            <Plus class="mr-2 h-4 w-4" />
                            Crear Usuario
                        </Button>
                    </div>

                    <div v-if="users.length === 0" class="p-12 text-center">
                        <User class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Sin usuarios</h3>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">
                            Este cliente no tiene usuarios de acceso.
                        </p>
                    </div>

                    <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div 
                            v-for="user in users" 
                            :key="user.id"
                            class="flex items-center justify-between p-4"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                    <User class="h-5 w-5" />
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ user.name }}</span>
                                        <span 
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="user.is_active 
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' 
                                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                                        >
                                            {{ user.is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ user.email }} · {{ user.role }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Button variant="ghost" size="sm">
                                    <Edit class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
