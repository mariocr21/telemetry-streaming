<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import DashboardHeader from '@/components/Dashboard/DashboardHeader.vue'
import MapWidget from '@/components/Dashboard/MapWidget.vue'
import PrimarySensorsWidget from '@/components/Dashboard/PrimarySensorsWidget.vue'
import SecondarySensorsWidget from '@/components/Dashboard/SecondarySensorsWidget.vue'
import ThrottleWidget from '@/components/Dashboard/ThrottleWidget.vue'
import AllSensorsWidget from '@/components/Dashboard/AllSensorsWidget.vue'
import ConnectionStatusWidget from '@/components/Dashboard/ConnectionStatusWidget.vue'
import QuickActionsWidget from '@/components/Dashboard/QuickActionsWidget.vue'
import type { BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'

// Types
interface Sensor {
    id: number
    pid: string
    name: string
    description: string
    category: string
    unit: string
    data_type: string
    min_value?: number
    max_value?: number
    requires_calculation?: boolean
    calculation_formula?: string
}

interface VehicleSensor {
    id: number
    vehicle_id: number
    sensor_id: number
    is_active: boolean
    frequency_seconds: number
    min_value?: number
    max_value?: number
    last_reading_at?: string
    sensor: Sensor
}

interface Vehicle {
    id: number
    vin: string
    make: string
    model: string
    year: number
    nickname?: string
    status: boolean
    vehicle_sensors?: VehicleSensor[]
}

interface DeviceInventory {
    serial_number: string
    model: string
    firmware_version: string
}

interface Device {
    id: number
    device_name: string
    mac_address: string
    status: string
    activated_at: string
    last_ping: string
    device_inventory: DeviceInventory
    vehicles: Vehicle[]
}

interface ConnectionStatus {
    is_online: boolean
    status: string
    last_seen: string | null
    minutes_since_last_reading: number | null
    seconds_since_last_reading: number | null
    human_readable_last_seen: string
    formatted_inactivity?: string
}

// Props
const props = defineProps<{
    devices: Device[]
}>()

// State
const selectedDeviceId = ref<string | number>('')
const selectedVehicle = ref<Vehicle | null>(null)
const isConnected = ref(false)
const isRealTimeActive = ref(false)
const isLoading = ref(false)
const error = ref<string | null>(null)

// Connection and data state
const connectionStatus = ref<ConnectionStatus | null>(null)
const lastUpdate = ref<Date | null>(null)
const connectionRetries = ref(0)
const maxRetries = 5
const realTimeTimeout = ref<NodeJS.Timeout | null>(null)
const sensorReadings = ref<Record<string, number>>({})
const lastDataSource = ref<'cache' | 'database' | 'realtime' | 'simulation'>('database')

// WebSocket channel management
const currentVehicleId = ref<number | null>(null)
const activeChannels = ref<string[]>([]) // Track de canales activos

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
]

// Computed
const isDebugMode = computed(() => {
    return window.APP_DEBUG === true || import.meta.env.VITE_APP_ENV === 'local'
})

const activeSensors = computed(() => {
    return selectedVehicle.value?.vehicle_sensors?.filter(vs => vs.is_active) || []
})

const hasSensors = computed(() => activeSensors.value.length > 0)

// Sensores por PID para fácil acceso
const sensorMap = computed(() => {
    const map: Record<string, VehicleSensor> = {}
    activeSensors.value.forEach(sensor => {
        map[sensor.sensor.pid] = sensor
    })
    return map
})

// PIDs que se muestran en widgets específicos (para excluir del widget general)
const displayedPids = ['0x0D', 'vel_kmh', '0x0C', '0x05', '0x2F', '0x0B', '0x42', '0x11']

// Sensores principales
const primarySensors = computed(() => {
    const sensors = []
    
    // Velocidad
    if (sensorMap.value['0x0D'] || sensorMap.value['vel_kmh']) {
        sensors.push({
            id: 'speed',
            title: 'Velocidad',
            sensor: sensorMap.value['0x0D'] || sensorMap.value['vel_kmh'],
            value: getSensorValue('0x0D') || getSensorValue('vel_kmh'),
            defaultValue: 0
        })
    }
    
    // RPM
    if (sensorMap.value['0x0C']) {
        sensors.push({
            id: 'rpm',
            title: 'RPM',
            sensor: sensorMap.value['0x0C'],
            value: getSensorValue('0x0C'),
            defaultValue: 0
        })
    }
    
    return sensors
})

