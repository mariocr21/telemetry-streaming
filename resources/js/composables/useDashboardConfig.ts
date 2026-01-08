/**
 * useDashboardConfig Composable
 * 
 * Fetches and manages the dashboard configuration for a specific vehicle.
 * The configuration defines which widgets to render and how they are arranged.
 */
import { ref, readonly, computed, type Ref } from 'vue';
import axios from 'axios';
import type {
    DashboardConfig,
    DashboardApiResponse,
    WidgetDefinition
} from '@/types/dashboard.d';

interface UseDashboardConfigOptions {
    /** Pre-loaded config (for SSR with Inertia) */
    preloadedConfig?: DashboardConfig;
    /** Auto-fetch on mount */
    autoFetch?: boolean;
}

interface UseDashboardConfigReturn {
    /** The dashboard configuration */
    config: Readonly<Ref<DashboardConfig | null>>;
    /** Loading state */
    loading: Readonly<Ref<boolean>>;
    /** Error message if any */
    error: Readonly<Ref<string | null>>;
    /** Whether the config is empty (no layout configured) */
    isEmpty: Readonly<Ref<boolean>>;
    /** Fetch/refresh the configuration */
    fetchConfig: () => Promise<void>;
    /** Refresh alias */
    refresh: () => Promise<void>;
}

export function useDashboardConfig(
    vehicleId: number | Ref<number>,
    options: UseDashboardConfigOptions = {}
): UseDashboardConfigReturn {
    const { preloadedConfig, autoFetch = true } = options;

    // State
    const config = ref<DashboardConfig | null>(preloadedConfig ?? null);
    const loading = ref(!preloadedConfig);
    const error = ref<string | null>(null);

    // Computed
    const isEmpty = computed(() => {
        if (!config.value) return true;
        return config.value.meta?.is_empty === true || config.value.groups.length === 0;
    });

    // Get vehicle ID (support both ref and plain number)
    const getVehicleId = (): number => {
        return typeof vehicleId === 'number' ? vehicleId : vehicleId.value;
    };

    // Fetch configuration from API
    async function fetchConfig(): Promise<void> {
        const id = getVehicleId();

        if (!id) {
            error.value = 'Vehicle ID is required';
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get<DashboardApiResponse>(
                `/api/vehicles/${id}/dashboard`
            );

            if (response.data.success) {
                config.value = response.data.data;
            } else {
                error.value = 'Failed to load dashboard configuration';
            }
        } catch (err: any) {
            console.error('Error fetching dashboard config:', err);

            if (err.response?.status === 404) {
                error.value = 'Vehicle not found';
            } else if (err.response?.status === 403) {
                error.value = 'Access denied to this vehicle';
            } else {
                error.value = err.message || 'Failed to load dashboard configuration';
            }
        } finally {
            loading.value = false;
        }
    }

    // Auto-fetch on mount if no preloaded config
    if (autoFetch && !preloadedConfig) {
        fetchConfig();
    }

    return {
        config: readonly(config),
        loading: readonly(loading),
        error: readonly(error),
        isEmpty: readonly(isEmpty),
        fetchConfig,
        refresh: fetchConfig,
    };
}

// ─────────────────────────────────────────────────────────────
// WIDGET DEFINITIONS HELPER
// ─────────────────────────────────────────────────────────────

interface UseWidgetDefinitionsReturn {
    definitions: Readonly<Ref<WidgetDefinition[]>>;
    loading: Readonly<Ref<boolean>>;
    fetch: () => Promise<void>;
    getByType: (type: string) => WidgetDefinition | undefined;
}

/**
 * Fetch available widget definitions (for admin configurator)
 */
export function useWidgetDefinitions(): UseWidgetDefinitionsReturn {
    const definitions = ref<WidgetDefinition[]>([]);
    const loading = ref(false);

    async function fetch(): Promise<void> {
        loading.value = true;

        try {
            const response = await axios.get('/api/dashboard/widgets');
            definitions.value = response.data.data || [];
        } catch (err) {
            console.error('Error fetching widget definitions:', err);
        } finally {
            loading.value = false;
        }
    }

    function getByType(type: string): WidgetDefinition | undefined {
        return definitions.value.find(d => d.type === type);
    }

    return {
        definitions: readonly(definitions),
        loading: readonly(loading),
        fetch,
        getByType,
    };
}

export default useDashboardConfig;
