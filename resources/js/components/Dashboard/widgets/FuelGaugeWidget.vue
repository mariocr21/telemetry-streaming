<script setup lang="ts">
/**
 * FuelGaugeWidget.vue - NEURONA DESIGN SYSTEM
 * 
 * Circular fuel level indicator with icon and percentage.
 * Inspired by StarStream dashboard design.
 */
import { computed, ref, watch } from 'vue';
import { Fuel } from 'lucide-vue-next';

interface Props {
    value?: number;      // Current fuel level (0-100 or actual gallons)
    max?: number;        // Max capacity
    label?: string;
    unit?: string;       // '%' or 'gal'
    lowThreshold?: number;
    criticalThreshold?: number;
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    max: 100,
    label: 'FUEL',
    unit: '%',
    lowThreshold: 25,
    criticalThreshold: 10,
});

// Animated percentage
const displayValue = ref(0);

watch(() => props.value, (newVal) => {
    const target = newVal ?? 0;
    const start = displayValue.value;
    const duration = 800;
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

// Calculate percentage
const percentage = computed(() => {
    if (props.unit === '%') return displayValue.value;
    return (displayValue.value / props.max) * 100;
});

// SVG circle properties
const radius = 40;
const circumference = 2 * Math.PI * radius;

const strokeDashoffset = computed(() => {
    const progress = Math.min(Math.max(percentage.value, 0), 100) / 100;
    return circumference * (1 - progress);
});

// Determine fuel level zone
type FuelZone = 'critical' | 'low' | 'normal' | 'full';

const fuelZone = computed<FuelZone>(() => {
    const pct = percentage.value;
    if (pct <= props.criticalThreshold) return 'critical';
    if (pct <= props.lowThreshold) return 'low';
    if (pct >= 90) return 'full';
    return 'normal';
});

// Zone styles
const zoneStyles = computed(() => {
    const styles: Record<FuelZone, { stroke: string; text: string; icon: string; glow: string }> = {
        critical: {
            stroke: '#ef4444',
            text: 'text-red-400',
            icon: 'text-red-500',
            glow: '0 0 15px rgba(239, 68, 68, 0.5)',
        },
        low: {
            stroke: '#f59e0b',
            text: 'text-amber-400',
            icon: 'text-amber-500',
            glow: '0 0 15px rgba(245, 158, 11, 0.4)',
        },
        normal: {
            stroke: '#22c55e',
            text: 'text-green-400',
            icon: 'text-green-500',
            glow: '0 0 15px rgba(34, 197, 94, 0.3)',
        },
        full: {
            stroke: '#06b6d4',
            text: 'text-cyan-400',
            icon: 'text-cyan-500',
            glow: '0 0 15px rgba(6, 182, 212, 0.4)',
        },
    };
    return styles[fuelZone.value];
});

const isCritical = computed(() => fuelZone.value === 'critical');
</script>

<template>
    <div 
        class="fuel-gauge flex flex-col items-center justify-center p-2"
        :class="{ 'animate-pulse': isCritical }"
    >
        <!-- Circular Gauge -->
        <div class="relative w-24 h-24">
            <svg 
                class="w-full h-full transform -rotate-90"
                viewBox="0 0 100 100"
            >
                <!-- Background circle -->
                <circle
                    cx="50"
                    cy="50"
                    :r="radius"
                    fill="none"
                    stroke="rgba(71, 85, 105, 0.3)"
                    stroke-width="8"
                />
                
                <!-- Progress circle -->
                <circle
                    cx="50"
                    cy="50"
                    :r="radius"
                    fill="none"
                    :stroke="zoneStyles.stroke"
                    stroke-width="8"
                    stroke-linecap="round"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="strokeDashoffset"
                    class="transition-all duration-500"
                    :style="{ filter: `drop-shadow${zoneStyles.glow.replace('0 0', '(0 0').replace(')', '))')}` }"
                />
            </svg>
            
            <!-- Center content -->
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <Fuel 
                    class="w-5 h-5 transition-colors duration-300"
                    :class="zoneStyles.icon"
                />
                <span 
                    class="text-lg font-bold tabular-nums mt-0.5 transition-colors duration-300"
                    :class="zoneStyles.text"
                >
                    {{ Math.round(percentage) }}{{ unit === '%' ? '%' : '' }}
                </span>
            </div>
        </div>
        
        <!-- Label -->
        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mt-1">
            {{ label }}
        </div>
    </div>
</template>

<style scoped>
.fuel-gauge {
    min-width: 100px;
}
</style>
