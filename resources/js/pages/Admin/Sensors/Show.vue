<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Table from '@/components/ui/Table.vue';
import TableBody from '@/components/ui/TableBody.vue';
import TableCell from '@/components/ui/TableCell.vue';
import TableHead from '@/components/ui/TableHead.vue';
import TableHeader from '@/components/ui/TableHeader.vue';
import TableRow from '@/components/ui/TableRow.vue';
import {
    ArrowLeft,
    Edit,
    Trash2,
    Cpu,
    Zap,
    Activity,
    Gauge,
    Database,
    Car,
    User,
    Hash,
    Info,
    Settings,
    Calendar,
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
    requires_calculation: boolean;
    calculation_formula: string | null;
    data_bytes: number;
    is_standard: boolean;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

interface VehicleSensor {
    id: number;
    vehicle_id: number;
    sensor_id: number;
    mapping_key: string | null;
    source_type: string;
    is_active: boolean;
    vehicle: {
        id: number;
        vin: string;
        make: string | null;
        model: string | null;
        year: number | null;
        client_device: {
            id: number;
            device_name: string;
            client: {
                id: number;
                first_name: string;
                last_name: string;
                company: string | null;
            };
        };
    };
}

interface Props {
    sensor: Sensor;
    vehicleSensors: VehicleSensor[];
    usageCount: number;
}

const props = defineProps<Props>();

const deleteSensor = () => {
    if (props.usageCount > 0) {
        alert(`No se puede eliminar este sensor porque está en uso por ${props.usageCount} vehículo(s).`);
        return;
    }

    if (confirm(`¿Estás seguro de eliminar el sensor "${props.sensor.name}"?`)) {
        router.delete(route('admin.sensors.destroy', props.sensor.id));
    }
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

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('es-ES', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Sensores', href: '/admin/sensors' },
    { title: props.sensor.name, href: `/admin/sensors/${props.sensor.id}` },
];
</script>

<template>
    <Head :title="sensor.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header -->
        <template #header>
            <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
                <div class="flex items-center space-x-4">
                    <Link :href="route('admin.sensors.index')">
                        <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Volver
                        </Button>
                    </Link>

                    <div class="flex items-center space-x-4">
                        <div class="rounded-lg bg-cyan-100 p-3 dark:bg-cyan-900/50">
                            <component :is="getCategoryIcon(sensor.category)" class="h-8 w-8 text-cyan-600 dark:text-cyan-400" />
                        </div>
                        <div>
                            <div class="flex items-center space-x-3">
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ sensor.name }}</h1>
                                <Badge :class="getCategoryColor(sensor.category)">
                                    {{ sensor.category }}
                                </Badge>
                                <Badge
                                    :class="
                                        sensor.is_standard
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'
                                    "
                                >
                                    {{ sensor.is_standard ? 'OBD Estándar' : 'Custom/CAN' }}
                                </Badge>
                            </div>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">
                                PID:
                                <code class="rounded bg-gray-200 px-2 py-0.5 font-mono text-cyan-600 dark:bg-gray-700 dark:text-cyan-400">
                                    {{ sensor.pid }}
                                </code>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link :href="route('admin.sensors.edit', sensor.id)">
                        <Button variant="outline" size="sm">
                            <Edit class="mr-2 h-4 w-4" />
                            Editar
                        </Button>
                    </Link>

                    <Button
                        variant="outline"
                        size="sm"
                        class="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
                        @click="deleteSensor"
                        :disabled="usageCount > 0"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Eliminar
                    </Button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Información Principal -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Descripción -->
                        <Card class="border border-gray-200 dark:border-gray-700">
                            <CardContent class="p-6">
                                <h3 class="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <Info class="mr-2 h-5 w-5 text-cyan-500" />
                                    Descripción
                                </h3>
                                <p v-if="sensor.description" class="text-gray-600 dark:text-gray-400">
                                    {{ sensor.description }}
                                </p>
                                <p v-else class="italic text-gray-400">Sin descripción</p>
                            </CardContent>
                        </Card>

                        <!-- Configuración -->
                        <Card class="border border-gray-200 dark:border-gray-700">
                            <CardContent class="p-6">
                                <h3 class="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <Settings class="mr-2 h-5 w-5 text-cyan-500" />
                                    Configuración Técnica
                                </h3>
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Unidad</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ sensor.unit }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Tipo de Datos</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ sensor.data_type }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Bytes</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ sensor.data_bytes }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Valor Mínimo</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ sensor.min_value ?? 'Sin límite' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Valor Máximo</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ sensor.max_value ?? 'Sin límite' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Requiere Cálculo</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ sensor.requires_calculation ? 'Sí' : 'No' }}</p>
                                    </div>
                                </div>

                                <div v-if="sensor.requires_calculation && sensor.calculation_formula" class="mt-4">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Fórmula de Conversión</p>
                                    <code class="mt-1 block rounded bg-gray-100 p-2 font-mono text-sm text-cyan-600 dark:bg-gray-700 dark:text-cyan-400">
                                        {{ sensor.calculation_formula }}
                                    </code>
                                </div>

                                <div v-if="sensor.notes" class="mt-4">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Notas</p>
                                    <p class="mt-1 text-gray-600 dark:text-gray-400">{{ sensor.notes }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Vehículos que usan este sensor -->
                        <Card class="border border-gray-200 dark:border-gray-700">
                            <CardContent class="p-6">
                                <h3 class="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <Car class="mr-2 h-5 w-5 text-cyan-500" />
                                    Vehículos Usando Este Sensor
                                    <Badge class="ml-2 bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-400">
                                        {{ usageCount }}
                                    </Badge>
                                </h3>

                                <div v-if="vehicleSensors.length > 0">
                                    <Table>
                                        <TableHeader>
                                            <TableRow class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                                <TableHead class="font-semibold">Vehículo</TableHead>
                                                <TableHead class="font-semibold">Cliente</TableHead>
                                                <TableHead class="font-semibold">Mapping Key</TableHead>
                                                <TableHead class="font-semibold">Estado</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow
                                                v-for="vs in vehicleSensors"
                                                :key="vs.id"
                                                class="border-b border-gray-100 dark:border-gray-800"
                                            >
                                                <TableCell>
                                                    <div class="flex items-center space-x-2">
                                                        <Car class="h-4 w-4 text-gray-400" />
                                                        <div>
                                                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                                                {{ vs.vehicle.make }} {{ vs.vehicle.model }}
                                                                {{ vs.vehicle.year ? `(${vs.vehicle.year})` : '' }}
                                                            </p>
                                                            <p class="text-xs text-gray-500">VIN: {{ vs.vehicle.vin }}</p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div class="flex items-center space-x-2">
                                                        <User class="h-4 w-4 text-gray-400" />
                                                        <div>
                                                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                                                {{ vs.vehicle.client_device.client.first_name }}
                                                                {{ vs.vehicle.client_device.client.last_name }}
                                                            </p>
                                                            <p v-if="vs.vehicle.client_device.client.company" class="text-xs text-gray-500">
                                                                {{ vs.vehicle.client_device.client.company }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <code
                                                        v-if="vs.mapping_key"
                                                        class="rounded bg-gray-100 px-2 py-1 font-mono text-sm dark:bg-gray-700"
                                                    >
                                                        {{ vs.mapping_key }}
                                                    </code>
                                                    <span v-else class="text-gray-400">—</span>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        :class="
                                                            vs.is_active
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                                : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                                        "
                                                    >
                                                        {{ vs.is_active ? 'Activo' : 'Inactivo' }}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                                <div v-else class="py-8 text-center text-gray-500">
                                    <Car class="mx-auto mb-2 h-12 w-12 text-gray-300" />
                                    <p>Este sensor no está asignado a ningún vehículo</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Info -->
                        <Card class="border border-gray-200 dark:border-gray-700">
                            <CardContent class="p-6">
                                <h3 class="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <Hash class="mr-2 h-5 w-5 text-cyan-500" />
                                    Información
                                </h3>
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">ID</p>
                                        <p class="font-mono text-gray-900 dark:text-gray-100">{{ sensor.id }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">PID</p>
                                        <code class="rounded bg-gray-100 px-2 py-0.5 font-mono text-cyan-600 dark:bg-gray-700 dark:text-cyan-400">
                                            {{ sensor.pid }}
                                        </code>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Categoría</p>
                                        <Badge :class="getCategoryColor(sensor.category)" class="mt-1">
                                            {{ sensor.category }}
                                        </Badge>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Tipo</p>
                                        <Badge
                                            :class="
                                                sensor.is_standard
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'
                                            "
                                            class="mt-1"
                                        >
                                            {{ sensor.is_standard ? 'OBD Estándar' : 'Custom/CAN' }}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Fechas -->
                        <Card class="border border-gray-200 dark:border-gray-700">
                            <CardContent class="p-6">
                                <h3 class="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <Calendar class="mr-2 h-5 w-5 text-cyan-500" />
                                    Fechas
                                </h3>
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Creado</p>
                                        <p class="text-gray-900 dark:text-gray-100">{{ formatDate(sensor.created_at) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Actualizado</p>
                                        <p class="text-gray-900 dark:text-gray-100">{{ formatDate(sensor.updated_at) }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Uso -->
                        <Card class="border border-gray-200 dark:border-gray-700">
                            <CardContent class="p-6">
                                <h3 class="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <Activity class="mr-2 h-5 w-5 text-cyan-500" />
                                    Uso
                                </h3>
                                <div class="text-center">
                                    <p class="text-4xl font-bold text-cyan-600 dark:text-cyan-400">{{ usageCount }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">vehículo{{ usageCount !== 1 ? 's' : '' }} usando este sensor</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
