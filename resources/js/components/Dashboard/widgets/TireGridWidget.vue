<script setup lang="ts">
/**
 * TireGridWidget.vue
 * 
 * Visual representation of 4 tires with pressure/temperature values.
 * Shows a car diagram with values for each wheel position.
 */
import { computed } from 'vue';

// Types
interface TireSlot {
    value: number | null;
    label?: string;
    unit?: string;
    thresholds?: {
        warning?: number;
        critical?: number;
        low?: number;
    };
}

// Props
interface Props {
    slots?: {
        fl?: TireSlot;  // Front Left
        fr?: TireSlot;  // Front Right
        rl?: TireSlot;  // Rear Left
        rr?: TireSlot;  // Rear Right
    };
    metric?: 'pressure' | 'temperature';
    unit?: string;
    showLabels?: boolean;
    minValue?: number;
    maxValue?: number;
}

const props = withDefaults(defineProps<Props>(), {
    slots: () => ({}),
    metric: 'pressure',
    unit: 'PSI',
    showLabels: true,
    minValue: 0,
    maxValue: 50,
});

// Tire positions
const tirePositions = [
    { key: 'fl', label: 'FL', gridArea: 'fl' },
    { key: 'fr', label: 'FR', gridArea: 'fr' },
    { key: 'rl', label: 'RL', gridArea: 'rl' },
    { key: 'rr', label: 'RR', gridArea: 'rr' },
] as const;

// Get tire value
function getTireValue(key: string): string {
    const tire = props.slots?.[key as keyof typeof props.slots];
    if (!tire || tire.value === null || tire.value === undefined) {
        return '--';
    }
    return String(Math.round(tire.value * 10) / 10);
}

// Get tire status color
function getTireColor(key: string): string {
    const tire = props.slots?.[key as keyof typeof props.slots];
    if (!tire || tire.value === null) {
        return 'text-slate-500';
    }

    const value = tire.value;
    const thresholds = tire.thresholds;

    // Critical high
    if (thresholds?.critical !== undefined && value >= thresholds.critical) {
        return 'text-red-400';
    }
    // Warning high
    if (thresholds?.warning !== undefined && value >= thresholds.warning) {
        return 'text-orange-400';
    }
    // Low pressure warning
    if (thresholds?.low !== undefined && value <= thresholds.low) {
        return 'text-yellow-400';
    }

    return 'text-green-400';
}

// Get tire background based on status
function getTireBg(key: string): string {
    const color = getTireColor(key);
    if (color.includes('red')) return 'bg-red-500/10 border-red-500/30';
    if (color.includes('orange')) return 'bg-orange-500/10 border-orange-500/30';
    if (color.includes('yellow')) return 'bg-yellow-500/10 border-yellow-500/30';
    if (color.includes('green')) return 'bg-green-500/10 border-green-500/30';
    return 'bg-slate-700/30 border-slate-600/30';
}
</script>

<template>
    <div class="tire-grid-widget w-full p-4">
        <!-- Car diagram with tires -->
        <div class="tire-diagram">
            <!-- Car body outline (SVG) -->
            <svg 
                class="car-outline absolute inset-0 w-full h-full"
                viewBox="0 0 100 140"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <!-- Car body -->
                <path 
                    d="M20 30 L80 30 C85 30 90 35 90 40 L90 110 C90 115 85 120 80 120 L20 120 C15 120 10 115 10 110 L10 40 C10 35 15 30 20 30 Z"
                    stroke="currentColor"
                    stroke-width="1.5"
                    class="text-slate-600"
                    fill="none"
                />
                <!-- Windshield -->
                <path 
                    d="M25 45 L75 45"
                    stroke="currentColor"
                    stroke-width="1"
                    class="text-slate-600"
                />
                <!-- Rear window -->
                <path 
                    d="M25 100 L75 100"
                    stroke="currentColor"
                    stroke-width="1"
                    class="text-slate-600"
                />
            </svg>

            <!-- Tire positions -->
            <div 
                v-for="tire in tirePositions"
                :key="tire.key"
                class="tire-box absolute flex flex-col items-center justify-center p-2 rounded-lg border transition-all"
                :class="[getTireBg(tire.key), `grid-${tire.gridArea}`]"
            >
                <!-- Position label -->
                <span 
                    v-if="showLabels"
                    class="text-[10px] uppercase text-slate-500 mb-0.5"
                >
                    {{ tire.label }}
                </span>
                
                <!-- Value -->
                <span 
                    class="text-lg font-bold tabular-nums"
                    :class="getTireColor(tire.key)"
                >
                    {{ getTireValue(tire.key) }}
                </span>
                
                <!-- Unit -->
                <span class="text-[10px] text-slate-500">{{ unit }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.tire-grid-widget {
    min-height: 180px;
}

.tire-diagram {
    position: relative;
    width: 100%;
    height: 160px;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    grid-template-rows: 1fr 1fr 1fr;
    grid-template-areas:
        "fl . fr"
        ". car ."
        "rl . rr";
    gap: 0.5rem;
}

.car-outline {
    grid-area: car;
    place-self: center;
    width: 60%;
    height: 100%;
    opacity: 0.5;
}

.grid-fl { grid-area: fl; }
.grid-fr { grid-area: fr; }
.grid-rl { grid-area: rl; }
.grid-rr { grid-area: rr; }

.tire-box {
    min-width: 60px;
}

.tabular-nums {
    font-variant-numeric: tabular-nums;
}
</style>
