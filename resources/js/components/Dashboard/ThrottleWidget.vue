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

interface ThrottleSensor {
    id: string
    title: string
    emoji: string
    sensor: VehicleSensor
    value: number
    defaultValue: number
}

defineProps<{
    sensor: ThrottleSensor
    isRealTimeActive: boolean
}>()
</script>

<template>
    <!-- Throttle Position (Progress Bar) -->
    <div class="rounded-xl border border-cyan-500/20 bg-gradient-to-br from-slate-800/90 to-slate-900/90 p-4 backdrop-blur-xl transition-all duration-300 hover:border-cyan-500/40">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xs font-semibold tracking-wide text-slate-300 uppercase">
                {{ sensor.emoji }} {{ sensor.title }}
            </h3>
            <div class="flex items-center gap-1">
                <span class="font-mono text-xl font-bold text-cyan-400">{{ sensor.value }}</span>
                <span class="text-xs text-slate-400">{{ sensor.sensor.sensor.unit }}</span>
            </div>
        </div>
        <div class="mb-2 h-3 w-full rounded-full bg-slate-800/50 overflow-hidden">
            <div 
                class="h-3 rounded-full bg-gradient-to-r from-cyan-500 to-cyan-400 transition-all duration-500 ease-out"
                :style="`width: ${Math.min(100, Math.max(0, sensor.value))}%`"
            />
        </div>
        <div class="flex justify-between text-xs text-slate-500">
            <span>0%</span>
            <span>50%</span>
            <span>100%</span>
        </div>
        
        <!-- Real-time data indicator -->
        <div class="mt-2 flex items-center justify-between">
            <div class="text-xs text-slate-600">{{ sensor.sensor.sensor.pid }}</div>
            <div v-if="isRealTimeActive" class="flex items-center gap-1">
                <div class="h-1 w-1 rounded-full bg-green-400 animate-pulse"/>
                <span class="text-xs text-green-400">ACTUALIZANDO</span>
            </div>
        </div>
    </div>
</template>