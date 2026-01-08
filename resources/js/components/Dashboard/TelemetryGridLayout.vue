<script setup lang="ts">
/**
 * TelemetryGridLayout.vue - Responsive Grid Layout System
 * Neurona Off Road Telemetry - Tactical Dashboard Grid
 * 
 * Features:
 * - CSS Grid-based responsive layout
 * - Configurable column spans
 * - Drag-and-drop ready structure
 * - Dark industrial aesthetics
 */

import { computed } from 'vue';

interface Props {
    columns?: number;
    gap?: string;
    padding?: string;
}

const props = withDefaults(defineProps<Props>(), {
    columns: 12,
    gap: '1rem',
    padding: '1.5rem'
});

const gridStyle = computed(() => ({
    '--grid-columns': props.columns,
    '--grid-gap': props.gap,
    '--grid-padding': props.padding
}));
</script>

<template>
    <div class="telemetry-grid" :style="gridStyle">
        <slot />
    </div>
</template>

<style scoped>
.telemetry-grid {
    display: grid;
    grid-template-columns: repeat(var(--grid-columns, 12), 1fr);
    gap: var(--grid-gap, 1rem);
    padding: var(--grid-padding, 1.5rem);
    width: 100%;
    min-height: 100vh;
}

/* Responsive breakpoints for tablet/mobile */
@media (max-width: 1280px) {
    .telemetry-grid {
        grid-template-columns: repeat(8, 1fr);
    }
}

@media (max-width: 1024px) {
    .telemetry-grid {
        grid-template-columns: repeat(6, 1fr);
        gap: 0.75rem;
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .telemetry-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 0.5rem;
        padding: 0.75rem;
    }
}

@media (max-width: 480px) {
    .telemetry-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
        padding: 0.5rem;
    }
}
</style>
