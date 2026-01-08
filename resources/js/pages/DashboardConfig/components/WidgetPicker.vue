<script setup lang="ts">
/**
 * WidgetPicker.vue
 * 
 * Modal to select widget type from the available definitions catalog.
 */
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    X,
    Gauge,
    BarChart3,
    Hash,
    Grid3X3,
    CircleDot,
    Zap,
    Wifi,
    ChevronRight,
    Thermometer,
    Fuel,
    MapPin,
    Cog,
    Battery,
} from 'lucide-vue-next';

// Types
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
}

// Props
interface Props {
    show: boolean;
    definitions: WidgetDefinition[];
}

const props = defineProps<Props>();

// Emits
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', definition: WidgetDefinition): void;
}>();

// Icon map - Extended for all widget types
const iconMap: Record<string, any> = {
    // Gauges
    'gauge': Gauge,
    'radial_gauge': Gauge,
    'speedometer': Gauge,
    'tachometer': Gauge,
    // Bars
    'linear_bar': BarChart3,
    'bar': BarChart3,
    'pressure_bar': BarChart3,
    // Digital/Text
    'digital_value': Hash,
    'number': Hash,
    'text_grid': Grid3X3,
    'grid': Grid3X3,
    // Special
    'tire_grid': CircleDot,
    'shift_lights': Zap,
    'connection_status': Wifi,
    // Temperature - Now with proper thermometer icon
    'temperature_card': Thermometer,
    'thermometer': Thermometer,
    // Fuel
    'fuel_gauge': Fuel,
    'fuel': Fuel,
    // Battery/Electrical
    'battery': Battery,
    'battery_voltage': Battery,
    // GPS/Map
    'gps_info': MapPin,
    'gps_map': MapPin,
    'map_widget': MapPin,
    // Transmission
    'gear_scale': Cog,
};

// Category order for display (most important first)
const categoryOrder = [
    'visualization',
    'racing',
    'gauges',
    'temperature',
    'pressure',
    'medidores',
    'electrical',
    'fuel',
    'transmission',
    'text',
    'navegacion',
    'gps',
    'special',
    'other',
];

// Group by category
const groupedDefinitions = computed(() => {
    const groups: Record<string, WidgetDefinition[]> = {};
    
    for (const def of props.definitions) {
        const category = def.category || 'other';
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(def);
    }
    
    // Sort groups by categoryOrder
    const sortedGroups: Record<string, WidgetDefinition[]> = {};
    for (const cat of categoryOrder) {
        if (groups[cat]) {
            sortedGroups[cat] = groups[cat];
        }
    }
    // Add any remaining categories not in the order
    for (const cat of Object.keys(groups)) {
        if (!sortedGroups[cat]) {
            sortedGroups[cat] = groups[cat];
        }
    }
    
    return sortedGroups;
});

const categoryLabels: Record<string, string> = {
    'visualization': '📊 Visualización',
    'racing': '🏎️ Racing',
    'gauges': '🎯 Medidores Radiales',
    'temperature': '🌡️ Temperatura',
    'pressure': '💨 Presión',
    'medidores': '📏 Medidores',
    'electrical': '🔋 Eléctrico',
    'fuel': '⛽ Combustible',
    'transmission': '⚙️ Transmisión',
    'text': '📝 Texto/Numérico',
    'navegacion': '🗺️ Navegación',
    'gps': '📍 GPS',
    'special': '⚡ Especiales',
    'other': '📦 Otros',
};

// Methods
function getIcon(definition: WidgetDefinition) {
    return iconMap[definition.icon] || iconMap[definition.type] || Gauge;
}

function selectWidget(definition: WidgetDefinition) {
    emit('select', definition);
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div 
                v-if="show" 
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
                @click.self="emit('close')"
            >
                <div class="w-full max-w-2xl max-h-[80vh] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                Seleccionar Widget
                            </h2>
                            <p class="text-sm text-gray-500">
                                Elige el tipo de widget para agregar al grupo
                            </p>
                        </div>
                        <button 
                            @click="emit('close')"
                            class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            <X class="h-5 w-5 text-gray-500" />
                        </button>
                    </div>
                    
                    <!-- Content -->
                    <div class="p-6 overflow-y-auto max-h-[60vh]">
                        <div v-for="(definitions, category) in groupedDefinitions" :key="category" class="mb-6 last:mb-0">
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-3">
                                {{ categoryLabels[category] || category }}
                            </h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button
                                    v-for="def in definitions"
                                    :key="def.id"
                                    @click="selectWidget(def)"
                                    class="group flex items-center gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-cyan-500 hover:bg-cyan-50 dark:hover:bg-cyan-900/20 transition-all text-left"
                                >
                                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800 group-hover:bg-cyan-100 dark:group-hover:bg-cyan-900/50 transition-colors">
                                        <component :is="getIcon(def)" class="h-6 w-6 text-slate-500 group-hover:text-cyan-600" />
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-gray-900 dark:text-white group-hover:text-cyan-600 transition-colors">
                                            {{ def.name }}
                                        </h4>
                                        <p class="text-xs text-gray-500 truncate">
                                            {{ def.description || def.component_name }}
                                        </p>
                                        
                                        <!-- Feature badges -->
                                        <div class="flex gap-1 mt-1">
                                            <span 
                                                v-if="def.supports_thresholds"
                                                class="text-[10px] px-1.5 py-0.5 rounded bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400"
                                            >
                                                Thresholds
                                            </span>
                                            <span 
                                                v-if="def.supports_multiple_slots"
                                                class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400"
                                            >
                                                Multi-slot
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <ChevronRight class="h-5 w-5 text-slate-300 group-hover:text-cyan-500 transition-colors" />
                                </button>
                            </div>
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
