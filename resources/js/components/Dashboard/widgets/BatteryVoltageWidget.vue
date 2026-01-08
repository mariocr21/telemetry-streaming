<script setup lang="ts">
/**
 * BatteryVoltageWidget.vue - NEURONA DESIGN SYSTEM
 * 
 * Premium battery voltage widget with visual battery icon.
 * Features:
 * - Animated battery icon showing charge level
 * - Zone-based coloring (Dead → Low → Normal → Charging → Overcharge)
 * - Glow effects and pulse animation on critical states
 * - Responsive sizing
 * 
 * Typical voltage ranges:
 * - < 11.5V = Critical (dead battery)
 * - 11.5-12.0V = Low
 * - 12.0-12.6V = Normal (engine off)
 * - 12.6-14.0V = Good (engine running)
 * - 14.0-14.8V = Charging (alternator active)
 * - > 14.8V = Overcharge warning
 */
import { computed, ref, watch } from 'vue';
import { BatteryCharging, BatteryFull, BatteryLow, BatteryMedium, BatteryWarning, Zap } from 'lucide-vue-next';

interface Props {
    value?: number;
    label?: string;
    unit?: string;
    min?: number;
    max?: number;
    // Voltage thresholds
    criticalLow?: number;    // Below this = dead battery
    lowThreshold?: number;   // Below this = low battery
    normalThreshold?: number; // Below this = normal (engine off)
    chargingThreshold?: number; // Below this = good (engine running), above = charging
    overchargeThreshold?: number; // Above this = overcharge warning
}

const props = withDefaults(defineProps<Props>(), {
    value: 12.6,
    label: 'BATTERY',
    unit: 'V',
    min: 10,
    max: 16,
    criticalLow: 11.5,
    lowThreshold: 12.0,
    normalThreshold: 12.6,
    chargingThreshold: 14.0,
    overchargeThreshold: 14.8,
});

// Animated display value
const displayValue = ref(12.6);

