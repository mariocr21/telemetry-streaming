<script setup lang="ts">
/**
 * LinearBarD3.vue - High-Performance D3.js Linear Bar Component
 * Neurona Off Road Telemetry - Cyberpunk Industrial Design
 * 
 * Features:
 * - Horizontal bars for temperatures, pressures, etc.
 * - Animated fill transitions with D3.js
 * - Dynamic color based on thresholds
 * - Minimalist tactical design
 */

import { ref, computed, watch, onMounted } from 'vue';
import * as d3 from 'd3';
import { useResizeObserver } from '@vueuse/core';
import { throttle } from 'lodash';

interface Threshold {
    value: number;
    color: string;
}

interface Props {
    value: number;
    min?: number;
    max?: number;
    label?: string;
    unit?: string;
    thresholds?: Threshold[] | Record<string, any>; // Accept both array and object
    showScale?: boolean;
    height?: number;
    animated?: boolean;
    variant?: 'default' | 'compact' | 'thermometer';
}

const props = withDefaults(defineProps<Props>(), {
    min: 0,
    max: 100,
    label: 'SENSOR',
    unit: '',
    thresholds: () => [
        { value: 60, color: '#00ff9d' },
        { value: 80, color: '#ff8a00' },
        { value: 100, color: '#ff003c' }
    ],
    showScale: true,
    height: 20,
    animated: true,
    variant: 'default'
});

const containerRef = ref<HTMLDivElement | null>(null);
const barRef = ref<SVGSVGElement | null>(null);
const dimensions = ref({ width: 200 });
const displayValue = ref(props.value);

const percentage = computed(() => {
    const clamped = Math.max(props.min, Math.min(props.max, props.value));
    return ((clamped - props.min) / (props.max - props.min)) * 100;
});

// Safe thresholds helper - ensures it's always an array
const safeThresholds = computed(() => {
    if (Array.isArray(props.thresholds)) return props.thresholds;
    return [
        { value: 60, color: '#00ff9d' },
        { value: 80, color: '#ff8a00' },
        { value: 100, color: '#ff003c' }
    ];
});

const currentColor = computed(() => {
    const pct = percentage.value;
    const thresholds = safeThresholds.value;
    for (let i = 0; i < thresholds.length; i++) {
        if (pct <= thresholds[i].value) {
            return thresholds[i].color;
        }
    }
    return thresholds[thresholds.length - 1]?.color || '#00ff9d';
});

const isCritical = computed(() => {
    const pct = percentage.value;
    const thresholds = safeThresholds.value;
    return pct > (thresholds[thresholds.length - 2]?.value || 80);
});

const createBar = () => {
    if (!barRef.value) return;

    const svg = d3.select(barRef.value);
    svg.selectAll('*').remove();

    const width = dimensions.value.width;
    const height = props.height;
    const borderRadius = height / 2;

    // Defs
    const defs = svg.append('defs');

    // Gradient for fill
    const gradient = defs.append('linearGradient')
        .attr('id', 'bar-gradient')
        .attr('x1', '0%')
        .attr('y1', '0%')
        .attr('x2', '100%')
        .attr('y2', '0%');

    // Ensure thresholds is an array
    const thresholdsArray = Array.isArray(props.thresholds) ? props.thresholds : [
        { value: 60, color: '#00ff9d' },
        { value: 80, color: '#ff8a00' },
        { value: 100, color: '#ff003c' }
    ];
    
    thresholdsArray.forEach((t, i) => {
        gradient.append('stop')
            .attr('offset', `${t.value}%`)
            .attr('stop-color', t.color);
    });

    // Glow filter
    const filter = defs.append('filter')
        .attr('id', 'bar-glow')
        .attr('x', '-20%')
        .attr('y', '-50%')
        .attr('width', '140%')
        .attr('height', '200%');

    filter.append('feGaussianBlur')
        .attr('in', 'SourceGraphic')
        .attr('stdDeviation', 3)
        .attr('result', 'blur');

    filter.append('feColorMatrix')
        .attr('in', 'blur')
        .attr('type', 'matrix')
        .attr('values', '1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 12 -5');

    const feMerge = filter.append('feMerge');
    feMerge.append('feMergeNode').attr('in', 'blur');
    feMerge.append('feMergeNode').attr('in', 'SourceGraphic');

    // Background bar
    svg.append('rect')
        .attr('x', 0)
        .attr('y', 0)
        .attr('width', width)
        .attr('height', height)
        .attr('rx', borderRadius)
        .attr('fill', 'rgba(255, 255, 255, 0.05)');

    // Value bar
    svg.append('rect')
        .attr('class', 'bar-fill')
        .attr('x', 0)
        .attr('y', 0)
        .attr('width', 0)
        .attr('height', height)
        .attr('rx', borderRadius)
        .attr('fill', currentColor.value)
        .attr('filter', 'url(#bar-glow)');

    // Scale ticks
    if (props.showScale && props.variant !== 'compact') {
        const tickGroup = svg.append('g').attr('class', 'scale-ticks');
        const tickCount = 5;
        
        for (let i = 0; i <= tickCount; i++) {
            const x = (i / tickCount) * width;
            tickGroup.append('line')
                .attr('x1', x)
                .attr('y1', height + 4)
                .attr('x2', x)
                .attr('y2', height + 8)
                .attr('stroke', 'rgba(255, 255, 255, 0.2)')
                .attr('stroke-width', 1);
        }
    }

    updateBar();
};

