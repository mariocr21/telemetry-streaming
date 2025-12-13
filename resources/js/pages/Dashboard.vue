<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

// --- Importaciones de Componentes y Hooks ---
import MapWidget from '@/components/Dashboard/MapWidget.vue';
import SpeedometerWidget from '@/components/Dashboard/SpeedometerWidget.vue';
import TachometerWidget from '@/components/Dashboard/TachometerWidget.vue';
import TemperatureWidget from '@/components/Dashboard/TemperatureGaugeWidget.vue';
import ThrottleWidget from '@/components/Dashboard/ThrottleWidget.vue';
import TransmissionGearWidget from '@/components/Dashboard/TransmissionGearWidget.vue';
import DashboardHeader from '@/components/DashboardHeader.vue';
import DeviceSelectModal from '@/components/DeviceSelectModal.vue';
import DtcWidget from '@/components/Dtcwidget.vue';
import BatteryWidget from '@/components/Dashboard/BatteryWidget.vue';
import { useI18n } from '@/i18n/useI18n';

const { t } = useI18n();

// --- TIPOS DE DATOS ---

interface Vehicle {
    id: number;
    vin: string;
    make: string;
    model: string;
    status: boolean;
}
interface Activevehicle {
    id: number;
    vin: string;
    make: string | null;
    model: string | null;
}
interface Device {
    id: number;
    device_name: string;
    status: string;
    last_ping: string;
    active_vehicle: Activevehicle | null;
}
interface devicesCollectionInterface {
    data: Device[];
}
interface ConnectionStatus {
    is_online: boolean;
    status: string;
    last_seen: string | null;
    human_readable_last_seen: string;
}
interface DiagnosticTroubleCode {
    id: number;
    code: string;
    description: string;
    severity: 'high' | 'medium' | 'low' | 'unknown';
    detected_at: string;
    is_active: boolean;
}

// --- PROPS Y ESTADO ---
const props = defineProps<{ devices: devicesCollectionInterface }>();

const selectedDevice = ref<Device | null>(null);
const selectedVehicle = ref<Vehicle | null>(null);
const showDeviceModal = ref(false);

const lastDataReceivedAt = ref<Date | null>(null); // Última vez que Reverb envió datos
const vehicleLoadedAt = ref<Date | null>(null); // Cuando se cargó el vehículo
const connectionCheckInterval = ref<NodeJS.Timeout | null>(null); // Interval para verificar estado

// --- CONSTANTES DE TIEMPO (en milisegundos) ---
const ONLINE_THRESHOLD = 1 * 60 * 1000; // 2 minutos - después de esto: "pendiente"
const OFFLINE_THRESHOLD = 5 * 60 * 1000; // 5 minutos - después de esto: "desconectado"
const INITIAL_WAIT_THRESHOLD = 1 * 60 * 1000; // 1 minuto - si no hay datos después de cargar: "desconectado"
const CHECK_INTERVAL = 10 * 1000; // Verificar cada 10 segundos

// Estado para los datos pre-estructurados del backend
const primarySensorsData = ref<Record<string, any>>({});
const secondarySensorsData = ref<any[]>([]);
const gpsReadings = ref<Record<string, number>>({});

// Conexión y datos
const isConnected = ref(false);
const isRealTimeActive = ref(false);
const isLoading = ref(false);
const error = ref<string | null>(null);
const connectionStatus = ref<ConnectionStatus | null>(null);
const lastUpdate = ref<Date | null>(null);
const connectionRetries = ref(0);
const maxRetries = 5;
const realTimeTimeout = ref<NodeJS.Timeout | null>(null);
const sensorReadings = ref<Record<string, number>>({});
const dtcCodes = ref<DiagnosticTroubleCode[]>([]);

// Tracking de canales activos y vehicle ID actual
const currentVehicleId = ref<number | null>(null);
const activeChannels = ref<string[]>([]);

const mapWidgetRef = ref<any>(null);

// --- MÉTODOS DE DATOS Y LÓGICA DE INTERFAZ ---

const createWidgetData = (data: any, key: string, emoji?: string) => {
    if (!data) return null;

    //para sensores que no son de gps se manda con 2 decimales
    const value = typeof data.value === 'number' ? parseFloat(data.value.toFixed(2)) : 0;

    return {
        id: key,
        title: data.name,
        emoji: emoji || '',
        value: value,
        defaultValue: 0,
        sensor: {
            sensor: {
                pid: data.pid,
                unit: data.unit,
                name: data.name,
                description: data.description,
                min_value: data.min_value,
                max_value: data.max_value,
            },
        },
    };
};

// --- COMPUTED PROPERTIES PARA WIDGETS ---

