<script setup lang="ts">
/**
 * SpeedometerWidget.vue - NEURONA DESIGN SYSTEM
 * 
 * SVG-based speedometer gauge. Compatible with WidgetRenderer.
 */
import { computed, ref, watch, onMounted } from 'vue'

// Props compatible with WidgetRenderer
interface Props {
    value?: number;
    min?: number;
    max?: number;
    label?: string;
    unit?: string;
    [key: string]: any; // Accept any additional props
}

const props = withDefaults(defineProps<Props>(), {
    value: 0,
    min: 0,
    max: 200,
    label: 'Speed',
    unit: 'km/h',
});

// Animated state
const currentSpeed = ref(0);

// Constants for the speedometer
const centerX = 100;
const centerY = 90;
const startAngle = -140;
const endAngle = 140;
const outerRadius = 85;
const innerRadius = 70;
const textRadius = 60;
const needleLength = 65;

// Convert speed to angle
const speedToAngle = (speed: number): number => {
    const min = props.min ?? 0;
    const max = props.max ?? 200;
    const percentage = (speed - min) / (max - min);
    return startAngle + percentage * (endAngle - startAngle);
};

// Convert angle to radians
const angleToRadians = (angle: number): number => {
    return angle * Math.PI / 180;
};

// Calculate needle position
const needleX = computed(() => {
    const angle = speedToAngle(currentSpeed.value);
    const radians = angleToRadians(angle);
    return centerX + needleLength * Math.cos(radians);
});

const needleY = computed(() => {
    const angle = speedToAngle(currentSpeed.value);
    const radians = angleToRadians(angle);
    return centerY + needleLength * Math.sin(radians);
});

// Calculate progress arc
const arcPath = computed(() => {
    const angle = speedToAngle(currentSpeed.value);
    const radius = 78;
    
    const startX = centerX + radius * Math.cos(angleToRadians(startAngle));
    const startY = centerY + radius * Math.sin(angleToRadians(startAngle));
    const endX = centerX + radius * Math.cos(angleToRadians(angle));
    const endY = centerY + radius * Math.sin(angleToRadians(angle));
    
    const largeArcFlag = Math.abs(angle - startAngle) <= 180 ? "0" : "1";
    
    return `M ${startX} ${startY} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`;
});

// Tick mark type
interface TickMark {
    value: number;
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    textX: number;
    textY: number;
    type: 'major' | 'minor';
}

// Generate tick marks
const ticks = computed<TickMark[]>(() => {
    const tickMarks: TickMark[] = [];
    const min = props.min ?? 0;
    const max = props.max ?? 200;
    const range = max - min;
    const majorStep = range / 10; // 10 major divisions
    
    for (let speed = min; speed <= max; speed += majorStep) {
        const angle = speedToAngle(speed);
        const radians = angleToRadians(angle);
        
        tickMarks.push({
            value: Math.round(speed),
            x1: centerX + innerRadius * Math.cos(radians),
            y1: centerY + innerRadius * Math.sin(radians),
            x2: centerX + outerRadius * Math.cos(radians),
            y2: centerY + outerRadius * Math.sin(radians),
            textX: centerX + textRadius * Math.cos(radians),
            textY: centerY + textRadius * Math.sin(radians),
            type: 'major'
        });
        
        // Minor tick in between
        const minorSpeed = speed + majorStep / 2;
        if (minorSpeed < max) {
            const minorAngle = speedToAngle(minorSpeed);
            const minorRadians = angleToRadians(minorAngle);
            
            tickMarks.push({
                value: Math.round(minorSpeed),
                x1: centerX + (innerRadius + 5) * Math.cos(minorRadians),
                y1: centerY + (innerRadius + 5) * Math.sin(minorRadians),
                x2: centerX + outerRadius * Math.cos(minorRadians),
                y2: centerY + outerRadius * Math.sin(minorRadians),
                textX: centerX + textRadius * Math.cos(minorRadians),
                textY: centerY + textRadius * Math.sin(minorRadians),
                type: 'minor'
            });
        }
    }
    
    return tickMarks;
});

