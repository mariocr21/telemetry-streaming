<script setup lang="ts">
/**
 * ShiftLightsBar.vue - NEURONA DESIGN SYSTEM
 * 
 * Racing-style shift indicator lights that illuminate progressively
 * as RPM approaches the shift point.
 * 
 * Features:
 * - Compatible with dynamic widget binding (uses 'value' prop)
 * - Fully configurable from props_schema
 * - Progressive lighting: Green → Yellow → Red
 * - Flash effect at shift point
 * - Glow effects for premium look
 * 
 * Perfect for: RPM-based shift indication in racing scenarios
 */
import { computed, ref, watch } from 'vue';

interface LegacyConfig {
    totalLights?: number;
    startRpm?: number;
    maxRpm?: number;
    shiftRpm?: number;
}

interface Props {
    // Main value (RPM from binding - for widget mode)
    value?: number;
    // Legacy prop (RPM directly - for header mode)
    rpm?: number;
    // Legacy config object (for header mode)
    config?: LegacyConfig;
    // Label (optional, shown on hover or compact mode)
    label?: string;
    // Configuration (can be individual props or from config object)
    totalLights?: number;      // Number of indicator lights (default: 10)
    startRpm?: number;         // RPM where lights start illuminating (default: 4000)
    shiftRpm?: number;         // RPM for shift warning (default: 7000)
    maxRpm?: number;           // Max RPM / redline (default: 8000)
    // Visual options
    lightSize?: 'sm' | 'md' | 'lg';  // Size of lights
    showLabel?: boolean;       // Show RPM value next to lights
    showShiftText?: boolean;   // Show "SHIFT!" text at shift point
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    rpm: undefined,
    config: undefined,
    label: 'RPM',
    totalLights: 10,
    startRpm: 4000,
    shiftRpm: 7000,
    maxRpm: 8000,
    lightSize: 'md',
    showLabel: true,
    showShiftText: true,
});

// Resolve RPM value: prefer 'rpm' prop (legacy header mode), fallback to 'value' (widget mode)
const rpmValue = computed(() => props.rpm ?? props.value ?? 0);

// Resolve config: merge legacy config object with individual props
const resolvedConfig = computed(() => ({
    totalLights: Number(props.config?.totalLights ?? props.totalLights),
    startRpm: Number(props.config?.startRpm ?? props.startRpm),
    shiftRpm: Number(props.config?.shiftRpm ?? props.shiftRpm),
    maxRpm: Number(props.config?.maxRpm ?? props.maxRpm),
}));

// Animated RPM display
const displayRpm = ref(0);

watch(rpmValue, (newVal) => {
    const target = newVal ?? 0;
    const start = displayRpm.value;
    const duration = 100; // Fast animation for responsive feel
    const startTime = Date.now();
    
    const animate = () => {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        displayRpm.value = start + (target - start) * progress;
        
        if (progress < 1) requestAnimationFrame(animate);
    };
    requestAnimationFrame(animate);
}, { immediate: true });

// Calculate how many lights should be active
const activeLights = computed(() => {
    const cfg = resolvedConfig.value;
    if (displayRpm.value < cfg.startRpm) return 0;
    
    const rpmRange = cfg.maxRpm - cfg.startRpm;
    const currentInRange = displayRpm.value - cfg.startRpm;
    const percentage = Math.min(currentInRange / rpmRange, 1);
    
    return Math.ceil(percentage * cfg.totalLights);
});

// Is at shift point?
const atShiftPoint = computed(() => displayRpm.value >= resolvedConfig.value.shiftRpm);

// Is at redline?
const atRedline = computed(() => displayRpm.value >= resolvedConfig.value.maxRpm);

// Get color for each light based on position
function getLightColor(index: number): { bg: string; glow: string } {
    const position = (index + 1) / resolvedConfig.value.totalLights;
    
    if (position <= 0.5) {
        // First half: Green
        return {
            bg: 'bg-green-500',
            glow: '0 0 12px rgba(34, 197, 94, 0.8)',
        };
    } else if (position <= 0.8) {
        // Middle: Yellow
        return {
            bg: 'bg-yellow-400',
            glow: '0 0 12px rgba(250, 204, 21, 0.8)',
        };
    } else {
        // Top: Red
        return {
            bg: 'bg-red-500',
            glow: '0 0 15px rgba(239, 68, 68, 0.9)',
        };
    }
}