const rpmWidgetData = computed(() => createWidgetData(primarySensorsData.value.rpm, 'rpm'));
const speedWidgetData = computed(() => createWidgetData(primarySensorsData.value.vel_kmh, 'vel_kmh'));
const tempWidgetData = computed(() => createWidgetData(primarySensorsData.value.temperature, 'temperature', '🌡️'));
const batteryWidgetData = computed(() => createWidgetData(primarySensorsData.value.battery, 'battery', '🔋'));
const fuelWidgetData = computed(() => createWidgetData(primarySensorsData.value.fuelLevel, 'fuelLevel', '⛽'));
const throttleWidgetData = computed(() => createWidgetData(primarySensorsData.value.throttlePosition, 'throttlePosition', '⚙️'));
const gearWidgetData = computed(() => createWidgetData(primarySensorsData.value.GEAR, 'GEAR', '⚙️'));

const secondarySensors = computed(() => {
    return secondarySensorsData.value.map((sensorData: any) => createWidgetData(sensorData, sensorData.pid));
});

const displayConnectionStatus = computed(() => {
    const now = Date.now();

    // Si no hay vehículo seleccionado
    if (!selectedVehicle.value) {
        return {
            text: t('connectionStatusOffline') || 'Sin conexión',
            color: 'gray',
            icon: 'disconnected',
            description: 'Selecciona un vehículo',
        };
    }

    // Si nunca hemos recibido datos de Reverb
    if (!lastDataReceivedAt.value) {
        // Verificar si ha pasado más de 1 minuto desde que se cargó el vehículo
        if (vehicleLoadedAt.value) {
            const timeSinceLoad = now - vehicleLoadedAt.value.getTime();

            if (timeSinceLoad >= INITIAL_WAIT_THRESHOLD) {
                // Más de 1 minuto sin datos después de cargar = desconectado
                return {
                    text: t('connectionStatusOffline') || 'Desconectado',
                    color: 'red',
                    icon: 'disconnected',
                    description: 'Sin datos del dispositivo',
                };
            } else {
                // Menos de 1 minuto - esperando datos iniciales
                const secondsRemaining = Math.ceil((INITIAL_WAIT_THRESHOLD - timeSinceLoad) / 1000);
                return {
                    text: t('connectionStatusWaiting') || 'Conectando...',
                    color: 'yellow',
                    icon: 'warning',
                    description: `Esperando datos (${secondsRemaining}s)`,
                };
            }
        }

        // Sin fecha de carga (no debería pasar)
        return {
            text: t('connectionStatusOffline') || 'Sin conexión',
            color: 'gray',
            icon: 'disconnected',
            description: 'Sin datos disponibles',
        };
    }

    // Tenemos datos - calcular tiempo desde última recepción
    const timeSinceLastData = now - lastDataReceivedAt.value.getTime();

    // CASO 1: Menos de 2 minutos = EN LÍNEA (tiempo real)
    if (timeSinceLastData < ONLINE_THRESHOLD) {
        const secondsAgo = Math.floor(timeSinceLastData / 1000);
        return {
            text: t('connectionStatusOnline') || 'En Vivo',
            color: 'green',
            icon: 'live',
            description: secondsAgo < 5 ? 'Recibiendo datos' : `Hace ${secondsAgo}s`,
        };
    }

    // CASO 2: Entre 2 y 5 minutos = PENDIENTE
    if (timeSinceLastData < OFFLINE_THRESHOLD) {
        const minutesAgo = Math.floor(timeSinceLastData / 60000);
        return {
            text: t('connectionStatusWarning') || 'Pendiente',
            color: 'yellow',
            icon: 'warning',
            description: `Última actualización hace ${minutesAgo} min`,
        };
    }

    // CASO 3: Más de 5 minutos = DESCONECTADO
    const minutesAgo = Math.floor(timeSinceLastData / 60000);
    return {
        text: t('connectionStatusOffline') || 'Desconectado',
        color: 'red',
        icon: 'disconnected',
        description: `Sin datos hace ${minutesAgo} min`,
    };
});
// --- FUNCIÓN: Iniciar verificación periódica del estado ---
const startConnectionCheck = () => {
    // Limpiar interval anterior si existe
    if (connectionCheckInterval.value) {
        clearInterval(connectionCheckInterval.value);
    }

    // Crear nuevo interval para forzar re-evaluación del computed
    connectionCheckInterval.value = setInterval(() => {
        // Forzar actualización del computed tocando una ref reactiva
        // El computed se re-evaluará automáticamente por el cambio de tiempo
        lastUpdate.value = lastUpdate.value ? new Date(lastUpdate.value) : null;
    }, CHECK_INTERVAL);
};

// --- FUNCIÓN: Detener verificación periódica ---
const stopConnectionCheck = () => {
    if (connectionCheckInterval.value) {
        clearInterval(connectionCheckInterval.value);
        connectionCheckInterval.value = null;
    }
};

