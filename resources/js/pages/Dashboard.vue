<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
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

// Props
const props = defineProps<{
    devices: Device[]
}>()

// State
const selectedDeviceId = ref<string | number>('')
const selectedVehicle = ref<Vehicle | null>(null)
const isConnected = ref(false)
const isLoading = ref(false)
const error = ref<string | null>(null)
const currentTime = ref(new Date())

// WebSocket/Echo
const lastUpdate = ref<Date | null>(null)
const connectionRetries = ref(0)
const maxRetries = 5

// Sensor readings - valores en tiempo real
const sensorReadings = ref<Record<string, number>>({})

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
]

// Computed
const formattedTime = computed(() => {
    return currentTime.value.toLocaleTimeString('es-MX', { hour12: false })
})

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

// Sensores principales que queremos mostrar
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

const additionalSensors = computed(() => {
    const sensors = []
    
    // Carga motor
    if (sensorMap.value['0x04']) {
        sensors.push({
            id: 'engineLoad',
            title: 'Carga Motor',
            sensor: sensorMap.value['0x04'],
            value: getSensorValue('0x04'),
            defaultValue: 0
        })
    }
    
    // Aire admisión
    if (sensorMap.value['0x0F']) {
        sensors.push({
            id: 'intakeTemp',
            title: 'Aire Admisión',
            sensor: sensorMap.value['0x0F'],
            value: getSensorValue('0x0F'),
            defaultValue: 25
        })
    }
    
    // Flujo MAF
    if (sensorMap.value['0x10']) {
        sensors.push({
            id: 'mafFlow',
            title: 'Flujo MAF',
            sensor: sensorMap.value['0x10'],
            value: getSensorValue('0x10'),
            defaultValue: 0.0
        })
    }
    
    return sensors
})

