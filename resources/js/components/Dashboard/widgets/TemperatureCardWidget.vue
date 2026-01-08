<script setup lang="ts">
/**
 * TemperatureCardWidget.vue - NEURONA DESIGN SYSTEM
 * 
 * Displays a temperature value with color-coded background based on temperature zone.
 * Inspired by StarStream dashboard design.
 * 
 * Props:
 * - value: number (temperature value)
 * - label: string (sensor name like "COOLANT", "OIL")
 * - unit: string (default "°F")
 * - min: number (cold threshold)
 * - max: number (hot threshold)
 */
import { computed, ref, watch } from 'vue';

interface Props {
    value?: number;
    label?: string;
    unit?: string;
    min?: number;
    max?: number;
    coldThreshold?: number;
    optimalThreshold?: number;
    warmThreshold?: number;
    hotThreshold?: number;
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    label: 'TEMP',
    unit: '°F',
    min: 0,
    max: 300,
    coldThreshold: 120,
    optimalThreshold: 200,
    warmThreshold: 220,
    hotThreshold: 250,
});

// Animated value
const displayValue = ref(0);

// Animate value changes
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

// Determine zone based on temperature
type Zone = 'cold' | 'optimal' | 'warm' | 'hot' | 'critical';

const zone = computed<Zone>(() => {
    const v = displayValue.value;
    if (v < props.coldThreshold) return 'cold';
    if (v < props.optimalThreshold) return 'optimal';
    if (v < props.warmThreshold) return 'warm';
    if (v < props.hotThreshold) return 'hot';
    return 'critical';
});

// Zone colors matching Neurona design system
const zoneStyles = computed(() => {
    const styles: Record<Zone, { bg: string; text: string; glow: string }> = {
        cold: {
            bg: 'bg-cyan-500/20',
            text: 'text-cyan-400',
            glow: '0 0 20px rgba(6, 182, 212, 0.4)',
        },
        optimal: {
            bg: 'bg-green-500/20',
            text: 'text-green-400',
            glow: '0 0 20px rgba(34, 197, 94, 0.4)',
        },
        warm: {
            bg: 'bg-amber-500/20',
            text: 'text-amber-400',
            glow: '0 0 20px rgba(245, 158, 11, 0.4)',
        },
        hot: {
            bg: 'bg-orange-500/20',
            text: 'text-orange-400',
            glow: '0 0 20px rgba(249, 115, 22, 0.4)',
        },
        critical: {
            bg: 'bg-red-500/20',
            text: 'text-red-400',
            glow: '0 0 20px rgba(239, 68, 68, 0.5)',
        },
    };
    return styles[zone.value];
});

// Is critical (for pulsing animation)
const isCritical = computed(() => zone.value === 'critical' || zone.value === 'hot');
</script>

<template>
    <div 
        class="temperature-card rounded-lg border border-slate-700/50 overflow-hidden transition-all duration-300"
        :class="[
            zoneStyles.bg,
            { 'animate-pulse-slow': isCritical }
        ]"
        :style="{ boxShadow: zoneStyles.glow }"
    >
        <div class="p-3 flex flex-col items-center justify-center min-h-[80px]">
            <!-- Value -->
            <div 
                class="text-3xl font-bold tabular-nums leading-none transition-colors duration-300"
                :class="zoneStyles.text"
            >
                {{ Math.round(displayValue) }}
            </div>
            
            <!-- Label -->
            <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mt-1">
                {{ label }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.temperature-card {
    backdrop-filter: blur(8px);
}

@keyframes pulse-slow {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.animate-pulse-slow {
    animation: pulse-slow 1.5s ease-in-out infinite;
}
</style>
