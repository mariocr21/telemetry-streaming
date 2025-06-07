<script setup lang="ts">
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
    ArrowLeft,
    Car,
    CheckCircle2,
    Edit,
    Eye,
    Filter,
    Gauge,
    MapPin,
    MoreVertical,
    Plus,
    Power,
    PowerOff,
    RefreshCw,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface Vehicle {
    id: number;
    make?: string;
    model?: string;
    year?: number;
    license_plate?: string;
    color?: string;
    nickname?: string;
    vin?: string;
    status: boolean;
    auto_detected: boolean;
    is_configured: boolean;
    display_name: string;
    last_reading_at?: string;
    created_at: string;
    sensors_count: number;
    active_sensors_count: number;
    can: {
        view: boolean;
        update: boolean;
        deactivate: boolean;
        activate: boolean;
    };
}

interface Device {
    id: number;
    device_name: string;
    mac_address: string;
    status: string;
}

interface Client {
    id: number;
    full_name: string;
    email: string;
}

interface Props {
    client: Client;
    device: Device;
    vehicles: {
        data: Vehicle[];
        links: any[];
        meta: any;
    };
    filters: {
        search?: string;
        status?: string;
    };
    can: {
        create_vehicle: boolean;
    };
}

const props = defineProps<Props>();
const page = usePage();

// Estado reactivo
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');

// Computadas
const flashMessage = computed(() => {
    const flash = page.props.flash as any;
    return flash?.message;
});

