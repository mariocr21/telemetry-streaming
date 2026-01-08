<script setup lang="ts">
/**
 * TelemetryWidget.vue - Base Widget Container
 * Neurona Off Road Telemetry - Cyberpunk Industrial Design
 * 
 * Features:
 * - Consistent card styling
 * - Configurable grid span
 * - Status indicator support
 * - Hover effects and animations
 */

import { computed } from 'vue';

type WidgetStatus = 'normal' | 'warning' | 'critical' | 'offline';

interface Props {
    title?: string;
    colSpan?: number | string;
    rowSpan?: number;
    status?: WidgetStatus;
    accent?: string;
    compact?: boolean;
    glow?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    colSpan: 3,
    rowSpan: 1,
    status: 'normal',
    accent: '#00ff9d',
    compact: false,
    glow: false
});

const statusColors: Record<WidgetStatus, string> = {
    normal: '#00ff9d',
    warning: '#ff8a00',
    critical: '#ff003c',
    offline: '#64748b'
};

const widgetStyle = computed(() => ({
    '--widget-span': typeof props.colSpan === 'number' ? props.colSpan : 'auto',
    '--widget-row-span': props.rowSpan,
    '--accent-color': props.accent,
    '--status-color': statusColors[props.status]
}));

const gridClass = computed(() => {
    if (typeof props.colSpan === 'string') {
        return `col-span-${props.colSpan}`;
    }
    return '';
});
</script>

<template>
    <div 
        class="telemetry-widget"
        :class="[
            gridClass,
            `status-${status}`,
            { 'is-compact': compact, 'has-glow': glow }
        ]"
        :style="widgetStyle"
    >
        <!-- Top accent line -->
        <div class="widget-accent" />
        
        <!-- Header -->
        <div v-if="title" class="widget-header">
            <div class="widget-title">
                <span class="status-dot" />
                {{ title }}
            </div>
            <slot name="header-right" />
        </div>
        
        <!-- Content -->
        <div class="widget-content">
            <slot />
        </div>
        
        <!-- Footer (optional) -->
        <div v-if="$slots.footer" class="widget-footer">
            <slot name="footer" />
        </div>
    </div>
</template>

<style scoped>
.telemetry-widget {
    grid-column: span var(--widget-span, 3);
    grid-row: span var(--widget-row-span, 1);
    
    position: relative;
    display: flex;
    flex-direction: column;
    
    background: rgba(10, 12, 15, 0.85);
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.06);
    
    backdrop-filter: blur(12px);
    overflow: hidden;
    
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.telemetry-widget:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.telemetry-widget.has-glow {
    box-shadow: 0 0 30px rgba(var(--accent-color), 0.15);
}

/* Status variations */
.telemetry-widget.status-warning {
    border-color: rgba(255, 138, 0, 0.3);
}

.telemetry-widget.status-critical {
    animation: critical-border 1s ease-in-out infinite;
    border-color: rgba(255, 0, 60, 0.5);
}

@keyframes critical-border {
    0%, 100% {
        box-shadow: 0 0 20px rgba(255, 0, 60, 0.2);
    }
    50% {
        box-shadow: 0 0 40px rgba(255, 0, 60, 0.4);
    }
}

.telemetry-widget.status-offline {
    opacity: 0.6;
}

/* Accent line */
.widget-accent {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--status-color), transparent);
}

/* Header */
.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem 0;
}

.widget-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.6);
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--status-color);
    box-shadow: 0 0 8px var(--status-color);
}

.telemetry-widget.status-critical .status-dot {
    animation: dot-pulse 0.6s ease-in-out infinite;
}

@keyframes dot-pulse {
    0%, 100% { 
        opacity: 1; 
        transform: scale(1);
    }
    50% { 
        opacity: 0.5; 
        transform: scale(1.2);
    }
}

/* Content */
.widget-content {
    flex: 1;
    padding: 0.75rem 1rem 1rem;
    display: flex;
    flex-direction: column;
}

.is-compact .widget-content {
    padding: 0.5rem 0.75rem 0.75rem;
}

/* Footer */
.widget-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.04);
    padding: 0.5rem 1rem;
    font-size: 0.6rem;
    color: rgba(255, 255, 255, 0.4);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .telemetry-widget {
        grid-column: span min(var(--widget-span, 3), 4);
    }
    
    .widget-header {
        padding: 0.5rem 0.75rem 0;
    }
    
    .widget-content {
        padding: 0.5rem 0.75rem 0.75rem;
    }
}

@media (max-width: 480px) {
    .telemetry-widget {
        grid-column: span 2;
    }
}
</style>
