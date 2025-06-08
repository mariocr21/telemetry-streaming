<script setup lang="ts">
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
    isRealTimeActive: boolean
}>()
</script>

<template>
    <!-- All Other Sensors Widget -->
    <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
        <h3 class="mb-4 text-xs font-semibold tracking-wide text-slate-300 uppercase">📊 Todos los Sensores</h3>
        <div class="grid grid-cols-2 gap-3">
            <div 
                v-for="sensor in sensors" 
                :key="sensor.id"
                class="rounded-lg border border-slate-700/30 bg-slate-900/40 p-3 transition-all duration-200 hover:border-slate-600/50"
            >
                <div class="mb-1 text-xs tracking-wide text-slate-400 uppercase">{{ sensor.title }}</div>
                <div class="font-mono text-sm font-semibold text-cyan-400 mb-1">
                    {{ sensor.value }}{{ sensor.sensor.sensor.unit }}
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-xs text-slate-600">{{ sensor.sensor.sensor.pid }}</div>
                    <div v-if="isRealTimeActive" class="h-1 w-1 rounded-full bg-green-400 animate-pulse"/>
                </div>
            </div>
        </div>
    </div>
</template>