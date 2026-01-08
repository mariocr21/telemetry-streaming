<script setup lang="ts">
/**
 * ThermometerWidget.vue - NEURONA DESIGN SYSTEM
 * 
 * Premium thermometer widget for temperature visualization.
 * Features:
 * - SVG thermometer with animated mercury level
 * - Zone-based coloring (Cold → Optimal → Warm → Hot → Critical)
 * - Glow effects and pulse animation on critical state
 * - Responsive sizing
 * 
 * Perfect for: Coolant Temp, Oil Temp, Transmission Temp, Air Intake Temp
 */
import { computed, ref, watch } from 'vue';

interface Props {
    value?: number;
    label?: string;
    unit?: string;
    min?: number;
    max?: number;
    // Temperature zone thresholds
    coldThreshold?: number;      // Below this = cold (blue)
    optimalThreshold?: number;   // Below this = optimal (green)
    warmThreshold?: number;      // Below this = warm (yellow)
    hotThreshold?: number;       // Below this = hot (orange), above = critical (red)
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    label: 'TEMP',
    unit: '°F',
    min: 0,
    max: 280,
    coldThreshold: 140,
    optimalThreshold: 200,
    warmThreshold: 230,
    hotThreshold: 250,
});

// Animated display value
const displayValue = ref(0);

watch(() => props.value, (newVal) => {
    const target = newVal ?? 0;
    const start = displayValue.value;
    const duration = 600;
    const startTime = Date.now();
    
    const animate = () => {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Ease out cubic for smooth deceleration
        const easeOut = 1 - Math.pow(1 - progress, 3);
        displayValue.value = start + (target - start) * easeOut;
        
        if (progress < 1) requestAnimationFrame(animate);
    };
    requestAnimationFrame(animate);
}, { immediate: true });

// Calculate mercury fill percentage (0-100)
const fillPercentage = computed(() => {
    const range = props.max - props.min;
    if (range <= 0) return 0;
    const pct = ((displayValue.value - props.min) / range) * 100;
    return Math.min(Math.max(pct, 0), 100);
});

// Determine temperature zone
type TempZone = 'cold' | 'optimal' | 'warm' | 'hot' | 'critical';

const zone = computed<TempZone>(() => {
    const v = displayValue.value;
    if (v < props.coldThreshold) return 'cold';
    if (v < props.optimalThreshold) return 'optimal';
    if (v < props.warmThreshold) return 'warm';
    if (v < props.hotThreshold) return 'hot';
    return 'critical';
});

// Zone colors - Neurona palette
const zoneConfig = computed(() => {
    const configs: Record<TempZone, { 
        color: string; 
        glow: string; 
        textClass: string;
        bgClass: string;
    }> = {
        cold: {
            color: '#06b6d4',     // Cyan
            glow: '0 0 20px rgba(6, 182, 212, 0.6)',
            textClass: 'text-cyan-400',
            bgClass: 'bg-cyan-500/20',
        },
        optimal: {
            color: '#22c55e',     // Green
            glow: '0 0 20px rgba(34, 197, 94, 0.5)',
            textClass: 'text-green-400',
            bgClass: 'bg-green-500/20',
        },
        warm: {
            color: '#eab308',     // Yellow
            glow: '0 0 20px rgba(234, 179, 8, 0.5)',
            textClass: 'text-yellow-400',
            bgClass: 'bg-yellow-500/20',
        },
        hot: {
            color: '#f97316',     // Orange
            glow: '0 0 25px rgba(249, 115, 22, 0.6)',
            textClass: 'text-orange-400',
            bgClass: 'bg-orange-500/20',
        },
        critical: {
            color: '#ef4444',     // Red
            glow: '0 0 30px rgba(239, 68, 68, 0.7)',
            textClass: 'text-red-400',
            bgClass: 'bg-red-500/30',
        },
    };
    return configs[zone.value];
});

const isCritical = computed(() => zone.value === 'critical' || zone.value === 'hot');

// SVG dimensions for thermometer
const thermometerHeight = 120; // Height of the tube portion
const bulbRadius = 16;
const tubeWidth = 12;
const mercuryWidth = 8;

// Calculate mercury height based on fill percentage
const mercuryHeight = computed(() => {
    return (fillPercentage.value / 100) * thermometerHeight;
});
</script>