const lastUpdateFormatted = computed(() => {
    return lastUpdate.value
        ? lastUpdate.value.toLocaleTimeString('es-MX', { hour12: false })
        : connectionStatus.value?.human_readable_last_seen || 'N/A';
});

// --- LÓGICA DE CONEXIÓN Y DATOS ---

const cleanupWebSocketConnections = () => {
    if (!window.Echo) return;

    // Detener verificación periódica
    stopConnectionCheck();

    activeChannels.value.forEach((channel) => {
        try {
            window.Echo.leave(channel);
        } catch (error) {
            console.warn(`⚠️ Error cerrando canal ${channel}:`, error);
        }
    });

    try {
        window.Echo.leave('telemetry');
    } catch (error) {
        console.warn('⚠️ Error cerrando canal telemetry:', error);
    }

    if (currentVehicleId.value) {
        try {
            window.Echo.leave(`vehicle.${currentVehicleId.value}`);
        } catch (error) {
            console.warn(`⚠️ Error cerrando canal vehicle.${currentVehicleId.value}:`, error);
        }
    }

    activeChannels.value = [];
    currentVehicleId.value = null;
    isConnected.value = false;
    isRealTimeActive.value = false;

    // Reset de timestamps
    lastDataReceivedAt.value = null;
    vehicleLoadedAt.value = null;
};

const setupWebSocketConnection = () => {
    if (!selectedVehicle.value || !window.Echo) {
        console.warn('❌ No se puede configurar WebSocket');
        return;
    }

    cleanupWebSocketConnections();

    try {
        console.log('🔧 Configurando WebSocket para vehículo:', selectedVehicle.value.id);

        const publicChannel = 'telemetry';
        window.Echo.channel(publicChannel)
            .listen('.telemetry.updated', (data: any) => {
                if (data.vehicle_id === currentVehicleId.value) {
                    handleTelemetryUpdate(data);
                }
            })
            .error((error: any) => {
                console.error('❌ Error en canal público:', error);
            });

        activeChannels.value.push(publicChannel);

        const privateChannel = `vehicle.${selectedVehicle.value.id}`;
        window.Echo.private(privateChannel)
            .listen('.telemetry.updated', (data: any) => {
                handleTelemetryUpdate(data);
            })
            .error((error: any) => {
                console.error(`❌ Error en canal privado ${privateChannel}:`, error);
            });

        activeChannels.value.push(privateChannel);
        currentVehicleId.value = selectedVehicle.value.id;

        isConnected.value = true;
        connectionRetries.value = 0;
    } catch (error) {
        console.error('❌ Error configurando WebSocket:', error);
        isConnected.value = false;

        if (connectionRetries.value < maxRetries) {
            connectionRetries.value++;
            const delay = Math.pow(2, connectionRetries.value) * 1000;
            setTimeout(() => {
                setupWebSocketConnection();
            }, delay);
        }
    }
};

/**
 * 🔥 FUNCIÓN CORREGIDA: Procesa datos de telemetría del backend
 * El backend envía: { vehicle_id, device_id, timestamp, data: {...}, dtc_codes: [...] }
 * donde "data" contiene TODOS los sensores con estructura:
 * { "PID": { pid, raw_value, processed_value, unit, name, timestamp }, ... }
 */
const handleTelemetryUpdate = (payload: any) => {
    try {
        if (payload.vehicle_id !== currentVehicleId.value) {
            return;
        }

        console.log('📡 Telemetría recibida:', payload);

        // Actualizar timestamp de última recepción
        lastDataReceivedAt.value = new Date();
        isRealTimeActive.value = true;

        if (realTimeTimeout.value) {
            clearTimeout(realTimeTimeout.value);
        }

        // OPCIÓN 1: Si el backend envía datos pre-estructurados (preferred)
        if (payload.structured_sensors) {
            if (payload.structured_sensors.primary) {
                primarySensorsData.value = payload.structured_sensors.primary;
            }
            if (payload.structured_sensors.secondary) {
                secondarySensorsData.value = payload.structured_sensors.secondary;
            }
            if (payload.structured_sensors.gps) {
                Object.keys(payload.structured_sensors.gps).forEach((pid) => {
                    gpsReadings.value[pid] = payload.structured_sensors.gps[pid].value;
                });
                nextTick(updateMapGpsData);
            }
        }
        // OPCIÓN 2: Si el backend envía "data" con todos los sensores mezclados
        else if (payload.data && typeof payload.data === 'object') {
            processTelemetryData(payload.data);
            nextTick(updateMapGpsData);
        }

        // Procesar códigos DTC
        if (payload.dtc_codes && Array.isArray(payload.dtc_codes)) {
            dtcCodes.value = payload.dtc_codes.map((dtc: any) => ({
                id: dtc.id || 0,
                code: dtc.code,
                description: dtc.description,
                severity: dtc.severity,
                detected_at: payload.timestamp,
                is_active: true,
            }));
        }

        lastUpdate.value = new Date();

        // Desactivar indicador de real-time después de 2 minutos sin datos
        realTimeTimeout.value = setTimeout(() => {
            isRealTimeActive.value = false;
        }, 120000);
    } catch (error) {
        console.error('❌ Error procesando datos de telemetría:', error);
    }
};