const secondarySensors = computed(() => {
    const sensors = []
    
    // Temperatura
    if (sensorMap.value['0x05']) {
        sensors.push({
            id: 'coolantTemp',
            title: 'Temperatura',
            emoji: '🌡️',
            sensor: sensorMap.value['0x05'],
            value: getSensorValue('0x05'),
            defaultValue: 85
        })
    }
    
    // Combustible
    if (sensorMap.value['0x2F']) {
        sensors.push({
            id: 'fuel',
            title: 'Combustible',
            emoji: '⛽',
            sensor: sensorMap.value['0x2F'],
            value: getSensorValue('0x2F'),
            defaultValue: 75
        })
    }
    
    // Presión MAP
    if (sensorMap.value['0x0B']) {
        sensors.push({
            id: 'pressure',
            title: 'Presión',
            emoji: '⚡',
            sensor: sensorMap.value['0x0B'],
            value: getSensorValue('0x0B'),
            defaultValue: 100
        })
    }
    
    // Batería
    if (sensorMap.value['0x42']) {
        sensors.push({
            id: 'battery',
            title: 'Batería',
            emoji: '🔋',
            sensor: sensorMap.value['0x42'],
            value: getSensorValue('0x42'),
            defaultValue: 12.4
        })
    }
    
    return sensors
})

const throttleSensor = computed(() => {
    if (sensorMap.value['0x11']) {
        return {
            id: 'throttle',
            title: 'Posición Mariposa',
            emoji: '⚙️',
            sensor: sensorMap.value['0x11'],
            value: getSensorValue('0x11'),
            defaultValue: 0
        }
    }
    return null
})

// Todos los demás sensores (excluyendo los que ya se muestran en widgets específicos)
const remainingSensors = computed(() => {
    return activeSensors.value.filter(sensor => 
        !displayedPids.includes(sensor.sensor.pid)
    ).map(sensor => ({
        id: sensor.sensor.pid,
        title: sensor.sensor.name,
        sensor: sensor,
        value: getSensorValue(sensor.sensor.pid),
        defaultValue: 0
    }))
})

// Formatted last update time
const lastUpdateFormatted = computed(() => {
    if (!lastUpdate.value) {
        if (connectionStatus.value?.human_readable_last_seen) {
            return connectionStatus.value.human_readable_last_seen
        }
        return 'Sin datos'
    }
    
    const diff = Date.now() - lastUpdate.value.getTime()
    if (diff < 1000) return 'Ahora mismo'
    if (diff < 60000) return `Hace ${Math.floor(diff / 1000)}s`
    if (diff < 3600000) return `Hace ${Math.floor(diff / 60000)}min`
    return lastUpdate.value.toLocaleTimeString('es-MX', { hour12: false })
})

// Connection status display
const displayConnectionStatus = computed(() => {
    if (isRealTimeActive.value) {
        return {
            text: 'En Vivo',
            color: 'green',
            icon: 'live',
            description: 'Recibiendo datos en tiempo real'
        }
    }
    
    if (connectionStatus.value?.is_online) {
        return {
            text: 'En Línea',
            color: 'cyan',
            icon: 'online',
            description: 'Conectado (datos históricos)'
        }
    }
    
    if (connectionStatus.value?.status === 'offline') {
        return {
            text: 'Fuera de Línea',
            color: 'orange',
            icon: 'offline',
            description: `Última conexión: ${connectionStatus.value.human_readable_last_seen}`
        }
    }
    
    return {
        text: 'Desconectado',
        color: 'red',
        icon: 'disconnected',
        description: 'Sin datos disponibles'
    }
})

// Methods
const getSensorValue = (pid: string): number => {
    if (sensorReadings.value[pid] !== undefined) {
        const sensor = sensorMap.value[pid]
        let value = sensorReadings.value[pid]
        
        // Aplicar cálculo si es necesario
        if (sensor?.sensor.requires_calculation && sensor.sensor.calculation_formula) {
            value = calculateSensorValue(value, sensor.sensor.calculation_formula)
        }
        
        return parseFloat(value.toFixed(2))
    }
    return 0
}

const calculateSensorValue = (rawValue: number, formula: string): number => {
    try {
        const A = rawValue
        const B = 0 // Para datos de 2 bytes, implementar después
        
        const calculatedFormula = formula
            .replace(/\bA\b/g, A.toString())
            .replace(/\bB\b/g, B.toString())
        
        return new Function('return ' + calculatedFormula)()
    } catch (error) {
        console.error('Error calculating sensor value:', error)
        return rawValue
    }
}

