<script setup lang="ts">
/**
 * PressureBarWidget.vue - NEURONA DESIGN SYSTEM
 * 
 * Horizontal bar showing pressure with label and value.
 * Used for Oil PSI, Fuel PSI, Coolant pressure, etc.
 */
import { computed, ref, watch } from 'vue';

interface Props {
    value?: number;
    min?: number;
    max?: number;
    label?: string;
    unit?: string;
    lowThreshold?: number;
    highThreshold?: number;
    criticalThreshold?: number;
    showBar?: boolean;
    color?: string;  // Override color
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    min: 0,
    max: 100,
    label: 'PRESSURE',
    unit: 'PSI',
    lowThreshold: 20,
    highThreshold: 80,
    criticalThreshold: 95,
    showBar: true,
});

// Animated value
const displayValue = ref(0);

watch(() => props.value, (newVal) => {
    const target = newVal ?? 0;
    const start = displayValue.value;
    const duration = 500;
    const startTime = Date.now();
    
    const animate = () => {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        displayValue.value = start + (target - start) * easeOut;
        
        if (progress < 1) requestAnimationFrame(animate);
    };
    requestAnimationFrame(animate);
}, { immediate: true });

// Calculate percentage for bar
const percentage = computed(() => {
    const range = props.max - props.min;
    if (range <= 0) return 0;
    return Math.min(Math.max((displayValue.value - props.min) / range * 100, 0), 100);
});

// Determine zone
type PressureZone = 'low' | 'normal' | 'high' | 'critical';

const zone = computed<PressureZone>(() => {
    const v = displayValue.value;
    if (v <= props.lowThreshold) return 'low';
    if (v >= props.criticalThreshold) return 'critical';
    if (v >= props.highThreshold) return 'high';
    return 'normal';
});

// Zone colors
const zoneStyles = computed(() => {
    if (props.color) {
        return {
            bar: props.color,
            text: props.color,
            glow: `0 0 10px ${props.color}40`,
        };
    }
    
    const styles: Record<PressureZone, { bar: string; text: string; glow: string }> = {
        low: {
            bar: '#06b6d4',
            text: 'text-cyan-400',
            glow: '0 0 10px rgba(6, 182, 212, 0.4)',
        },
        normal: {
            bar: '#22c55e',
            text: 'text-green-400',
            glow: '0 0 10px rgba(34, 197, 94, 0.3)',
        },
        high: {
            bar: '#f59e0b',
            text: 'text-amber-400',
            glow: '0 0 10px rgba(245, 158, 11, 0.4)',
        },
        critical: {
            bar: '#ef4444',
            text: 'text-red-400',
            glow: '0 0 15px rgba(239, 68, 68, 0.5)',
        },
    };
    return styles[zone.value];
});

const isCritical = computed(() => zone.value === 'critical');
</script>

<template>
    <div 
        class="pressure-bar-widget py-1.5"
        :class="{ 'animate-pulse': isCritical }"
    >
        <!-- Label and Value Row -->
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-medium text-slate-400 uppercase">
                {{ label }}
            </span>
            <span 
                class="text-sm font-bold tabular-nums transition-colors duration-300"
                :class="typeof zoneStyles.text === 'string' && zoneStyles.text.startsWith('text-') ? zoneStyles.text : ''"
                :style="typeof zoneStyles.text === 'string' && !zoneStyles.text.startsWith('text-') ? { color: zoneStyles.text } : {}"
            >
                {{ displayValue.toFixed(1) }}
            </span>
        </div>
        
        <!-- Progress Bar -->
        <div 
            v-if="showBar"
            class="h-2 bg-slate-700/50 rounded-full overflow-hidden"
        >
            <div 
                class="h-full rounded-full transition-all duration-500"
                :style="{
                    width: `${percentage}%`,
                    backgroundColor: zoneStyles.bar,
                    boxShadow: zoneStyles.glow,
                }"
            />
        </div>
    </div>
</template>

<style scoped>
.pressure-bar-widget {
    min-width: 120px;
}
</style>