// Formatted last update time
const lastUpdateFormatted = computed(() => {
    if (!lastUpdate.value) return 'Sin datos'
    const diff = Date.now() - lastUpdate.value.getTime()
    if (diff < 1000) return 'Ahora'
    if (diff < 60000) return `${Math.floor(diff / 1000)}s`
    return lastUpdate.value.toLocaleTimeString('es-MX', { hour12: false })
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

const fetchVehicleData = async (deviceId: number) => {
    if (!deviceId) return
    
    try {
        isLoading.value = true
        error.value = null
        
        const response = await axios.get(`/vehicle/${deviceId}`)
        
        if (response.data.vehicle) {
            selectedVehicle.value = response.data.vehicle
            initializeSensorReadings()
            setupWebSocketConnection()
            console.log('Vehicle loaded:', selectedVehicle.value.make, selectedVehicle.value.model)
        }
        
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Error al cargar datos del vehículo'
        selectedVehicle.value = null
    } finally {
        isLoading.value = false
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

const setupWebSocketConnection = () => {
    if (!selectedVehicle.value || !window.Echo) return
    
    try {
        console.log('🔧 Configurando WebSocket para vehículo:', selectedVehicle.value.id)
        
        // Limpiar listeners anteriores
        window.Echo.leave('telemetry')
        if (selectedVehicle.value.id) {
            window.Echo.leave(`vehicle.${selectedVehicle.value.id}`)
        }
        
        // Escuchar canal público de telemetría
        window.Echo.channel('telemetry')
            .listen('.telemetry.updated', (data: any) => {
                console.log('📡 Datos recibidos en canal público:', data)
                if (data.vehicle_id === selectedVehicle.value?.id) {
                    handleTelemetryUpdate(data)
                }
            })
        
        // Escuchar canal privado del vehículo específico
        window.Echo.private(`vehicle.${selectedVehicle.value.id}`)
            .listen('.telemetry.updated', (data: any) => {
                console.log('📡 Datos recibidos en canal privado:', data)
                handleTelemetryUpdate(data)
            })
        
        // Simular conexión exitosa después de un delay
        setTimeout(() => {
            isConnected.value = true
            connectionRetries.value = 0
            console.log('✅ WebSocket conectado para vehículo:', selectedVehicle.value?.id)
        }, 1000)
        
    } catch (error) {
        console.error('❌ Error configurando WebSocket:', error)
        isConnected.value = false
        
        // Reintentar conexión
        if (connectionRetries.value < maxRetries) {
            connectionRetries.value++
            setTimeout(() => {
                setupWebSocketConnection()
            }, 2000 * connectionRetries.value) // Backoff exponencial
        }
    }
}

const handleTelemetryUpdate = (data: any) => {
    try {
        console.log('📡 Procesando datos de telemetría:', data)
        
        if (data.data && typeof data.data === 'object') {
            // Actualizar readings con los nuevos datos
            Object.keys(data.data).forEach(pid => {
                const sensorData = data.data[pid]
                if (sensorData && typeof sensorData.processed_value === 'number') {
                    sensorReadings.value[pid] = sensorData.processed_value
                }
            })
            
            lastUpdate.value = new Date()
            
            // Log para debug
            console.log('🔄 Sensores actualizados:', Object.keys(data.data).length)
        }
        
    } catch (error) {
        console.error('Error procesando datos de telemetría:', error)
    }
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

const simulateRealTimeData = () => {
    // Solo simular si no hay conexión real
    const interval = setInterval(() => {
        if (isConnected.value || !selectedVehicle.value) {
            clearInterval(interval)
            return
        }
        
        // Simular variaciones realistas solo si no hay datos reales
        Object.keys(sensorReadings.value).forEach(pid => {
            const sensor = sensorMap.value[pid]
            if (!sensor) return
            
            const currentValue = sensorReadings.value[pid]
            let variation = 0
            
            // Variaciones específicas por tipo de sensor
            switch (pid) {
                case '0x0D': case 'vel_kmh': // Velocidad
                    variation = (Math.random() - 0.5) * 5
                    break
                case '0x0C': // RPM
                    variation = (Math.random() - 0.5) * 100
                    break
                case '0x05': // Temperatura motor
                    variation = (Math.random() - 0.5) * 1
                    break
                case '0x2F': // Combustible (decrece lentamente)
                    variation = -Math.random() * 0.05
                    break
                case '0x0B': // Presión MAP
                    variation = (Math.random() - 0.5) * 3
                    break
                case '0x42': // Batería
                    variation = (Math.random() - 0.5) * 0.1
                    break
                case '0x11': // Throttle
                    variation = (Math.random() - 0.5) * 10
                    break
                default:
                    variation = (Math.random() - 0.5) * 2
            }
            
            const newValue = currentValue + variation
            const min = sensor.sensor.min_value || 0
            const max = sensor.sensor.max_value || 255
            
            sensorReadings.value[pid] = Math.max(min, Math.min(max, newValue))
        })
        
        lastUpdate.value = new Date()
    }, 2000) // Actualizar cada 2 segundos en modo simulación
}

// Test functions
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
    // Clock
    setInterval(() => {
        currentTime.value = new Date()
    }, 1000)

    // Auto-select first device
    if (props.devices.length > 0) {
        selectedDeviceId.value = props.devices[0].id
    }
    
    // Start simulation
    setTimeout(() => {
        simulateRealTimeData()
    }, 2000)
})

onUnmounted(() => {
    // Limpiar conexiones WebSocket
    if (window.Echo && selectedVehicle.value) {
        window.Echo.leave('telemetry')
        window.Echo.leave(`vehicle.${selectedVehicle.value.id}`)
    }
})
</script>

<template>
    <Head title="Dashboard Telemetría" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-950 text-white">
            
            <!-- Header -->
            <div class="border-b border-cyan-500/20 bg-slate-900/95 px-6 py-4 backdrop-blur-xl">
                <div class="flex items-center justify-between">
                    
                    <!-- Brand -->
                    <div>
                        <p class="text-sm text-slate-400">Monitoreo en Tiempo Real</p>
                    </div>

                    <!-- Device Selector -->
                    <div class="flex items-center gap-6">
                        <div>
                            <label class="mb-1 block text-xs tracking-wide text-slate-400 uppercase">
                                Dispositivo
                            </label>
                            <select
                                v-model="selectedDeviceId"
                                :disabled="isLoading"
                                class="min-w-[200px] rounded-lg border border-cyan-500/30 bg-slate-800/80 px-4 py-2 text-sm text-white focus:border-cyan-400 focus:outline-none disabled:opacity-50"
                            >
                                <option value="" disabled>Seleccionar dispositivo...</option>
                                <option v-for="device in devices" :key="device.id" :value="device.id">
                                    {{ device.device_name || device.device_inventory.serial_number }}
                                </option>
                            </select>
                        </div>

                        <!-- Loading -->
                        <div v-if="isLoading" class="flex items-center gap-2 text-cyan-400">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">Cargando...</span>
                        </div>

                        <!-- Error -->
                        <div v-if="error" class="text-red-400 text-sm bg-red-500/10 px-3 py-2 rounded-lg border border-red-500/30">
                            {{ error }}
                        </div>

                        <!-- Vehicle Info -->
                        <div v-if="selectedVehicle && !isLoading" class="rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-4 py-2">
                            <div class="text-xs tracking-wide text-slate-400 uppercase">Monitoreando</div>
                            <div class="text-sm font-semibold text-cyan-400">
                                {{ selectedVehicle.nickname || `${selectedVehicle.make} ${selectedVehicle.model}` }}
                            </div>
                            <div class="text-xs text-slate-500">
                                VIN: {{ selectedVehicle.vin.slice(-6) }} | {{ activeSensors.length }} sensores
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="flex items-center gap-6">
                        <div class="font-mono text-lg font-semibold text-cyan-400">
                            {{ formattedTime }}
                        </div>
                        <div class="flex items-center gap-2 rounded-full border border-cyan-500/30 bg-cyan-500/10 px-3 py-2">
                            <div
                                class="h-2 w-2 rounded-full transition-all duration-300"
                                :class="isConnected ? 'animate-pulse bg-cyan-400' : 'bg-red-400'"
                            />
                            <span class="text-sm font-medium">
                                {{ isConnected ? 'En Vivo' : 'Simulado' }}
                            </span>
                        </div>
                        <div class="text-sm text-slate-400">
                            Última actualización: {{ lastUpdateFormatted }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Layout: 65% Map + 35% Widgets -->
            <div class="flex h-[calc(100vh-100px)]">
                
                <!-- Map Area (65%) -->
                <div class="relative w-[65%] border-r border-cyan-500/20 bg-slate-900/50">
                    <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-900">
                        <div class="text-center">
                            <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full border-2 border-cyan-500/40 bg-cyan-500/20">
                                <svg class="h-12 w-12 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <h3 class="mb-2 text-xl font-semibold text-cyan-400">Mapa de Seguimiento GPS</h3>
                            <p class="text-sm text-slate-400">
                                <span v-if="selectedVehicle && !isLoading">
                                    Rastreando: {{ selectedVehicle.nickname || `${selectedVehicle.make} ${selectedVehicle.model}` }}
                                </span>
                                <span v-else-if="isLoading">Cargando datos del vehículo...</span>
                                <span v-else>Selecciona un dispositivo para comenzar el seguimiento</span>
                            </p>
                        </div>
                        
                        <!-- Map Controls -->
                        <div class="absolute top-6 right-6 flex flex-col gap-2">
                            <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-cyan-500/30 bg-slate-800/80 text-cyan-400 transition-all duration-200 hover:bg-slate-700/80">
                                <span class="text-lg font-bold">+</span>
                            </button>
                            <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-cyan-500/30 bg-slate-800/80 text-cyan-400 transition-all duration-200 hover:bg-slate-700/80">
                                <span class="text-lg font-bold">-</span>
                            </button>
                        </div>

                        <!-- Real-time indicator -->
                        <div v-if="isConnected" class="absolute top-6 left-6">
                            <div class="flex items-center gap-2 rounded-lg border border-green-500/30 bg-green-500/10 px-3 py-2">
                                <div class="h-2 w-2 rounded-full bg-green-400 animate-pulse"/>
                                <span class="text-xs text-green-400 font-medium">DATOS EN VIVO</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Widgets Panel (35%) -->
                <div class="w-[35%] overflow-y-auto bg-slate-900/30">
                    <div class="space-y-6 p-6">

                        <!-- Primary Sensors (Speed & RPM) -->
                        <div v-if="primarySensors.length > 0" class="grid gap-4" :class="primarySensors.length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
                            <div 
                                v-for="sensor in primarySensors" 
                                :key="sensor.id"
                                class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40"
                            >
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-xs font-semibold tracking-wide text-slate-300 uppercase">{{ sensor.title }}</h3>
                                    <svg class="h-4 w-4 text-cyan-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
                                    </svg>
                                </div>
                                <div class="mb-3 flex h-20 w-full items-center justify-center rounded-lg border border-cyan-500/20 bg-slate-800/50">
                                    <span class="text-xs text-cyan-400">{{ sensor.title }}</span>
                                </div>
                                <div class="text-center">
                                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensor.value }}</span>
                                    <span class="ml-1 text-xs text-slate-400">{{ sensor.sensor.unit }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Secondary Sensors -->
                        <div v-if="secondarySensors.length > 0" class="grid gap-4" :class="secondarySensors.length <= 2 ? 'grid-cols-2' : 'grid-cols-1'">
                            <div 
                                v-for="sensor in secondarySensors" 
                                :key="sensor.id"
                                class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40"
                            >
                                <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-300 uppercase">
                                    {{ sensor.emoji ? sensor.emoji + ' ' : '' }}{{ sensor.title }}
                                </h3>
                                <div class="flex items-baseline gap-1">
                                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensor.value }}</span>
                                    <span class="text-xs text-slate-400">{{ sensor.sensor.unit }}</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ sensor.sensor.description }}</div>
                                
                                <!-- Real-time indicator per sensor -->
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="text-xs text-slate-600">{{ sensor.sensor.pid }}</div>
                                    <div v-if="isConnected" class="flex items-center gap-1">
                                        <div class="h-1 w-1 rounded-full bg-green-400 animate-pulse"/>
                                        <span class="text-xs text-green-400">LIVE</span>
                                    </div>
                                </div>
                            </div>
                        </div>
