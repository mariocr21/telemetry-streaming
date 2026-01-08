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
    Plus,
    Search,
    Eye,
    Edit,
    Trash2,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    RefreshCw,
    X,
    Cpu,
    Activity,
    Gauge,
    Database,
    Filter,
    Zap,
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Sensor {
    id: number;
    pid: string;
    name: string;
    description: string | null;
    category: string;
    unit: string;
    data_type: string;
    min_value: number | null;
    max_value: number | null;
    is_standard: boolean;
    vehicle_sensors_count: number;
    created_at: string;
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

interface Filters {
    search: string | null;
    category: string | null;
    is_standard: string | null;
    sort: string;
    direction: string;
}

interface Stats {
    total: number;
    standard: number;
    custom: number;
    categories_count: number;
}

interface Props {
    sensors: PaginatedData<Sensor>;
    categories: string[];
    filters: Filters;
    stats: Stats;
}

const props = defineProps<Props>();
const page = usePage();

// Estado reactivo
const searchInput = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');
const selectedType = ref(props.filters.is_standard || '');
const sort = ref(props.filters.sort || 'name');
const direction = ref(props.filters.direction || 'asc');
const isLoading = ref(false);

// Debounced search
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
        route('admin.sensors.index'),
        {
            search: searchInput.value || undefined,
            category: selectedCategory.value || undefined,
            is_standard: selectedType.value || undefined,
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
    selectedCategory.value = '';
    selectedType.value = '';
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

const deleteSensor = (sensor: Sensor) => {
    if (confirm(`¿Estás seguro de eliminar el sensor "${sensor.name}"?`)) {
        router.delete(route('admin.sensors.destroy', sensor.id));
    }
};

const getSortIcon = (column: string) => {
    if (sort.value !== column) return ArrowUpDown;
    return direction.value === 'asc' ? ArrowUp : ArrowDown;
};

const getCategoryIcon = (category: string) => {
    const icons: Record<string, any> = {
        engine: Activity,
        fuel: Gauge,
        diagnostics: Cpu,
        vehicle: Zap,
    };
    return icons[category] || Database;
};

const getCategoryColor = (category: string) => {
    const colors: Record<string, string> = {
        engine: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        fuel: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        diagnostics: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        vehicle: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        gps: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    };
    return colors[category] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};

const flashMessage = computed(() => {
    const flash = page.props.flash as any;
    return flash?.message;
});

const flashError = computed(() => {
    const flash = page.props.flash as any;
    return flash?.error;
});

const hasActiveFilters = computed(() => searchInput.value || selectedCategory.value || selectedType.value);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Sensores', href: '/admin/sensors' },
];

watch(selectedCategory, performSearch);
watch(selectedType, performSearch);
</script>

<template>
    <Head title="Gestión de Sensores" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header -->
        <template #header>
            <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
                <div class="flex items-center space-x-4">
                    <div class="rounded-lg bg-cyan-100 p-3 dark:bg-cyan-900/50">
                        <Cpu class="h-8 w-8 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Catálogo de Sensores</h1>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">
                            Gestiona los {{ stats.total }} sensores del sistema
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
                        <RefreshCw :class="['h-4 w-4', { 'animate-spin': isLoading }]" />
                        <span class="ml-2 hidden sm:inline">Actualizar</span>
                    </Button>

                    <Link :href="route('admin.sensors.create')">
                        <Button class="bg-cyan-600 text-white shadow-lg hover:bg-cyan-700">
                            <Plus class="h-4 w-4" />
                            <span class="ml-2">Nuevo Sensor</span>
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Flash Messages -->
                <div
                    v-if="flashMessage"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm dark:border-green-800 dark:bg-green-900/20"
                >
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <p class="ml-3 text-sm font-medium text-green-800 dark:text-green-200">{{ flashMessage }}</p>
                    </div>
                </div>

                <div
                    v-if="flashError"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 shadow-sm dark:border-red-800 dark:bg-red-900/20"
                >
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <p class="ml-3 text-sm font-medium text-red-800 dark:text-red-200">{{ flashError }}</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-cyan-50 p-2 dark:bg-cyan-900/50">
                                    <Database class="h-6 w-6 text-cyan-600 dark:text-cyan-400" />
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
                                    <Zap class="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">OBD Estándar</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.standard }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-purple-50 p-2 dark:bg-purple-900/50">
                                    <Cpu class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Custom/CAN</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.custom }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <div class="flex items-center">
                                <div class="rounded-lg bg-amber-50 p-2 dark:bg-amber-900/50">
                                    <Filter class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Categorías</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.categories_count }}</p>
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
                                        placeholder="Buscar por nombre, PID o descripción..."
                                        class="h-12 border-gray-300 pl-10 pr-10 text-base focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-600"
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

                            <!-- Category Filter -->
                            <select
                                v-model="selectedCategory"
                                class="h-12 rounded-lg border border-gray-300 bg-white px-4 text-gray-700 focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                            >
                                <option value="">Todas las categorías</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">
                                    {{ cat.charAt(0).toUpperCase() + cat.slice(1) }}
                                </option>
                            </select>

                            <!-- Type Filter -->
                            <select
                                v-model="selectedType"
                                class="h-12 rounded-lg border border-gray-300 bg-white px-4 text-gray-700 focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                            >
                                <option value="">Todos los tipos</option>
                                <option value="true">OBD Estándar</option>
                                <option value="false">Custom/CAN</option>
                            </select>

                            <!-- Add New Sensor Button -->
                            <Link :href="route('admin.sensors.create')">
                                <Button class="h-12 bg-cyan-600 text-white shadow-lg hover:bg-cyan-700">
                                    <Plus class="h-4 w-4" />
                                    <span class="ml-2">Nuevo Sensor</span>
                                </Button>
                            </Link>

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
                            <span class="text-lg font-medium">Cargando sensores...</span>
                        </div>
                    </div>

                    <div class="relative">
                        <Table>
                            <TableHeader>
                                <TableRow class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                    <TableHead class="cursor-pointer transition-colors hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('pid')">
                                        <div class="flex items-center space-x-2 font-semibold">
                                            <span>PID</span>
                                            <component :is="getSortIcon('pid')" class="h-4 w-4" />
                                        </div>
                                    </TableHead>
                                    <TableHead class="cursor-pointer transition-colors hover:bg-gray-100 dark:hover:bg-gray-700" @click="sortBy('name')">
                                        <div class="flex items-center space-x-2 font-semibold">
                                            <span>Nombre</span>
                                            <component :is="getSortIcon('name')" class="h-4 w-4" />
                                        </div>
                                    </TableHead>
                                    <TableHead class="font-semibold">Categoría</TableHead>
                                    <TableHead class="font-semibold">Tipo</TableHead>
                                    <TableHead class="font-semibold">Unidad</TableHead>
                                    <TableHead class="font-semibold">Rango</TableHead>
                                    <TableHead class="font-semibold">En Uso</TableHead>
                                    <TableHead class="text-center font-semibold">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="sensor in sensors.data"
                                    :key="sensor.id"
                                    class="border-b border-gray-100 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                                >
                                    <!-- PID -->
                                    <TableCell class="py-4">
                                        <code class="rounded bg-gray-100 px-2 py-1 font-mono text-sm text-cyan-600 dark:bg-gray-700 dark:text-cyan-400">
                                            {{ sensor.pid }}
                                        </code>
                                    </TableCell>

                                    <!-- Nombre -->
                                    <TableCell class="py-4">
                                        <div class="flex items-center space-x-3">
                                            <component :is="getCategoryIcon(sensor.category)" class="h-5 w-5 text-gray-400" />
                                            <div>
                                                <Link
                                                    :href="route('admin.sensors.show', sensor.id)"
                                                    class="font-semibold text-gray-900 transition-colors hover:text-cyan-600 dark:text-gray-100 dark:hover:text-cyan-400"
                                                >
                                                    {{ sensor.name }}
                                                </Link>
                                                <p v-if="sensor.description" class="max-w-xs truncate text-sm text-gray-500 dark:text-gray-400">
                                                    {{ sensor.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- Categoría -->
                                    <TableCell class="py-4">
                                        <Badge :class="getCategoryColor(sensor.category)">
                                            {{ sensor.category }}
                                        </Badge>
                                    </TableCell>

                                    <!-- Tipo -->
                                    <TableCell class="py-4">
                                        <Badge
                                            :variant="sensor.is_standard ? 'default' : 'secondary'"
                                            :class="
                                                sensor.is_standard
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'
                                            "
                                        >
                                            {{ sensor.is_standard ? 'OBD' : 'Custom' }}
                                        </Badge>
                                    </TableCell>

                                    <!-- Unidad -->
                                    <TableCell class="py-4">
                                        <span class="text-gray-600 dark:text-gray-400">{{ sensor.unit }}</span>
                                    </TableCell>

                                    <!-- Rango -->
                                    <TableCell class="py-4">
                                        <span v-if="sensor.min_value !== null || sensor.max_value !== null" class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ sensor.min_value ?? '-∞' }} - {{ sensor.max_value ?? '∞' }}
                                        </span>
                                        <span v-else class="text-sm italic text-gray-400">Sin límites</span>
                                    </TableCell>

                                    <!-- En Uso -->
                                    <TableCell class="py-4">
                                        <Badge
                                            :class="
                                                sensor.vehicle_sensors_count > 0
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                            "
                                        >
                                            {{ sensor.vehicle_sensors_count }} vehículo{{ sensor.vehicle_sensors_count !== 1 ? 's' : '' }}
                                        </Badge>
                                    </TableCell>

                                    <!-- Acciones -->
                                    <TableCell class="py-4">
                                        <div class="flex items-center justify-center space-x-1">
                                            <Link
                                                :href="route('admin.sensors.show', sensor.id)"
                                                class="rounded-lg p-2 text-gray-400 transition-all hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/50"
                                                title="Ver detalles"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Link>

                                            <Link
                                                :href="route('admin.sensors.edit', sensor.id)"
                                                class="rounded-lg p-2 text-gray-400 transition-all hover:bg-green-50 hover:text-green-600 dark:hover:bg-green-900/50"
                                                title="Editar"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </Link>

                                            <SimpleDropdown align="right">
                                                <template #trigger>
                                                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                        <MoreVertical class="h-4 w-4" />
                                                    </Button>
                                                </template>

                                                <Link
                                                    :href="route('admin.sensors.show', sensor.id)"
                                                    class="flex items-center px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    <Eye class="mr-3 h-4 w-4" />
                                                    Ver Detalles
                                                </Link>

                                                <Link
                                                    :href="route('admin.sensors.edit', sensor.id)"
                                                    class="flex items-center px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    <Edit class="mr-3 h-4 w-4" />
                                                    Editar Sensor
                                                </Link>

                                                <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                                                <button
                                                    @click="deleteSensor(sensor)"
                                                    class="flex w-full items-center px-4 py-2 text-sm text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    :disabled="sensor.vehicle_sensors_count > 0"
                                                    :class="{ 'cursor-not-allowed opacity-50': sensor.vehicle_sensors_count > 0 }"
                                                >
                                                    <Trash2 class="mr-3 h-4 w-4" />
                                                    Eliminar Sensor
                                                </button>
                                            </SimpleDropdown>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Empty State -->
                    <div v-if="sensors.data.length === 0 && !isLoading" class="px-6 py-20 text-center">
                        <div class="mx-auto max-w-md">
                            <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                                <Cpu class="h-12 w-12 text-gray-400" />
                            </div>

                            <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ hasActiveFilters ? 'No se encontraron resultados' : 'No hay sensores registrados' }}
                            </h3>

                            <p class="mb-8 text-gray-600 dark:text-gray-400">
                                {{
                                    hasActiveFilters
                                        ? 'Intenta con otros filtros o términos de búsqueda.'
                                        : 'Comienza agregando sensores para que puedan ser asignados a vehículos.'
                                }}
                            </p>

                            <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Link v-if="!hasActiveFilters" :href="route('admin.sensors.create')">
                                    <Button size="lg" class="bg-cyan-600 text-white shadow-lg hover:bg-cyan-700">
                                        <Plus class="mr-2 h-5 w-5" />
                                        Crear Primer Sensor
                                    </Button>
                                </Link>

                                <Button v-if="hasActiveFilters" variant="outline" size="lg" @click="clearFilters">
                                    <X class="mr-2 h-4 w-4" />
                                    Limpiar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="sensors.links.length > 3 && sensors.data.length > 0"
                        class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50"
                    >
                        <div class="flex flex-col space-y-4 sm:flex-row sm:items-center sm:justify-between sm:space-y-0">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Mostrando <span class="font-semibold">{{ sensors.meta.from }}</span> a
                                <span class="font-semibold">{{ sensors.meta.to }}</span> de
                                <span class="font-semibold">{{ sensors.meta.total }}</span> sensores
                            </div>

                            <div class="flex items-center space-x-2">
                                <template v-for="link in sensors.links" :key="link.label">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        :class="[
                                            'rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200',
                                            link.active
                                                ? 'bg-cyan-600 text-white shadow-md'
                                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300',
                                        ]"
                                    >
                                        <span v-html="link.label"></span>
                                    </Link>
                                    <span
                                        v-else
                                        :class="[
                                            'cursor-not-allowed rounded-lg px-4 py-2 text-sm font-medium opacity-50',
                                            link.active ? 'bg-cyan-600 text-white' : 'text-gray-400',
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
    </AppLayout>
</template>
