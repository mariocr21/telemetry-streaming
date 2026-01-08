<script setup lang="ts">
/**
 * GroupCard.vue - Estilo "Starstream" / Bento Grid (REWRITTEN)
 * Contenedor principal para grupos de widgets.
 */
import { ref, computed } from 'vue';
import type { WidgetGroup } from '@/types/dashboard';
import WidgetRenderer from './WidgetRenderer.vue';
import draggable from 'vuedraggable';

const props = defineProps<{
    group: WidgetGroup;
    telemetryData: any;
    isEditing?: boolean;
}>();

const emit = defineEmits(['update:widgets']);

const isCollapsed = ref(false);

// Ordenar widgets por sort_order
const sortedWidgets = computed({
    get: () => [...(props.group.widgets || [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
    set: (value) => emit('update:widgets', value)
});

// Grid style calculation para el layout interno del grupo
const gridStyle = computed(() => {
    // Protección contra valores nulos
    const span = Number(props.group.grid_column_span) || 12;
    
    // Bento logic: Adaptive columns based on content count
    const widgetCount = props.group.widgets?.length || 1;
    let cols = 1;

    if (span >= 10) {
        // Full width: Adapt to widget count (max 4, min 1)
        // If we have 2 widgets, use 2 cols (50% each). If 3, use 3 (33%).
        // If 5+, wrap at 4.
        cols = Math.min(Math.max(widgetCount, 1), 4);
    }
    else if (span >= 6) {
        // Half width: usually 2 cols, but if only 1 widget, use 1.
        cols = Math.min(Math.max(widgetCount, 1), 2);
    }
    else {
        cols = 1;
    }

    // Override heurísticos por nombre
    const name = (props.group.name || '').toLowerCase();
    
    // PRIMARY DISPLAY / PRINCIPAL: Always 2x2. Use minmax(0, 1fr) to prevent blowout.
    if (name.includes('primary') || name.includes('principal')) {
        return { 
            display: 'grid', 
            gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', 
            gap: '0.5rem',
            alignContent: 'start'
        }; 
    }
    
    // Engine specific logic overrides (if needed, but adaptive logic is usually better)
    if (name.includes('engine') && widgetCount > 2) return { display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', gap: '0.5rem' };

    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))`,
        gap: '0.5rem', 
    };
});

// Estilo para el contenedor principal (el span externo)
const containerStyle = computed(() => {
    // Si isEditing es true, dejamos que el GroupEditor controle el span
    if (props.isEditing) return {};

    // Asegurar que sea un número válido. Default a 12 (full width) si falla.
    // IMPORTANTE: Si es Primary Display, forzarlo a 4 columnas si el prop está mal, 
    // para arreglar el bug visual del "hueco".
    const name = (props.group.name || '').toLowerCase();
    let span = Number(props.group.grid_column_span) || 12;
    
    // Safety Net: Si es Primary Display y tiene span 12 (default), forzarlo a 4 para que quepa al lado del mapa
    if ((name.includes('primary') || name.includes('principal')) && span === 12) {
        span = 4;
    }

    return {
        gridColumn: `span ${span} / span ${span}`,
    };
});
</script>

<template>
    <div 
        class="group-card bento-panel"
        :style="containerStyle"
    >
        <!-- Technical Header -->
        <div class="panel-header" @dblclick="isEditing ? null : (isCollapsed = !isCollapsed)">
            <div class="header-accent bg-[var(--neurona-accent)] shadow-[0_0_8px_var(--neurona-accent)]"></div>
            <h3 class="panel-title uppercase tracking-wider text-[10px]">{{ group.name }}</h3>
            <div class="header-line flex-1 ml-3 h-[1px] bg-white/10"></div>
        </div>

        <!-- Render Widgets -->
        <div v-show="!isCollapsed" class="panel-body p-3" :style="gridStyle">
            <template v-if="group.widgets && group.widgets.length > 0">
                <div 
                    v-for="widget in sortedWidgets" 
                    :key="widget.id || widget.tempId"
                    class="widget-wrapper relative group/widget"
                    :class="widget.size_class === 'full' ? 'col-span-full' : ''"
                >
                    <WidgetRenderer 
                        :widget="widget" 
                        :telemetry-data="telemetryData"
                    />
                </div>
            </template>
            <div v-else class="h-full flex items-center justify-center text-xs text-gray-600 italic py-4">
                Empty Group
            </div>
        </div>
    </div>
</template>

<style scoped>
.bento-panel {
    background: #0f1014; /* Deep Tech Background */
    border: 1px solid rgba(255, 255, 255, 0.08); /* Subtle border */
    border-radius: 8px; /* Slightly tighter radius */
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.panel-header {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid rgba(255,255,255,0.03);
    user-select: none;
}

.header-accent {
    width: 3px;
    height: 12px;
    margin-right: 10px;
    border-radius: 1px;
}

.panel-title {
    font-family: 'Orbitron', sans-serif; /* Tech Font */
    font-weight: 700;
    color: rgba(255, 255, 255, 0.7);
}

.panel-body {
    flex: 1;
}
</style>
