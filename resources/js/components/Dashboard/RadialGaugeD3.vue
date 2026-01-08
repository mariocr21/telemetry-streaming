<script setup lang="ts">
/**
 * RadialGaugeD3.vue - NEURONA PREMIUM EDITION
 * 
 * Features:
 * - Segmented Arc Design (Neurona Style)
 * - Zone-based coloring (Cold -> Optimal -> Hot)
 * - Premium Typography & Glow Effects
 * - Responsive sizing
 */

import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import * as d3 from 'd3';
import { useResizeObserver } from '@vueuse/core';
import { throttle } from 'lodash';

// Props Definition
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
    thresholds?: Threshold[];
    startAngle?: number;
    endAngle?: number;
    animated?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    min: 0,
    max: 10000,
    label: 'RPM',
    unit: '',
    thresholds: () => [
        { value: 6500, color: '#00E5A0' }, // Normal range (up to this)
        { value: 8500, color: '#FFB800' }, // Warning
        { value: 10000, color: '#FF3366' } // Redline
    ],
    startAngle: -140, // Wider angle for racing look
    endAngle: 140,
    animated: true,
});

// Refs
const containerRef = ref<HTMLDivElement | null>(null);
const svgRef = ref<SVGSVGElement | null>(null);
const dimensions = ref({ width: 200, height: 200 });
const displayValue = ref(props.value);
let animationFrame: number;

// Constants
const SEGMENT_GAP = 0.02; // Radians between segments
const TOTAL_SEGMENTS = 40; // Total blocks in the arc

// Computed
const normalizedValue = computed(() => {
    return Math.max(props.min, Math.min(props.max, props.value));
});

const percent = computed(() => {
    return (normalizedValue.value - props.min) / (props.max - props.min);
});

// Determine current zone color
const currentColor = computed(() => {
    const val = normalizedValue.value;
    // Iterate thresholds to find which zone we are in
    let color = 'var(--neurona-primary)';
    
    // Sort thresholds just in case
    const sorted = [...props.thresholds].sort((a,b) => a.value - b.value);
    
    for (const t of sorted) {
        if (val <= t.value) {
            return t.color; // Found the zone
        }
    }
    // If above all, use last color
    return sorted[sorted.length - 1]?.color || color;
});

const isCritical = computed(() => {
    // Critical if in the last 10% of range or above highest threshold
    const sorted = [...props.thresholds].sort((a,b) => a.value - b.value);
    const criticalThreshold = sorted[sorted.length - 1]?.value || props.max * 0.9;
    return normalizedValue.value >= criticalThreshold;
});

// D3 Logic
const degToRad = (deg: number) => (deg * Math.PI) / 180;

