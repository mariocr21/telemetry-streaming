<script setup lang="ts">
/**
 * WidgetRenderer.vue
 * 
 * Core "Component Factory" that dynamically resolves and renders
 * the appropriate widget component based on widget type.
 * 
 * This is the key component that enables database-driven widget rendering.
 */
import { computed, inject, defineAsyncComponent, type Ref, type Component } from 'vue';
import { applyTransform } from '@/composables/useTelemetryBinding';
import type { WidgetInstance, TelemetryData, SensorBinding } from '@/types/dashboard.d';

// Props
interface Props {
    widget: WidgetInstance;
    telemetryData?: TelemetryData;
}

const props = defineProps<Props>();

// Inject telemetry if not passed directly
const injectedTelemetry = inject<Ref<TelemetryData>>('telemetryData');
const telemetry = computed(() => props.telemetryData || injectedTelemetry?.value || {});

// ─────────────────────────────────────────────────────────────
// COMPONENT REGISTRY
// ─────────────────────────────────────────────────────────────

/**
 * Map of widget types to their Vue components.
 * Using defineAsyncComponent for code splitting.
 */
const componentRegistry: Record<string, Component> = {
    // Core gauges
    'RadialGaugeD3': defineAsyncComponent(() => 
        import('@/components/Dashboard/RadialGaugeD3.vue')
    ),
    'LinearBarD3': defineAsyncComponent(() => 
        import('@/components/Dashboard/LinearBarD3.vue')
    ),
    'SpeedometerWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/SpeedometerWidget.vue')
    ),
    
    // Digital displays
    'DigitalValueWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/DigitalValueWidget.vue')
    ),
    'TextGridWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/TextGridWidget.vue')
    ),
    
    // Temperature widgets
    'TemperatureCardWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/TemperatureCardWidget.vue')
    ),
    'ThermometerWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/ThermometerWidget.vue')
    ),
    'TemperatureGaugeWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/TemperatureGaugeWidget.vue')
    ),
    
    // Fuel widgets
    'FuelGaugeWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/FuelGaugeWidget.vue')
    ),
    
    // Battery/Electrical widgets
    'BatteryVoltageWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/BatteryVoltageWidget.vue')
    ),
    
    // Pressure widgets
    'PressureBarWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/PressureBarWidget.vue')
    ),
    
    // GPS widgets
    'GPSInfoWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/GPSInfoWidget.vue')
    ),
    
    // Grid/multi-value widgets
    'TireGridWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/TireGridWidget.vue')
    ),
    
    // Special widgets
    'ConnectionStatusWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/ConnectionStatusWidget.vue')
    ),
    'ShiftLightsBar': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/ShiftLightsBar.vue')
    ),
    
    // Transmission/Gear
    'TransmissionGearWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/TransmissionGearWidget.vue')
    ),
    'GearScaleWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/GearScaleWidget.vue')
    ),
    
    // Video Streaming
    'VideoStreamWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/widgets/VideoStreamWidget.vue')
    ),
    
    // Legacy compatibility
    'TachometerWidget': defineAsyncComponent(() => 
        import('@/components/Dashboard/TachometerWidget.vue')
    ),
};

// ─────────────────────────────────────────────────────────────
// COMPUTED PROPERTIES
// ─────────────────────────────────────────────────────────────

/**
 * Resolve the Vue component to render based on widget.component name
 */
const resolvedComponent = computed<Component | null>(() => {
    const componentName = props.widget.component;
    
    if (!componentName) {
        console.warn(`Widget ${props.widget.id} has no component specified`);
        return null;
    }

    const component = componentRegistry[componentName];
    
    if (!component) {
        console.warn(`Component "${componentName}" not found in registry for widget ${props.widget.id}`);
        return null;
    }

    return component;
});

/**
 * Process bindings and get the current values from telemetry.
 * For single-value widgets, returns the main value.
 * For multi-slot widgets, returns an object with slot values.
 */
const boundValues = computed(() => {
    const values: Record<string, any> = {};
    
    if (!props.widget.bindings || props.widget.bindings.length === 0) {
        return values;
    }

    for (const binding of props.widget.bindings) {
        const rawValue = telemetry.value[binding.telemetry_key];
        const transformedValue = applyTransform(
            typeof rawValue === 'number' ? rawValue : null,
            binding.transform
        );

        if (binding.slot) {
            // Multi-slot widget (e.g., tire grid, text grid)
            values[binding.slot] = {
                value: transformedValue,
                label: binding.label,
                unit: binding.unit,
                thresholds: binding.thresholds,
            };
        } else {
            // Single value widget - use target_prop
            values[binding.target_prop] = transformedValue;
            
            // Also set metadata
            if (binding.label) values['label'] = binding.label;
            if (binding.unit) values['unit'] = binding.unit;
            if (binding.thresholds) values['thresholds'] = binding.thresholds;
        }
    }

    return values;
});

/**
 * Primary value for single-value widgets
 */
const primaryValue = computed(() => {
    return boundValues.value['value'] ?? 0;
});

/**
 * Merge widget props with bound values to pass to the component.
 * Priority: bindings > widget.props > defaults
 */
const mergedProps = computed(() => {
    const widgetProps = props.widget.props || {};
    const bound = boundValues.value;
    
    // Extract binding metadata (label, unit, thresholds)
    // These should override widget props when a sensor is bound
    const bindingMetadata: Record<string, any> = {};
    if (bound.label !== undefined) bindingMetadata.label = bound.label;
    if (bound.unit !== undefined) bindingMetadata.unit = bound.unit;
    if (bound.thresholds !== undefined) bindingMetadata.thresholds = bound.thresholds;
    
    return {
        // First: widget default props
        ...widgetProps,
        // Second: binding metadata (label/unit from sensor) - overrides defaults
        ...bindingMetadata,
        // Third: telemetry value
        value: primaryValue.value,
        // Slot values for multi-slot widgets
        slotValues: hasSlots.value ? bound : undefined,
        // Pass all slot data for TextGrid/TireGrid
        slots: hasSlots.value ? bound : undefined,
        // Last: style overrides
        ...(props.widget.style_override || {}),
    };
});

/**
 * Check if this widget uses slots
 */
const hasSlots = computed(() => {
    return props.widget.bindings?.some(b => b.slot) ?? false;
});

/**
 * Size class for the widget container
 * Widgets should fill their container - the GroupCard controls the actual size
 */
const sizeClass = computed(() => {
    // bento-grid logic: always fill
    return 'w-full h-full min-h-[100px]'; 
});
</script>

<template>
    <div 
        class="widget-wrapper flex items-center justify-center w-full h-full"
    >
        <!-- Fallback for missing component -->
        <div 
            v-if="!resolvedComponent"
            class="flex flex-col items-center justify-center p-4 bg-red-900/20 rounded-lg border border-red-500/30 text-center w-full h-full"
        >
            <span class="text-red-400 text-sm font-medium">⚠️ Widget Error</span>
            <span class="text-red-300 text-xs mt-1">{{ widget.component }} not found</span>
        </div>
        
        <!-- Dynamic Component Rendering -->
        <component
            v-else
            :is="resolvedComponent"
            v-bind="mergedProps"
            class="w-full h-full"
        />
    </div>
</template>

<style scoped>
.widget-wrapper {
    transition: all 0.2s ease;
}

.widget-wrapper:hover {
    transform: scale(1.01);
}
</style>
