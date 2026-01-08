/**
 * Dashboard Components Index
 * 
 * Export all dynamic dashboard components for easy importing.
 */

// Core dynamic components
export { default as DynamicDashboard } from './DynamicDashboard.vue';
export { default as GroupCard } from './GroupCard.vue';
export { default as WidgetRenderer } from './WidgetRenderer.vue';

// Widget components
export { default as DigitalValueWidget } from './widgets/DigitalValueWidget.vue';
export { default as TextGridWidget } from './widgets/TextGridWidget.vue';
export { default as TireGridWidget } from './widgets/TireGridWidget.vue';
export { default as ShiftLightsBar } from './widgets/ShiftLightsBar.vue';

// Existing widgets (re-export for registry)
export { default as RadialGaugeD3 } from './RadialGaugeD3.vue';
export { default as LinearBarD3 } from './LinearBarD3.vue';
export { default as SpeedometerWidget } from './SpeedometerWidget.vue';
export { default as ConnectionStatusWidget } from './ConnectionStatusWidget.vue';
export { default as MapWidget } from './MapWidget.vue';
