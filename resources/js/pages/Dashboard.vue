<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'

// Interfaces - Manteniendo tu estructura original
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

interface VehicleSensor {
    id: number
    vehicle_id: number
    sensor_id: number
    is_active: boolean
    frequency_seconds: number
    min_value?: number
    max_value?: number
    last_reading_at?: string
    sensor: {
        id: number
        pid: string
        name: string
        description: string
        category: string
        unit: string
        data_type: string
        min_value?: number
        max_value?: number
    }
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

// Props - Igual que tu componente original
const props = defineProps<{
    devices: Device[]
}>()

// State - Basado en tu estructura
const selectedVehicle = ref<Vehicle | null>(null)
const vehicleData = ref<Vehicle | null>(null)
const isConnected = ref(false)
const isLoading = ref(false)
const currentTime = ref(new Date())
const selectedDeviceId = ref<string | number>('')
const error = ref<string | null>(null)

// Breadcrumbs - Igual que tu componente
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
]

// Computed - Manteniendo tu lógica original
const formattedTime = computed(() => {
    return currentTime.value.toLocaleTimeString('es-MX', { hour12: false })
})

// Nuevos computed para manejar sensores
const activeSensors = computed(() => {
    return vehicleData.value?.vehicle_sensors?.filter(vs => vs.is_active) || []
})

const sensorValues = computed(() => {
    const sensors = activeSensors.value
    const getValue = (pid: string, defaultValue: any = 0) => {
        const sensor = sensors.find(s => s.sensor.pid === pid)
        return sensor ? defaultValue : defaultValue // Por ahora retornamos defaults, después conectaremos datos reales
    }
    
    return {
        speed: getValue('0x0D', 0),
        rpm: getValue('0x0C', 0),
        coolantTemp: getValue('0x05', 85),
        fuelLevel: getValue('0x2F', 75),
        mapPressure: getValue('0x0B', 100),
        batteryVoltage: getValue('0x42', 12.4),
        throttlePosition: getValue('0x11', 0),
        engineLoad: getValue('0x04', 0),
        intakeTemp: getValue('0x0F', 25),
        mafFlow: getValue('0x10', 0.0)
    }
})

// Methods - Nueva funcionalidad para el endpoint
const fetchVehicleData = async (deviceId: number) => {
    if (!deviceId) return
    
    try {
        isLoading.value = true
        error.value = null
        
        const response = await axios.get(`/vehicle/${deviceId}`)
        
        if (response.data.vehicle) {
            vehicleData.value = response.data.vehicle
            selectedVehicle.value = response.data.vehicle
            console.log('Vehicle data loaded:', response.data.vehicle)
        }
        
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Error al cargar datos del vehículo'
        console.error('Error fetching vehicle data:', err)
        vehicleData.value = null
        selectedVehicle.value = null
    } finally {
        isLoading.value = false
    }
}

// Watcher para el cambio de dispositivo
watch(selectedDeviceId, (newId) => {
    console.log('Selected Device ID:', newId)
    if (newId) {
        fetchVehicleData(Number(newId))
    }
})