<template>
    <div 
        class="thermometer-widget flex items-center justify-center gap-4 p-4 rounded-xl border transition-all duration-300"
        :class="[
            zoneConfig.bgClass,
            { 'animate-pulse-critical': isCritical },
            'border-slate-700/50'
        ]"
        :style="{ boxShadow: zoneConfig.glow }"
    >
        <!-- Thermometer SVG -->
        <div class="thermometer-container relative">
            <svg 
                width="50" 
                height="160" 
                viewBox="0 0 50 160"
                class="thermometer-svg"
            >
                <!-- Definitions for gradients and filters -->
                <defs>
                    <!-- Mercury gradient -->
                    <linearGradient id="mercuryGradient" x1="0%" y1="100%" x2="0%" y2="0%">
                        <stop offset="0%" :stop-color="zoneConfig.color" stop-opacity="1" />
                        <stop offset="100%" :stop-color="zoneConfig.color" stop-opacity="0.7" />
                    </linearGradient>
                    
                    <!-- Glass effect gradient -->
                    <linearGradient id="glassGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="rgba(255,255,255,0.15)" />
                        <stop offset="50%" stop-color="rgba(255,255,255,0.05)" />
                        <stop offset="100%" stop-color="rgba(255,255,255,0.15)" />
                    </linearGradient>
                    
                    <!-- Glow filter -->
                    <filter id="mercuryGlow" x="-50%" y="-50%" width="200%" height="200%">
                        <feGaussianBlur stdDeviation="3" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>
                
                <!-- Background tube (glass effect) -->
                <rect
                    :x="(50 - tubeWidth) / 2"
                    y="10"
                    :width="tubeWidth"
                    :height="thermometerHeight"
                    rx="6"
                    fill="rgba(30, 41, 59, 0.8)"
                    stroke="rgba(100, 116, 139, 0.5)"
                    stroke-width="1"
                />
                
                <!-- Glass highlight -->
                <rect
                    :x="(50 - tubeWidth) / 2 + 1"
                    y="12"
                    :width="tubeWidth - 4"
                    :height="thermometerHeight - 4"
                    rx="4"
                    fill="url(#glassGradient)"
                />
                
                <!-- Bulb (bottom circle) -->
                <circle
                    cx="25"
                    :cy="10 + thermometerHeight + bulbRadius - 4"
                    :r="bulbRadius"
                    fill="rgba(30, 41, 59, 0.9)"
                    stroke="rgba(100, 116, 139, 0.5)"
                    stroke-width="1"
                />
                
                <!-- Mercury in bulb -->
                <circle
                    cx="25"
                    :cy="10 + thermometerHeight + bulbRadius - 4"
                    :r="bulbRadius - 4"
                    :fill="zoneConfig.color"
                    filter="url(#mercuryGlow)"
                    class="transition-all duration-500"
                />
                
                <!-- Mercury column (animated) -->
                <rect
                    :x="(50 - mercuryWidth) / 2"
                    :y="10 + thermometerHeight - mercuryHeight"
                    :width="mercuryWidth"
                    :height="mercuryHeight + 8"
                    rx="4"
                    fill="url(#mercuryGradient)"
                    filter="url(#mercuryGlow)"
                    class="transition-all duration-500"
                />
                
                <!-- Scale marks on the right side -->
                <g class="scale-marks">
                    <!-- Max mark -->
                    <line x1="35" y1="15" x2="42" y2="15" stroke="rgba(148, 163, 184, 0.5)" stroke-width="1" />
                    <text x="44" y="18" fill="rgba(148, 163, 184, 0.6)" font-size="8" font-family="monospace">{{ max }}</text>
                    
                    <!-- Mid mark -->
                    <line x1="35" :y1="10 + thermometerHeight/2" x2="40" :y2="10 + thermometerHeight/2" stroke="rgba(148, 163, 184, 0.3)" stroke-width="1" />
                    
                    <!-- Min mark -->
                    <line x1="35" :y1="thermometerHeight + 5" x2="42" :y2="thermometerHeight + 5" stroke="rgba(148, 163, 184, 0.5)" stroke-width="1" />
                    <text x="44" :y="thermometerHeight + 8" fill="rgba(148, 163, 184, 0.6)" font-size="8" font-family="monospace">{{ min }}</text>
                </g>
                
                <!-- Zone indicators on left side -->
                <g class="zone-indicators">
                    <!-- Critical zone marker -->
                    <rect x="4" y="15" width="3" height="20" rx="1" fill="#ef4444" opacity="0.4" />
                    <!-- Hot zone marker -->
                    <rect x="4" y="35" width="3" height="20" rx="1" fill="#f97316" opacity="0.4" />
                    <!-- Optimal zone marker -->
                    <rect x="4" y="55" width="3" height="40" rx="1" fill="#22c55e" opacity="0.4" />
                    <!-- Cold zone marker -->
                    <rect x="4" y="95" width="3" height="30" rx="1" fill="#06b6d4" opacity="0.4" />
                </g>
            </svg>
        </div>
        
        <!-- Value Display -->
        <div class="value-section flex flex-col items-center justify-center">
            <!-- Temperature Value -->
            <div 
                class="text-4xl font-black tabular-nums leading-none transition-colors duration-300 font-mono"
                :class="zoneConfig.textClass"
                :style="{ textShadow: zoneConfig.glow }"
            >
                {{ Math.round(displayValue) }}
            </div>
            
            <!-- Unit -->
            <div 
                class="text-lg font-bold opacity-70 transition-colors duration-300"
                :class="zoneConfig.textClass"
            >
                {{ unit }}
            </div>
            
            <!-- Label -->
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-2">
                {{ label }}
            </div>
            
            <!-- Zone indicator badge -->
            <div 
                class="mt-2 px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide"
                :class="[zoneConfig.bgClass, zoneConfig.textClass]"
            >
                {{ zone === 'optimal' ? 'OPTIMAL' : zone === 'cold' ? 'COLD' : zone === 'warm' ? 'WARM' : zone === 'hot' ? 'HOT' : 'CRITICAL' }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.thermometer-widget {
    min-width: 160px;
    min-height: 180px;
    backdrop-filter: blur(8px);
}

.thermometer-svg {
    filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.3));
}

@keyframes pulse-critical {
    0%, 100% { 
        opacity: 1;
        transform: scale(1);
    }
    50% { 
        opacity: 0.85;
        transform: scale(1.01);
    }
}

.animate-pulse-critical {
    animation: pulse-critical 1s ease-in-out infinite;
}

.tabular-nums {
    font-variant-numeric: tabular-nums;
}
</style>
