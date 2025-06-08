<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'

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

const props = defineProps<{
    sensor: SensorData
    minPressure?: number
    maxPressure?: number
}>()

// Valores por defecto para presión MAP (kPa)
const minPressure = props.minPressure ?? 0
const maxPressure = props.maxPressure ?? 120

// Estado animado
const currentPressure = ref(0)

// Función para calcular porcentaje
const pressurePercentage = computed(() => {
    const percentage = ((currentPressure.value - minPressure) / (maxPressure - minPressure)) * 100
    return Math.max(0, Math.min(100, percentage))
})

// Función para obtener color basado en presión
const getPressureColor = (pressure: number): string => {
    if (pressure < 30) return '#ef4444' // rojo - muy baja
    if (pressure < 50) return '#f59e0b' // amarillo - baja
    if (pressure < 100) return '#10b981' // verde - normal
    return '#06b6d4' // cyan - alta
}

const pressureColor = computed(() => getPressureColor(currentPressure.value))

// Animación suave
const animateValue = (targetValue: number, duration = 600) => {
    const startValue = currentPressure.value
    const startTime = Date.now()
    
    const animate = () => {
        const elapsed = Date.now() - startTime
        const progress = Math.min(elapsed / duration, 1)
        
        const easeOut = 1 - Math.pow(1 - progress, 3)
        currentPressure.value = startValue + (targetValue - startValue) * easeOut
        
        if (progress < 1) {
            requestAnimationFrame(animate)
        }
    }
    
    requestAnimationFrame(animate)
}

// Observar cambios
watch(() => props.sensor?.value, (newValue) => {
    const targetValue = typeof newValue === 'number' ? newValue : 0
    animateValue(targetValue)
}, { immediate: true })

onMounted(() => {
    currentPressure.value = 0
})

const displayPressure = computed(() => Math.round(currentPressure.value * 10) / 10)
</script>

<template>
    <div class="w-full h-full flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold text-slate-300 uppercase">
                {{ sensor.emoji || '⚡' }} {{ sensor.title }}
            </h3>
            <div class="text-xs text-slate-500">{{ sensor.sensor.sensor.pid }}</div>
        </div>
        
        <!-- Gauge circular -->
        <div class="flex-1 flex items-center justify-center">
            <div class="relative w-24 h-24">
                <svg viewBox="0 0 100 100" class="w-full h-full transform -rotate-90">
                    <!-- Fondo del gauge -->
                    <circle
                        cx="50"
                        cy="50"
                        r="40"
                        fill="none"
                        stroke="rgba(71, 85, 105, 0.3)"
                        stroke-width="8"
                    />
                    
                    <!-- Progreso del gauge -->
                    <circle
                        cx="50"
                        cy="50"
                        r="40"
                        fill="none"
                        :stroke="pressureColor"
                        stroke-width="8"
                        stroke-linecap="round"
                        :stroke-dasharray="`${(pressurePercentage * 251.2) / 100} 251.2`"
                        class="transition-all duration-300"
                        style="filter: drop-shadow(0 0 2px currentColor)"
                    />
                </svg>
                
                <!-- Valor central -->
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-lg font-bold text-white">{{ displayPressure }}</span>
                    <span class="text-xs text-slate-400">{{ sensor.sensor.sensor.unit }}</span>
                </div>
            </div>
        </div>
        
        <!-- Indicadores de rango -->
        <div class="flex justify-between text-xs text-slate-500 mt-1">
            <span>{{ minPressure }}</span>
            <span>{{ maxPressure }}</span>
        </div>
    </div>
</template>
