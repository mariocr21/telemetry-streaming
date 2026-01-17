<script setup lang="ts">
/**
 * BindingModal.vue
 * 
 * Modal to create/edit sensor bindings AND configure widget props.
 * Features:
 * - Tab-based UI for better organization
 * - Pre-loads existing bindings for editing
 * - Widget props configuration from props_schema
 * - Clear visual feedback for current state
 */
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { X, Link2, Search, Settings2, Unlink, Check, AlertCircle } from 'lucide-vue-next';

// Types
interface SensorOption {
    id: number;
    value: string;
    label: string;
    unit?: string;
    category?: string;
}

interface Binding {
    id?: number;
    vehicle_sensor_id: number;
    telemetry_key: string;
    target_prop: string;
    slot?: string;
    label?: string;
    unit?: string;
}

interface PropSchema {
    type: string;
    default?: any;
    label?: string;
    min?: number;
    max?: number;
    options?: string[];
}

interface WidgetDefinition {
    id: number;
    type: string;
    name: string;
    supports_multiple_slots: boolean;
    props_schema?: Record<string, PropSchema>;
}

interface Widget {
    id?: number;
    definition?: WidgetDefinition;
    props: Record<string, any>;
    bindings: Binding[];
}

// Props
interface Props {
    show: boolean;
    sensors: SensorOption[];
    widget: Widget | null;
}

const props = defineProps<Props>();

// Emits
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', payload: { binding: Binding | null; widgetProps: Record<string, any> }): void;
}>();

// State
const activeTab = ref<'config' | 'sensor'>('config');
const searchQuery = ref('');
const selectedSensor = ref<string>('');
const targetProp = ref<string>('value');
const slot = ref<string>('');
const customLabel = ref<string>('');
const customUnit = ref<string>('');

// Widget props state - initialized from widget.props
const widgetPropsForm = ref<Record<string, any>>({});

// Track original values for change detection
const originalSensorKey = ref<string>('');

// Reset form when modal opens
watch(() => props.show, (isOpen) => {
    if (isOpen && props.widget) {
        searchQuery.value = '';
        targetProp.value = 'value';
        slot.value = '';
        
        // Initialize widget props from current widget
        if (props.widget.props) {
            widgetPropsForm.value = { ...props.widget.props };
        } else {
            widgetPropsForm.value = {};
        }
        
        // Fill defaults from schema for missing props
        if (props.widget.definition?.props_schema) {
            for (const [key, schema] of Object.entries(props.widget.definition.props_schema)) {
                if (widgetPropsForm.value[key] === undefined && schema.default !== undefined) {
                    widgetPropsForm.value[key] = schema.default;
                }
            }
        }
        
        // Pre-load existing binding if widget has one
        if (props.widget.bindings && props.widget.bindings.length > 0) {
            const existingBinding = props.widget.bindings[0];
            selectedSensor.value = existingBinding.telemetry_key || '';
            originalSensorKey.value = existingBinding.telemetry_key || '';
            customLabel.value = existingBinding.label || '';
            customUnit.value = existingBinding.unit || '';
            slot.value = existingBinding.slot || '';
            targetProp.value = existingBinding.target_prop || 'value';
        } else {
            selectedSensor.value = '';
            originalSensorKey.value = '';
            customLabel.value = '';
            customUnit.value = '';
        }
        // Start on config tab for special widgets or if has configurable props
        const widgetType = props.widget?.definition?.type || '';
        const specialWidgets = ['video_stream', 'map_widget', 'gps_info'];
        const isSpecial = specialWidgets.includes(widgetType);
        
        // Special widgets always show config tab, regular widgets show config if has props
        if (isSpecial) {
            activeTab.value = 'config';
        } else {
            activeTab.value = (props.widget?.definition?.props_schema && 
                Object.keys(props.widget.definition.props_schema).length > 0) ? 'config' : 'sensor';
        }
    }
});

// Computed
const filteredSensors = computed(() => {
    if (!searchQuery.value) return props.sensors;
    
    const query = searchQuery.value.toLowerCase();
    return props.sensors.filter(s => 
        s.label.toLowerCase().includes(query) || 
        s.value.toLowerCase().includes(query)
    );
});

const groupedSensors = computed(() => {
    const groups: Record<string, SensorOption[]> = {};
    
    for (const sensor of filteredSensors.value) {
        const category = sensor.category || 'general';
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(sensor);
    }
    
    return groups;
});