const updateBar = () => {
    if (!barRef.value) return;

    const svg = d3.select(barRef.value);
    const width = dimensions.value.width;
    const targetWidth = (percentage.value / 100) * width;

    const barFill = svg.select('.bar-fill');

    if (props.animated) {
        barFill
            .transition()
            .duration(400)
            .ease(d3.easeCubicOut)
            .attr('width', targetWidth)
            .attr('fill', currentColor.value);

        // Animate display value
        const startVal = displayValue.value;
        const endVal = props.value;
        const duration = 400;
        const startTime = performance.now();

        const animate = (currentTime: number) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = d3.easeCubicOut(progress);
            displayValue.value = startVal + (endVal - startVal) * eased;

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        requestAnimationFrame(animate);
    } else {
        barFill
            .attr('width', targetWidth)
            .attr('fill', currentColor.value);
        displayValue.value = props.value;
    }
};

const throttledUpdate = throttle(updateBar, 50);

// Resize handling
useResizeObserver(containerRef, (entries) => {
    const entry = entries[0];
    if (entry) {
        dimensions.value = { width: entry.contentRect.width };
        createBar();
    }
});

watch(() => props.value, () => {
    throttledUpdate();
});

watch(() => [props.min, props.max, props.thresholds], () => {
    createBar();
}, { deep: true });

onMounted(() => {
    createBar();
});
</script>

<template>
    <div 
        ref="containerRef" 
        class="linear-bar-container"
        :class="[
            `variant-${variant}`,
            { 'is-critical': isCritical }
        ]"
    >
        <!-- Header -->
        <div class="bar-header">
            <span class="bar-label">{{ label }}</span>
            <span 
                class="bar-value" 
                :style="{ color: currentColor }"
            >
                {{ typeof displayValue === 'number' ? (displayValue % 1 === 0 ? Math.round(displayValue) : displayValue.toFixed(1)) : displayValue }}
                <span class="bar-unit">{{ unit }}</span>
            </span>
        </div>
        
        <!-- SVG Bar -->
        <svg 
            ref="barRef" 
            :width="dimensions.width" 
            :height="showScale && variant !== 'compact' ? height + 12 : height"
            class="linear-bar-svg"
        />
        
        <!-- Scale Labels (optional) -->
        <div v-if="showScale && variant !== 'compact'" class="bar-scale">
            <span>{{ min }}</span>
            <span>{{ max }}</span>
        </div>
    </div>
</template>

<style scoped>
.linear-bar-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: rgba(10, 10, 10, 0.6);
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(8px);
    transition: all 0.3s ease;
}

.linear-bar-container:hover {
    border-color: rgba(255, 255, 255, 0.1);
}

.linear-bar-container.is-critical {
    animation: critical-flash 1s ease-in-out infinite;
    border-color: rgba(255, 0, 60, 0.3);
}

@keyframes critical-flash {
    0%, 100% {
        background: rgba(10, 10, 10, 0.6);
    }
    50% {
        background: rgba(255, 0, 60, 0.1);
    }
}

.linear-bar-container.variant-compact {
    padding: 0.5rem 0.75rem;
    gap: 0.375rem;
}

.linear-bar-container.variant-thermometer {
    background: linear-gradient(135deg, rgba(10, 10, 10, 0.8), rgba(20, 15, 10, 0.6));
}

.bar-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.bar-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.5);
}

.bar-value {
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-size: 1.1rem;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
    transition: color 0.3s ease;
}

.bar-unit {
    font-size: 0.6rem;
    font-weight: 600;
    opacity: 0.6;
    margin-left: 0.15rem;
}

.linear-bar-svg {
    width: 100%;
}

.bar-scale {
    display: flex;
    justify-content: space-between;
    font-size: 0.55rem;
    color: rgba(255, 255, 255, 0.3);
    font-family: 'JetBrains Mono', monospace;
}

/* Compact variant adjustments */
.variant-compact .bar-label {
    font-size: 0.6rem;
}

.variant-compact .bar-value {
    font-size: 0.95rem;
}
</style>