const createGauge = () => {
    if (!svgRef.value) return;

    const svg = d3.select(svgRef.value);
    svg.selectAll('*').remove();

    const { width, height } = dimensions.value;
    const radius = Math.min(width, height) / 2 - 10;
    const innerRadius = radius - 15; // Width of the arc bar
    const centerX = width / 2;
    const centerY = height / 2;

    const g = svg.append('g')
        .attr('transform', `translate(${centerX}, ${centerY})`);

    // 1. Create Defs (Gradients/Filters)
    const defs = svg.append('defs');
    
    // Glow Filter
    const filter = defs.append('filter')
        .attr('id', 'neurona-glow')
        .attr('x', '-50%').attr('y', '-50%')
        .attr('width', '200%').attr('height', '200%');
    
    filter.append('feGaussianBlur')
        .attr('stdDeviation', '2.5')
        .attr('result', 'coloredBlur');
        
    const feMerge = filter.append('feMerge');
    feMerge.append('feMergeNode').attr('in', 'coloredBlur');
    feMerge.append('feMergeNode').attr('in', 'SourceGraphic');

    // 2. Draw Background Tracks (Segments)
    // We draw discrete segments for the entire range
    const totalAngle = degToRad(props.endAngle - props.startAngle);
    const startRad = degToRad(props.startAngle);
    
    // Calculate segment size
    const segmentAngle = totalAngle / TOTAL_SEGMENTS;
    const padAngle = 0.02; // Gap between segments

    const arcGen = d3.arc()
        .innerRadius(innerRadius)
        .outerRadius(radius)
        .cornerRadius(2);

    // Track segments (dimmed)
    for (let i = 0; i < TOTAL_SEGMENTS; i++) {
        const segStart = startRad + (i * segmentAngle);
        const segEnd = segStart + segmentAngle - padAngle;
        
        g.append('path')
            .attr('d', arcGen({
                startAngle: segStart,
                endAngle: segEnd
            } as any))
            .attr('fill', 'rgba(255,255,255,0.08)')
            .attr('class', 'track-segment');
    }
    
    // 3. Draw Active Segments Group
    g.append('g').attr('class', 'active-segments');

    // 4. Draw Ticks/Labels
    const majorTicks = 5;
    const labelRadius = innerRadius - 20;
    
    for (let i = 0; i <= majorTicks; i++) {
        const t = i / majorTicks; // 0 to 1
        const angle = startRad + t * totalAngle;
        const val = Math.round(props.min + t * (props.max - props.min));
        
        // Convert polar to cartesian
        const lx = Math.cos(angle - Math.PI/2) * labelRadius;
        const ly = Math.sin(angle - Math.PI/2) * labelRadius;
        
        g.append('text')
            .attr('x', lx)
            .attr('y', ly)
            .attr('text-anchor', 'middle')
            .attr('dominant-baseline', 'middle')
            .attr('fill', 'rgba(255,255,255,0.4)')
            .attr('font-size', '10px')
            .attr('font-family', 'JetBrains Mono')
            .text(val >= 1000 ? (val/1000).toFixed(0) + 'k' : val);
    }
    
    updateGauge();
};

const updateGauge = () => {
    if (!svgRef.value) return;
    const svg = d3.select(svgRef.value);
    const activeGroup = svg.select('.active-segments');
    
    // Calculate how many segments are active
    const activeCount = Math.ceil(percent.value * TOTAL_SEGMENTS);
    
    const { width, height } = dimensions.value;
    const radius = Math.min(width, height) / 2 - 10;
    const innerRadius = radius - 15;
    const totalAngle = degToRad(props.endAngle - props.startAngle);
    const startRad = degToRad(props.startAngle);
    const segmentAngle = totalAngle / TOTAL_SEGMENTS;
    const padAngle = 0.02;

    const arcGen = d3.arc()
        .innerRadius(innerRadius)
        .outerRadius(radius)
        .cornerRadius(2);

    // Rebuild active segments
    // We do a full rebuild for simplicity and cleaner binding given the segmented nature
    // Performance is fine for 40 segments
    
    const segmentsData = Array.from({ length: activeCount }, (_, i) => i);
    
    const segments = activeGroup.selectAll('path')
        .data(segmentsData);
        
    // Enter
    segments.enter()
        .append('path')
        .merge(segments as any)
        .attr('d', (i: any) => {
            const segStart = startRad + (i * segmentAngle);
            const segEnd = segStart + segmentAngle - padAngle;
            return arcGen({ startAngle: segStart, endAngle: segEnd } as any);
        })
        .attr('fill', currentColor.value)
        .attr('filter', 'url(#neurona-glow)')
        .attr('opacity', (i: any) => {
             // Fade opacity slightly for lower segments? No, solid looks better for racing.
             return 1;
        });
        
    // Exit
    segments.exit().remove();
    
    // Animate Text Value
    const startVal = displayValue.value;
    const endVal = props.value;
    
    if (props.animated && Math.abs(endVal - startVal) > 1) {
        // Simple lerp animation for the number
        const animate = () => {
            const diff = endVal - displayValue.value;
            if (Math.abs(diff) < 0.5) {
                displayValue.value = endVal;
            } else {
                displayValue.value += diff * 0.2;
                animationFrame = requestAnimationFrame(animate);
            }
        };
        cancelAnimationFrame(animationFrame);
        animationFrame = requestAnimationFrame(animate);
    } else {
        displayValue.value = endVal;
    }
};