const getStatusBadge = (status: boolean) => {
    return status
        ? { text: 'Activo', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }
        : { text: 'Inactivo', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' };
};

const getDetectionBadge = (autoDetected: boolean) => {
    return autoDetected
        ? { text: 'Auto-detectado', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' }
        : { text: 'Manual', class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' };
};

const formatLastReading = (dateString?: string) => {
    if (!dateString) return 'Sin lecturas';

    const date = new Date(dateString);
    const now = new Date();
    const diffHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);

    if (diffHours < 1) {
        const diffMinutes = Math.floor(diffHours * 60);
        return `Hace ${diffMinutes} minutos`;
    } else if (diffHours < 24) {
        return `Hace ${Math.floor(diffHours)} horas`;
    } else {
        const diffDays = Math.floor(diffHours / 24);
        return `Hace ${diffDays} días`;
    }
};

// Métodos
const applyFilters = () => {
    router.get(
        route('clients.devices.vehicles.index', [props.client.id, props.device.id]),
        {
            search: search.value || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    search.value = '';
    statusFilter.value = 'all';
    applyFilters();
};

const toggleVehicleStatus = (vehicle: Vehicle) => {
    const action = vehicle.status ? 'deactivate' : 'activate';
    router.post(route(`clients.devices.vehicles.${action}`, [props.client.id, props.device.id, vehicle.id]));
};

const deleteVehicle = (vehicle: Vehicle) => {
    if (confirm(`¿Estás seguro de que deseas desactivar el vehículo ${vehicle.display_name}?`)) {
        router.delete(route('clients.devices.vehicles.destroy', [props.client.id, props.device.id, vehicle.id]));
    }
};

// Watchers
watch([search, statusFilter], () => {
    // Debounce search
    clearTimeout(searchTimeout.value);
    searchTimeout.value = setTimeout(() => {
        applyFilters();
    }, 300);
});

const searchTimeout = ref<number>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clientes', href: '/clients' },
    { title: props.client.full_name, href: `/clients/${props.client.id}` },
    { title: 'Dispositivos', href: `/clients/${props.client.id}/devices` },
    { title: props.device.device_name, href: `/clients/${props.client.id}/devices/${props.device.id}` },
    { title: 'Vehículos', href: `/clients/${props.client.id}/devices/${props.device.id}/vehicles` },
];
</script>

<template>
    <Head :title="`Vehículos - ${device.device_name}`" />

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

                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Vehículos</h1>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Gestiona los vehículos asociados al dispositivo {{ device.device_name }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link v-if="can.create_vehicle" :href="route('clients.devices.vehicles.create', [client.id, device.id])">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Agregar Vehículo
                        </Button>
                    </Link>
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

                <!-- Filtros y búsqueda -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center text-lg">
                            <Filter class="mr-2 h-5 w-5" />
                            Filtros
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col space-y-4 md:flex-row md:items-center md:space-y-0 md:space-x-4">
                            <div class="flex-1">
                                <div class="relative">
                                    <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        v-model="search"
                                        type="text"
                                        placeholder="Buscar por marca, modelo, placa o VIN..."
                                        class="w-full rounded-lg border border-gray-300 py-2 pr-4 pl-10 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center space-x-4">
                                <select
                                    v-model="statusFilter"
                                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                >
                                    <option value="all">Todos los estados</option>
                                    <option value="active">Activos</option>
                                    <option value="inactive">Inactivos</option>
                                </select>

                                <Button @click="clearFilters" variant="outline" size="sm"> Limpiar </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Lista de vehículos -->
                <div v-if="vehicles.data.length === 0" class="py-12 text-center">
                    <Car class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ search || statusFilter !== 'all' ? 'No se encontraron vehículos' : 'No hay vehículos registrados' }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{
                            search || statusFilter !== 'all'
                                ? 'Intenta cambiar los filtros de búsqueda.'
                                : 'Comienza agregando tu primer vehículo a este dispositivo.'
                        }}
                    </p>
                    <div v-if="!search && statusFilter === 'all'" class="mt-6">
                        <Link :href="route('clients.devices.vehicles.create', [client.id, device.id])">
                            <Button>
                                <Plus class="mr-2 h-4 w-4" />
                                Agregar Primer Vehículo
                            </Button>
                        </Link>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="vehicle in vehicles.data"
                        :key="vehicle.id"
                        class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <!-- Header del vehículo -->
                                <div class="flex items-center space-x-4">
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600"
                                    >
                                        <Car class="h-6 w-6 text-white" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            {{ vehicle.display_name }}
                                        </h3>
                                        <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                                            <span v-if="vehicle.license_plate" class="flex items-center space-x-1">
                                                <MapPin class="h-3 w-3" />
                                                <span>{{ vehicle.license_plate }}</span>
                                            </span>
                                            <span v-if="vehicle.color">{{ vehicle.color }}</span>
                                            <span v-if="vehicle.vin" class="font-mono">VIN: {{ vehicle.vin.slice(-6) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información y estadísticas -->
                                <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Estado</p>
                                        <Badge :class="getStatusBadge(vehicle.status).class" class="mt-1">
                                            {{ getStatusBadge(vehicle.status).text }}
                                        </Badge>
                                    </div>

                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Tipo de Registro</p>
                                        <Badge :class="getDetectionBadge(vehicle.auto_detected).class" class="mt-1">
                                            {{ getDetectionBadge(vehicle.auto_detected).text }}
                                        </Badge>
                                    </div>

                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Sensores</p>
                                        <div class="mt-1 flex items-center space-x-1">
                                            <Gauge class="h-4 w-4 text-blue-500" />
                                            <span class="text-sm font-medium"> {{ vehicle.active_sensors_count }}/{{ vehicle.sensors_count }} </span>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Última Lectura</p>
                                        <div class="mt-1 flex items-center space-x-1">
                                            <Activity class="h-4 w-4 text-gray-400" />
                                            <span class="text-sm">
                                                {{ formatLastReading(vehicle.last_reading_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Configuración -->
                                <div class="mt-4 flex items-center space-x-4">
                                    <div class="flex items-center space-x-2">
                                        <CheckCircle2 v-if="vehicle.is_configured" class="h-4 w-4 text-green-500" />
                                        <AlertCircle v-else class="h-4 w-4 text-yellow-500" />
                                        <span class="text-sm">
                                            {{ vehicle.is_configured ? 'Configurado' : 'Pendiente de configuración' }}
                                        </span>
                                    </div>

                                    <div class="text-xs text-gray-500">Registrado {{ new Date(vehicle.created_at).toLocaleDateString('es-ES') }}</div>
                                </div>
                            </div>

                            <!-- Acciones -->
                            <div class="flex items-center space-x-2">
                                <Link :href="route('clients.devices.vehicles.show', [client.id, device.id, vehicle.id])">
                                    <Button size="sm" variant="outline">
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                </Link>

                                <Link v-if="vehicle.can.update" :href="route('clients.devices.vehicles.edit', [client.id, device.id, vehicle.id])">
                                    <Button size="sm" variant="outline">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                </Link>

                                <Button
                                    v-if="vehicle.can.activate && !vehicle.status"
                                    @click="toggleVehicleStatus(vehicle)"
                                    size="sm"
                                    variant="outline"
                                    class="text-green-600 hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-900/20"
                                >
                                    <Power class="h-4 w-4" />
                                </Button>

                                <Button
                                    v-if="vehicle.can.deactivate && vehicle.status"
                                    @click="toggleVehicleStatus(vehicle)"
                                    size="sm"
                                    variant="outline"
                                    class="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
                                >
                                    <PowerOff class="h-4 w-4" />
                                </Button>

                                <SimpleDropdown align="right">
                                    <template #trigger>
                                        <Button size="sm" variant="outline">
                                            <MoreVertical class="h-4 w-4" />
                                        </Button>
                                    </template>

                                    <Link
                                        :href="route('clients.devices.vehicles.show', [client.id, device.id, vehicle.id])"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        <Eye class="mr-2 h-4 w-4" />
                                        Ver Detalles
                                    </Link>

                                    <Link
                                        v-if="vehicle.can.update"
                                        :href="route('clients.devices.vehicles.edit', [client.id, device.id, vehicle.id])"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        <Edit class="mr-2 h-4 w-4" />
                                        Editar Vehículo
                                    </Link>

                                    <button
                                        v-if="vehicle.sensors_count > 0"
                                        @click="router.post(route('clients.devices.vehicles.sync', [client.id, device.id, vehicle.id]))"
                                        class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        <RefreshCw class="mr-2 h-4 w-4" />
                                        Sincronizar Sensores
                                    </button>

                                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                                    <button
                                        v-if="vehicle.can.activate && !vehicle.status"
                                        @click="toggleVehicleStatus(vehicle)"
                                        class="flex w-full items-center px-4 py-2 text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20"
                                    >
                                        <Power class="mr-2 h-4 w-4" />
                                        Activar Vehículo
                                    </button>

                                    <button
                                        v-if="vehicle.can.deactivate && vehicle.status"
                                        @click="deleteVehicle(vehicle)"
                                        class="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                    >
                                        <PowerOff class="mr-2 h-4 w-4" />
                                        Desactivar Vehículo
                                    </button>
                                </SimpleDropdown>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginación -->
                <div
                    v-if="vehicles.meta.last_page > 1"
                    class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex flex-1 justify-between sm:hidden">
                        <Link
                            v-if="vehicles.meta.current_page > 1"
                            :href="vehicles.links[0].url"
                            class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Anterior
                        </Link>
                        <Link
                            v-if="vehicles.meta.current_page < vehicles.meta.last_page"
                            :href="vehicles.links[vehicles.links.length - 1].url"
                            class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Siguiente
                        </Link>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Mostrando
                                <span class="font-medium">{{ vehicles.meta.from }}</span>
                                a
                                <span class="font-medium">{{ vehicles.meta.to }}</span>
                                de
                                <span class="font-medium">{{ vehicles.meta.total }}</span>
                                resultados
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <Link
                                    v-for="link in vehicles.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 text-sm font-medium',
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-gray-900 ring-1 ring-gray-300 ring-inset hover:bg-gray-50 focus:outline-offset-0',
                                        link.url ? 'cursor-pointer' : 'cursor-not-allowed opacity-50',
                                    ]"
                                >
                                    <span v-if="typeof link.label === 'string'" v-html="link.label"></span>
                                    <span v-else>{{ link.label }}</span>
                                </Link>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
