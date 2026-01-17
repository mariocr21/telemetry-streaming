<script setup lang="ts">
/**
 * GroupEditor.vue (Grid Aware)
 * 
 * Component for editing a single dashboard group with its widgets.
 * Now supports resizing grid span directly.
 */
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import draggable from 'vuedraggable';
import {
    ChevronDown,
    ChevronUp,
    Trash2,
    Plus,
    GripVertical,
    Settings2,
    Link2,
    Unlink,
    Gauge,
    BarChart3,
    Hash,
    Grid3X3
} from 'lucide-vue-next';

// Types
interface Binding {
    id?: number;
    telemetry_key: string;
    target_prop: string;
    slot?: string;
    label?: string;
    unit?: string;
}

interface WidgetDefinition {
    id: number;
    type: string;
    name: string;
    icon: string;
    component_name: string;
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

interface Sensor {
    id: number;
    sensor_key: string;
    label: string;
}

// Props
interface Props {
    group: Group;
    groupIndex: number;
    widgetDefinitions: WidgetDefinition[];
    sensors: Sensor[];
}

const props = defineProps<Props>();

// Emits
const emit = defineEmits<{
    (e: 'toggle-expand'): void;
    (e: 'remove'): void;
    (e: 'add-widget'): void;
    (e: 'remove-widget', widgetIndex: number): void;
    (e: 'edit-binding', widgetIndex: number): void;
    (e: 'remove-binding', widgetIndex: number, bindingIndex: number): void;
    (e: 'widget-reorder', widgets: Widget[]): void;
    (e: 'update:span', span: number): void;
}>();

function onWidgetReorder() {
    // Update sort_order after drag & drop
    props.group.widgets.forEach((w, i) => w.sort_order = i);
    emit('widget-reorder', props.group.widgets);
}

// Icon map
const iconMap: Record<string, any> = {
    'gauge': Gauge,
    'radial_gauge': Gauge,
    'linear_bar': BarChart3,
    'digital_value': Hash,
    'text_grid': Grid3X3,
};

function getWidgetIcon(widget: Widget) {
    const type = widget.definition?.type || widget.definition?.icon || 'gauge';
    return iconMap[type] || Gauge;
}

function getWidgetName(widget: Widget) {
    return widget.definition?.name || 'Widget';
}
</script>

<template>
    <div class="group-editor h-full flex flex-col rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 shadow-sm overflow-hidden transition-all duration-300">
        <!-- Group Header -->
        <div 
            class="flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-800 cursor-move border-b border-slate-200 dark:border-slate-700"
            @click="emit('toggle-expand')"
        >
            <div class="flex items-center gap-2 overflow-hidden">
                <GripVertical class="group-drag-handle h-4 w-4 text-slate-400 cursor-move shrink-0" />
                
                <input
                    v-model="group.name"
                    type="text"
                    class="bg-transparent font-bold text-sm text-gray-800 dark:text-gray-100 border-none focus:outline-none focus:ring-0 p-0 w-full truncate placeholder-gray-500"
                    placeholder="Nombre del Grupo"
                    @click.stop
                />
            </div>
            
            <div class="flex items-center gap-1 shrink-0">
                <!-- Quick Width Controls -->
                <div class="flex bg-slate-200 dark:bg-slate-900 rounded p-0.5 mr-2">
                    <button 
                        v-for="opt in [1, 2, 3, 4, 6, 8, 12]" 
                        :key="opt"
                        @click.stop="emit('update:span', opt)"
                        class="px-1.5 py-0.5 text-[10px] rounded transition-colors"
                        :class="group.grid_column_span === opt ? 'bg-cyan-500 text-white' : 'text-slate-500 hover:text-slate-300'"
                        :title="`Ancho: ${opt} cols`"
                    >
                        {{ opt }}
                    </button>
                </div>

                <Button 
                    variant="ghost" 
                    size="sm" 
                    class="h-6 w-6 p-0 text-red-500 hover:bg-red-500/10"
                    @click.stop="emit('remove')"
                >
                    <Trash2 class="h-3 w-3" />
                </Button>
                
