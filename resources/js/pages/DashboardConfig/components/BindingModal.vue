<script setup lang="ts">
/**
 * BindingModal.vue
 * 
 * Modal to create/edit sensor bindings AND configure widget props.
 * Now supports:
 * - Sensor binding selection
 * - Widget props configuration from props_schema
 */
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { X, Link2, Search, Settings2, ChevronDown, ChevronUp } from 'lucide-vue-next';

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
    (e: 'save', payload: { binding: Binding; widgetProps: Record<string, any> }): void;
}>();

// State
const searchQuery = ref('');
const selectedSensor = ref<string>('');
const targetProp = ref<string>('value');
const slot = ref<string>('');
const customLabel = ref<string>('');
const customUnit = ref<string>('');
const showConfigSection = ref(true);

// Widget props state - initialized from widget.props
const widgetPropsForm = ref<Record<string, any>>({});

// Reset form when modal opens
watch(() => props.show, (isOpen) => {
    if (isOpen) {
        searchQuery.value = '';
        selectedSensor.value = '';
        targetProp.value = 'value';
        slot.value = '';
        customLabel.value = '';
        customUnit.value = '';
        
        // Initialize widget props from current widget
        if (props.widget?.props) {
            widgetPropsForm.value = { ...props.widget.props };
        } else {
            widgetPropsForm.value = {};
        }
        
        // Fill defaults from schema for missing props
        if (props.widget?.definition?.props_schema) {
            for (const [key, schema] of Object.entries(props.widget.definition.props_schema)) {
                if (widgetPropsForm.value[key] === undefined && schema.default !== undefined) {
                    widgetPropsForm.value[key] = schema.default;
                }
            }
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
const propsSchemaArray = computed(() => {
    const schema = props.widget?.definition?.props_schema;
    if (!schema) return [];
    
    return Object.entries(schema).map(([key, config]) => ({
        key,
        ...config,
    }));
});

// Check if widget has configurable props
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
        binding: binding as Binding, 
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
                    
                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        
                        <!-- ========================================== -->
                        <!-- SECTION 1: Widget Configuration (Props) -->
                        <!-- ========================================== -->
                        <div v-if="hasConfigurableProps" class="space-y-4">
                            <button 
                                @click="showConfigSection = !showConfigSection"
                                class="w-full flex items-center justify-between text-left"
                            >
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    <Settings2 class="h-4 w-4 text-cyan-500" />
                                    Configuración del Widget
                                </h3>
                                <component 
                                    :is="showConfigSection ? ChevronUp : ChevronDown" 
                                    class="h-4 w-4 text-gray-400"
                                />
                            </button>
                            
                            <div v-show="showConfigSection" class="grid grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                                <div 
                                    v-for="prop in propsSchemaArray" 
                                    :key="prop.key"
                                    class="space-y-1"
                                    :class="prop.type === 'boolean' ? 'col-span-1' : ''"
                                >
                                    <label 
                                        :for="`prop-${prop.key}`"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        {{ prop.label || prop.key }}
                                    </label>
                                    
                                    <!-- Boolean (Checkbox) -->
                                    <div v-if="prop.type === 'boolean'" class="flex items-center">
                                        <input
                                            :id="`prop-${prop.key}`"
                                            v-model="widgetPropsForm[prop.key]"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            {{ widgetPropsForm[prop.key] ? 'Sí' : 'No' }}
                                        </span>
                                    </div>
                                    
                                    <!-- Select -->
                                    <select
                                        v-else-if="prop.type === 'select'"
                                        :id="`prop-${prop.key}`"
                                        v-model="widgetPropsForm[prop.key]"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
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
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    />
                                    
                                    <!-- String (default) -->
                                    <input
                                        v-else
                                        :id="`prop-${prop.key}`"
                                        v-model="widgetPropsForm[prop.key]"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>
                        
                        <!-- ========================================== -->
                        <!-- SECTION 2: Sensor Binding -->
                        <!-- ========================================== -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <Link2 class="h-4 w-4 text-cyan-500" />
                                Vincular Sensor
                            </h3>
                            
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
                            <div class="space-y-4 max-h-[300px] overflow-y-auto">
                                <div v-for="(sensors, category) in groupedSensors" :key="category">
                                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">
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
                                                    ? 'border-cyan-500 bg-cyan-50 dark:bg-cyan-900/20'
                                                    : 'border-slate-200 dark:border-slate-700 hover:border-cyan-300'
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
                                            <span v-if="sensor.unit" class="text-xs text-cyan-600 dark:text-cyan-400 font-mono">
                                                {{ sensor.unit }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Slot selection (for multi-slot widgets) -->
                            <div v-if="isMultiSlot && selectedSensor" class="space-y-2">
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
                            <div v-if="selectedSensor" class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Etiqueta
                                    </label>
                                    <input
                                        v-model="customLabel"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-gray-900 dark:text-white"
                                        placeholder="Ej: RPM"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Unidad
                                    </label>
                                    <input
                                        v-model="customUnit"
                                        type="text"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-gray-900 dark:text-white"
                                        placeholder="Ej: °F"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-700 px-6 py-4">
                        <p class="text-xs text-gray-500">
                            <span v-if="!selectedSensor && hasConfigurableProps">
                                💡 Puedes guardar solo la configuración sin vincular sensor
                            </span>
                        </p>
                        <div class="flex gap-3">
                            <Button variant="outline" @click="emit('close')">
                                Cancelar
                            </Button>
                            <Button 
                                @click="save"
                                :disabled="!selectedSensor && !hasConfigurableProps"
                            >
                                <Settings2 class="h-4 w-4 mr-2" />
                                {{ selectedSensor ? 'Guardar y Vincular' : 'Guardar Configuración' }}
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