/**
 * 🔥 NUEVA FUNCIÓN: Procesa el objeto "data" del backend y separa sensores primarios/secundarios
 * El backend envía: { "0x0C": {...}, "0x0D": {...}, ... }
 */
const processTelemetryData = (telemetryData: Record<string, any>) => {
    // Definir PIDs de sensores primarios (ajusta según tus necesidades)
    const primaryPIDsMap: Record<string, string> = {
        '0x0C': 'rpm',
        'vel_kmh': 'vel_kmh',
        '0x05': 'temperature',
        '0x42': 'battery',
        '0x2F': 'fuelLevel',
        '0x11': 'throttlePosition',
        'GEAR': 'GEAR',
    };

    // PIDs de GPS
    const gpsPIDs = ['lat', 'lng', 'alt_m', 'rumbo', 'vel_kmh'];

    const primary: Record<string, any> = {};
    const secondary: any[] = [];

    Object.entries(telemetryData).forEach(([pid, sensorData]) => {
        if (!sensorData || typeof sensorData !== 'object') return;

        const processedValue = sensorData.processed_value ?? sensorData.value ?? 0;

        // Actualizar sensorReadings para compatibilidad
        sensorReadings.value[pid] = processedValue;

        // Clasificar como GPS
        if (gpsPIDs.includes(pid)) {
            gpsReadings.value[pid] = processedValue;
            return;
        }

        // Clasificar como primario
        if (primaryPIDsMap[pid]) {
            const key = primaryPIDsMap[pid];
            primary[key] = {
                pid: sensorData.pid || pid,
                value: processedValue,
                unit: sensorData.unit || '',
                name: sensorData.name || key,
                min_value: sensorData.min_value || 0,
                max_value: sensorData.max_value || 100,
                description: sensorData.description || '',
            };
        }
        // Clasificar como secundario
        else {
            secondary.push({
                pid: sensorData.pid || pid,
                value: processedValue,
                unit: sensorData.unit || '',
                name: sensorData.name || pid,
                min_value: sensorData.min_value || 0,
                max_value: sensorData.max_value || 100,
                description: sensorData.description || '',
            });
        }
    });

    // Actualizar las refs reactivas
    primarySensorsData.value = { ...primarySensorsData.value, ...primary };
    secondarySensorsData.value = secondary;

    console.log('✅ Datos procesados:', {
        primarios: Object.keys(primary).length,
        secundarios: secondary.length,
        gps: Object.keys(gpsReadings.value).length,
    });
};

/**
 * Función legacy para compatibilidad con formato antiguo
 */
const updatePrimarySensorFromOldFormat = (pid: string, value: number) => {
    const pidMapping: Record<string, string> = {
        '0x0C': 'rpm',
        'vel_kmh': 'vel_kmh',
        '0x0D': 'vel_kmh',
        '0x05': 'temperature',
        '0x42': 'battery',
        BAT: 'battery',
        volt: 'battery',
        '0x0B': 'oilPressure',
        '0x11': 'throttlePosition',
        '0x2F': 'fuelLevel',
        GEAR: 'GEAR',
    };

    const key = pidMapping[pid];
    if (key && primarySensorsData.value[key]) {
        primarySensorsData.value[key] = {
            ...primarySensorsData.value[key],
            value: value,
        };
    }

    if (['lat', 'lng', 'alt_m', 'rumbo', 'vel_kmh'].includes(pid)) {
        gpsReadings.value[pid] = value;
    }
};

const loadHistoricalData = (readings: any, structuredSensors: any) => {
    if (readings.data && typeof readings.data === 'object') {
        const initialReadings: Record<string, number> = {};
        Object.keys(readings.data).forEach((pid) => {
            if (typeof readings.data[pid] === 'number') {
                initialReadings[pid] = readings.data[pid];
            }
        });
        sensorReadings.value = initialReadings;
        lastUpdate.value = readings.timestamp ? new Date(readings.timestamp) : new Date();

        const gps: Record<string, number> = {};
        Object.keys(structuredSensors.gps).forEach((pid) => {
            if (structuredSensors.gps[pid]?.value !== undefined) {
                gps[pid] = structuredSensors.gps[pid].value;
            }
        });
        gpsReadings.value = gps;

        nextTick(() => {
            updateMapGpsData();
        });
    }
};

