<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Badge from '@/components/ui/Badge.vue';
import Table from '@/components/ui/Table.vue';
import TableBody from '@/components/ui/TableBody.vue';
import TableCell from '@/components/ui/TableCell.vue';
import TableHead from '@/components/ui/TableHead.vue';
import TableHeader from '@/components/ui/TableHeader.vue';
import TableRow from '@/components/ui/TableRow.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import SimpleDropdown from '@/components/ui/SimpleDropdown.vue';
import {
    MoreVertical,
    Search,
    Eye,
    Edit,
    Trash2,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    RefreshCw,
    X,
    Car,
    Smartphone,
    Users,
    Gauge,
    CheckCircle2,
    XCircle,
    Link as LinkIcon,
    Unlink,
    Activity,
    Plus,
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import axios from 'axios';

interface Client {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    company: string | null;
}

interface DeviceInventory {
    id: number;
    serial_number: string;
    model: string;
}

interface ClientDevice {
    id: number;
    device_name: string;
    mac_address: string;
    status: string;
    client_id: number;
    device_inventory: DeviceInventory | null;
}

interface Vehicle {
    id: number;
    make: string | null;
    model: string | null;
    year: number | null;
    license_plate: string | null;
    nickname: string | null;
    vin: string | null;
    status: boolean;
    client_id: number;
    client_device_id: number | null;
    client: Client;
    client_device: ClientDevice | null;
    vehicle_sensors_count: number;
    created_at: string;
    last_reading_at: string | null;
}

interface PaginatedData<T> {
    data: T[];
    links: any[];
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
}

interface ClientOption {
    id: number;
    name: string;
    company: string | null;
}

interface Filters {
    search: string | null;
    client_id: string | null;
    status: string | null;
    has_device: string | null;
    sort: string;
    direction: string;
}

interface Stats {
    total: number;
    active: number;
    with_device: number;
    with_sensors: number;
}

interface AvailableDevice {
    id: number;
    name: string;
    mac: string;
    status: string;
    serial: string | null;
    model: string | null;
}

interface Props {
    vehicles: PaginatedData<Vehicle>;
    clients: ClientOption[];
    filters: Filters;
    stats: Stats;
}

const props = defineProps<Props>();
const page = usePage();

// State
const searchInput = ref(props.filters.search || '');
const selectedClient = ref(props.filters.client_id || '');
const selectedStatus = ref(props.filters.status || '');
const selectedHasDevice = ref(props.filters.has_device || '');
const sort = ref(props.filters.sort || 'created_at');
const direction = ref(props.filters.direction || 'desc');
const isLoading = ref(false);

// Device assignment modal state
const showDeviceModal = ref(false);
const selectedVehicle = ref<Vehicle | null>(null);
const availableDevices = ref<AvailableDevice[]>([]);
const loadingDevices = ref(false);
const selectedDeviceId = ref<number | null>(null);

// Create vehicle modal state
const showCreateModal = ref(false);
const createForm = ref({
    client_id: '',
    device_id: '',
    make: '',
    model: '',
    year: new Date().getFullYear(),
    license_plate: '',
    nickname: '',
    vin: '',
});
const createErrors = ref<Record<string, string>>({});
const isCreating = ref(false);
const createDevices = ref<AvailableDevice[]>([]);
const loadingCreateDevices = ref(false);

// Search debounce
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchInput, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        performSearch();
    }, 300);
});

const performSearch = () => {
    if (isLoading.value) return;

    isLoading.value = true;
    router.get(
        route('admin.vehicles.index'),
        {
            search: searchInput.value || undefined,
            client_id: selectedClient.value || undefined,
            status: selectedStatus.value || undefined,
            has_device: selectedHasDevice.value || undefined,
            sort: sort.value,
            direction: direction.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        }
    );
};

const clearFilters = () => {
    searchInput.value = '';
    selectedClient.value = '';
    selectedStatus.value = '';
    selectedHasDevice.value = '';
    performSearch();
};