watch(() => props.value, (newVal) => {
    const target = newVal ?? 12.6;
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

// Determine voltage zone
type VoltageZone = 'critical' | 'low' | 'normal' | 'good' | 'charging' | 'overcharge';

const zone = computed<VoltageZone>(() => {
    const v = displayValue.value;
    if (v < props.criticalLow) return 'critical';
    if (v < props.lowThreshold) return 'low';
    if (v < props.normalThreshold) return 'normal';
    if (v < props.chargingThreshold) return 'good';
    if (v < props.overchargeThreshold) return 'charging';
    return 'overcharge';
});

// Zone colors - Neurona palette
const zoneConfig = computed(() => {
    const configs: Record<VoltageZone, { 
        color: string; 
        glow: string; 
        textClass: string;
        bgClass: string;
        statusText: string;
        batteryLevel: number; // 0-100 for visual
    }> = {
        critical: {
            color: '#ef4444',
            glow: '0 0 25px rgba(239, 68, 68, 0.7)',
            textClass: 'text-red-400',
            bgClass: 'bg-red-500/20',
            statusText: 'DEAD',
            batteryLevel: 5,
        },
        low: {
            color: '#f97316',
            glow: '0 0 20px rgba(249, 115, 22, 0.6)',
            textClass: 'text-orange-400',
            bgClass: 'bg-orange-500/20',
            statusText: 'LOW',
            batteryLevel: 25,
        },
        normal: {
            color: '#eab308',
            glow: '0 0 15px rgba(234, 179, 8, 0.5)',
            textClass: 'text-yellow-400',
            bgClass: 'bg-yellow-500/20',
            statusText: 'NORMAL',
            batteryLevel: 50,
        },
        good: {
            color: '#22c55e',
            glow: '0 0 20px rgba(34, 197, 94, 0.5)',
            textClass: 'text-green-400',
            bgClass: 'bg-green-500/20',
            statusText: 'GOOD',
            batteryLevel: 75,
        },
        charging: {
            color: '#06b6d4',
            glow: '0 0 25px rgba(6, 182, 212, 0.6)',
            textClass: 'text-cyan-400',
            bgClass: 'bg-cyan-500/20',
            statusText: 'CHARGING',
            batteryLevel: 100,
        },
        overcharge: {
            color: '#a855f7',
            glow: '0 0 30px rgba(168, 85, 247, 0.7)',
            textClass: 'text-purple-400',
            bgClass: 'bg-purple-500/30',
            statusText: 'HIGH!',
            batteryLevel: 100,
        },
    };
    return configs[zone.value];
});

const isCritical = computed(() => zone.value === 'critical' || zone.value === 'overcharge');
const isCharging = computed(() => zone.value === 'charging');

// Calculate battery fill width (0-100%)
const batteryFillWidth = computed(() => {
    return Math.min(Math.max(zoneConfig.value.batteryLevel, 0), 100);
});

// Battery bar segments (for segmented look)
const batterySegments = computed(() => {
    const filled = Math.floor(zoneConfig.value.batteryLevel / 25);
    return [
        { id: 1, filled: filled >= 1 },
        { id: 2, filled: filled >= 2 },
        { id: 3, filled: filled >= 3 },
        { id: 4, filled: filled >= 4 },
    ];
});
</script>

<template>
    <div 
        class="battery-widget flex flex-col items-center justify-center p-4 rounded-xl border transition-all duration-300"
        :class="[
            zoneConfig.bgClass,
            { 'animate-pulse-critical': isCritical },
            'border-slate-700/50'
        ]"
        :style="{ boxShadow: zoneConfig.glow }"
    >
        <!-- Battery Icon SVG -->
        <div class="battery-icon-container relative mb-3">
            <svg 
                width="80" 
                height="40" 
                viewBox="0 0 80 40"
                class="battery-svg"
            >
                <defs>
                    <!-- Battery fill gradient -->
                    <linearGradient id="batteryFillGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" :stop-color="zoneConfig.color" stop-opacity="0.9" />
                        <stop offset="100%" :stop-color="zoneConfig.color" stop-opacity="0.6" />
                    </linearGradient>
                    
                    <!-- Glow filter -->
                    <filter id="batteryGlow" x="-30%" y="-30%" width="160%" height="160%">
                        <feGaussianBlur stdDeviation="2" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>
                
                <!-- Battery body outline -->
                <rect
                    x="2"
                    y="5"
                    width="68"
                    height="30"
                    rx="4"
                    fill="rgba(30, 41, 59, 0.8)"
                    stroke="rgba(100, 116, 139, 0.6)"
                    stroke-width="2"
                />
                
                <!-- Battery cap (positive terminal) -->
                <rect
                    x="70"
                    y="12"
                    width="6"
                    height="16"
                    rx="2"
                    fill="rgba(100, 116, 139, 0.6)"
                />
                
                <!-- Battery segments -->
                <g class="battery-segments">
                    <rect
                        v-for="(seg, idx) in batterySegments"
                        :key="seg.id"
                        :x="6 + idx * 16"
                        y="9"
                        width="14"
                        height="22"
                        rx="2"
                        :fill="seg.filled ? zoneConfig.color : 'rgba(51, 65, 85, 0.5)'"
                        :filter="seg.filled ? 'url(#batteryGlow)' : ''"
                        class="transition-all duration-300"
                    />
                </g>
                
                <!-- Charging bolt icon (when charging) -->
                <g v-if="isCharging" class="charging-bolt animate-pulse">
                    <path
                        d="M38 8 L32 18 L38 18 L34 32 L46 16 L38 16 L44 8 Z"
                        :fill="zoneConfig.color"
                        filter="url(#batteryGlow)"
                    />
                </g>
            </svg>
        </div>
        
        <!-- Voltage Value -->
        <div class="value-section flex flex-col items-center">
            <div 
                class="text-4xl font-black tabular-nums leading-none transition-colors duration-300 font-mono"
                :class="zoneConfig.textClass"
                :style="{ textShadow: zoneConfig.glow }"
            >
                {{ displayValue.toFixed(1) }}
            </div>
            
            <!-- Unit -->
            <div 
                class="text-xl font-bold opacity-80 transition-colors duration-300 -mt-1"
                :class="zoneConfig.textClass"
            >
                {{ unit }}
            </div>
            
            <!-- Label -->
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-2">
                {{ label }}
            </div>
            
            <!-- Status badge -->
            <div 
                class="mt-2 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide flex items-center gap-1"
                :class="[zoneConfig.bgClass, zoneConfig.textClass]"
            >
                <Zap v-if="isCharging" class="w-3 h-3" />
                {{ zoneConfig.statusText }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.battery-widget {
    min-width: 140px;
    min-height: 180px;
    backdrop-filter: blur(8px);
}

.battery-svg {
    filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.3));
}

@keyframes pulse-critical {
    0%, 100% { 
        opacity: 1;
        transform: scale(1);
    }
    50% { 
        opacity: 0.8;
        transform: scale(1.02);
    }
}

.animate-pulse-critical {
    animation: pulse-critical 1s ease-in-out infinite;
}

.charging-bolt {
    animation: bolt-pulse 1.5s ease-in-out infinite;
}

@keyframes bolt-pulse {
    0%, 100% { 
        opacity: 1;
        transform: scale(1);
    }
    50% { 
        opacity: 0.6;
        transform: scale(1.1);
    }
}

.tabular-nums {
    font-variant-numeric: tabular-nums;
}
</style>
