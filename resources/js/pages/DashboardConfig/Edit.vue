<script setup lang="ts">
/**
 * DashboardConfig/Edit.vue
 * 
 * Visual dashboard editor with drag & drop widget configuration.
 * Allows users to customize groups, widgets, and sensor bindings.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Badge from '@/components/ui/Badge.vue';
import WidgetPicker from './components/WidgetPicker.vue';
import GroupEditor from './components/GroupEditor.vue';
import BindingModal from './components/BindingModal.vue';
import draggable from 'vuedraggable';
import {
    ArrowLeft,
    Save,
    Loader2,
    Plus,
    LayoutDashboard,
    Paintbrush,
    Eye,
    Undo2,
    Settings2,
    Trash2,
    GripVertical,
    Zap,
    Map as MapIcon,
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

// Types
interface Sensor {
    id: number;
    sensor_key: string;
    label: string;
    unit: string;
    category: string;
}

interface WidgetDefinition {
    id: number;
    type: string;
    name: string;
    icon: string;
    component_name: string;
    category: string;
    description: string;
    supports_thresholds: boolean;
    supports_multiple_slots: boolean;
    props_schema: Record<string, any>;
}

interface Binding {
    id?: number;
    vehicle_sensor_id: number; // Required by backend
    telemetry_key: string;
    target_prop: string;
    slot?: string;
    label?: string;
    unit?: string;
}

interface Widget {
    id?: number;
    tempId?: string;
    widget_definition_id: number;
    definition?: WidgetDefinition;
    size_class: string;
    props: Record<string, any>;
    bindings: Binding[];
    sort_order: number;
}

interface Group {
    id?: number;
    tempId?: string;
    name: string;
    slug: string;
    icon: string;
    grid_column_start: number;
    grid_column_span: number;
    style_config: Record<string, any>;
    sort_order: number;
    widgets: Widget[];
    is_expanded?: boolean;
}

interface Layout {
    id?: number;
    name: string;
    theme: string;
    grid_config: Record<string, any>;
    is_active: boolean;
}

interface Vehicle {
    id: number;
    make: string;
    model: string;
    year: number;
    nickname?: string;
}

// Props
interface Props {
    vehicle: Vehicle;
    layout?: Layout;
    groups?: Group[];
    sensors: Sensor[];
    widgetDefinitions: WidgetDefinition[];
}

const props = defineProps<Props>();

// State
const saving = ref(false);
const hasChanges = ref(false);
const isInitialized = ref(false);
const showWidgetPicker = ref(false);
const activeGroupIndex = ref<number | null>(null);
const showBindingModal = ref(false);
const bindingTarget = ref<{ groupIndex: number; widgetIndex: number } | null>(null);

// Form data
const layoutForm = ref<Layout>({
    id: props.layout?.id,
    name: props.layout?.name || `${props.vehicle.nickname || props.vehicle.make} Dashboard`,
    theme: props.layout?.theme || 'cyberpunk-dark',
    grid_config: props.layout?.grid_config || { columns: 12, gap: 4 },
    is_active: props.layout?.is_active ?? true,
});

const groupsForm = ref<Group[]>(
    props.groups?.map(g => ({
        ...g,
        is_expanded: true,
        widgets: g.widgets || [],
    })) || []
);

// Map configuration
const mapConfig = ref({
    enabled: props.layout?.grid_config?.map?.enabled ?? true,
    defaultLayer: props.layout?.grid_config?.map?.defaultLayer ?? 'dark',
});

const mapLayers = [
    { value: 'dark', label: '🌑 Oscuro (Default)' },
    { value: 'light', label: '☀️ Claro' },
    { value: 'satellite', label: '🛰️ Satélite' },
];

// Shift Lights configuration (special component in header)
const shiftLightsConfig = ref({
    enabled: props.layout?.grid_config?.shiftLights?.enabled ?? true,
    totalLights: props.layout?.grid_config?.shiftLights?.totalLights ?? 10,
    startRpm: props.layout?.grid_config?.shiftLights?.startRpm ?? 4000,
    shiftRpm: props.layout?.grid_config?.shiftLights?.shiftRpm ?? 7000,
    maxRpm: props.layout?.grid_config?.shiftLights?.maxRpm ?? 8000,
    rpmSensorKey: props.layout?.grid_config?.shiftLights?.rpmSensorKey ?? 'RPM',
});

// Available themes (only show implemented ones)
const themes = [
    { value: 'cyberpunk-dark', label: '🌃 Cyberpunk Dark', description: 'Verde neón, futurista' },
    { value: 'racing-red', label: '🏁 Slate Pro', description: 'Cyan/Slate, profesional' },
];

// Computed
const vehicleName = computed(() => {
    if (props.vehicle.nickname) return props.vehicle.nickname;
    return `${props.vehicle.year} ${props.vehicle.make} ${props.vehicle.model}`;
});

const totalWidgets = computed(() => {
    return groupsForm.value.reduce((sum, g) => sum + g.widgets.length, 0);
});

const sensorOptions = computed(() => {
    return props.sensors.map(s => ({
        id: s.id,
        value: s.sensor_key,
        label: s.label || s.sensor_key,
        unit: s.unit,
        category: s.category,
    }));
});

// Watch for changes - only after initialization
watch([layoutForm, groupsForm, shiftLightsConfig, mapConfig], () => {
    if (isInitialized.value) {
        hasChanges.value = true;
    }
}, { deep: true });

// Mark as initialized after first render
onMounted(() => {
    // Use nextTick to ensure watchers have settled
    setTimeout(() => {
        isInitialized.value = true;
    }, 100);
});

// Methods
function generateTempId(): string {
    return `temp_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
}

function addGroup() {
    const newGroup: Group = {
        tempId: generateTempId(),
        name: `Grupo ${groupsForm.value.length + 1}`,
        slug: `group-${groupsForm.value.length + 1}`,
        icon: 'gauge',
        grid_column_start: 1,
        grid_column_span: 12,
        style_config: { bgColor: 'bg-slate-800/60', borderColor: 'border-slate-700/50' },
        sort_order: groupsForm.value.length,
        widgets: [],
        is_expanded: true,
    };
    groupsForm.value.push(newGroup);
}

function removeGroup(index: number) {
    if (confirm('¿Estás seguro de eliminar este grupo y todos sus widgets?')) {
        groupsForm.value.splice(index, 1);
        // Update sort orders
        groupsForm.value.forEach((g, i) => g.sort_order = i);
    }
}

function toggleGroupExpand(index: number) {
    groupsForm.value[index].is_expanded = !groupsForm.value[index].is_expanded;
}

function onGroupReorder() {
    // Update sort_order after drag & drop
    groupsForm.value.forEach((g, i) => g.sort_order = i);
}

function openWidgetPicker(groupIndex: number) {
    activeGroupIndex.value = groupIndex;
    showWidgetPicker.value = true;
}

function addWidget(definition: WidgetDefinition) {
    if (activeGroupIndex.value === null) return;
    
    const group = groupsForm.value[activeGroupIndex.value];
    
    // Default props with min/max for gauge widgets
    const defaultProps: Record<string, any> = {
        ...definition.props_schema,
    };
    
    // Set default min/max for gauge and bar widgets
    if (definition.type === 'radial_gauge' || definition.type === 'linear_bar') {
        defaultProps.min = defaultProps.min ?? 0;
        defaultProps.max = defaultProps.max ?? 100;
    }
    
    const newWidget: Widget = {
        tempId: generateTempId(),
        widget_definition_id: definition.id,
        definition: definition,
        size_class: 'md',
        props: defaultProps,
        bindings: [],
        sort_order: group.widgets.length,
    };
    
    group.widgets.push(newWidget);
    showWidgetPicker.value = false;
}

function removeWidget(groupIndex: number, widgetIndex: number) {
    groupsForm.value[groupIndex].widgets.splice(widgetIndex, 1);
    // Update sort orders
    groupsForm.value[groupIndex].widgets.forEach((w, i) => w.sort_order = i);
}

function openBindingModal(groupIndex: number, widgetIndex: number) {
    bindingTarget.value = { groupIndex, widgetIndex };
    showBindingModal.value = true;
}

function saveBinding(payload: { binding: Binding | null; widgetProps: Record<string, any> }) {
    if (!bindingTarget.value) return;
    
    const widget = groupsForm.value[bindingTarget.value.groupIndex]
        .widgets[bindingTarget.value.widgetIndex];
    
    // Update widget props if provided
    if (payload.widgetProps) {
        widget.props = { ...widget.props, ...payload.widgetProps };
    }
    
    // Handle binding if a sensor was selected
    if (payload.binding) {
        const binding = payload.binding;
        
        // Check if binding for this slot already exists
        const existingIndex = widget.bindings.findIndex(b => 
            b.slot === binding.slot && b.target_prop === binding.target_prop
        );
        
        if (existingIndex >= 0) {
            widget.bindings[existingIndex] = binding;
        } else {
            widget.bindings.push(binding);
        }
    }
    
    showBindingModal.value = false;
    bindingTarget.value = null;
}

function removeBinding(groupIndex: number, widgetIndex: number, bindingIndex: number) {
    groupsForm.value[groupIndex].widgets[widgetIndex].bindings.splice(bindingIndex, 1);
}

async function saveLayout() {
    saving.value = true;
    
    try {
        // Prepare payload
        const payload = {
            layout: {
                name: layoutForm.value.name,
                theme: layoutForm.value.theme,
                grid_config: {
                    ...layoutForm.value.grid_config,
                    shiftLights: shiftLightsConfig.value,
                    map: mapConfig.value,
                },
                is_active: layoutForm.value.is_active,
            },
            groups: groupsForm.value.map((g, gi) => ({
                id: g.id,
                name: g.name,
                slug: g.slug || g.name.toLowerCase().replace(/\s+/g, '-'),
                icon: g.icon,
                grid_column_start: g.grid_column_start,
                grid_column_span: g.grid_column_span,
                style_config: g.style_config,
                sort_order: gi,
                widgets: g.widgets.map((w, wi) => ({
                    id: w.id,
                    widget_definition_id: w.widget_definition_id,
                    size_class: w.size_class,
                    props: w.props,
                    sort_order: wi,
                    bindings: w.bindings.map(b => ({
                        id: b.id,
                        vehicle_sensor_id: b.vehicle_sensor_id,
                        telemetry_key: b.telemetry_key,
                        target_prop: b.target_prop,
                        slot: b.slot,
                        label: b.label,
                        unit: b.unit,
                    })),
                })),
            })),
        };
        
        const response = await fetch(`/api/vehicles/${props.vehicle.id}/dashboard`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(payload),
        });
        
        if (response.ok) {
            hasChanges.value = false;
            // Optionally show success message
        } else {
            const error = await response.json();
            console.error('Save failed:', error);
            alert('Error al guardar: ' + (error.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Save error:', error);
        alert('Error de conexión al guardar');
    } finally {
        saving.value = false;
    }
}

function previewDashboard() {
    window.open(`/dashboard-dynamic/${props.vehicle.id}`, '_blank');
}

// Breadcrumbs
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Configuración', href: '/dashboard-config' },
    { title: vehicleName.value, href: '#' },
]);
</script>

<template>
    <Head :title="`Configurar Dashboard - ${vehicleName}`" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Page Header (inline, not using slot) -->
        <div class="mb-6 px-4 pt-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/dashboard-config">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                    
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600">
                            <LayoutDashboard class="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                Configurar Dashboard
                            </h1>
                            <p class="text-sm text-gray-500">{{ vehicleName }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="previewDashboard" class="gap-2">
                        <Eye class="h-4 w-4" />
                        Vista Previa
                    </Button>
                    
                    <Button 
                        @click="saveLayout" 
                        :disabled="saving"
                        :class="[
                            'gap-2',
                            hasChanges ? 'ring-2 ring-orange-500 ring-offset-2' : ''
                        ]"
                    >
                        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        {{ saving ? 'Guardando...' : (hasChanges ? 'Guardar *' : 'Guardar') }}
                    </Button>
                </div>
            </div>
        </div>

        <div class="pb-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                
                <!-- Layout Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Paintbrush class="h-5 w-5 text-purple-500" />
                            Configuración General
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <!-- Layout Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Nombre del Layout
                                </label>
                                <input
                                    v-model="layoutForm.name"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700"
                                    placeholder="Mi Dashboard"
                                />
                            </div>
                            
                            <!-- Theme -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Tema Visual
                                </label>
                                <select
                                    v-model="layoutForm.theme"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700"
                                >
                                    <option v-for="theme in themes" :key="theme.value" :value="theme.value">
                                        {{ theme.label }}
                                    </option>
                                </select>
                            </div>
                            
                            <!-- Stats -->
                            <div class="flex items-center gap-4">
                                <div class="rounded-lg bg-slate-100 px-4 py-2 dark:bg-slate-800">
                                    <span class="text-2xl font-bold text-cyan-500">{{ groupsForm.length }}</span>
                                    <span class="ml-1 text-sm text-gray-500">grupos</span>
                                </div>
                                <div class="rounded-lg bg-slate-100 px-4 py-2 dark:bg-slate-800">
                                    <span class="text-2xl font-bold text-green-500">{{ totalWidgets }}</span>
                                    <span class="ml-1 text-sm text-gray-500">widgets</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                
                <!-- Map Configuration -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <MapIcon class="h-5 w-5 text-blue-500" />
                            Mapa GPS
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Mostrar Mapa GPS</h3>
                                <p class="text-sm text-gray-500">Panel principal con ubicación y rastro en tiempo real</p>
                            </div>
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" v-model="mapConfig.enabled" class="peer sr-only">
                                <div class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none dark:bg-gray-700"></div>
                            </label>
                        </div>
                        
                        <div v-if="mapConfig.enabled" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Capa por Defecto
                                </label>
                                <select
                                    v-model="mapConfig.defaultLayer"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700"
                                >
                                    <option v-for="layer in mapLayers" :key="layer.value" :value="layer.value">
                                        {{ layer.label }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">
                                    El usuario podrá cambiar esto en tiempo real, pero este será el valor inicial.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Shift Lights Configuration -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Zap class="h-5 w-5 text-yellow-500" />
                            Luces de Cambio (Shift Lights)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <!-- Enable Toggle -->
                            <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-800">
                                <div>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">Mostrar Shift Lights</span>
                                    <p class="text-sm text-gray-500">Barra de luces indicadora de cambio de marcha en la parte superior</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input 
                                        v-model="shiftLightsConfig.enabled" 
                                        type="checkbox" 
                                        class="sr-only peer"
                                    />
                                    <div class="w-11 h-6 bg-gray-300 peer-focus:ring-2 peer-focus:ring-cyan-300 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
                                </label>
                            </div>
                            
                            <!-- Config Fields (only show if enabled) -->
                            <div v-show="shiftLightsConfig.enabled" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                <!-- Total Lights -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Número de Luces
                                    </label>
                                    <input
                                        v-model.number="shiftLightsConfig.totalLights"
                                        type="number"
                                        min="5"
                                        max="20"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                
                                <!-- Start RPM -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        RPM Inicio
                                    </label>
                                    <input
                                        v-model.number="shiftLightsConfig.startRpm"
                                        type="number"
                                        step="500"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                
                                <!-- Shift RPM -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        RPM Cambio (SHIFT)
                                    </label>
                                    <input
                                        v-model.number="shiftLightsConfig.shiftRpm"
                                        type="number"
                                        step="500"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                
                                <!-- Max RPM -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        RPM Máximo (Redline)
                                    </label>
                                    <input
                                        v-model.number="shiftLightsConfig.maxRpm"
                                        type="number"
                                        step="500"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                
                                <!-- RPM Sensor -->
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Sensor RPM
                                    </label>
                                    <select
                                        v-model="shiftLightsConfig.rpmSensorKey"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    >
                                        <option 
                                            v-for="sensor in sensorOptions.filter(s => s.label.toLowerCase().includes('rpm') || s.value.toLowerCase().includes('rpm'))" 
                                            :key="sensor.value" 
                                            :value="sensor.value"
                                        >
                                            {{ sensor.label }}
                                        </option>
                                        <option value="RPM">RPM (default)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Visual Preview -->
                            <div v-if="shiftLightsConfig.enabled" class="p-4 bg-slate-900 rounded-lg">
                                <p class="text-xs text-slate-500 mb-2">Vista previa:</p>
                                <div class="flex items-center justify-center gap-1.5">
                                    <div 
                                        v-for="i in shiftLightsConfig.totalLights" 
                                        :key="i"
                                        class="w-4 h-4 rounded-full transition-colors"
                                        :class="[
                                            i <= 5 ? 'bg-green-500' : 
                                            i <= 8 ? 'bg-yellow-400' : 
                                            'bg-red-500'
                                        ]"
                                        :style="{ 
                                            boxShadow: i <= 5 ? '0 0 8px rgba(34, 197, 94, 0.6)' : 
                                                       i <= 8 ? '0 0 8px rgba(250, 204, 21, 0.6)' : 
                                                       '0 0 8px rgba(239, 68, 68, 0.8)'
                                        }"
                                    />
                                </div>
                                <p class="text-xs text-center text-slate-500 mt-2">
                                    {{ shiftLightsConfig.startRpm.toLocaleString() }} → 
                                    <span class="text-yellow-400">{{ shiftLightsConfig.shiftRpm.toLocaleString() }}</span> → 
                                    <span class="text-red-400">{{ shiftLightsConfig.maxRpm.toLocaleString() }}</span> RPM
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            Grupos de Widgets
                        </h2>
                        <Button @click="addGroup" size="sm" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Agregar Grupo
                        </Button>
                    </div>
                    
                    <!-- Groups List (Comfortable Editor View) -->
                    <draggable
                        v-if="groupsForm.length > 0"
                        v-model="groupsForm"
                        item-key="id"
                        handle=".group-drag-handle"
                        ghost-class="opacity-50"
                        animation="200"
                        class="space-y-4"
                        @end="onGroupReorder"
                    >
                        <template #item="{ element: group, index: groupIndex }">
                             <GroupEditor
                                :group="group"
                                :group-index="groupIndex"
                                :widget-definitions="widgetDefinitions"
                                :sensors="sensors"
                                @toggle-expand="toggleGroupExpand(groupIndex)"
                                @remove="removeGroup(groupIndex)"
                                @add-widget="openWidgetPicker(groupIndex)"
                                @remove-widget="(wi) => removeWidget(groupIndex, wi)"
                                @edit-binding="(wi) => openBindingModal(groupIndex, wi)"
                                @remove-binding="(wi, bi) => removeBinding(groupIndex, wi, bi)"
                                @update:span="(val) => group.grid_column_span = val"
                            />
                        </template>
                    </draggable>
                    
                    <!-- Empty State -->
                    <Card v-else class="p-8 text-center border-dashed">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <LayoutDashboard class="h-8 w-8 text-slate-400" />
                        </div>
                        <h3 class="mt-4 font-semibold text-gray-800 dark:text-gray-200">
                            Sin grupos configurados
                        </h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Agrega tu primer grupo para comenzar a organizar widgets
                        </p>
                        <Button @click="addGroup" class="mt-4 gap-2">
                            <Plus class="h-4 w-4" />
                            Crear Primer Grupo
                        </Button>
                    </Card>
                </div>
                
            </div>
        </div>
        
        <!-- Widget Picker Modal -->
        <WidgetPicker
            :show="showWidgetPicker"
            :definitions="widgetDefinitions"
            @close="showWidgetPicker = false"
            @select="addWidget"
        />
        
        <!-- Binding Modal -->
        <BindingModal
            :show="showBindingModal"
            :sensors="sensorOptions"
            :widget="bindingTarget ? groupsForm[bindingTarget.groupIndex]?.widgets[bindingTarget.widgetIndex] : null"
            @close="showBindingModal = false; bindingTarget = null"
            @save="saveBinding"
        />
    </AppLayout>
</template>
