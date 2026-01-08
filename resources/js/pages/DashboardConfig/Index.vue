<script setup lang="ts">
/**
 * DashboardConfig/Index.vue
 * 
 * Main page for managing dashboard configurations.
 * Lists all vehicles and their current dashboard layouts.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Badge from '@/components/ui/Badge.vue';
import {
    LayoutDashboard,
    Car,
    Settings2,
    Plus,
    Zap,
    Eye,
    Trash2,
    AlertCircle,
    CheckCircle2,
    Loader2,
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

// Types
interface VehicleSensor {
    id: number;
    sensor_key: string;
    label: string;
}

interface DashboardLayout {
    id: number;
    name: string;
    theme: string;
    is_active: boolean;
    groups_count: number;
    widgets_count: number;
    updated_at: string;
}

interface Vehicle {
    id: number;
    make: string;
    model: string;
    year: number;
    nickname?: string;
    status: boolean;
    sensors: VehicleSensor[];
    active_layout?: DashboardLayout;
}

// Props
interface Props {
    vehicles: Vehicle[];
}

const props = defineProps<Props>();

// State
const generatingFor = ref<number | null>(null);

// Computed
const vehiclesWithLayouts = computed(() => 
    props.vehicles.filter(v => v.active_layout)
);

const vehiclesWithoutLayouts = computed(() => 
    props.vehicles.filter(v => !v.active_layout)
);

// Methods
function getVehicleName(vehicle: Vehicle): string {
    if (vehicle.nickname) return vehicle.nickname;
    return `${vehicle.year} ${vehicle.make} ${vehicle.model}`;
}

async function generateLayout(vehicleId: number) {
    generatingFor.value = vehicleId;
    
    try {
        const response = await fetch(`/api/vehicles/${vehicleId}/dashboard/generate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        if (response.ok) {
            // Reload the page to show the new layout
            router.reload();
        } else {
            console.error('Failed to generate layout');
        }
    } catch (error) {
        console.error('Error generating layout:', error);
    } finally {
        generatingFor.value = null;
    }
}

async function deleteLayout(vehicleId: number) {
    if (!confirm('¿Estás seguro de eliminar este layout? Esta acción no se puede deshacer.')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/vehicles/${vehicleId}/dashboard`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        if (response.ok) {
            router.reload();
        }
    } catch (error) {
        console.error('Error deleting layout:', error);
    }
}

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Configuración de Dashboards', href: '#' },
];
</script>

<template>
    <Head title="Configuración de Dashboards" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Page Header (inline, not using slot) -->
        <div class="mb-6 px-4 pt-4 sm:px-6 lg:px-8">
            <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
                <div class="flex items-center space-x-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 shadow-lg">
                        <LayoutDashboard class="h-7 w-7 text-white" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Configuración de Dashboards
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Personaliza los widgets y layout de cada vehículo
                        </p>
                    </div>
                </div>
                <!-- Action Buttons -->
                <div class="flex gap-2">
                    <Link href="/admin/vehicles">
                        <Button size="sm" class="gap-1.5">
                            <Plus class="h-4 w-4" />
                            Nuevo Vehículo
                        </Button>
                    </Link>
                </div>
            </div>
        </div>

        <div class="pb-6">
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                
                <!-- Vehicles with Layouts -->
                <section v-if="vehiclesWithLayouts.length > 0">
                    <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-gray-200">
                        <CheckCircle2 class="h-5 w-5 text-green-500" />
                        Vehículos Configurados ({{ vehiclesWithLayouts.length }})
                    </h2>
                    
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <Card 
                            v-for="vehicle in vehiclesWithLayouts" 
                            :key="vehicle.id"
                            class="overflow-hidden transition-all hover:shadow-lg"
                        >
                            <!-- Header with gradient -->
                            <div class="bg-gradient-to-r from-slate-800 to-slate-700 p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-white/10">
                                            <Car class="h-6 w-6 text-cyan-400" />
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-white">
                                                {{ getVehicleName(vehicle) }}
                                            </h3>
                                            <p class="text-sm text-slate-400">
                                                {{ vehicle.sensors?.length || 0 }} sensores
                                            </p>
                                        </div>
                                    </div>
                                    <Badge class="bg-green-500/20 text-green-400 border border-green-500/30">
                                        Configurado
                                    </Badge>
                                </div>
                            </div>
                            
                            <CardContent class="p-4">
                                <!-- Layout info -->
                                <div class="mb-4 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ vehicle.active_layout?.name }}
                                        </span>
                                        <Badge class="bg-cyan-500/20 text-cyan-600 dark:text-cyan-400">
                                            {{ vehicle.active_layout?.theme }}
                                        </Badge>
                                    </div>
                                    <div class="mt-2 flex gap-4 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ vehicle.active_layout?.groups_count }} grupos</span>
                                        <span>{{ vehicle.active_layout?.widgets_count }} widgets</span>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="space-y-2">
                                    <!-- V1 Row -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 w-8">V1</span>
                                        <Link :href="`/dashboard-dynamic/${vehicle.id}`" target="_blank">
                                            <Button size="sm" variant="outline" class="gap-1.5">
                                                <Eye class="h-4 w-4" />
                                                Ver
                                            </Button>
                                        </Link>
                                        <Link :href="`/dashboard-config/${vehicle.id}/edit`">
                                            <Button size="sm" class="gap-1.5">
                                                <Settings2 class="h-4 w-4" />
                                                Editar
                                            </Button>
                                        </Link>
                                    </div>
                                    
                                    <!-- V2 Row -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-cyan-500 w-8">V2</span>
                                        <Link :href="`/dashboard-v2/${vehicle.id}`" target="_blank">
                                            <Button size="sm" variant="outline" class="gap-1.5 border-cyan-500 text-cyan-600 hover:bg-cyan-50 dark:text-cyan-400 dark:hover:bg-cyan-900/20">
                                                <Eye class="h-4 w-4" />
                                                Ver
                                            </Button>
                                        </Link>
                                        <Link :href="`/dashboard-v2/${vehicle.id}/config`">
                                            <Button size="sm" class="gap-1.5 bg-cyan-600 hover:bg-cyan-700">
                                                <Settings2 class="h-4 w-4" />
                                                Editar
                                            </Button>
                                        </Link>
                                    </div>
                                    
                                    <!-- Delete -->
                                    <div class="flex justify-end pt-2 border-t border-gray-100 dark:border-gray-700">
                                        <Button 
                                            size="sm" 
                                            variant="ghost" 
                                            class="text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 gap-1.5"
                                            @click="deleteLayout(vehicle.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            Eliminar
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>
                
                <!-- Vehicles without Layouts -->
                <section v-if="vehiclesWithoutLayouts.length > 0">
                    <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-gray-200">
                        <AlertCircle class="h-5 w-5 text-yellow-500" />
                        Vehículos Sin Configurar ({{ vehiclesWithoutLayouts.length }})
                    </h2>
                    
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <Card 
                            v-for="vehicle in vehiclesWithoutLayouts" 
                            :key="vehicle.id"
                            class="overflow-hidden border-dashed transition-all hover:shadow-lg"
                        >
                            <CardContent class="p-6">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800">
                                        <Car class="h-7 w-7 text-slate-400" />
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">
                                            {{ getVehicleName(vehicle) }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            {{ vehicle.sensors?.length || 0 }} sensores disponibles
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="mt-6 flex flex-wrap gap-2">
                                    <Button 
                                        class="flex-1 gap-1.5"
                                        @click="generateLayout(vehicle.id)"
                                        :disabled="generatingFor === vehicle.id"
                                    >
                                        <Loader2 v-if="generatingFor === vehicle.id" class="h-4 w-4 animate-spin" />
                                        <Zap v-else class="h-4 w-4" />
                                        {{ generatingFor === vehicle.id ? 'Generando...' : 'Auto-Generar' }}
                                    </Button>
                                    
                                    <Link :href="`/dashboard-config/${vehicle.id}/edit`" class="flex-1">
                                        <Button variant="outline" class="w-full gap-1.5">
                                            <Plus class="h-4 w-4" />
                                            Crear Manual
                                        </Button>
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>
                
                <!-- Empty State -->
                <section v-if="vehicles.length === 0">
                    <Card class="p-12 text-center">
                        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <Car class="h-10 w-10 text-slate-400" />
                        </div>
                        <h3 class="mt-6 text-lg font-semibold text-gray-800 dark:text-gray-200">
                            No hay vehículos registrados
                        </h3>
                        <p class="mt-2 text-gray-500">
                            Primero necesitas registrar un vehículo antes de configurar su dashboard.
                        </p>
                        <Link href="/clients">
                            <Button class="mt-6">
                                Ir a Clientes
                            </Button>
                        </Link>
                    </Card>
                </section>
                
            </div>
        </div>
    </AppLayout>
</template>