const refreshData = () => {
    performSearch();
};

const sortBy = (column: string) => {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        direction.value = 'asc';
    }
    performSearch();
};

const getSortIcon = (column: string) => {
    if (sort.value !== column) return ArrowUpDown;
    return direction.value === 'asc' ? ArrowUp : ArrowDown;
};

// Open device assignment modal
const openDeviceModal = async (vehicle: Vehicle) => {
    selectedVehicle.value = vehicle;
    selectedDeviceId.value = vehicle.client_device_id;
    showDeviceModal.value = true;
    
    // Fetch available devices for this client
    loadingDevices.value = true;
    try {
        const response = await axios.get(route('admin.vehicles.available-devices'), {
            params: { client_id: vehicle.client_id }
        });
        availableDevices.value = response.data.devices;
    } catch (error) {
        console.error('Error fetching devices:', error);
        availableDevices.value = [];
    } finally {
        loadingDevices.value = false;
    }
};

const closeDeviceModal = () => {
    showDeviceModal.value = false;
    selectedVehicle.value = null;
    availableDevices.value = [];
    selectedDeviceId.value = null;
};

const assignDevice = () => {
    if (!selectedVehicle.value) return;
    
    router.post(
        route('admin.vehicles.assign-device', selectedVehicle.value.id),
        { device_id: selectedDeviceId.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeviceModal();
            },
        }
    );
};

const toggleStatus = (vehicle: Vehicle) => {
    router.post(route('admin.vehicles.toggle-status', vehicle.id), {}, {
        preserveScroll: true,
    });
};

const deleteVehicle = (vehicle: Vehicle) => {
    const displayName = vehicle.nickname || `${vehicle.make} ${vehicle.model}` || 'Vehículo';
    if (confirm(`¿Estás seguro de eliminar "${displayName}"?`)) {
        router.delete(route('admin.vehicles.destroy', vehicle.id));
    }
};

const getVehicleDisplayName = (vehicle: Vehicle) => {
    if (vehicle.nickname) return vehicle.nickname;
    const parts = [];
    if (vehicle.make) parts.push(vehicle.make);
    if (vehicle.model) parts.push(vehicle.model);
    if (vehicle.year) parts.push(`(${vehicle.year})`);
    return parts.length > 0 ? parts.join(' ') : 'Vehículo sin nombre';
};

const flashMessage = computed(() => {
    const flash = page.props.flash as any;
    return flash?.message;
});

const flashError = computed(() => {
    const flash = page.props.flash as any;
    return flash?.error;
});

const hasActiveFilters = computed(() => 
    searchInput.value || selectedClient.value || selectedStatus.value || selectedHasDevice.value
);

watch(selectedClient, performSearch);
watch(selectedStatus, performSearch);
watch(selectedHasDevice, performSearch);

// Create vehicle modal functions
const openCreateModal = () => {
    showCreateModal.value = true;
    createForm.value = {
        client_id: '',
        device_id: '',
        make: '',
        model: '',
        year: new Date().getFullYear(),
        license_plate: '',
        nickname: '',
        vin: '',
    };
    createErrors.value = {};
    createDevices.value = [];
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.value = {
        client_id: '',
        device_id: '',
        make: '',
        model: '',
        year: new Date().getFullYear(),
        license_plate: '',
        nickname: '',
        vin: '',
    };
    createErrors.value = {};
    createDevices.value = [];
};

// Fetch devices when client is selected in create modal
const onCreateClientChange = async () => {
    createDevices.value = [];
    createForm.value.device_id = '';
    
    if (!createForm.value.client_id) return;
    
    loadingCreateDevices.value = true;
    try {
        const response = await axios.get(route('admin.vehicles.available-devices'), {
            params: { client_id: createForm.value.client_id }
        });
        createDevices.value = response.data.devices;
    } catch (error) {
        console.error('Error fetching devices:', error);
    } finally {
        loadingCreateDevices.value = false;
    }
};