const cleanupWebSocketConnections = () => {
    if (!window.Echo) return
    
    console.log('🧹 Limpiando conexiones WebSocket existentes...')
    
    // Limpiar todos los canales activos registrados
    activeChannels.value.forEach(channel => {
        try {
            window.Echo.leave(channel)
            console.log(`✅ Canal ${channel} cerrado correctamente`)
        } catch (error) {
            console.warn(`⚠️ Error cerrando canal ${channel}:`, error)
        }
    })
    
    // Limpiar también canales genéricos por si acaso
    try {
        window.Echo.leave('telemetry')
        console.log('✅ Canal público telemetry cerrado')
    } catch (error) {
        console.warn('⚠️ Error cerrando canal telemetry:', error)
    }
    
    // Limpiar canal del vehículo anterior si existe
    if (currentVehicleId.value) {
        try {
            window.Echo.leave(`vehicle.${currentVehicleId.value}`)
            console.log(`✅ Canal vehicle.${currentVehicleId.value} cerrado`)
        } catch (error) {
            console.warn(`⚠️ Error cerrando canal vehicle.${currentVehicleId.value}:`, error)
        }
    }
    
    // Reset tracking
    activeChannels.value = []
    currentVehicleId.value = null
    isConnected.value = false
    isRealTimeActive.value = false
    
    console.log('🧹 Limpieza de WebSocket completada')
}

const setupWebSocketConnection = () => {
    if (!selectedVehicle.value || !window.Echo) {
        console.warn('❌ No se puede configurar WebSocket:', {
            vehicle: !!selectedVehicle.value,
            echo: !!window.Echo
        })
        return
    }
    
    // PASO 1: Limpiar conexiones anteriores ANTES de crear nuevas
    cleanupWebSocketConnections()
    
    try {
        console.log('🔧 Configurando WebSocket para vehículo:', selectedVehicle.value.id)
        
        // PASO 2: Configurar canal público de telemetría
        const publicChannel = 'telemetry'
        window.Echo.channel(publicChannel)
            .listen('.telemetry.updated', (data: any) => {
                console.log('📡 Datos recibidos en canal PÚBLICO:', data)
                
                // IMPORTANTE: Solo procesar si es del vehículo actual
                if (data.vehicle_id === selectedVehicle.value?.id) {
                    console.log('✅ Datos coinciden con vehículo actual, procesando...')
                    handleTelemetryUpdate(data)
                } else {
                    console.log('⚠️ Datos filtrados - no coinciden:', {
                        received: data.vehicle_id,
                        expected: selectedVehicle.value?.id,
                        action: 'IGNORADO'
                    })
                }
            })
            .error((error: any) => {
                console.error('❌ Error en canal público:', error)
            })
        
        activeChannels.value.push(publicChannel)
        console.log(`📡 Canal público ${publicChannel} configurado`)
        
        // PASO 3: Configurar canal privado del vehículo específico
        const privateChannel = `vehicle.${selectedVehicle.value.id}`
        window.Echo.private(privateChannel)
            .listen('.telemetry.updated', (data: any) => {
                console.log('📡 Datos recibidos en canal PRIVADO:', privateChannel, data)
                handleTelemetryUpdate(data)
            })
            .error((error: any) => {
                console.error(`❌ Error en canal privado ${privateChannel}:`, error)
            })
        
        activeChannels.value.push(privateChannel)
        currentVehicleId.value = selectedVehicle.value.id
        console.log(`📡 Canal privado ${privateChannel} configurado`)
        
        // PASO 4: Marcar como conectado
        isConnected.value = true
        connectionRetries.value = 0
        
        console.log('✅ WebSocket configurado correctamente')
        console.log('📋 Canales activos:', activeChannels.value)
        console.log('🎯 Vehículo objetivo:', currentVehicleId.value)
        
    } catch (error) {
        console.error('❌ Error configurando WebSocket:', error)
        isConnected.value = false
        
        // Reintentar conexión
        if (connectionRetries.value < maxRetries) {
            connectionRetries.value++
            console.log(`🔄 Reintentando conexión ${connectionRetries.value}/${maxRetries} en ${2000 * connectionRetries.value}ms`)
            setTimeout(() => {
                setupWebSocketConnection()
            }, 2000 * connectionRetries.value)
        } else {
            console.error('❌ Máximo de reintentos alcanzado')
        }
    }
}