const selectedSensorData = computed(() => {
    return props.sensors.find(s => s.value === selectedSensor.value);
});

const isMultiSlot = computed(() => {
    return props.widget?.definition?.supports_multiple_slots ?? false;
});

// Props schema as array for iteration
// For widgets that need sensor binding: filter out 'label' and 'unit' as those come from binding
// For special widgets (video_stream, etc): show all props
const propsSchemaArray = computed(() => {
    const schema = props.widget?.definition?.props_schema;
    if (!schema) return [];
    
    const widgetType = props.widget?.definition?.type || '';
    const specialWidgets = ['video_stream', 'map_widget', 'gps_info']; // Widgets that don't need sensor binding
    const isSpecialWidget = specialWidgets.includes(widgetType);
    
    return Object.entries(schema)
        .filter(([key]) => {
            // Special widgets show all their props
            if (isSpecialWidget) return true;
            // Regular widgets: filter out label/unit (those come from sensor)
            return !['label', 'unit'].includes(key);
        })
        .map(([key, config]) => ({
            key,
            ...config,
        }));
});

// Check if widget has configurable props (excluding label/unit)
const hasConfigurableProps = computed(() => {
    return propsSchemaArray.value.length > 0;
});

// Slot options for multi-slot widgets
const slotOptions = computed(() => {
    const type = props.widget?.definition?.type || '';
    
    if (type === 'tire_grid') {
        return [
            { value: 'fl', label: 'Frontal Izquierda (FL)' },
            { value: 'fr', label: 'Frontal Derecha (FR)' },
            { value: 'rl', label: 'Trasera Izquierda (RL)' },
            { value: 'rr', label: 'Trasera Derecha (RR)' },
        ];
    }
    
    if (type === 'text_grid') {
        return [
            { value: 'slot1', label: 'Slot 1' },
            { value: 'slot2', label: 'Slot 2' },
            { value: 'slot3', label: 'Slot 3' },
            { value: 'slot4', label: 'Slot 4' },
        ];
    }
    
    return [];
});

// Check if widget has an existing binding
const hasExistingBinding = computed(() => {
    return props.widget?.bindings && props.widget.bindings.length > 0;
});

// Check if widget is a special type that doesn't need sensor binding
const isSpecialWidget = computed(() => {
    const widgetType = props.widget?.definition?.type || '';
    const specialWidgets = ['video_stream', 'map_widget', 'gps_info'];
    return specialWidgets.includes(widgetType);
});

// Methods
function selectSensor(sensorKey: string) {
    selectedSensor.value = sensorKey;
    
    // Auto-fill label and unit from sensor data
    const sensor = props.sensors.find(s => s.value === sensorKey);
    if (sensor) {
        customLabel.value = sensor.label;
        customUnit.value = sensor.unit || '';
    }
}

function clearBinding() {
    selectedSensor.value = '';
    customLabel.value = '';
    customUnit.value = '';
    slot.value = '';
}

function save() {
    // Build binding if sensor is selected
    let binding: Binding | null = null;
    
    if (selectedSensor.value && selectedSensorData.value) {
        binding = {
            vehicle_sensor_id: selectedSensorData.value.id,
            telemetry_key: selectedSensor.value,
            target_prop: targetProp.value,
            slot: isMultiSlot.value ? slot.value : undefined,
            label: customLabel.value || selectedSensorData.value?.label,
            unit: customUnit.value || selectedSensorData.value?.unit,
        };
    }
    
    emit('save', { 
        binding: binding, 
        widgetProps: widgetPropsForm.value 
    });
}

// Get input type for prop schema
function getInputType(propType: string): string {
    switch (propType) {
        case 'number': return 'number';
        case 'boolean': return 'checkbox';
        case 'select': return 'select';
        default: return 'text';
    }
}