// Check if light is active
function isLightActive(index: number): boolean {
    return index < activeLights.value;
}

// Light size classes
const lightSizeClass = computed(() => {
    switch (props.lightSize) {
        case 'sm': return 'w-3 h-3';
        case 'lg': return 'w-6 h-6';
        default: return 'w-4 h-4 md:w-5 md:h-5';
    }
});

// Generate lights array
const lights = computed(() => {
    return Array.from({ length: resolvedConfig.value.totalLights }, (_, i) => ({
        index: i,
        active: isLightActive(i),
        color: getLightColor(i),
    }));
});
</script>

<template>
    <div 
        class="shift-lights-widget flex flex-col items-center justify-center p-3 rounded-xl transition-all duration-200"
        :class="[
            atRedline ? 'bg-red-900/40 border-red-500/50' : 'bg-slate-900/60 border-slate-700/50',
            { 'animate-redline-flash': atRedline }
        ]"
        style="border-width: 1px;"
    >
        <!-- RPM Display (optional) -->
        <div 
            v-if="showLabel" 
            class="text-xs font-mono mb-2 transition-colors duration-200"
            :class="atShiftPoint ? 'text-red-400' : 'text-slate-400'"
        >
            {{ Math.round(displayRpm).toLocaleString() }} <span class="text-slate-600">RPM</span>
        </div>
        
        <!-- Shift Lights Container -->
        <div class="flex items-center justify-center gap-1.5 md:gap-2">
            <!-- Individual lights -->
            <div 
                v-for="light in lights"
                :key="light.index"
                class="shift-light rounded-full transition-all duration-75"
                :class="[
                    lightSizeClass,
                    light.active ? light.color.bg : 'bg-slate-700/60',
                    { 'animate-shift-flash': atShiftPoint && light.active && light.index >= resolvedConfig.totalLights * 0.8 }
                ]"
                :style="light.active ? { boxShadow: light.color.glow } : {}"
            />
        </div>
        
        <!-- SHIFT indicator -->
        <transition name="shift-text">
            <div 
                v-if="showShiftText && atShiftPoint"
                class="mt-2 flex items-center gap-2"
            >
                <span 
                    class="text-sm md:text-base font-black tracking-widest animate-pulse"
                    :class="atRedline ? 'text-red-400' : 'text-yellow-400'"
                >
                    {{ atRedline ? '⚠️ REDLINE!' : '↑ SHIFT!' }}
                </span>
            </div>
        </transition>
        
        <!-- Progress bar (alternative compact view) -->
        <div class="w-full mt-2 h-1 bg-slate-800 rounded-full overflow-hidden">
            <div 
                class="h-full transition-all duration-100 rounded-full"
                :class="[
                    atRedline ? 'bg-red-500' : atShiftPoint ? 'bg-yellow-400' : 'bg-green-500'
                ]"
                :style="{ 
                    width: `${Math.min((displayRpm / resolvedConfig.maxRpm) * 100, 100)}%`,
                    boxShadow: atShiftPoint ? '0 0 10px currentColor' : 'none'
                }"
            />
        </div>
    </div>
</template>

<style scoped>
.shift-lights-widget {
    backdrop-filter: blur(8px);
    min-height: 80px;
}

.shift-light {
    transition: all 0.075s ease-out;
}

/* Shift point flash animation */
@keyframes shift-flash {
    0%, 100% { 
        opacity: 1;
        transform: scale(1);
    }
    50% { 
        opacity: 0.4;
        transform: scale(1.1);
    }
}

.animate-shift-flash {
    animation: shift-flash 0.15s ease-in-out infinite;
}

/* Redline flash for entire widget */
@keyframes redline-flash {
    0%, 100% { 
        background-color: rgba(127, 29, 29, 0.4);
    }
    50% { 
        background-color: rgba(185, 28, 28, 0.6);
    }
}

.animate-redline-flash {
    animation: redline-flash 0.3s ease-in-out infinite;
}

/* Text transition */
.shift-text-enter-active,
.shift-text-leave-active {
    transition: all 0.2s ease;
}

.shift-text-enter-from,
.shift-text-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