const submitCreateVehicle = () => {
    createErrors.value = {};
    
    // Basic validation
    if (!createForm.value.client_id) {
        createErrors.value.client_id = 'Selecciona un cliente';
        return;
    }
    if (!createForm.value.make) {
        createErrors.value.make = 'La marca es obligatoria';
        return;
    }
    if (!createForm.value.model) {
        createErrors.value.model = 'El modelo es obligatorio';
        return;
    }
    
    isCreating.value = true;
    
    router.post(
        route('admin.vehicles.store'),
        createForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateModal();
            },
            onError: (errors) => {
                createErrors.value = errors as Record<string, string>;
            },
            onFinish: () => {
                isCreating.value = false;
            },
        }
    );
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Vehículos', href: '/admin/vehicles' },
];
</script>

<template>
    <Head title="Gestión de Vehículos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
                    <div class="flex items-center space-x-4">
                        <div class="rounded-lg bg-orange-100 p-3 dark:bg-orange-900/50">
                            <Car class="h-8 w-8 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Catálogo de Vehículos</h1>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">
                                Gestiona los {{ stats.total }} vehículos del sistema
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
                            <RefreshCw :class="['h-4 w-4', { 'animate-spin': isLoading }]" />
                            <span class="ml-2 hidden sm:inline">Actualizar</span>
                        </Button>

                        <Button @click="openCreateModal" class="bg-orange-600 text-white shadow-lg hover:bg-orange-700">
                            <Plus class="h-4 w-4" />
                            <span class="ml-2">Nuevo Vehículo</span>
                        </Button>
                    </div>
                </div>

                <!-- Flash Messages -->
                <div
                    v-if="flashMessage"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm dark:border-green-800 dark:bg-green-900/20"
                >
                    <div class="flex items-center">
                        <CheckCircle2 class="h-5 w-5 text-green-400" />
                        <p class="ml-3 text-sm font-medium text-green-800 dark:text-green-200">{{ flashMessage }}</p>
                    </div>
                </div>

                <div
                    v-if="flashError"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 shadow-sm dark:border-red-800 dark:bg-red-900/20"
                >
                    <div class="flex items-center">
                        <XCircle class="h-5 w-5 text-red-400" />
                        <p class="ml-3 text-sm font-medium text-red-800 dark:text-red-200">{{ flashError }}</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-orange-50 p-2 dark:bg-orange-900/50">
                                    <Car class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-green-50 p-2 dark:bg-green-900/50">
                                    <CheckCircle2 class="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Activos</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.active }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-blue-50 p-2 dark:bg-blue-900/50">
                                    <Smartphone class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Con Dispositivo</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.with_device }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-purple-50 p-2 dark:bg-purple-900/50">
                                    <Gauge class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Con Sensores</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.with_sensors }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Search and Filters -->
                <Card class="border border-gray-200 dark:border-gray-700">
                    <CardContent class="p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                            <!-- Search -->
                            <div class="flex-1">
                                <div class="relative">
                                    <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        v-model="searchInput"
                                        placeholder="Buscar por marca, modelo, placa, VIN, cliente..."
                                        class="h-12 border-gray-300 pl-10 pr-10 text-base focus:border-orange-500 focus:ring-orange-500 dark:border-gray-600"
                                    />
                                    <button
                                        v-if="searchInput"
                                        @click="searchInput = ''"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <X class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Client Filter -->
                            <select
                                v-model="selectedClient"
                                class="h-12 rounded-lg border border-gray-300 bg-white px-4 text-gray-700 focus:border-orange-500 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                            >
                                <option value="">Todos los clientes</option>
                                <option v-for="client in clients" :key="client.id" :value="client.id">
                                    {{ client.name }} {{ client.company ? `(${client.company})` : '' }}
                                </option>
                            </select>

                            <!-- Status Filter -->
                            <select
                                v-model="selectedStatus"
                                class="h-12 rounded-lg border border-gray-300 bg-white px-4 text-gray-700 focus:border-orange-500 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                            >
                                <option value="">Todos los estados</option>
                                <option value="true">Activos</option>
                                <option value="false">Inactivos</option>
                            </select>

                            <!-- Device Assignment Filter -->
                            <select
                                v-model="selectedHasDevice"
                                class="h-12 rounded-lg border border-gray-300 bg-white px-4 text-gray-700 focus:border-orange-500 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                            >
                                <option value="">Dispositivo: Todos</option>
                                <option value="yes">Con dispositivo</option>
                                <option value="no">Sin dispositivo</option>
                            </select>

                            <!-- Clear Filters -->
                            <Button v-if="hasActiveFilters" variant="outline" @click="clearFilters">
                                <X class="mr-2 h-4 w-4" />
                                Limpiar
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Table -->
                <Card class="overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div v-if="isLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 dark:bg-gray-900/80">
                        <div class="flex items-center space-x-3 text-gray-600 dark:text-gray-400">
                            <RefreshCw class="h-6 w-6 animate-spin" />
                            <span class="text-lg font-medium">Cargando vehículos...</span>
                        </div>
                    </div>

                    <div class="relative">
                        <Table>
                            <TableHeader>
                                <TableRow class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                    <TableHead class="cursor-pointer transition-colors hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('make')">
                                        <div class="flex items-center space-x-2 font-semibold">
                                            <span>Vehículo</span>
                                            <component :is="getSortIcon('make')" class="h-4 w-4" />
                                        </div>
                                    </TableHead>
                                    <TableHead class="font-semibold">Cliente</TableHead>
                                    <TableHead class="font-semibold">Dispositivo</TableHead>
                                    <TableHead class="font-semibold">Sensores</TableHead>
                                    <TableHead class="cursor-pointer transition-colors hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('status')">
                                        <div class="flex items-center space-x-2 font-semibold">
                                            <span>Estado</span>
                                            <component :is="getSortIcon('status')" class="h-4 w-4" />
                                        </div>
                                    </TableHead>
                                    <TableHead class="text-center font-semibold">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="vehicle in vehicles.data"
                                    :key="vehicle.id"
                                    class="border-b border-gray-100 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                                >
                                    <!-- Vehicle -->
                                    <TableCell class="py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600">
                                                <Car class="h-5 w-5 text-white" />
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ getVehicleDisplayName(vehicle) }}
                                                </p>
                                                <div class="flex items-center space-x-2 text-sm text-gray-500">
                                                    <span v-if="vehicle.license_plate">{{ vehicle.license_plate }}</span>
                                                    <span v-if="vehicle.vin" class="font-mono text-xs">{{ vehicle.vin }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- Client -->
                                    <TableCell class="py-4">
                                        <div class="flex items-center space-x-2">
                                            <Users class="h-4 w-4 text-gray-400" />
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ vehicle.client.first_name }} {{ vehicle.client.last_name }}
                                                </p>
                                                <p v-if="vehicle.client.company" class="text-xs text-gray-500">
                                                    {{ vehicle.client.company }}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- Device -->
                                    <TableCell class="py-4">
                                        <div v-if="vehicle.client_device" class="flex items-center space-x-2">
                                            <Smartphone class="h-4 w-4 text-green-500" />
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ vehicle.client_device.device_name }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    {{ vehicle.client_device.device_inventory?.serial_number || vehicle.client_device.mac_address }}
                                                </p>
                                            </div>
                                            <button
                                                @click="openDeviceModal(vehicle)"
                                                class="ml-2 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-orange-600 dark:hover:bg-gray-700"
                                                title="Cambiar dispositivo"
                                            >
                                                <Edit class="h-3 w-3" />
                                            </button>
                                        </div>
                                        <div v-else>
                                            <Button
                                                @click="openDeviceModal(vehicle)"
                                                size="sm"
                                                variant="outline"
                                                class="text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20"
                                            >
                                                <LinkIcon class="mr-1 h-3 w-3" />
                                                Asignar
                                            </Button>
                                        </div>
                                    </TableCell>

                                    <!-- Sensors -->
                                    <TableCell class="py-4">
                                        <Badge
                                            :class="
                                                vehicle.vehicle_sensors_count > 0
                                                    ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                            "
                                        >
                                            <Gauge class="mr-1 h-3 w-3" />
                                            {{ vehicle.vehicle_sensors_count }} sensores
                                        </Badge>
                                    </TableCell>

                                    <!-- Status -->
                                    <TableCell class="py-4">
                                        <button
                                            @click="toggleStatus(vehicle)"
                                            :class="[
                                                'flex items-center space-x-1 rounded-full px-3 py-1 text-sm font-medium transition-colors',
                                                vehicle.status
                                                    ? 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400'
                                            ]"
                                        >
                                            <span :class="['h-2 w-2 rounded-full', vehicle.status ? 'bg-green-500' : 'bg-red-500']"></span>
                                            <span>{{ vehicle.status ? 'Activo' : 'Inactivo' }}</span>
                                        </button>
                                    </TableCell>

                                    <!-- Actions -->
                                    <TableCell class="py-4">
                                        <div class="flex items-center justify-center space-x-1">
                                            <Link
                                                v-if="vehicle.client_device"
                                                :href="`/clients/${vehicle.client_id}/devices/${vehicle.client_device_id}/vehicles/${vehicle.id}`"
                                                class="rounded-lg p-2 text-blue-600 transition-all hover:bg-blue-50 hover:text-blue-700 dark:text-blue-400 dark:hover:bg-blue-900/50"
                                                title="Ver detalles"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Link>

                                            <Link
                                                :href="`/dashboard-dynamic/${vehicle.id}`"
                                                class="rounded-lg p-2 text-cyan-600 transition-all hover:bg-cyan-50 hover:text-cyan-700 dark:text-cyan-400 dark:hover:bg-cyan-900/50"
                                                title="Dashboard en vivo"
                                            >
                                                <Activity class="h-4 w-4" />
                                            </Link>

                                            <SimpleDropdown align="right">
                                                <template #trigger>
                                                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                                                        <MoreVertical class="h-4 w-4" />
                                                    </Button>
                                                </template>

                                                <button
                                                    @click="openDeviceModal(vehicle)"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    <LinkIcon class="mr-3 h-4 w-4" />
                                                    {{ vehicle.client_device ? 'Cambiar Dispositivo' : 'Asignar Dispositivo' }}
                                                </button>

                                                <button
                                                    @click="toggleStatus(vehicle)"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    <component :is="vehicle.status ? XCircle : CheckCircle2" class="mr-3 h-4 w-4" />
                                                    {{ vehicle.status ? 'Desactivar' : 'Activar' }}
                                                </button>

                                                <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                                                <button
                                                    @click="deleteVehicle(vehicle)"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                                                >
                                                    <Trash2 class="mr-3 h-4 w-4" />
                                                    Eliminar Vehículo
                                                </button>
                                            </SimpleDropdown>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Empty State -->
                    <div v-if="vehicles.data.length === 0 && !isLoading" class="px-6 py-20 text-center">
                        <div class="mx-auto max-w-md">
                            <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                                <Car class="h-12 w-12 text-gray-400" />
                            </div>

                            <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ hasActiveFilters ? 'No se encontraron resultados' : 'No hay vehículos registrados' }}
                            </h3>

                            <p class="mb-8 text-gray-600 dark:text-gray-400">
                                {{
                                    hasActiveFilters
                                        ? 'Intenta con otros filtros o términos de búsqueda.'
                                        : 'Los vehículos se crean desde la sección de dispositivos de cada cliente.'
                                }}
                            </p>

                            <Button v-if="hasActiveFilters" variant="outline" size="lg" @click="clearFilters">
                                <X class="mr-2 h-4 w-4" />
                                Limpiar Filtros
                            </Button>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="vehicles.links.length > 3 && vehicles.data.length > 0"
                        class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50"
                    >
                        <div class="flex flex-col space-y-4 sm:flex-row sm:items-center sm:justify-between sm:space-y-0">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Mostrando <span class="font-semibold">{{ vehicles.meta.from }}</span> a
                                <span class="font-semibold">{{ vehicles.meta.to }}</span> de
                                <span class="font-semibold">{{ vehicles.meta.total }}</span> vehículos
                            </div>

                            <div class="flex items-center space-x-2">
                                <template v-for="link in vehicles.links" :key="link.label">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        :class="[
                                            'rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200',
                                            link.active
                                                ? 'bg-orange-600 text-white shadow-md'
                                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </Link>
                                    <span
                                        v-else
                                        :class="[
                                            'cursor-not-allowed rounded-lg px-4 py-2 text-sm font-medium opacity-50',
                                            link.active ? 'bg-orange-600 text-white' : 'text-gray-400',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Device Assignment Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDeviceModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeDeviceModal"></div>
                    
                    <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                {{ selectedVehicle?.client_device ? 'Cambiar Dispositivo' : 'Asignar Dispositivo' }}
                            </h3>
                            <button
                                @click="closeDeviceModal"
                                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div v-if="selectedVehicle" class="mb-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                {{ getVehicleDisplayName(selectedVehicle) }}
                            </p>
                            <p class="text-sm text-gray-500">
                                Cliente: {{ selectedVehicle.client.first_name }} {{ selectedVehicle.client.last_name }}
                            </p>
                        </div>

                        <div v-if="loadingDevices" class="flex items-center justify-center py-8">
                            <RefreshCw class="h-6 w-6 animate-spin text-gray-400" />
                        </div>

                        <div v-else-if="availableDevices.length === 0" class="py-8 text-center text-gray-500">
                            <Smartphone class="mx-auto mb-2 h-8 w-8 text-gray-300" />
                            <p>Este cliente no tiene dispositivos disponibles</p>
                        </div>

                        <div v-else class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Selecciona un dispositivo:
                            </label>
                            
                            <!-- Option to unassign -->
                            <label
                                :class="[
                                    'flex cursor-pointer items-center rounded-lg border-2 p-3 transition-all',
                                    selectedDeviceId === null
                                        ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'
                                ]"
                            >
                                <input
                                    type="radio"
                                    v-model="selectedDeviceId"
                                    :value="null"
                                    class="sr-only"
                                />
                                <Unlink class="mr-3 h-5 w-5 text-red-500" />
                                <span class="text-gray-600 dark:text-gray-400">Sin dispositivo asignado</span>
                            </label>

                            <label
                                v-for="device in availableDevices"
                                :key="device.id"
                                :class="[
                                    'flex cursor-pointer items-center rounded-lg border-2 p-3 transition-all',
                                    selectedDeviceId === device.id
                                        ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20'
                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'
                                ]"
                            >
                                <input
                                    type="radio"
                                    v-model="selectedDeviceId"
                                    :value="device.id"
                                    class="sr-only"
                                />
                                <Smartphone class="mr-3 h-5 w-5 text-gray-400" />
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ device.name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ device.serial || device.mac }}
                                        <span v-if="device.model"> · {{ device.model }}</span>
                                    </p>
                                </div>
                                <Badge
                                    :class="device.status === 'active'
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-gray-100 text-gray-500'"
                                    class="text-xs"
                                >
                                    {{ device.status === 'active' ? 'Online' : 'Offline' }}
                                </Badge>
                            </label>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <Button variant="outline" @click="closeDeviceModal">Cancelar</Button>
                            <Button
                                @click="assignDevice"
                                class="bg-orange-600 text-white hover:bg-orange-700"
                                :disabled="availableDevices.length === 0"
                            >
                                Guardar
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Create Vehicle Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeCreateModal"></div>
                    
                    <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="rounded-lg bg-orange-100 p-2 dark:bg-orange-900/50">
                                    <Plus class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Nuevo Vehículo
                                </h3>
                            </div>
                            <button
                                @click="closeCreateModal"
                                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <form @submit.prevent="submitCreateVehicle" class="space-y-4">
                            <!-- Client Selection -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Cliente <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="createForm.client_id"
                                    @change="onCreateClientChange"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800"
                                    :class="createErrors.client_id ? 'border-red-500' : ''"
                                >
                                    <option value="">Selecciona un cliente</option>
                                    <option v-for="client in clients" :key="client.id" :value="client.id">
                                        {{ client.name }} {{ client.company ? `(${client.company})` : '' }}
                                    </option>
                                </select>
                                <p v-if="createErrors.client_id" class="mt-1 text-xs text-red-500">{{ createErrors.client_id }}</p>
                            </div>

                            <!-- Device Selection (optional) -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Dispositivo (opcional)
                                </label>
                                <div v-if="loadingCreateDevices" class="flex items-center space-x-2 text-gray-500">
                                    <RefreshCw class="h-4 w-4 animate-spin" />
                                    <span class="text-sm">Cargando dispositivos...</span>
                                </div>
                                <select
                                    v-else
                                    v-model="createForm.device_id"
                                    :disabled="!createForm.client_id"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="">Sin dispositivo asignado</option>
                                    <option v-for="device in createDevices" :key="device.id" :value="device.id">
                                        {{ device.name }} ({{ device.serial || device.mac }})
                                    </option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Make -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Marca <span class="text-red-500">*</span>
                                    </label>
                                    <Input
                                        v-model="createForm.make"
                                        placeholder="Toyota, Honda..."
                                        :class="createErrors.make ? 'border-red-500' : ''"
                                    />
                                    <p v-if="createErrors.make" class="mt-1 text-xs text-red-500">{{ createErrors.make }}</p>
                                </div>

                                <!-- Model -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Modelo <span class="text-red-500">*</span>
                                    </label>
                                    <Input
                                        v-model="createForm.model"
                                        placeholder="Corolla, Civic..."
                                        :class="createErrors.model ? 'border-red-500' : ''"
                                    />
                                    <p v-if="createErrors.model" class="mt-1 text-xs text-red-500">{{ createErrors.model }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Year -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Año
                                    </label>
                                    <Input
                                        v-model.number="createForm.year"
                                        type="number"
                                        min="1990"
                                        :max="new Date().getFullYear() + 2"
                                    />
                                </div>

                                <!-- License Plate -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Placa
                                    </label>
                                    <Input
                                        v-model="createForm.license_plate"
                                        placeholder="ABC-123"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Nickname -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Apodo
                                    </label>
                                    <Input
                                        v-model="createForm.nickname"
                                        placeholder="Mi carro, Baja Beast..."
                                    />
                                </div>

                                <!-- VIN -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        VIN
                                    </label>
                                    <Input
                                        v-model="createForm.vin"
                                        placeholder="17 caracteres"
                                        maxlength="17"
                                        class="font-mono"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <Button type="button" variant="outline" @click="closeCreateModal">
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    :disabled="isCreating"
                                    class="bg-orange-600 text-white hover:bg-orange-700"
                                >
                                    <RefreshCw v-if="isCreating" class="mr-2 h-4 w-4 animate-spin" />
                                    <Plus v-else class="mr-2 h-4 w-4" />
                                    {{ isCreating ? 'Creando...' : 'Crear Vehículo' }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