const handleTelemetryUpdate = (data: any) => {
    try {
        console.log('📡 ===== PROCESANDO TELEMETRÍA =====')
        console.log('📡 Datos recibidos:', data)
        console.log('🎯 Vehículo actual:', currentVehicleId.value)
        console.log('📊 Vehicle ID en datos:', data.vehicle_id)
        
        // VALIDACIÓN EXTRA: Asegurar que los datos son del vehículo correcto
        if (data.vehicle_id !== currentVehicleId.value) {
            console.warn('⛔ DATOS FILTRADOS - Vehicle ID no coincide:', {
                received: data.vehicle_id,
                expected: currentVehicleId.value,
                action: 'RECHAZADO'
            })
            return // Salir sin procesar
        }
        
        console.log('✅ Validación de Vehicle ID exitosa, procesando...')
        
        if (data.data && typeof data.data === 'object') {
            console.log('✅ data.data es válido:', data.data)
            console.log('📊 PIDs recibidos:', Object.keys(data.data))
            
            // Activar modo tiempo real
            isRealTimeActive.value = true
            lastDataSource.value = 'realtime'
            console.log('🟢 Modo tiempo real ACTIVADO')
            
            // Limpiar timeout anterior
            if (realTimeTimeout.value) {
                clearTimeout(realTimeTimeout.value)
                console.log('🔄 Timeout anterior limpiado')
            }
            
            // Actualizar readings con los nuevos datos
            let updatedCount = 0
            Object.keys(data.data).forEach(pid => {
                const sensorData = data.data[pid]
                console.log(`📊 Procesando PID ${pid}:`, sensorData)
                
                if (sensorData && typeof sensorData.processed_value === 'number') {
                    const oldValue = sensorReadings.value[pid]
                    sensorReadings.value[pid] = sensorData.processed_value
                    updatedCount++
                    console.log(`✅ Actualizado ${pid}: ${oldValue} → ${sensorData.processed_value}`)
                } else {
                    console.warn(`⚠️ Datos inválidos para PID ${pid}:`, sensorData)
                }
            })
            
            lastUpdate.value = new Date()
            
            // Configurar timeout para desactivar modo tiempo real después de 2 minutos sin datos
            realTimeTimeout.value = setTimeout(() => {
                isRealTimeActive.value = false
                console.log('⏰ Timeout: Desactivando modo tiempo real por inactividad')
            }, 120000) // 2 minutos
            
            console.log('🔄 Sensores actualizados:', updatedCount)
            console.log('📊 Estado actual sensorReadings:', sensorReadings.value)
            console.log('📡 ===== FIN PROCESAMIENTO =====')
        } else {
            console.error('❌ data.data no es válido:', {
                data: data.data,
                type: typeof data.data,
                keys: data.data ? Object.keys(data.data) : 'N/A'
            })
        }
        
    } catch (error) {
        console.error('❌ Error procesando datos de telemetría:', error)
        console.error('❌ Stack trace:', error.stack)
        console.error('❌ Datos que causaron el error:', data)
    }
}

const fetchVehicleData = async (deviceId: number) => {
    if (!deviceId) return
    
    try {
        // PASO 1: Limpiar estado anterior ANTES de cargar nuevo vehículo
        console.log('🔄 Cambiando de vehículo, limpiando estado anterior...')
        cleanupWebSocketConnections()
        
        // Reset de estados
        selectedVehicle.value = null
        connectionStatus.value = null
        sensorReadings.value = {}
        isRealTimeActive.value = false
        
        isLoading.value = true
        error.value = null
        
        console.log('📡 Cargando datos para dispositivo:', deviceId)
        const response = await axios.get(`/vehicle/${deviceId}`)
        
        if (response.data.vehicle) {
            selectedVehicle.value = response.data.vehicle
            connectionStatus.value = response.data.connection_status
            
            console.log('🎯 Nuevo vehículo seleccionado:', {
                id: selectedVehicle.value.id,
                make: selectedVehicle.value.make,
                model: selectedVehicle.value.model
            })
            
            // Cargar datos históricos primero
            if (response.data.latest_readings?.data) {
                loadHistoricalData(response.data.latest_readings)
                console.log('📥 Datos históricos cargados:', Object.keys(response.data.latest_readings.data).length, 'sensores')
            } else {
                initializeSensorReadings()
            }
            
            // PASO 2: Configurar WebSocket para el NUEVO vehículo
            setupWebSocketConnection()
            
            if(selectedVehicle.value){
                console.log('✅ Vehículo cargado:', selectedVehicle.value.make, selectedVehicle.value.model)
            }
            console.log('📊 Estado de conexión:', connectionStatus.value?.status)
        }
        
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Error al cargar datos del vehículo'
        selectedVehicle.value = null
        connectionStatus.value = null
        console.error('❌ Error cargando vehículo:', err)
    } finally {
        isLoading.value = false
    }
}

