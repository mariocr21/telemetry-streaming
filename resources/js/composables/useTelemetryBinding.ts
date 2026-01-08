/**
 * useTelemetryBinding Composable
 * 
 * Manages real-time WebSocket connection for vehicle telemetry data.
 * Subscribes to a private vehicle channel and provides reactive telemetry data.
 */
import { ref, readonly, onUnmounted, computed, type Ref } from 'vue';
import type { TelemetryData, TelemetryEvent, ConnectionStatus } from '@/types/dashboard.d';

// Extend Window interface for Laravel Echo
declare global {
    interface Window {
        Echo: any;
    }
}

interface UseTelemetryBindingOptions {
    /** Auto-subscribe on creation */
    autoSubscribe?: boolean;
    /** Merge new data with existing (preserve old values) */
    mergeData?: boolean;
    /** Callback when new data arrives */
    onData?: (data: TelemetryData) => void;
    /** Enable demo mode with simulated data (for testing widgets) */
    demoMode?: boolean;
}

interface UseTelemetryBindingReturn {
    /** Reactive telemetry data object */
    telemetryData: Readonly<Ref<TelemetryData>>;
    /** Current connection status */
    connectionStatus: Readonly<Ref<ConnectionStatus>>;
    /** Whether connected to WebSocket */
    isConnected: Readonly<Ref<boolean>>;
    /** Last update timestamp */
    lastUpdate: Readonly<Ref<Date | null>>;
    /** Subscribe to the vehicle channel */
    subscribe: () => void;
    /** Unsubscribe from the channel */
    unsubscribe: () => void;
    /** Get a specific value with fallback */
    getValue: <T = number>(key: string, fallback?: T) => T;
    /** Reset all telemetry data */
    reset: () => void;
    /** Toggle demo mode */
    setDemoMode: (enabled: boolean) => void;
    /** Whether in demo mode */
    isDemoMode: Readonly<Ref<boolean>>;
}

/**
 * Demo data generator - simulates realistic telemetry values
 */
function generateDemoData(): TelemetryData {
    const time = Date.now() / 1000;

    // Simulate RPM with oscillation (idle to high RPM)
    const rpm = 800 + Math.sin(time * 0.5) * 3000 + Math.random() * 500;

    // Simulate speed based on RPM
    const speed = Math.max(0, (rpm - 800) / 100 + Math.random() * 5);

    // Simulate temperatures with gradual warming
    const baseTemp = 180;
    const tempVariation = Math.sin(time * 0.1) * 30;

    return {
        // Performance
        RPM: Math.round(rpm),
        Vehicle_Speed: Math.round(speed),
        Throttle_Position: Math.round(30 + Math.sin(time * 0.3) * 30 + Math.random() * 10),

        // Temperatures (°F) - varied to show different zones
        Coolant_Temp: Math.round(baseTemp + tempVariation + Math.random() * 10),
        Oil_Temperature: Math.round(baseTemp + 20 + tempVariation + Math.random() * 15),
        Transmission_Temp: Math.round(baseTemp - 10 + tempVariation * 0.5),
        Intake_Air_Temp: Math.round(95 + Math.sin(time * 0.2) * 20),

        // Pressures
        Fuel_Pressure: Math.round(45 + Math.sin(time * 0.4) * 10 + Math.random() * 5),
        Oil_Pressure: Math.round(40 + Math.sin(time * 0.3) * 15),

        // Electrical
        Battery_Voltage: Number((12.5 + Math.sin(time * 0.1) * 1.5).toFixed(1)),
        Alternator_Current: Math.round(30 + Math.sin(time * 0.2) * 20),

        // Gear
        Current_Gear: Math.floor(Math.abs(Math.sin(time * 0.1)) * 6) + 1,

        // GPS (static for demo)
        GPS_Latitude: 32.7157,
        GPS_Longitude: -117.1611,
        GPS_Speed: Math.round(speed * 1.6), // km/h
        GPS_Heading: Math.round((time * 10) % 360),

        // Demo timestamp
        _demo_timestamp: Date.now(),
    };
}

