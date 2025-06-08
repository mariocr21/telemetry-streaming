<script setup lang="ts">
import SpeedometerWidget from './SpeedometerWidget.vue'
import TachometerWidget from './TachometerWidget.vue'

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

interface SensorData {
    id: string
    title: string
    sensor: VehicleSensor
    value: number
    defaultValue: number
}

defineProps<{
    sensors: SensorData[]
}>()

// Identificar qué sensor es el de velocidad
const isSpeedSensor = (sensor: SensorData) => {
    return sensor.title.toLowerCase().includes('velocidad') || 
           sensor.sensor?.sensor?.pid === '0x0D' ||
           sensor.sensor?.sensor?.pid === 'vel_kmh'
}

// Identificar qué sensor es el de RPM
const isRpmSensor = (sensor: SensorData) => {
    return sensor.title.toLowerCase().includes('rpm') || 
           sensor.sensor?.sensor?.pid === '0x0C'
}
</script>

<template>
    <!-- Primary Sensors (Speed & RPM) -->
    <div class="grid gap-4" :class="sensors.length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
        <div 
            v-for="sensor in sensors" 
            :key="sensor.id"
            class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40"
        >
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-xs font-semibold tracking-wide text-slate-300 uppercase">{{ sensor.title }}</h3>
                <svg class="h-4 w-4 text-cyan-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
                </svg>
            </div>
            
            <!-- Área del widget -->
            <div class="mb-3 h-56 w-full rounded-lg border border-cyan-500/20 bg-slate-800/50 overflow-hidden">
                <!-- Velocímetro para sensor de velocidad -->
                <SpeedometerWidget 
                    v-if="isSpeedSensor(sensor)" 
                    :sensor="sensor" 
                />
                
                <!-- Tacómetro para sensor de RPM -->
                <TachometerWidget 
                    v-else-if="isRpmSensor(sensor)" 
                    :sensor="sensor" 
                />
                
                <!-- Placeholder para otros sensores -->
                <div v-else class="flex h-full w-full items-center justify-center">
                    <div class="text-center">
                        <div class="text-xs text-cyan-400 mb-1">{{ sensor.title }}</div>
                        <div class="text-lg font-bold text-white">{{ sensor.value }}</div>
                        <div class="text-xs text-slate-400">{{ sensor.sensor.sensor.unit }}</div>
                    </div>
                </div>
            </div>
            
            <div v-if="!isSpeedSensor(sensor) && !isRpmSensor(sensor)" class="text-center">
                <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensor.value }}</span>
                <span class="ml-1 text-xs text-slate-400">{{ sensor.sensor.sensor.unit }}</span>
            </div>
        </div>
    </div>
</template>
