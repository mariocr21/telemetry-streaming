/**
 * Dashboard Configuration Types
 * 
 * Types for the dynamic dashboard system that allows
 * per-vehicle widget configuration via database.
 */

// ─────────────────────────────────────────────────────────────
// API RESPONSE
// ─────────────────────────────────────────────────────────────

export interface DashboardApiResponse {
    success: boolean;
    data: DashboardConfig;
}

export interface DashboardConfig {
    vehicle_id: number;
    layout: DashboardLayout;
    groups: WidgetGroup[];
    special_components: SpecialComponents;
    meta: ConfigMeta;
}

// ─────────────────────────────────────────────────────────────
// LAYOUT
// ─────────────────────────────────────────────────────────────

export interface DashboardLayout {
    id: number | null;
    name: string;
    theme: string;
    grid_config: GridConfig;
}

export interface GridConfig {
    columns: number;
    gap: number;
    breakpoints?: Record<string, { columns: number }>;
}

// ─────────────────────────────────────────────────────────────
// GROUPS
// ─────────────────────────────────────────────────────────────

export interface WidgetGroup {
    id: number;
    name: string;
    slug: string;
    icon?: string;
    grid: GroupGrid;
    style?: GroupStyle;
    is_collapsible?: boolean;
    is_collapsed?: boolean;
    widgets: WidgetInstance[];
}

export interface GroupGrid {
    colStart: number;
    colSpan: number;
    rowStart?: number | null;
    rowSpan?: number;
}

export interface GroupStyle {
    bgColor?: string;
    borderColor?: string;
    variant?: 'default' | 'highlight' | 'minimal';
}

// ─────────────────────────────────────────────────────────────
// WIDGET INSTANCES
// ─────────────────────────────────────────────────────────────

export interface WidgetInstance {
    id: number;
    type: WidgetType;
    component: string;
    size: WidgetSize;
    props: Record<string, any>;
    style_override?: Record<string, string> | null;
    bindings: SensorBinding[];
}

export type WidgetType = 
    | 'radial_gauge'
    | 'linear_bar'
    | 'digital_value'
    | 'text_grid'
    | 'tire_grid'
    | 'speedometer'
    | 'connection_status'
    | 'shift_lights';

export type WidgetSize = 'sm' | 'md' | 'lg' | 'xl' | 'full';

// ─────────────────────────────────────────────────────────────
// SENSOR BINDINGS
// ─────────────────────────────────────────────────────────────

export interface SensorBinding {
    telemetry_key: string;
    target_prop: string;
    slot?: string | null;
    transform?: BindingTransform | null;
    label?: string;
    unit?: string;
    thresholds?: BindingThresholds | null;
}

export interface BindingTransform {
    multiply?: number;
    offset?: number;
    round?: number;
    clamp?: {
        min?: number;
        max?: number;
    };
}

export interface BindingThresholds {
    warning?: number;
    critical?: number;
}

// ─────────────────────────────────────────────────────────────
// SPECIAL COMPONENTS
// ─────────────────────────────────────────────────────────────

export interface SpecialComponents {
    map?: MapComponentConfig;
    shift_lights?: ShiftLightsConfig;
}

export interface MapComponentConfig {
    enabled: boolean;
    grid?: GroupGrid;
    bindings?: {
        latitude: string;
        longitude: string;
        heading?: string;
        speed?: string;
    };
}

export interface ShiftLightsConfig {
    enabled: boolean;
    position?: 'top' | 'bottom';
    bindings?: {
        rpm: string;
    };
    config?: {
        totalLights: number;
        startRpm: number;
        maxRpm: number;
        shiftRpm: number;
    };
}

// ─────────────────────────────────────────────────────────────
// METADATA
// ─────────────────────────────────────────────────────────────

export interface ConfigMeta {
    generated_at: string;
    cache_ttl: number;
    version: string;
    is_empty?: boolean;
}

// ─────────────────────────────────────────────────────────────
// TELEMETRY DATA
// ─────────────────────────────────────────────────────────────

export interface TelemetryData {
    [key: string]: number | string | null | undefined;
}

export interface TelemetryEvent {
    vehicle_id: number;
    device_id: string;
    timestamp: string;
    data: TelemetryData;
    dtc_codes?: string[];
    has_dtc?: boolean;
}

// ─────────────────────────────────────────────────────────────
// CONNECTION STATUS
// ─────────────────────────────────────────────────────────────

export type ConnectionStatus = 'connecting' | 'connected' | 'disconnected' | 'error';

// ─────────────────────────────────────────────────────────────
// WIDGET DEFINITIONS (for admin/configurator)
// ─────────────────────────────────────────────────────────────

export interface WidgetDefinition {
    id: number;
    type: WidgetType;
    name: string;
    icon?: string;
    category: 'visualization' | 'text' | 'special';
    description?: string;
    supports_thresholds: boolean;
    supports_multiple_slots: boolean;
}