export function useTelemetryBinding(
    vehicleId: number | Ref<number>,
    options: UseTelemetryBindingOptions = {}
): UseTelemetryBindingReturn {
    const {
        autoSubscribe = true,
        mergeData = true,
        onData,
        demoMode: initialDemoMode = false
    } = options;

    // State
    const telemetryData = ref<TelemetryData>({});
    const connectionStatus = ref<ConnectionStatus>('disconnected');
    const lastUpdate = ref<Date | null>(null);
    const isDemoMode = ref(initialDemoMode);

    // Internal
    let channel: any = null;
    let demoInterval: ReturnType<typeof setInterval> | null = null;

    // Computed
    const isConnected = computed(() => connectionStatus.value === 'connected' || isDemoMode.value);

    // Get vehicle ID
    const getVehicleId = (): number => {
        return typeof vehicleId === 'number' ? vehicleId : vehicleId.value;
    };

    /**
     * Start demo mode - generates simulated telemetry data
     */
    function startDemoMode(): void {
        if (demoInterval) return;

        console.log('[Telemetry] 🎮 Demo mode started');
        connectionStatus.value = 'connected';

        // Generate initial data immediately
        telemetryData.value = generateDemoData();
        lastUpdate.value = new Date();

        // Update every 100ms for smooth animations
        demoInterval = setInterval(() => {
            telemetryData.value = generateDemoData();
            lastUpdate.value = new Date();
            if (onData) {
                onData(telemetryData.value);
            }
        }, 100);
    }

    /**
     * Stop demo mode
     */
    function stopDemoMode(): void {
        if (demoInterval) {
            clearInterval(demoInterval);
            demoInterval = null;
            console.log('[Telemetry] 🎮 Demo mode stopped');
        }
    }

    /**
     * Toggle demo mode on/off
     */
    function setDemoMode(enabled: boolean): void {
        isDemoMode.value = enabled;
        if (enabled) {
            unsubscribe(); // Stop real connection
            startDemoMode();
        } else {
            stopDemoMode();
            reset();
            if (autoSubscribe) {
                subscribe();
            }
        }
    }

    /**
     * Subscribe to the vehicle telemetry channel
     */
    function subscribe(): void {
        // If in demo mode, don't try real connection
        if (isDemoMode.value) {
            startDemoMode();
            return;
        }

        const id = getVehicleId();

        if (!id) {
            console.error('[Telemetry] Vehicle ID is required');
            connectionStatus.value = 'error';
            return;
        }

        if (!window.Echo) {
            console.warn('[Telemetry] Laravel Echo is not configured - switching to demo mode');
            setDemoMode(true);
            return;
        }

        // Don't re-subscribe if already connected
        if (channel && connectionStatus.value === 'connected') {
            console.log('[Telemetry] Already connected');
            return;
        }

        connectionStatus.value = 'connecting';
        console.log(`[Telemetry] Subscribing to vehicle.${id}...`);

        try {
            // Subscribe to private vehicle channel
            channel = window.Echo.private(`vehicle.${id}`)
                .listen('.telemetry.updated', (event: TelemetryEvent) => {
                    handleTelemetryEvent(event);
                })
                .subscribed(() => {
                    connectionStatus.value = 'connected';
                    console.log(`[Telemetry] ✓ Connected to vehicle.${id}`);
                })
                .error((error: any) => {
                    console.error('[Telemetry] Channel error:', error);
                    connectionStatus.value = 'error';
                });
        } catch (err) {
            console.error('[Telemetry] Subscribe error:', err);
            connectionStatus.value = 'error';
        }
    }

    /**
     * Handle incoming telemetry event
     */
    function handleTelemetryEvent(event: TelemetryEvent): void {
        if (!event.data) return;

        // Update data
        if (mergeData) {
            // Merge with existing data (preserve old values not in new packet)
            telemetryData.value = {
                ...telemetryData.value,
                ...event.data,
            };
        } else {
            // Replace entirely
            telemetryData.value = event.data;
        }

        lastUpdate.value = new Date();

        // Callback
        if (onData) {
            onData(event.data);
        }
    }

    /**
     * Unsubscribe from the channel
     */
    function unsubscribe(): void {
        const id = getVehicleId();

        if (channel && window.Echo) {
            window.Echo.leave(`vehicle.${id}`);
            channel = null;
            connectionStatus.value = 'disconnected';
            console.log(`[Telemetry] Disconnected from vehicle.${id}`);
        }

        stopDemoMode();
    }

    /**
     * Get a specific telemetry value with optional fallback
     */
    function getValue<T = number>(key: string, fallback: T = 0 as T): T {
        const value = telemetryData.value[key];
        if (value === null || value === undefined) {
            return fallback;
        }
        return value as T;
    }

    /**
     * Reset all telemetry data
     */
    function reset(): void {
        telemetryData.value = {};
        lastUpdate.value = null;
    }

    // Auto-subscribe if enabled (or start demo mode)
    if (autoSubscribe || initialDemoMode) {
        if (initialDemoMode) {
            startDemoMode();
        } else {
            subscribe();
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        unsubscribe();
        stopDemoMode();
    });

    return {
        telemetryData: readonly(telemetryData),
        connectionStatus: readonly(connectionStatus),
        isConnected: readonly(isConnected),
        lastUpdate: readonly(lastUpdate),
        isDemoMode: readonly(isDemoMode),
        subscribe,
        unsubscribe,
        getValue,
        reset,
        setDemoMode,
    };
}

/**
 * Apply a binding transform to a raw value
 */
export function applyTransform(
    value: number | null | undefined,
    transform: { multiply?: number; offset?: number; round?: number; clamp?: { min?: number; max?: number } } | null
): number | null {
    if (value === null || value === undefined) return null;
    if (!transform) return value;

    let result = value;

    // Apply multiplier
    if (transform.multiply !== undefined) {
        result = result * transform.multiply;
    }

    // Apply offset
    if (transform.offset !== undefined) {
        result = result + transform.offset;
    }

    // Apply clamping
    if (transform.clamp) {
        const min = transform.clamp.min ?? -Infinity;
        const max = transform.clamp.max ?? Infinity;
        result = Math.max(min, Math.min(max, result));
    }

    // Apply rounding
    if (transform.round !== undefined) {
        result = Number(result.toFixed(transform.round));
    }

    return result;
}

export default useTelemetryBinding;
