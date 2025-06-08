<script setup lang="ts">
import PressureGaugeWidget from './PressureGaugeWidget.vue'
import TemperatureGaugeWidget from './TemperatureGaugeWidget.vue'

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
    emoji?: string
    sensor: VehicleSensor
    value: number
    defaultValue: number
}

defineProps<{
    sensors: SensorData[]
    isRealTimeActive: boolean
}>()

// Identificar tipos de sensores
const isPressureSensor = (sensor: SensorData) => {
    return sensor.title.toLowerCase().includes('presión') || 
           sensor.sensor?.sensor?.pid === '0x0B' ||
           sensor.title.toLowerCase().includes('map')
}

const isTemperatureSensor = (sensor: SensorData) => {
    return sensor.title.toLowerCase().includes('temperatura') || 
           sensor.sensor?.sensor?.pid === '0x05' ||
           sensor.title.toLowerCase().includes('refrigerante')
}
</script>

<template>
    <!-- Secondary Sensors -->
    <div class="grid gap-4" :class="sensors.length <= 2 ? 'grid-cols-2' : 'grid-cols-1'">
        <div 
            v-for="sensor in sensors" 
            :key="sensor.id"
            class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40"
        >
            <!-- Widget de presión -->
            <div v-if="isPressureSensor(sensor)" class="h-40">
                <PressureGaugeWidget :sensor="sensor" />
            </div>
            
            <!-- Widget de temperatura -->
            <div v-else-if="isTemperatureSensor(sensor)" class="h-40">
                <TemperatureGaugeWidget :sensor="sensor" />
            </div>
            
            <!-- Widget genérico para otros sensores -->
            <div v-else>
                <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-300 uppercase">
                    {{ sensor.emoji ? sensor.emoji + ' ' : '' }}{{ sensor.title }}
                </h3>
                <div class="flex items-baseline gap-1">
                    <span class="font-mono text-2xl font-bold text-cyan-400">{{ sensor.value }}</span>
                    <span class="text-xs text-slate-400">{{ sensor.sensor.sensor.unit }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ sensor.sensor.sensor.description }}</div>
                
                <!-- Real-time indicator per sensor -->
                <div class="mt-2 flex items-center justify-between">
                    <div class="text-xs text-slate-600">{{ sensor.sensor.sensor.pid }}</div>
                    <div v-if="isRealTimeActive" class="flex items-center gap-1">
                        <div class="h-1 w-1 rounded-full bg-green-400 animate-pulse"/>
                        <span class="text-xs text-green-400">LIVE</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