const throttledUpdate = throttle(updateGauge, 50);

// Resize handling
useResizeObserver(containerRef, (entries) => {
    const entry = entries[0];
    if (entry) {
        dimensions.value = {
            width: entry.contentRect.width,
            height: entry.contentRect.height
        };
        createGauge();
    }
});

// Watchers
watch(() => props.value, () => throttledUpdate());
watch(() => [props.min, props.max, props.thresholds], () => createGauge(), { deep: true });

onMounted(() => {
    createGauge();
});

onUnmounted(() => {
    cancelAnimationFrame(animationFrame);
});
</script>

<template>
    <div 
        ref="containerRef" 
        class="neurona-gauge-container"
        :class="{ 'is-critical': isCritical }"
    >
        <svg 
            ref="svgRef" 
            :width="dimensions.width" 
            :height="dimensions.height"
            class="gauge-svg"
        />
        
        <!-- Center Value -->
        <div class="gauge-center">
            <div 
                class="value-display"
                :style="{ color: currentColor, textShadow: `0 0 20px ${currentColor}44` }"
            >
                {{ Math.round(displayValue) }}
            </div>
            <div class="unit-display">{{ unit }}</div>
            <div class="label-display">{{ label }}</div>
        </div>
    </div>
</template>

<style scoped>
.neurona-gauge-container {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 180px; /* Minimal usable height */
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--neurona-bg-card);
    border-radius: var(--radius-xl);
    border: 1px solid var(--border-subtle);
    overflow: hidden;
    /* Glassmorphism subtle */
    backdrop-filter: blur(10px);
}

/* Background grid effect */
.neurona-gauge-container::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: 
        radial-gradient(circle at center, transparent 0%, var(--neurona-bg-deep) 70%),
        linear-gradient(0deg, transparent 96%, rgba(255,255,255,0.03) 96%, rgba(255,255,255,0.03) 97%, transparent 97%),
        linear-gradient(90deg, transparent 96%, rgba(255,255,255,0.03) 96%, rgba(255,255,255,0.03) 97%, transparent 97%);
    background-size: 100% 100%, 40px 40px, 40px 40px;
    opacity: 0.5;
    pointer-events: none;
    z-index: 0;
}

.gauge-svg {
    position: absolute;
    z-index: 10;
}

.gauge-center {
    position: relative;
    z-index: 20;
    display: flex;
    flex-direction: column;
    align-items: center;
    transform: translateY(10px); /* Adjust for half-circle or wide arc aesthetics */
}

.value-display {
    font-family: 'JetBrains Mono', monospace;
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 900;
    line-height: 0.9;
    letter-spacing: -2px;
    transition: color 0.3s ease;
}

.unit-display {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-top: 4px;
}

.label-display {
    margin-top: 8px;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 2px;
    color: var(--text-secondary);
    text-transform: uppercase;
    background: rgba(255,255,255,0.05);
    padding: 2px 8px;
    border-radius: 4px;
}

/* Critical Animation */
.is-critical {
    animation: critical-border-pulse 1s infinite alternate;
}

.is-critical .value-display {
    animation: critical-text-shake 0.1s infinite;
}

@keyframes critical-border-pulse {
    from { border-color: var(--zone-critical); box-shadow: 0 0 10px var(--zone-critical); }
    to { border-color: transparent; box-shadow: 0 0 30px var(--zone-critical); }
}

@keyframes critical-text-shake {
    0% { transform: translate(1px, 1px); }
    50% { transform: translate(-1px, -1px); }
    100% { transform: translate(1px, -1px); }
}
</style>
