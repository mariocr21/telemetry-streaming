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
    minTemp?: number
    maxTemp?: number
    normalTemp?: number
}>()

// Valores por defecto para temperatura del refrigerante (°C)
const minTemp = props.minTemp ?? 0
const maxTemp = props.maxTemp ?? 120
const normalTemp = props.normalTemp ?? 90

// Estado animado
const currentTemp = ref(0)

// Función para calcular porcentaje
const tempPercentage = computed(() => {
    const percentage = ((currentTemp.value - minTemp) / (maxTemp - minTemp)) * 100
    return Math.max(0, Math.min(100, percentage))
})

// Función para obtener color basado en temperatura
const getTempColor = (temp: number): string => {
    if (temp < 60) return '#06b6d4' // cyan - frío
    if (temp < 80) return '#10b981' // verde - calentando
    if (temp < 95) return '#f59e0b' // amarillo - normal
    if (temp < 105) return '#f97316' // naranja - caliente
    return '#ef4444' // rojo - sobrecalentamiento
}

const tempColor = computed(() => getTempColor(currentTemp.value))

// // Generar menos marcas para el termómetro compacto
// const generateTicks = () => {
//     const ticks = []
//     for (let temp = minTemp; temp <= maxTemp; temp += 30) {
//         const percentage = ((temp - minTemp) / (maxTemp - minTemp)) * 100
//         ticks.push({
//             temp,
//             percentage,
//             isNormal: temp >= 80 && temp <= 95
//         })
//     }
//     return ticks
// }

// const ticks = generateTicks()

// Animación suave
const animateValue = (targetValue: number, duration = 600) => {
    const startValue = currentTemp.value
    const startTime = Date.now()
    
    const animate = () => {
        const elapsed = Date.now() - startTime
        const progress = Math.min(elapsed / duration, 1)
        
        const easeOut = 1 - Math.pow(1 - progress, 3)
        currentTemp.value = startValue + (targetValue - startValue) * easeOut
        
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
    currentTemp.value = 0
})

const displayTemp = computed(() => Math.round(currentTemp.value))
</script>

<template>
    <div class="w-full h-full relative">
        <!-- PID en esquina superior derecha -->
        <div class="absolute top-0 right-0 text-xs text-slate-500">
            {{ sensor.sensor.sensor.pid }}
        </div>
        
        <!-- Título en esquina superior izquierda -->
        <!-- <div class="absolute top-0 left-0 text-xs font-semibold text-slate-300 uppercase">
            {{ sensor.emoji || '🌡️' }} {{ sensor.title }}
        </div> -->
        
        <!-- Estado en esquina inferior izquierda -->
        <div class="absolute bottom-0 left-0">
            <span 
                class="text-xs px-2 py-1 rounded-full"
                :class="{
                    'bg-cyan-500/20 text-cyan-400': currentTemp < 60,
                    'bg-green-500/20 text-green-400': currentTemp >= 60 && currentTemp < 95,
                    'bg-yellow-500/20 text-yellow-400': currentTemp >= 95 && currentTemp < 105,
                    'bg-red-500/20 text-red-400': currentTemp >= 105
                }"
            >
                {{ currentTemp < 60 ? 'FRÍO' : currentTemp < 95 ? 'NORMAL' : currentTemp < 105 ? 'CALIENTE' : 'CRÍTICO' }}
            </span>
        </div>
        
        <!-- Contenido central -->
        <div class="flex items-center justify-center h-full">
            <div class="flex items-center gap-4">
                <!-- Termómetro compacto -->
                <div class="relative w-4 h-20 bg-slate-700/50 rounded-full overflow-hidden">
                    <!-- Relleno del termómetro -->
                    <div 
                        class="absolute bottom-0 w-full rounded-full transition-all duration-300"
                        :style="{ 
                            height: `${tempPercentage}%`, 
                            backgroundColor: tempColor,
                            filter: 'drop-shadow(0 0 2px currentColor)'
                        }"
                    />
                    
                    <!-- Bulbo del termómetro -->
                    <div 
                        class="absolute bottom-0 w-6 h-6 rounded-full -left-1 transition-colors duration-300"
                        :style="{ backgroundColor: tempColor }"
                    />
                </div>
                
                <!-- Valor principal -->
                <div class="text-center">
                    <div class="text-3xl font-bold text-white">{{ displayTemp }}</div>
                    <div class="text-sm text-slate-400">{{ sensor.sensor.sensor.unit }}</div>
                </div>
                
                <!-- Escala compacta -->
                <div class="relative h-20 flex flex-col justify-between text-xs text-slate-400">
                    <span>{{ maxTemp }}°</span>
                    <span class="text-yellow-400 text-center">{{ normalTemp }}°</span>
                    <span>{{ minTemp }}°</span>
                </div>
            </div>
        </div>
    </div>
</template>
