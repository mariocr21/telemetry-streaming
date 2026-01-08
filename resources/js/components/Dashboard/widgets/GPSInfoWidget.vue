<script setup lang="ts">
/**
 * GPSInfoWidget.vue - NEURONA DESIGN SYSTEM
 * 
 * Displays GPS information (latitude, longitude, satellites).
 * Single value version - use multiple instances for each value.
 */
import { computed, ref, watch } from 'vue';

interface Props {
    value?: number | string;
    label?: string;
    unit?: string;
    precision?: number;  // Decimal places for coordinates
    type?: 'latitude' | 'longitude' | 'satellites' | 'speed' | 'heading' | 'altitude' | 'default';
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    label: 'GPS',
    unit: '',
    precision: 6,
    type: 'default',
});

// Animated value for numeric types
const displayValue = ref<number>(0);

watch(() => props.value, (newVal) => {
    if (typeof newVal === 'number') {
        const target = newVal;
        const start = displayValue.value;
        const duration = 400;
        const startTime = Date.now();
        
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeOut = 1 - Math.pow(1 - progress, 3);
            displayValue.value = start + (target - start) * easeOut;
            
            if (progress < 1) requestAnimationFrame(animate);
        };
        requestAnimationFrame(animate);
    }
}, { immediate: true });

// Format value based on type
const formattedValue = computed(() => {
    const val = typeof props.value === 'number' ? displayValue.value : props.value;
    
    if (val === null || val === undefined) return '--';
    
    switch (props.type) {
        case 'latitude':
        case 'longitude':
            return typeof val === 'number' ? val.toFixed(props.precision) : val;
        case 'satellites':
            return typeof val === 'number' ? Math.round(val) : val;
        case 'speed':
            return typeof val === 'number' ? Math.round(val) : val;
        case 'heading':
            return typeof val === 'number' ? `${Math.round(val)}°` : val;
        case 'altitude':
            return typeof val === 'number' ? Math.round(val) : val;
        default:
            return typeof val === 'number' ? val.toFixed(2) : val;
    }
});

// Get text color based on type
const textColorClass = computed(() => {
    switch (props.type) {
        case 'latitude': return 'text-cyan-400';
        case 'longitude': return 'text-green-400';
        case 'satellites': return 'text-purple-400';
        case 'speed': return 'text-amber-400';
        case 'heading': return 'text-blue-400';
        case 'altitude': return 'text-orange-400';
        default: return 'text-white';
    }
});

// Font size based on type (coordinates need smaller font)
const fontSizeClass = computed(() => {
    switch (props.type) {
        case 'latitude':
        case 'longitude':
            return 'text-lg';
        default:
            return 'text-2xl';
    }
});
</script>

<template>
    <div class="gps-info-widget rounded-lg bg-slate-800/50 border border-slate-700/40 p-3">
        <div class="flex flex-col items-center justify-center min-h-[60px]">
            <!-- Value -->
            <div 
                class="font-bold tabular-nums leading-none transition-colors duration-300"
                :class="[textColorClass, fontSizeClass]"
            >
                {{ formattedValue }}
            </div>
            
            <!-- Label -->
            <div class="text-[9px] font-semibold text-slate-500 uppercase tracking-wider mt-1.5">
                {{ label }}{{ unit ? ` (${unit})` : '' }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.gps-info-widget {
    backdrop-filter: blur(8px);
    min-width: 80px;
}
</style>