const categoryLabels: Record<string, string> = {
    'performance': '🏎️ Rendimiento',
    'temperature': '🌡️ Temperaturas',
    'pressure': '💨 Presiones',
    'electrical': '⚡ Eléctrico',
    'gps': '📍 GPS',
    'general': '📊 General',
};
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div 
                v-if="show" 
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
                @click.self="emit('close')"
            >
                <div class="w-full max-w-2xl max-h-[90vh] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-100 dark:bg-cyan-900/30">
                                <Settings2 class="h-5 w-5 text-cyan-600" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                    Configurar Widget
                                </h2>
                                <p class="text-sm text-gray-500">
                                    {{ widget?.definition?.name || 'Widget' }}
                                </p>
                            </div>
                        </div>
                        <button 
                            @click="emit('close')"
                            class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            <X class="h-5 w-5 text-gray-500" />
                        </button>
                    </div>
                    
                    <!-- Tabs Navigation -->
                    <div class="flex border-b border-slate-200 dark:border-slate-700 px-6">
                        <button 
                            v-if="hasConfigurableProps"
                            @click="activeTab = 'config'"
                            class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors -mb-px"
                            :class="activeTab === 'config' 
                                ? 'border-cyan-500 text-cyan-600' 
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            <Settings2 class="h-4 w-4" />
                            Propiedades
                        </button>
                        <button 
                            v-if="!isSpecialWidget"
                            @click="activeTab = 'sensor'"
                            class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors -mb-px"
                            :class="activeTab === 'sensor' 
                                ? 'border-cyan-500 text-cyan-600' 
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                        >
                            <Link2 class="h-4 w-4" />
                            Sensor
                            <span 
                                v-if="hasExistingBinding" 
                                class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"
                            >
                                ✓
                            </span>
                            <span 
                                v-else
                                class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400"
                            >
                                !
                            </span>
                        </button>
                    </div>
                    
                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6">
                        
                        <!-- ========================================== -->
                        <!-- TAB 1: Widget Properties (Propiedades) -->
                        <!-- ========================================== -->
                        <div v-if="activeTab === 'config'" class="space-y-4">
                            <div v-if="hasConfigurableProps" class="grid grid-cols-2 gap-4">
                                <div 
                                    v-for="prop in propsSchemaArray" 
                                    :key="prop.key"
                                    class="space-y-1"
                                    :class="prop.type === 'boolean' ? 'col-span-1' : ''"
                                >
                                    <label 
                                        :for="`prop-${prop.key}`"
                                        class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide"
                                    >
                                        {{ prop.label || prop.key }}
                                    </label>
                                    
                                    <!-- Boolean (Toggle) -->
                                    <div v-if="prop.type === 'boolean'" class="flex items-center">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input 
                                                :id="`prop-${prop.key}`"
                                                v-model="widgetPropsForm[prop.key]"
                                                type="checkbox" 
                                                class="sr-only peer"
                                            />
                                            <div class="w-11 h-6 bg-gray-300 peer-focus:ring-2 peer-focus:ring-cyan-300 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
                                        </label>
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ widgetPropsForm[prop.key] ? 'Sí' : 'No' }}
                                        </span>
                                    </div>
                                    
                                    <!-- Select -->
                                    <select
                                        v-else-if="prop.type === 'select'"
                                        :id="`prop-${prop.key}`"
                                        v-model="widgetPropsForm[prop.key]"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-cyan-500"
                                    >
                                        <option 
                                            v-for="opt in prop.options" 
                                            :key="opt" 
                                            :value="opt"
                                        >
                                            {{ opt }}
                                        </option>
                                    </select>
                                    
                                    <!-- Number -->
                                    <input
                                        v-else-if="prop.type === 'number'"
                                        :id="`prop-${prop.key}`"
                                        v-model.number="widgetPropsForm[prop.key]"
                                        type="number"
                                        :min="prop.min"
                                        :max="prop.max"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-cyan-500"
                                    />
                                    
                                    <!-- String (default) -->
                                    <input
                                        v-else
                                        :id="`prop-${prop.key}`"
                                        v-model="widgetPropsForm[prop.key]"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-cyan-500"
                                    />
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-8 text-gray-500">
                                <Settings2 class="h-12 w-12 mx-auto mb-3 opacity-30" />
                                <p>Este widget no tiene propiedades configurables.</p>
                                <p class="text-sm mt-1">Ve a la pestaña "Sensor" para vincular datos.</p>
                            </div>
                        </div>
                        
                        <!-- ========================================== -->
                        <!-- TAB 2: Sensor Binding -->
                        <!-- ========================================== -->
                        <div v-if="activeTab === 'sensor'" class="space-y-4">
                            
                            <!-- Current Binding Status -->
                            <div 
                                v-if="hasExistingBinding"
                                class="flex items-center justify-between p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"
                            >
                                <div class="flex items-center gap-3">
                                    <Check class="h-5 w-5 text-green-600" />
                                    <div>
                                        <p class="font-medium text-green-800 dark:text-green-300">
                                            Sensor Vinculado: {{ customLabel || selectedSensor }}
                                        </p>
                                        <p class="text-xs text-green-600 dark:text-green-400">
                                            Key: {{ selectedSensor }}
                                        </p>
                                    </div>
                                </div>
                                <button 
                                    @click="clearBinding"
                                    class="p-2 text-green-600 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                    title="Desvincular sensor"
                                >
                                    <Unlink class="h-4 w-4" />
                                </button>
                            </div>
                            
                            <div 
                                v-else
                                class="flex items-center gap-3 p-3 rounded-lg bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800"
                            >
                                <AlertCircle class="h-5 w-5 text-orange-600" />
                                <div>
                                    <p class="font-medium text-orange-800 dark:text-orange-300">
                                        Sin Sensor Vinculado
                                    </p>
                                    <p class="text-xs text-orange-600 dark:text-orange-400">
                                        Selecciona un sensor para mostrar datos en este widget
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Search -->
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Buscar sensor..."
                                    class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                                />
                            </div>
                            
                            <!-- Sensor List -->
                            <div class="space-y-4 max-h-[250px] overflow-y-auto">
                                <div v-for="(sensors, category) in groupedSensors" :key="category">
                                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 sticky top-0 bg-white dark:bg-slate-900 py-1">
                                        {{ categoryLabels[category] || category }}
                                    </h4>
                                    
                                    <div class="grid grid-cols-2 gap-2">
                                        <button
                                            v-for="sensor in sensors"
                                            :key="sensor.value"
                                            @click="selectSensor(sensor.value)"
                                            class="flex items-center gap-2 p-3 rounded-lg border text-left transition-all"
                                            :class="[
                                                selectedSensor === sensor.value
                                                    ? 'border-cyan-500 bg-cyan-50 dark:bg-cyan-900/20 ring-2 ring-cyan-500/20'
                                                    : 'border-slate-200 dark:border-slate-700 hover:border-cyan-300 hover:bg-slate-50 dark:hover:bg-slate-800'
                                            ]"
                                        >
                                            <div class="flex-1 min-w-0">
                                                <span class="block font-medium text-gray-800 dark:text-gray-200 truncate">
                                                    {{ sensor.label }}
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    {{ sensor.value }}
                                                </span>
                                            </div>
                                            <span v-if="sensor.unit" class="text-xs text-cyan-600 dark:text-cyan-400 font-mono bg-cyan-50 dark:bg-cyan-900/30 px-1.5 py-0.5 rounded">
                                                {{ sensor.unit }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Slot selection (for multi-slot widgets) -->
                            <div v-if="isMultiSlot && selectedSensor" class="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Posición / Slot
                                </label>
                                <select
                                    v-model="slot"
                                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-gray-900 dark:text-white"
                                >
                                    <option value="">Seleccionar slot...</option>
                                    <option v-for="opt in slotOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                            
                            <!-- Custom label/unit -->
                            <div v-if="selectedSensor" class="grid grid-cols-2 gap-4 pt-2 border-t border-slate-200 dark:border-slate-700">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 uppercase tracking-wide">
                                        Etiqueta
                                    </label>
                                    <input
                                        v-model="customLabel"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-gray-900 dark:text-white text-sm"
                                        placeholder="Ej: RPM"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 uppercase tracking-wide">
                                        Unidad
                                    </label>
                                    <input
                                        v-model="customUnit"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-gray-900 dark:text-white text-sm"
                                        placeholder="Ej: °F"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-700 px-6 py-4 bg-slate-50 dark:bg-slate-800/50">
                        <p class="text-xs text-gray-500">
                            <span v-if="!selectedSensor && hasConfigurableProps">
                                💡 Puedes guardar propiedades sin vincular sensor
                            </span>
                            <span v-else-if="selectedSensor && hasExistingBinding && selectedSensor !== originalSensorKey">
                                ⚠️ Cambiarás el sensor vinculado
                            </span>
                        </p>
                        <div class="flex gap-3">
                            <Button variant="outline" @click="emit('close')">
                                Cancelar
                            </Button>
                            <Button 
                                @click="save"
                                :disabled="!isSpecialWidget && !selectedSensor && !hasConfigurableProps"
                                class="gap-2"
                            >
                                <Check class="h-4 w-4" />
                                Guardar Cambios
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>