// Get color based on value percentage
const speedColor = computed(() => {
    const min = props.min ?? 0;
    const max = props.max ?? 200;
    const percentage = (currentSpeed.value - min) / (max - min) * 100;
    
    if (percentage < 30) return '#06b6d4'; // cyan
    if (percentage < 60) return '#10b981'; // green
    if (percentage < 80) return '#f59e0b'; // yellow/amber
    return '#ef4444'; // red
});

// Smooth animation for value changes
const animateValue = (targetValue: number, duration = 600) => {
    const startValue = currentSpeed.value;
    const startTime = Date.now();
    
    const animate = () => {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Ease-out cubic
        const easeOut = 1 - Math.pow(1 - progress, 3);
        
        currentSpeed.value = startValue + (targetValue - startValue) * easeOut;
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    };
    
    requestAnimationFrame(animate);
};

// Watch for value changes
watch(() => props.value, (newValue) => {
    const targetValue = typeof newValue === 'number' ? newValue : 0;
    animateValue(Math.min(Math.max(targetValue, props.min ?? 0), props.max ?? 200));
}, { immediate: true });

// Initialize
onMounted(() => {
    currentSpeed.value = props.value ?? 0;
});

// Display unit
const displayUnit = computed(() => props.unit || 'km/h');
</script>

<template>
    <div class="w-full h-full min-h-[120px]">
        <svg viewBox="0 0 200 160" class="w-full h-full">
            <!-- Background circle -->
            <circle 
                cx="100" 
                cy="90" 
                r="85" 
                class="fill-slate-800/80 stroke-slate-700/50 stroke-1"
            />
            
            <!-- Progress arc -->
            <path
                :d="arcPath"
                :stroke="speedColor"
                class="fill-none stroke-[8] stroke-linecap-round transition-colors duration-300"
                style="filter: drop-shadow(0 0 2px currentColor)"
            />
            
            <!-- Tick marks -->
            <g v-for="tick in ticks" :key="`tick-${tick.value}-${tick.type}`">
                <!-- Tick lines -->
                <line
                    :x1="tick.x1" 
                    :y1="tick.y1"
                    :x2="tick.x2" 
                    :y2="tick.y2"
                    :stroke="tick.type === 'major' ? '#e2e8f0' : '#64748b'"
                    :stroke-width="tick.type === 'major' ? 2 : 1"
                />
                
                <!-- Numbers on major ticks -->
                <text
                    v-if="tick.type === 'major'"
                    :x="tick.textX" 
                    :y="tick.textY"
                    text-anchor="middle" 
                    dominant-baseline="middle"
                    class="text-xs fill-slate-300 font-bold"
                >
                    {{ tick.value }}
                </text>
            </g>
            
            <!-- Needle -->
            <g class="transition-transform duration-300">
                <line
                    x1="100" 
                    y1="90"
                    :x2="needleX" 
                    :y2="needleY"
                    :stroke="speedColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    class="transition-colors duration-300"
                    style="filter: drop-shadow(0 0 3px currentColor)"
                />
                <circle 
                    cx="100" 
                    cy="90" 
                    r="4" 
                    :fill="speedColor" 
                    class="transition-colors duration-300"
                />
            </g>
            
            <!-- Center dot -->
            <circle cx="100" cy="90" r="2" fill="#1e293b"/>
            
            <!-- Digital value -->
            <text 
                x="100" 
                y="120" 
                text-anchor="middle" 
                class="fill-white text-2xl font-bold"
            >
                {{ Math.round(currentSpeed) }}
            </text>
            
            <!-- Unit -->
            <text 
                x="100" 
                y="135" 
                text-anchor="middle" 
                class="fill-slate-400 text-sm"
            >
                {{ displayUnit }}
            </text>
        </svg>
    </div>
</template>
