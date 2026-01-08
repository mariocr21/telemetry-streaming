<script setup lang="ts">
/**
 * TextGridWidget.vue (ValueBox Style)
 * 
 * Renders simple, clean value boxes similar to the 'Temperatures' 
 * section in the competitor dashboard.
 */
import { computed } from 'vue';

interface Props {
    // Single Value Mode
    value?: number;
    label?: string;
    unit?: string;
    
    // Multi Value Mode (Binding Slots)
    slots?: Record<string, { value: number; label: string; unit: string; thresholds?: any }>;
}

const props = defineProps<Props>();

// Helper to determine color based on value/thresholds
const getColor = (val: number, thresholds: any[]) => {
    if (!thresholds || thresholds.length === 0) return 'white';
    const sorted = [...thresholds].sort((a,b) => a.value - b.value);
    for (const t of sorted) {
        if (val <= t.value) return t.color;
    }
    return sorted[sorted.length - 1]?.color || 'white';
};

// Normalize data into an array of items
const items = computed(() => {
    if (props.slots && Object.keys(props.slots).length > 0) {
        // Multi-slot mode
        return Object.values(props.slots).map(slot => ({
            value: slot.value,
            label: slot.label,
            unit: slot.unit,
            color: getColor(slot.value, slot.thresholds || [])
        }));
    } else {
        // Single value mode
        return [{
            value: props.value ?? 0,
            label: props.label ?? 'DATA',
            unit: props.unit ?? '',
            color: 'white' // Default color
        }];
    }
});

const gridStyle = computed(() => {
    // If we have items (slots), we want to fill the container 100%
    const count = items.value.length;
    // For single item, it just fills. For multiple, use grid.
    if (count <= 1) return {};
    
    // Grid logic based on count
    let cols = 2;
    if (count >= 5) cols = 3;
    if (count === 3) cols = 3;
    
    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${cols}, 1fr)`,
        gap: '0.75rem',
    };
});
</script>

<template>
    <div class="w-full h-full" :style="gridStyle">
        <div 
            v-for="(item, idx) in items" 
            :key="idx"
            class="data-box flex flex-col items-center justify-center"
        >
            <div 
                class="value-text"
                :style="{ color: item.color }"
            >
                {{ Math.round(item.value) }}
            </div>
            <div class="label-text">
                {{ item.label }} <span v-if="item.unit" class="text-white/30 text-[0.6em] ml-1">{{ item.unit }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.data-box {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 6px;
    padding: 8px;
    height: 100%;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.data-box:hover {
    border-color: rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.05);
}

.value-text {
    font-family: 'Orbitron', sans-serif;
    font-size: clamp(1.5rem, 3vw, 2.5rem);
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0.05em;
    text-shadow: 0 0 20px rgba(0,0,0,0.5);
}

.label-text {
    font-family: 'Inter', sans-serif;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(255,255,255,0.5);
    margin-top: 6px;
    letter-spacing: 0.1em;
}
</style>
