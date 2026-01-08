<script setup lang="ts">
/**
 * DigitalValueWidget.vue - NEURONA DESIGN SYSTEM
 * 
 * Premium widget for displaying single values like GEAR, Voltage, etc.
 * Features:
 * - Large, bold value with glow effect
 * - Optional icon or emoji
 * - Status coloring based on thresholds
 * - Compact and full variants
 * 
 * Perfect for: Gear indicator, Battery voltage, Fuel level
 */
import { computed } from 'vue';

// Zone colors - NEURONA palette
const zoneColors = {
    primary: '#00E5A0',     // Neurona Green
    accent: '#00D4FF',      // Cyan
    gold: '#FFB800',        // Gold
    warning: '#FF6B35',     // Orange
    danger: '#FF3366',      // Red
    neutral: '#ffffff',     // White
    muted: 'rgba(255, 255, 255, 0.5)',
};

// Props
interface Props {
    value?: number | string | null;
    label?: string;
    unit?: string;
    icon?: string;
    fontSize?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl' | '6xl';
    fontWeight?: 'normal' | 'medium' | 'semibold' | 'bold' | 'black';
    color?: 'primary' | 'accent' | 'gold' | 'warning' | 'danger' | 'neutral' | 'auto';
    variant?: 'default' | 'circle' | 'pill' | 'minimal';
    fallbackValue?: string;
    showLabel?: boolean;
    showGlow?: boolean;
    animated?: boolean;
    // For auto color mode
    min?: number;
    max?: number;
    thresholds?: {
        warning?: number;   // Above this = warning
        danger?: number;    // Above this = danger
    };
}

const props = withDefaults(defineProps<Props>(), {
    value: null,
    label: '',
    unit: '',
    icon: '',
    fontSize: '5xl',
    fontWeight: 'black',
    color: 'neutral',
    variant: 'default',
    fallbackValue: '--',
    showLabel: true,
    showGlow: true,
    animated: true,
    min: 0,
    max: 100,
});

// Computed: Display value
const displayValue = computed(() => {
    if (props.value === null || props.value === undefined) {
        return props.fallbackValue;
    }
    return String(props.value);
});

// Computed: Get color based on value or prop
const activeColor = computed(() => {
    if (props.color !== 'auto') {
        return zoneColors[props.color] || zoneColors.neutral;
    }
    
    // Auto mode - calculate based on value
    if (typeof props.value !== 'number') {
        return zoneColors.neutral;
    }
    
    const val = props.value;
    const thresholds = props.thresholds || { warning: props.max * 0.7, danger: props.max * 0.9 };
    
    if (val >= (thresholds.danger || props.max * 0.9)) {
        return zoneColors.danger;
    }
    if (val >= (thresholds.warning || props.max * 0.7)) {
        return zoneColors.warning;
    }
    return zoneColors.primary;
});

// Computed: Font size class
const fontSizeClass = computed(() => {
    const sizeMap: Record<string, string> = {
        'sm': 'text-sm',
        'md': 'text-base',
        'lg': 'text-lg',
        'xl': 'text-xl',
        '2xl': 'text-2xl',
        '3xl': 'text-3xl',
        '4xl': 'text-4xl',
        '5xl': 'text-5xl',
        '6xl': 'text-6xl',
    };
    return sizeMap[props.fontSize] || 'text-5xl';
});

// Computed: Font weight class
const fontWeightClass = computed(() => {
    const weightMap: Record<string, string> = {
        'normal': 'font-normal',
        'medium': 'font-medium',
        'semibold': 'font-semibold',
        'bold': 'font-bold',
        'black': 'font-black',
    };
    return weightMap[props.fontWeight] || 'font-black';
});

// Computed: Is critical (for animation)
const isCritical = computed(() => {
    if (props.color !== 'auto' || typeof props.value !== 'number') return false;
    const thresholds = props.thresholds || { danger: props.max * 0.9 };
    return props.value >= (thresholds.danger || props.max * 0.9);
});
</script>

<template>
    <div 
        class="digital-value-widget"
        :class="[
            `variant-${variant}`,
            { 'is-critical': isCritical }
        ]"
    >
        <!-- Circle Background (for circle variant) -->
        <div 
            v-if="variant === 'circle'"
            class="circle-bg"
            :style="{ 
                borderColor: activeColor,
                boxShadow: showGlow ? `0 0 30px ${activeColor}40` : 'none'
            }"
        />
        
        <!-- Content Container -->
        <div class="content-container">
            <!-- Icon (if provided) -->
            <span v-if="icon" class="value-icon">{{ icon }}</span>
            
            <!-- Main Value -->
            <div 
                class="value-display"
                :class="[fontSizeClass, fontWeightClass, { 'transition-all duration-200': animated }]"
                :style="{ 
                    color: activeColor,
                    textShadow: showGlow ? `0 0 30px ${activeColor}60` : 'none'
                }"
            >
                {{ displayValue }}
            </div>
            
            <!-- Unit (if provided) -->
            <span 
                v-if="unit"
                class="value-unit"
                :style="{ color: activeColor + '80' }"
            >
                {{ unit }}
            </span>
        </div>
        
        <!-- Label (below) -->
        <div 
            v-if="showLabel && label"
            class="value-label"
        >
            {{ label }}
        </div>
    </div>
</template>

<style scoped>
.digital-value-widget {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    position: relative;
    min-width: 100px;
    min-height: 100px;
}

/* Variants */
.variant-default {
    /* Clean, no background */
}

.variant-circle {
    padding: 1.5rem;
}

.variant-pill {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 9999px;
    padding: 0.75rem 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.variant-minimal {
    padding: 0.5rem;
    min-height: auto;
}

/* Circle background */
.circle-bg {
    position: absolute;
    inset: 0.5rem;
    border-radius: 50%;
    border: 3px solid;
    transition: all 0.3s ease;
    background: rgba(10, 10, 15, 0.6);
}

/* Content */
.content-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 1;
    gap: 0.25rem;
}

.value-icon {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
}

.value-display {
    font-family: 'JetBrains Mono', 'SF Mono', 'Fira Code', monospace;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    transition: all 0.3s ease;
}

.value-unit {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.value-label {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 0.5rem;
}

/* Critical state */
.is-critical {
    animation: critical-pulse 1s ease-in-out infinite;
}

@keyframes critical-pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.02);
    }
}

.is-critical .circle-bg {
    animation: critical-glow 1s ease-in-out infinite;
}

@keyframes critical-glow {
    0%, 100% {
        box-shadow: 0 0 20px rgba(255, 51, 102, 0.3);
    }
    50% {
        box-shadow: 0 0 40px rgba(255, 51, 102, 0.6);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .digital-value-widget {
        padding: 0.75rem;
        min-width: 80px;
        min-height: 80px;
    }
    
    .variant-circle {
        padding: 1rem;
    }
}
</style>