const loadHistoricalData = (readings: any) => {
    if (readings.data && typeof readings.data === 'object') {
        sensorReadings.value = { ...readings.data }
        lastDataSource.value = readings.source
        
        if (readings.timestamp) {
            lastUpdate.value = new Date(readings.timestamp)
        }
        
        console.log('📊 Datos cargados desde:', readings.source)
        console.log('🕐 Última actualización:', readings.timestamp)
    }
}

const initializeSensorReadings = () => {
    const readings: Record<string, number> = {}
    
    // Inicializar con valores por defecto
    activeSensors.value.forEach(sensor => {
        const pid = sensor.sensor.pid
        
        // Valores por defecto realistas
        switch (pid) {
            case '0x0D': case 'vel_kmh': readings[pid] = 0; break
            case '0x0C': readings[pid] = 800; break // RPM idle
            case '0x05': readings[pid] = 85; break // Temp motor
            case '0x2F': readings[pid] = 75; break // Combustible
            case '0x0B': readings[pid] = 100; break // Presión MAP
            case '0x42': readings[pid] = 12.4; break // Batería
            case '0x11': readings[pid] = 0; break // Throttle
            case '0x04': readings[pid] = 0; break // Carga motor
            case '0x0F': readings[pid] = 25; break // Aire admisión
            case '0x10': readings[pid] = 0; break // MAF
            default: readings[pid] = 0
        }
    })
    
    sensorReadings.value = readings
}

const fetchLatestTelemetry = async () => {
    if (!selectedVehicle.value) return
    
    try {
        const response = await axios.get(`/telemetry/latest/${selectedVehicle.value.id}`)
        
        if (response.data.data) {
            Object.keys(response.data.data).forEach(pid => {
                sensorReadings.value[pid] = response.data.data[pid]
            })
            lastUpdate.value = new Date()
            console.log('📥 Datos de telemetría actualizados desde cache')
        }
    } catch (error) {
        console.error('Error fetching latest telemetry:', error)
    }
}

// Test functions para desarrollo
const testWebSocket = async () => {
    if (!selectedVehicle.value) return
    
    try {
        const response = await axios.post(`/test/websocket/${selectedVehicle.value.id}`)
        console.log('✅ Test WebSocket enviado:', response.data)
    } catch (error) {
        console.error('❌ Error en test WebSocket:', error)
    }
}

const simulateData = async () => {
    if (!selectedVehicle.value) return
    
    try {
        const response = await axios.post(`/test/simulate/${selectedVehicle.value.id}`)
        console.log('✅ Datos simulados enviados:', response.data)
    } catch (error) {
        console.error('❌ Error simulando datos:', error)
    }
}

// Watchers
watch(selectedDeviceId, (newId) => {
    if (newId) {
        fetchVehicleData(Number(newId))
    }
})

// Lifecycle
onMounted(() => {
    // Auto-select first device
    if (props.devices.length > 0) {
        selectedDeviceId.value = props.devices[0].id
    }
})

onUnmounted(() => {
    console.log('🧹 Componente Dashboard desmontándose...')
    
    // Limpiar conexiones WebSocket
    cleanupWebSocketConnections()
    
    // Limpiar timeouts
    if (realTimeTimeout.value) {
        clearTimeout(realTimeTimeout.value)
        console.log('✅ Timeout de tiempo real limpiado')
    }
    
    console.log('🧹 Cleanup completo del Dashboard')
})
</script>