<!-- Throttle Position (Progress Bar) -->
                        <div v-if="throttleSensor" class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-xs font-semibold tracking-wide text-slate-300 uppercase">
                                    {{ throttleSensor.emoji }} {{ throttleSensor.title }}
                                </h3>
                                <div class="flex items-center gap-1">
                                    <span class="font-mono text-xl font-bold text-cyan-400">{{ throttleSensor.value }}</span>
                                    <span class="text-xs text-slate-400">{{ throttleSensor.sensor.unit }}</span>
                                </div>
                            </div>
                            <div class="mb-2 h-3 w-full rounded-full bg-slate-800/50 overflow-hidden">
                                <div 
                                    class="h-3 rounded-full bg-gradient-to-r from-cyan-500 to-cyan-400 transition-all duration-500 ease-out"
                                    :style="`width: ${Math.min(100, Math.max(0, throttleSensor.value))}%`"
                                />
                            </div>
                            <div class="flex justify-between text-xs text-slate-500">
                                <span>0%</span>
                                <span>50%</span>
                                <span>100%</span>
                            </div>
                            
                            <!-- Real-time data indicator -->
                            <div class="mt-2 flex items-center justify-between">
                                <div class="text-xs text-slate-600">{{ throttleSensor.sensor.pid }}</div>
                                <div v-if="isConnected" class="flex items-center gap-1">
                                    <div class="h-1 w-1 rounded-full bg-green-400 animate-pulse"/>
                                    <span class="text-xs text-green-400">ACTUALIZANDO</span>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Data Grid -->
                        <div v-if="additionalSensors.length > 0" class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                            <h3 class="mb-4 text-xs font-semibold tracking-wide text-slate-300 uppercase">📊 Datos Adicionales</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div 
                                    v-for="sensor in additionalSensors" 
                                    :key="sensor.id"
                                    class="rounded-lg border border-slate-700/30 bg-slate-900/40 p-3 transition-all duration-200 hover:border-slate-600/50"
                                >
                                    <div class="mb-1 text-xs tracking-wide text-slate-400 uppercase">{{ sensor.title }}</div>
                                    <div class="font-mono text-sm font-semibold text-cyan-400 mb-1">
                                        {{ sensor.value }}{{ sensor.sensor.unit }}
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="text-xs text-slate-600">{{ sensor.sensor.pid }}</div>
                                        <div v-if="isConnected" class="h-1 w-1 rounded-full bg-green-400 animate-pulse"/>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Connection Status Card -->
                        <div class="rounded-xl border p-4 backdrop-blur-xl transition-all duration-300"
                             :class="isConnected 
                                ? 'border-green-500/20 bg-gradient-to-br from-green-800/20 to-green-900/20' 
                                : 'border-orange-500/20 bg-gradient-to-br from-orange-800/20 to-orange-900/20'"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-semibold tracking-wide uppercase"
                                    :class="isConnected ? 'text-green-300' : 'text-orange-300'"
                                >
                                    Estado de Conexión
                                </h3>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-2 rounded-full"
                                         :class="isConnected ? 'bg-green-400 animate-pulse' : 'bg-orange-400'"
                                    />
                                    <span class="text-xs font-medium"
                                          :class="isConnected ? 'text-green-400' : 'text-orange-400'"
                                    >
                                        {{ isConnected ? 'WebSocket Conectado' : 'Modo Simulación' }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="space-y-2 text-xs text-slate-400">
                                <div class="flex justify-between">
                                    <span>Última actualización:</span>
                                    <span class="font-mono">{{ lastUpdateFormatted }}</span>
                                </div>
                                <div v-if="!isConnected && connectionRetries > 0" class="flex justify-between">
                                    <span>Reintentos:</span>
                                    <span class="font-mono">{{ connectionRetries }}/{{ maxRetries }}</span>
                                </div>
                                <div v-if="selectedVehicle" class="flex justify-between">
                                    <span>Vehículo ID:</span>
                                    <span class="font-mono">#{{ selectedVehicle.id }}</span>
                                </div>
                            </div>
                            
                            <!-- Reconnect button when disconnected -->
                            <div v-if="!isConnected && selectedVehicle" class="mt-3">
                                <button 
                                    @click="setupWebSocketConnection"
                                    class="w-full px-3 py-2 text-xs font-medium rounded-lg border border-orange-500/30 bg-orange-500/10 text-orange-400 hover:bg-orange-500/20 transition-all duration-200"
                                >
                                    🔄 Reconectar WebSocket
                                </button>
                            </div>
                        </div>

                        <!-- No Sensors Message -->
                        <div v-if="!isLoading && !hasSensors && selectedVehicle" class="rounded-xl border border-orange-500/30 bg-orange-500/10 p-6 text-center">
                            <div class="text-orange-400 mb-2 text-2xl">⚠️</div>
                            <h3 class="text-sm font-semibold text-orange-400 mb-2">Sin Sensores Configurados</h3>
                            <p class="text-xs text-slate-400">
                                Este vehículo no tiene sensores activos configurados.<br>
                                Configure los sensores para ver los widgets de telemetría.
                            </p>
                        </div>

                        <!-- Debug Info (Development Only) -->
                        <div v-if="hasSensors && isDebugMode" class="rounded-xl border border-slate-700/30 bg-slate-800/30 p-4">
                            <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                                🔧 Debug Info - Sensores Activos ({{ activeSensors.length }})
                            </h3>
                            <div class="space-y-1 max-h-32 overflow-y-auto">
                                <div v-for="sensor in activeSensors" :key="sensor.id" class="text-xs flex justify-between items-center">
                                    <span class="text-cyan-400 font-mono">{{ sensor.sensor.pid }}</span>
                                    <span class="text-slate-300">{{ sensor.sensor.name }}</span>
                                    <span class="text-slate-500">({{ sensor.sensor.unit }})</span>
                                    <span class="font-mono text-green-400">{{ getSensorValue(sensor.sensor.pid) }}</span>
                                </div>
                            </div>
                            
                            <!-- Raw data toggle -->
                            <div class="mt-3 pt-3 border-t border-slate-700/50">
                                <details class="cursor-pointer">
                                    <summary class="text-xs text-slate-500 hover:text-slate-400">Ver datos raw</summary>
                                    <pre class="mt-2 text-xs text-slate-600 bg-slate-900/50 p-2 rounded overflow-auto max-h-32">{{ JSON.stringify(sensorReadings, null, 2) }}</pre>
                                </details>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div v-if="selectedVehicle" class="rounded-xl border border-slate-700/30 bg-slate-800/30 p-4">
                            <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                                ⚡ Acciones Rápidas
                            </h3>
                            <div class="grid grid-cols-2 gap-2">
                                <button 
                                    @click="fetchLatestTelemetry"
                                    class="px-3 py-2 text-xs font-medium rounded-lg border border-cyan-500/30 bg-cyan-500/10 text-cyan-400 hover:bg-cyan-500/20 transition-all duration-200"
                                >
                                    🔄 Actualizar Datos
                                </button>
                                <button 
                                    @click="initializeSensorReadings"
                                    class="px-3 py-2 text-xs font-medium rounded-lg border border-slate-500/30 bg-slate-500/10 text-slate-400 hover:bg-slate-500/20 transition-all duration-200"
                                >
                                    🔄 Reset Sensores
                                </button>
                            </div>
                            
                            <!-- Test actions (development only) -->
                            <div v-if="isDebugMode" class="mt-2 grid grid-cols-2 gap-2">
                                <button 
                                    @click="testWebSocket"
                                    class="px-3 py-2 text-xs font-medium rounded-lg border border-purple-500/30 bg-purple-500/10 text-purple-400 hover:bg-purple-500/20 transition-all duration-200"
                                >
                                    📡 Test WebSocket
                                </button>
                                <button 
                                    @click="simulateData"
                                    class="px-3 py-2 text-xs font-medium rounded-lg border border-yellow-500/30 bg-yellow-500/10 text-yellow-400 hover:bg-yellow-500/20 transition-all duration-200"
                                >
                                    🎲 Simular Datos
                                </button>
                            </div>
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