                <div @click.stop="emit('toggle-expand')" class="cursor-pointer p-1">
                    <component 
                        :is="group.is_expanded ? ChevronUp : ChevronDown" 
                        class="h-4 w-4 text-slate-400" 
                    />
                </div>
            </div>
        </div>
        
        <!-- Group Content (Widgets) -->
        <div v-show="group.is_expanded" class="flex-1 p-3 bg-slate-100/50 dark:bg-slate-900/30">
            <!-- Widgets List with Drag & Drop -->
            <draggable
                v-if="group.widgets.length > 0"
                :list="group.widgets"
                item-key="id"
                handle=".widget-drag-handle"
                ghost-class="opacity-50"
                animation="200"
                class="space-y-2 min-h-[50px]"
                group="widgets-nested"
                @end="onWidgetReorder"
            >
                <template #item="{ element: widget, index: widgetIndex }">
                    <div 
                        class="flex items-center gap-2 p-2 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm group hover:border-cyan-500/50 transition-colors"
                    >
                        <!-- Widget Drag Handle -->
                        <GripVertical class="widget-drag-handle h-4 w-4 text-slate-300 dark:text-slate-600 cursor-move" />
                        
                        <!-- Widget Icon -->
                        <div class="w-6 h-6 flex items-center justify-center rounded bg-slate-100 dark:bg-slate-700 text-slate-500">
                             <component :is="getWidgetIcon(widget)" class="h-3.5 w-3.5" />
                        </div>
                        
                        <!-- Widget Info -->
                        <div class="flex-1 min-w-0 flex flex-col">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-200 truncate">
                                {{ getWidgetName(widget) }}
                            </span>
                            
                            <!-- Bindings Badges -->
                            <div class="flex flex-wrap gap-1 mt-0.5">
                                <div 
                                    v-if="widget.bindings.length === 0"
                                    class="text-[10px] text-orange-500 cursor-pointer hover:underline"
                                    @click="emit('edit-binding', widgetIndex)"
                                >
                                    ⚠️ Sin Vincular
                                </div>
                                <div 
                                    v-else
                                    v-for="(binding, bi) in widget.bindings"
                                    :key="bi"
                                    class="text-[10px] px-1.5 rounded-sm bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20 truncate max-w-[100px]"
                                    :title="binding.label"
                                >
                                    {{ binding.label || binding.telemetry_key }}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex gap-1 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                             <!-- Settings / Bindings -->
                             <button
                                @click="emit('edit-binding', widgetIndex)"
                                class="p-1 text-slate-400 hover:text-cyan-400 hover:bg-slate-700 rounded"
                                title="Configurar Widget"
                            >
                                <Settings2 class="h-3.5 w-3.5" />
                            </button>
                            
                            <button
                                @click="emit('remove-widget', widgetIndex)"
                                class="p-1 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </template>
            </draggable>
            
            <!-- Empty State / Add Button -->
            <button
                v-else
                @click="emit('add-widget')"
                class="w-full flex flex-col items-center justify-center gap-2 py-4 rounded border border-dashed border-slate-300 dark:border-slate-700 text-slate-500 hover:border-cyan-500 hover:text-cyan-500 hover:bg-cyan-500/5 transition-all"
            >
                <Plus class="h-5 w-5 opacity-50" />
                <span class="text-xs">Soltar widget aquí</span>
            </button>
            
            <!-- Contextual Add Button (if not empty) -->
            <div v-if="group.widgets.length > 0" class="mt-2 text-center">
                 <button 
                    @click="emit('add-widget')"
                    class="text-[10px] text-slate-400 hover:text-cyan-400 uppercase font-bold tracking-wider"
                >
                    + Añadir Widget
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.group-drag-handle {
    cursor: grab;
}
.group-drag-handle:active {
    cursor: grabbing;
}
</style>