const initializeSensorReadings = (structuredSensors: any) => {
    const readings: Record<string, number> = {};
    const gps: Record<string, number> = {};

    Object.values(structuredSensors.primary).forEach((s: any) => {
        readings[s.pid] = s.value;
    });
    structuredSensors.secondary.forEach((s: any) => {
        readings[s.pid] = s.value;
    });
    Object.values(structuredSensors.gps).forEach((s: any) => {
        readings[s.pid] = s.value;
        gps[s.pid] = s.value;
    });

    sensorReadings.value = readings;
    gpsReadings.value = gps;

    nextTick(() => {
        updateMapGpsData();
    });
};

const fetchVehicleData = async (deviceId: number) => {
    if (!deviceId) return;

    try {
        cleanupWebSocketConnections();
        selectedVehicle.value = null;
        connectionStatus.value = null;
        sensorReadings.value = {};
        isRealTimeActive.value = false;

        // Reset de timestamps de conexión
        lastDataReceivedAt.value = null;
        vehicleLoadedAt.value = null;

        isLoading.value = true;
        error.value = null;

        const response = await axios.get(`/vehicle/${deviceId}`);

        if (response.data.vehicle) {
            selectedVehicle.value = response.data.vehicle;
            connectionStatus.value = response.data.connection_status;
            dtcCodes.value = response.data.dtc_codes || [];

            if (response.data.structured_sensors) {
                primarySensorsData.value = response.data.structured_sensors.primary;
                secondarySensorsData.value = response.data.structured_sensors.secondary;

                if (response.data.latest_readings?.data) {
                    loadHistoricalData(response.data.latest_readings, response.data.structured_sensors);
                } else {
                    initializeSensorReadings(response.data.structured_sensors);
                }
            }

            // Marcar momento de carga e iniciar verificación
            vehicleLoadedAt.value = new Date();
            startConnectionCheck();

            setupWebSocketConnection();
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || t('errorLoadingVehicleData');
        selectedVehicle.value = null;
        connectionStatus.value = null;
    } finally {
        isLoading.value = false;
    }
};

const initSelectDevice = () => {
    if (props.devices.data.length > 0) {
        selectedDevice.value = props.devices.data[0];
        fetchVehicleData(props.devices.data[0].id);
    }
};

const selectDeviceFromModal = (deviceId: number) => {
    const device = props.devices.data.find((d) => d.id === deviceId) || null;
    selectedDevice.value = device;
    showDeviceModal.value = false;
};

const updateMapGpsData = () => {
    if (mapWidgetRef.value && mapWidgetRef.value.updateGpsData) {
        mapWidgetRef.value.updateGpsData(gpsReadings.value);
    }
};

watch(selectedDevice, async (newDevice) => {
    if (newDevice) {
        await fetchVehicleData(newDevice.id);
    } else {
        cleanupWebSocketConnections();
        selectedVehicle.value = null;
    }
});

onMounted(() => {
    initSelectDevice();
});

onUnmounted(() => {
    cleanupWebSocketConnections();
    stopConnectionCheck();

    if (realTimeTimeout.value) {
        clearTimeout(realTimeTimeout.value);
    }
});
</script>

<template>
    <AppLayout :title="t('dashboardTitle')">
        <Head :title="t('dashboardTitle')" />

        <!-- Container principal con padding responsivo -->
        <div class="dashboard-container">
            <!-- Header -->
            <div class="header-section">
                <DashboardHeader
                    :selected-device="selectedDevice"
                    :display-connection-status="displayConnectionStatus"
                    @open-modal="showDeviceModal = true"
                />
            </div>

            <!-- Contenido principal -->
            <div class="main-content">
                <!-- Layout principal: Stack en móvil, side-by-side en desktop -->
                <div class="main-layout">
                    <!-- Mapa -->
                    <div class="map-section">
                        <div class="map-container">
                            <MapWidget
                                ref="mapWidgetRef"
                                :is-loading="isLoading"
                                :is-real-time-active="isRealTimeActive"
                                :connection-status="connectionStatus"
                                :selected-vehicle="selectedVehicle"
                                class="map-widget"
                            />
                        </div>
                    </div>

                    <!-- Panel de Widgets -->
                    <div class="widgets-section">
                        <!-- Grid de widgets primarios -->
                        <div class="widgets-grid">
                            <!-- RPM Widget -->
                            <div v-if="rpmWidgetData" class="widget-card widget-gauge">
                                <span class="widget-label">RPM</span>
                                <div class="gauge-container">
                                    <TachometerWidget :sensor="rpmWidgetData" />
                                </div>
                            </div>

                            <!-- Velocidad Widget -->
                            <div v-if="speedWidgetData" class="widget-card widget-gauge">
                                <span class="widget-label">{{ t('speedTitle') || 'Velocidad' }}</span>
                                <div class="gauge-container">
                                    <SpeedometerWidget :sensor="speedWidgetData" />
                                </div>
                            </div>

                            <!-- Temperatura Widget -->
                            <div v-if="tempWidgetData" class="widget-card widget-gauge">
                                <span class="widget-label">{{ t('coolantTempTitle') || 'Temp. Motor' }}</span>
                                <div class="gauge-container">
                                    <TemperatureWidget :sensor="tempWidgetData" />
                                </div>
                            </div>
                            <div v-if="gearWidgetData" class="widget-card widget-gauge">
                                <span class="widget-label"> {{ t('transmissionGearTitle') || 'Transmisión' }} </span>
                                <div class="gauge-container">
                                    <TransmissionGearWidget :sensor="gearWidgetData" />
                                </div>
                            </div>

                            <!-- Batería Widget -->
                            <div v-if="batteryWidgetData" class="widget-card widget-simple">
                                <span class="widget-label">{{ t('batteryVoltageTitle') || 'Batería' }}</span>
                                <BatteryWidget :sensor="batteryWidgetData" />
                            </div>

                            <!-- Combustible Widget -->
                            <div v-if="fuelWidgetData" class="widget-card widget-simple">
                                <span class="widget-label">{{ t('fuelLevelTitle') || 'Combustible' }}</span>
                                <div class="value-container">
                                    <span class="value-main">
                                        {{ fuelWidgetData.value || 'N/A' }}
                                    </span>
                                    <span class="value-unit">{{ fuelWidgetData.sensor.sensor.unit || '%' }}</span>
                                </div>
                                <span class="update-time">{{ lastUpdateFormatted }}</span>
                            </div>

                            <!-- Throttle Widget -->
                            <div v-if="throttleWidgetData" class="widget-card-throttle widget-throttle">
                                <ThrottleWidget :sensor="throttleWidgetData" :is-real-time-active="isRealTimeActive" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección inferior: DTC y Sensores Adicionales -->
                <div class="bottom-section">
                    <!-- DTC Widget -->
                    <DtcWidget v-if="dtcCodes.length > 0" :dtc-codes="dtcCodes" class="dtc-widget" :is-real-time-active="isRealTimeActive" />

                    <!-- Sensores Adicionales -->
                    <div class="secondary-sensors-card">
                        <h3 class="section-title">
                            {{ t('additionalSensorsTitle') || 'Sensores Adicionales' }}
                            <span class="sensor-count">({{ secondarySensors.length }})</span>
                        </h3>

                        <div v-if="secondarySensors.length > 0" class="flex flex-wrap justify-center gap-2 md:gap-4">
                            <div v-for="sensorData in secondarySensors" :key="sensorData?.id" class="sensor-item">
                                <span class="sensor-name">{{ sensorData?.title }}</span>
                                <span class="sensor-value">
                                    <!-- validar si es string o number -->
                                    {{ typeof sensorData?.value === 'number' ? sensorData.value.toFixed(2) : sensorData?.value ?? 'N/A' }}
                                    <span class="sensor-unit">{{ sensorData?.sensor?.sensor?.unit }}</span>
                                </span>
                            </div>
                        </div>

                        <div v-else class="empty-sensors">
                            {{ t('noAdditionalSensors') || 'No hay sensores adicionales configurados.' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <DeviceSelectModal
            :show="showDeviceModal"
            :devices="props.devices"
            :selected-device-id="selectedDevice?.id || null"
            @close="showDeviceModal = false"
            @select="selectDeviceFromModal"
        />
    </AppLayout>
</template>

<style>
/* ===== VARIABLES CSS ===== */
:root {
    --color-primary: rgb(6, 182, 212);
    --color-primary-20: rgba(6, 182, 212, 0.2);
    --color-primary-40: rgba(6, 182, 212, 0.4);
    --color-bg-card: rgb(31, 41, 55);
    --color-bg-card-hover: rgb(55, 65, 81);
    --color-text-primary: white;
    --color-text-secondary: rgb(156, 163, 175);
    --color-text-muted: rgb(107, 114, 128);
    --color-border: rgb(55, 65, 81);
}

/* ===== CONTAINER PRINCIPAL ===== */
.dashboard-container {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 12px;
}

@media (min-width: 640px) {
    .dashboard-container {
        padding: 0 16px;
    }
}

@media (min-width: 768px) {
    .dashboard-container {
        padding: 0 24px;
    }
}

@media (min-width: 1024px) {
    .dashboard-container {
        padding: 0 32px;
    }
}

/* ===== HEADER ===== */
.header-section {
    padding: 12px 0;
}

@media (min-width: 640px) {
    .header-section {
        padding: 16px 0;
    }
}

@media (min-width: 768px) {
    .header-section {
        padding: 24px 0;
    }
}

/* ===== CONTENIDO PRINCIPAL ===== */
.main-content {
    padding-bottom: 24px;
}

@media (min-width: 640px) {
    .main-content {
        padding-bottom: 32px;
    }
}

/* ===== LAYOUT PRINCIPAL ===== */
.main-layout {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

@media (min-width: 640px) {
    .main-layout {
        gap: 20px;
    }
}

@media (min-width: 1024px) {
    .main-layout {
        flex-direction: row;
        gap: 24px;
    }
}

/* ===== SECCIÓN DEL MAPA ===== */
.map-section {
    width: 100%;
    order: 1;
}

@media (min-width: 1024px) {
    .map-section {
        width: 58.333%;
        order: 1;
    }
}

@media (min-width: 1280px) {
    .map-section {
        width: 66.666%;
    }
}

.map-container {
    position: relative;
    height: 330px;
    border-radius: 12px;
    overflow: hidden;
}

@media (min-width: 480px) {
    .map-container {
        height: 260px;
    }
}

@media (min-width: 640px) {
    .map-container {
        height: 300px;
    }
}

@media (min-width: 768px) {
    .map-container {
        height: 350px;
    }
}

@media (min-width: 1024px) {
    .map-container {
        height: 400px;
    }
}

@media (min-width: 1280px) {
    .map-container {
        height: 450px;
    }
}

.map-widget {
    width: 100%;
    height: 100%;
}

/* ===== SECCIÓN DE WIDGETS ===== */
.widgets-section {
    width: 100%;
    order: 2;
}

@media (min-width: 1024px) {
    .widgets-section {
        width: 41.666%;
        order: 2;
    }
}

@media (min-width: 1280px) {
    .widgets-section {
        width: 33.333%;
    }
}

/* ===== GRID DE WIDGETS ===== */
.widgets-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

@media (min-width: 640px) {
    .widgets-grid {
        gap: 16px;
    }
}

@media (min-width: 768px) and (max-width: 1023px) {
    .widgets-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* ===== WIDGET CARD BASE ===== */
.widget-card {
    display: flex;
    flex-direction: column;
    background: var(--color-bg-card) !important;
    border: 1px solid var(--color-primary-20) !important;
    border-radius: 12px;
    padding: 12px;
    transition: all 0.2s ease;
}
.widget-card-throttle {
    display: flex;
    flex-direction: column;
    grid-column: span 2;
    background: var(--color-bg-card) !important;
    border: 1px solid var(--color-primary-20) !important;
    border-radius: 12px;
    padding: 12px;
    transition: all 0.2s ease;
}

@media (min-width: 640px) {
    .widget-card {
        padding: 16px;
        border-radius: 16px;
    }
}

.widget-card:hover {
    border-color: var(--color-primary-40);
    box-shadow: 0 4px 20px rgba(6, 182, 212, 0.1);
}

/* ===== WIDGET CON GAUGE ===== */
.widget-gauge {
    min-height: 130px;
}

@media (min-width: 640px) {
    .widget-gauge {
        min-height: 150px;
    }
}

@media (min-width: 768px) {
    .widget-gauge {
        min-height: 170px;
    }
}

/* ===== WIDGET SIMPLE (valores numéricos) ===== */
.widget-simple {
    min-height: 90px;
    justify-content: space-between;
}

@media (min-width: 640px) {
    .widget-simple {
        min-height: 110px;
    }
}

/* ===== WIDGET THROTTLE ===== */
.widget-throttle {
    min-height: 130px;
}

@media (min-width: 640px) {
    .widget-throttle {
        min-height: 150px;
    }
}

/* ===== WIDGET LABEL ===== */
.widget-label {
    display: block;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-primary);
    margin-bottom: 4px;
}

@media (min-width: 640px) {
    .widget-label {
        font-size: 11px;
        margin-bottom: 8px;
    }
}

@media (min-width: 768px) {
    .widget-label {
        font-size: 12px;
    }
}

/* ===== GAUGE CONTAINER ===== */
.gauge-container {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
    width: 100%;
    max-width: 100px;
    margin: 0 auto;
    aspect-ratio: 1;
}

@media (min-width: 640px) {
    .gauge-container {
        max-width: 120px;
    }
}

@media (min-width: 768px) {
    .gauge-container {
        max-width: 140px;
    }
}

/* ===== VALUE CONTAINER ===== */
.value-container {
    display: flex;
    align-items: baseline;
    justify-content: center;
    flex: 1;
    gap: 4px;
}

.value-main {
    font-size: 24px;
    font-weight: 800;
    color: var(--color-text-primary);
    line-height: 1;
}

@media (min-width: 640px) {
    .value-main {
        font-size: 28px;
    }
}

@media (min-width: 768px) {
    .value-main {
        font-size: 32px;
    }
}

.value-unit {
    font-size: 12px;
    font-weight: 400;
    color: var(--color-text-secondary);
}

@media (min-width: 640px) {
    .value-unit {
        font-size: 14px;
    }
}

.update-time {
    font-size: 9px;
    color: var(--color-text-muted);
    text-align: center;
}

@media (min-width: 640px) {
    .update-time {
        font-size: 10px;
    }
}

/* ===== SECCIÓN INFERIOR ===== */
.bottom-section {
    margin-top: 16px;
    width: 100%;
}

@media (min-width: 640px) {
    .bottom-section {
        margin-top: 20px;
    }
}

@media (min-width: 1024px) {
    .bottom-section {
        margin-top: 24px;
    }
}

.dtc-widget {
    margin-bottom: 16px;
}

@media (min-width: 640px) {
    .dtc-widget {
        margin-bottom: 20px;
    }
}

/* ===== SENSORES SECUNDARIOS ===== */
.secondary-sensors-card {
    background: var(--color-bg-card);
    border: 1px solid var(--color-primary-20);
    border-radius: 12px;
    padding: 12px;
}

@media (min-width: 640px) {
    .secondary-sensors-card {
        padding: 16px;
        border-radius: 16px;
    }
}

@media (min-width: 768px) {
    .secondary-sensors-card {
        padding: 20px;
    }
}

.section-title {
    font-size: 14px;
    font-weight: 700;
    color: rgb(209, 213, 219);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--color-border);
}

@media (min-width: 640px) {
    .section-title {
        font-size: 16px;
        margin-bottom: 16px;
        padding-bottom: 12px;
    }
}

@media (min-width: 768px) {
    .section-title {
        font-size: 18px;
    }
}

.sensor-count {
    font-size: 12px;
    font-weight: 400;
    color: var(--color-text-muted);
}

@media (min-width: 640px) {
    .sensor-count {
        font-size: 14px;
    }
}

/* ===== SCROLL DE SENSORES ===== */
.sensors-scroll {
    margin: 0 -12px;
    padding: 0 12px;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--color-primary-40) transparent;
    -webkit-overflow-scrolling: touch;
}

@media (min-width: 640px) {
    .sensors-scroll {
        margin: 0 -16px;
        padding: 0 16px;
    }
}

.sensors-scroll::-webkit-scrollbar {
    height: 4px;
}

.sensors-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.sensors-scroll::-webkit-scrollbar-thumb {
    background-color: var(--color-primary-40);
    border-radius: 4px;
}

/* ===== SENSOR ITEM ===== */
.sensor-item {
    flex-shrink: 0;
    width: 90px;
    min-width: 90px;
    padding: 10px 8px;
    background: rgba(55, 65, 81, 0.8);
    border-radius: 8px;
    text-align: center;
    transition: background 0.2s ease;
}

@media (min-width: 640px) {
    .sensor-item {
        width: 110px;
        min-width: 110px;
        padding: 12px 10px;
        border-radius: 10px;
    }
}

@media (min-width: 768px) {
    .sensor-item {
        width: 130px;
        min-width: auto;
        flex-shrink: 1;
        padding: 14px 12px;
    }
}

.sensor-item:hover {
    background: var(--color-bg-card-hover);
}

.sensor-name {
    display: block;
    font-size: 9px;
    font-weight: 600;
    color: var(--color-primary);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (min-width: 640px) {
    .sensor-name {
        font-size: 10px;
        margin-bottom: 6px;
    }
}

@media (min-width: 768px) {
    .sensor-name {
        font-size: 11px;
    }
}

.sensor-value {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: var(--color-text-primary);
}

@media (min-width: 640px) {
    .sensor-value {
        font-size: 16px;
    }
}

@media (min-width: 768px) {
    .sensor-value {
        font-size: 18px;
    }
}

.sensor-unit {
    font-size: 9px;
    font-weight: 400;
    color: var(--color-text-secondary);
}

@media (min-width: 640px) {
    .sensor-unit {
        font-size: 10px;
    }
}

@media (min-width: 768px) {
    .sensor-unit {
        font-size: 11px;
    }
}

/* ===== EMPTY STATE ===== */
.empty-sensors {
    padding: 24px 0;
    text-align: center;
    font-size: 13px;
    color: var(--color-text-secondary);
}

@media (min-width: 640px) {
    .empty-sensors {
        padding: 32px 0;
        font-size: 14px;
    }
}

/* ===== LANDSCAPE MÓVIL ===== */
@media (max-height: 500px) and (orientation: landscape) {
    .map-container {
        height: 180px;
    }

    .widget-gauge {
        min-height: 110px;
    }

    .gauge-container {
        max-width: 80px;
    }
}

/* ===== SAFE AREA (NOTCH) ===== */
@supports (padding: max(0px)) {
    .dashboard-container {
        padding-left: max(12px, env(safe-area-inset-left));
        padding-right: max(12px, env(safe-area-inset-right));
    }
}

/* ===== ANIMACIÓN PARA REAL-TIME ===== */
@keyframes pulse-border {
    0%,
    100% {
        border-color: var(--color-primary-20);
    }
    50% {
        border-color: var(--color-primary-40);
    }
}

.widget-card.is-live {
    animation: pulse-border 2s ease-in-out infinite;
}
</style>