// Lifecycle - Manteniendo tu lógica original
onMounted(() => {
    // Update clock every second
    setInterval(() => {
        currentTime.value = new Date()
    }, 1000)

    // Simulate connection
    setTimeout(() => {
        isConnected.value = true
    }, 1000)

    // Auto-select first device
    if (props.devices.length > 0) {
        selectedDeviceId.value = props.devices[0].id
    }
})
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-950 text-white">
            <!-- Header with Device/Vehicle Selector -->
            <div class="border-b border-cyan-500/20 bg-slate-900/95 px-6 py-4 backdrop-blur-xl">
                <div class="flex items-center justify-between">
                    <!-- Left: Brand -->
                    <div>
                        <p class="text-sm text-slate-400">Monitoreo en Tiempo Real</p>
                    </div>

                    <!-- Center: Selectors -->
                    <div class="flex items-center gap-6">
                        <!-- Device Selector -->
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

                        <!-- Loading/Error Display -->
                        <div v-if="isLoading" class="flex items-center gap-2 text-cyan-400">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">Cargando...</span>
                        </div>

                        <div v-if="error" class="text-red-400 text-sm bg-red-500/10 px-3 py-2 rounded-lg border border-red-500/30">
                            {{ error }}
                        </div>

                        <!-- Selected Vehicle Info -->
                        <div v-if="selectedVehicle && !isLoading" class="rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-4 py-2">
                            <div class="text-xs tracking-wide text-slate-400 uppercase">Monitoreando</div>
                            <div class="text-sm font-semibold text-cyan-400">
                                {{ selectedVehicle.nickname || `${selectedVehicle.make} ${selectedVehicle.model}` }}
                            </div>
                            <div class="text-xs text-slate-500">
                                VIN: {{ selectedVehicle.vin.slice(-6) }}
                                <span v-if="activeSensors.length > 0"> | {{ activeSensors.length }} sensores</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Status -->
                    <div class="flex items-center gap-6">
                        <!-- Time -->
                        <div class="font-mono text-lg font-semibold text-cyan-400">
                            {{ formattedTime }}
                        </div>

                        <!-- Connection Status -->
                        <div class="flex items-center gap-2 rounded-full border border-cyan-500/30 bg-cyan-500/10 px-3 py-2">
                            <div
                                class="h-2 w-2 rounded-full transition-all duration-300"
                                :class="isConnected ? 'animate-pulse bg-cyan-400' : 'bg-red-400'"
                            />
                            <span class="text-sm font-medium">
                                {{ isConnected ? 'Conectado' : 'Desconectado' }}
                            </span>
                        </div>

                        <!-- Date -->
                        <div class="text-sm text-slate-400">
                            {{ new Date().toLocaleDateString('es-MX') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Layout: 65% Map + 35% Widgets -->
            <div class="flex h-[calc(100vh-100px)]">
                <!-- Map Area (65%) -->
                <div class="relative w-[65%] border-r border-cyan-500/20 bg-slate-900/50">
                    <!-- Map Placeholder -->
                    <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-900">
                        <div class="text-center">
                            <!-- Map Icon -->
                            <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full border-2 border-cyan-500/40 bg-cyan-500/20">
                                <svg class="h-12 w-12 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>

                            <!-- Map Title -->
                            <h3 class="mb-2 text-xl font-semibold text-cyan-400">Mapa de Seguimiento GPS</h3>

                            <!-- Map Status -->
                            <p class="text-sm text-slate-400">
                                <span v-if="selectedVehicle && !isLoading">
                                    Rastreando: {{ selectedVehicle.nickname || `${selectedVehicle.make} ${selectedVehicle.model}` }}
                                </span>
                                <span v-else-if="isLoading">
                                    Cargando datos del vehículo...
                                </span>
                                <span v-else>
                                    Selecciona un dispositivo para comenzar el seguimiento
                                </span>
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
                    </div>
                </div>

                <!-- Widgets Panel (35%) - Manteniendo tu estructura exacta -->
                <div class="w-[35%] overflow-y-auto bg-slate-900/30">
                    <div class="space-y-6 p-6">
                        <!-- Primary Metrics: Speed & RPM -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Speedometer -->
                            <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-xs font-semibold tracking-wide text-slate-300 uppercase">Velocidad</h3>
                                    <svg class="h-4 w-4 text-cyan-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
                                    </svg>
                                </div>
                                <!-- Speedometer Component Placeholder -->
                                <div class="mb-3 flex h-20 w-full items-center justify-center rounded-lg border border-cyan-500/20 bg-slate-800/50">
                                    <span class="text-xs text-cyan-400">Velocímetro</span>
                                </div>
                                <div class="text-center">
                                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensorValues.speed }}</span>
                                    <span class="ml-1 text-xs text-slate-400">km/h</span>
                                </div>
                            </div>

                            <!-- Tachometer -->
                            <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-xs font-semibold tracking-wide text-slate-300 uppercase">RPM</h3>
                                    <svg class="h-4 w-4 text-cyan-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                </div>
                                <!-- Tachometer Component Placeholder -->
                                <div class="mb-3 flex h-20 w-full items-center justify-center rounded-lg border border-cyan-500/20 bg-slate-800/50">
                                    <span class="text-xs text-cyan-400">Tacómetro</span>
                                </div>
                                <div class="text-center">
                                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensorValues.rpm }}</span>
                                    <span class="ml-1 text-xs text-slate-400">RPM</span>
                                </div>
                            </div>
                        </div>

                        <!-- Secondary Metrics -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Temperature -->
                            <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                                <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-300 uppercase">🌡️ Temperatura</h3>
                                <div class="flex items-baseline gap-1">
                                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensorValues.coolantTemp }}</span>
                                    <span class="text-xs text-slate-400">°C</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">Motor</div>
                            </div>

                            <!-- Fuel -->
                            <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                                <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-300 uppercase">⛽ Combustible</h3>
                                <div class="flex items-baseline gap-1">
                                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensorValues.fuelLevel }}</span>
                                    <span class="text-xs text-slate-400">%</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">Nivel</div>
                            </div>
                        </div>

                        <!-- Tertiary Metrics -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Pressure -->
                            <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                                <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-300 uppercase">⚡ Presión</h3>
                                <div class="flex items-baseline gap-1">
                                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensorValues.mapPressure }}</span>
                                    <span class="text-xs text-slate-400">kPa</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">MAP</div>
                            </div>

                            <!-- Battery -->
                            <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                                <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-300 uppercase">🔋 Batería</h3>
                                <div class="flex items-baseline gap-1">
                                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensorValues.batteryVoltage }}</span>
                                    <span class="text-xs text-slate-400">V</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">Voltaje</div>
                            </div>
                        </div>

                        <!-- Throttle Position -->
                        <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-xs font-semibold tracking-wide text-slate-300 uppercase">⚙️ Posición Mariposa</h3>
                                <div class="flex items-center gap-1">
                                    <span class="font-mono text-xl font-bold text-cyan-400">{{ sensorValues.throttlePosition }}</span>
                                    <span class="text-xs text-slate-400">%</span>
                                </div>
                            </div>
                            <!-- Progress Bar -->
                            <div class="mb-2 h-2 w-full rounded-full bg-slate-800/50">
                                <div 
                                    class="h-2 rounded-full bg-gradient-to-r from-cyan-500 to-cyan-400 transition-all duration-500"
                                    :style="`width: ${sensorValues.throttlePosition}%`"
                                ></div>
                            </div>
                            <div class="flex justify-between text-xs text-slate-500">
                                <span>0%</span>
                                <span>50%</span>
                                <span>100%</span>
                            </div>
                        </div>

                        <!-- Additional Data -->
                        <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
                            <h3 class="mb-4 text-xs font-semibold tracking-wide text-slate-300 uppercase">📊 Datos Adicionales</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-lg border border-slate-700/30 bg-slate-900/40 p-3">
                                    <div class="mb-1 text-xs tracking-wide text-slate-400 uppercase">Carga Motor</div>
                                    <div class="font-mono text-sm font-semibold text-cyan-400">{{ sensorValues.engineLoad }}%</div>
                                </div>
                                <div class="rounded-lg border border-slate-700/30 bg-slate-900/40 p-3">
                                    <div class="mb-1 text-xs tracking-wide text-slate-400 uppercase">Aire Admisión</div>
                                    <div class="font-mono text-sm font-semibold text-cyan-400">{{ sensorValues.intakeTemp }}°C</div>
                                </div>
                                <div class="rounded-lg border border-slate-700/30 bg-slate-900/40 p-3">
                                    <div class="mb-1 text-xs tracking-wide text-slate-400 uppercase">Flujo MAF</div>
                                    <div class="font-mono text-sm font-semibold text-cyan-400">{{ sensorValues.mafFlow }} g/s</div>
                                </div>
                                <div class="rounded-lg border border-slate-700/30 bg-slate-900/40 p-3">
                                    <div class="mb-1 text-xs tracking-wide text-slate-400 uppercase">Sensores Activos</div>
                                    <div class="font-mono text-sm font-semibold text-cyan-400">{{ activeSensors.length }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Debug Info (solo si hay sensores) -->
                        <div v-if="activeSensors.length > 0" class="rounded-xl border border-slate-700/30 bg-slate-800/30 p-4">
                            <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">🔧 Sensores Detectados</h3>
                            <div class="space-y-1 max-h-32 overflow-y-auto">
                                <div v-for="sensor in activeSensors" :key="sensor.id" class="text-xs flex justify-between">
                                    <span class="text-cyan-400 font-mono">{{ sensor.sensor.pid }}</span>
                                    <span class="text-slate-300">{{ sensor.sensor.name }}</span>
                                    <span class="text-slate-500">({{ sensor.sensor.unit }})</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>