<template>
    <Head title="Dashboard Telemetría" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-950 text-white">
            
            <!-- Header Component -->
            <DashboardHeader 
                :devices="devices"
                :selected-device-id="selectedDeviceId"
                :selected-vehicle="selectedVehicle"
                :is-loading="isLoading"
                :display-connection-status="displayConnectionStatus"
                :error="error"
                :last-update-formatted="lastUpdateFormatted"
                :active-sensors-count="activeSensors.length"
                :last-data-source="lastDataSource"
                @update:selected-device-id="selectedDeviceId = $event"
            />

            <!-- Main Layout: 65% Map + 35% Widgets -->
            <div class="flex h-[calc(100vh-100px)]">
                
                <!-- Map Component (65%) -->
                <MapWidget 
                    :selected-vehicle="selectedVehicle"
                    :is-loading="isLoading"
                    :is-real-time-active="isRealTimeActive"
                    :connection-status="connectionStatus"
                />

                <!-- Widgets Panel (35%) -->
                <div class="w-[35%] overflow-y-auto bg-slate-900/30">
                    <div class="space-y-6 p-6">

                        <!-- Primary Sensors Widget -->
                        <PrimarySensorsWidget 
                            v-if="primarySensors.length > 0"
                            :sensors="primarySensors"
                        />

                        <!-- Secondary Sensors Widget -->
                        <SecondarySensorsWidget 
                            v-if="secondarySensors.length > 0"
                            :sensors="secondarySensors"
                            :is-real-time-active="isRealTimeActive"
                        />

                        <!-- Throttle Position Widget -->
                        <ThrottleWidget 
                            v-if="throttleSensor"
                            :sensor="throttleSensor"
                            :is-real-time-active="isRealTimeActive"
                        />

                        <!-- All Other Sensors Widget -->
                        <AllSensorsWidget 
                            v-if="remainingSensors.length > 0"
                            :sensors="remainingSensors"
                            :is-real-time-active="isRealTimeActive"
                        />

                        <!-- Connection Status Widget -->
                        <ConnectionStatusWidget 
                            :is-connected="isConnected"
                            :is-real-time-active="isRealTimeActive"
                            :last-update-formatted="lastUpdateFormatted"
                            :connection-retries="connectionRetries"
                            :max-retries="maxRetries"
                            :selected-vehicle="selectedVehicle"
                            :connection-status="connectionStatus"
                            :last-data-source="lastDataSource"
                            @reconnect="setupWebSocketConnection"
                        />

                        <!-- Quick Actions Widget -->
                        <QuickActionsWidget 
                            :selected-vehicle="selectedVehicle"
                            :is-debug-mode="isDebugMode"
                            :active-sensors="activeSensors"
                            :sensor-readings="sensorReadings"
                            :get-sensor-value="getSensorValue"
                            @fetch-latest-telemetry="fetchLatestTelemetry"
                            @initialize-sensor-readings="initializeSensorReadings"
                            @test-websocket="testWebSocket"
                            @simulate-data="simulateData"
                        />

                        <!-- No Sensors Message -->
                        <div v-if="!isLoading && !hasSensors && selectedVehicle" class="rounded-xl border border-orange-500/30 bg-orange-500/10 p-6 text-center">
                            <div class="text-orange-400 mb-2 text-2xl">⚠️</div>
                            <h3 class="text-sm font-semibold text-orange-400 mb-2">Sin Sensores Configurados</h3>
                            <p class="text-xs text-slate-400">
                                Este vehículo no tiene sensores activos configurados.<br>
                                Configure los sensores para ver los widgets de telemetría.
                            </p>
                        </div>

                        <!-- Vehicle Selection Placeholder -->
                        <div v-if="!selectedVehicle && !isLoading" class="rounded-xl border border-cyan-500/30 bg-cyan-500/10 p-6 text-center">
                            <div class="text-cyan-400 mb-2 text-2xl">🚗</div>
                            <h3 class="text-sm font-semibold text-cyan-400 mb-2">Seleccionar Vehículo</h3>
                            <p class="text-xs text-slate-400">
                                Selecciona un dispositivo para comenzar a monitorear<br>
                                la telemetría en tiempo real.
                            </p>
                        </div>

                        <!-- Loading State -->
                        <div v-if="isLoading" class="rounded-xl border border-cyan-500/30 bg-cyan-500/10 p-6 text-center">
                            <div class="flex items-center justify-center mb-4">
                                <svg class="animate-spin h-8 w-8 text-cyan-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-cyan-400 mb-2">Cargando Vehículo</h3>
                            <p class="text-xs text-slate-400">
                                Obteniendo datos del vehículo y configurando sensores...
                            </p>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>