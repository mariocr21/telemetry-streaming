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
    sensor: VehicleSensor
    value: number
    defaultValue: number
}

interface TickMark {
    value: number
    x1: number
    y1: number
    x2: number
    y2: number
    textX: number
    textY: number
    type: 'major' | 'minor'
}

const props = defineProps<{
    sensor: SensorData
    minSpeed?: number
    maxSpeed?: number
}>()

// Valores por defecto
const minSpeed = props.minSpeed ?? 0
const maxSpeed = props.maxSpeed ?? 200

// Estado animado
const currentSpeed = ref(0)

// Constantes para el velocímetro
const centerX = 100
const centerY = 90
const startAngle = -140
const endAngle = 140
const outerRadius = 85
const innerRadius = 70
const textRadius = 60
const needleLength = 65

// Función para convertir velocidad a ángulo
const speedToAngle = (speed: number): number => {
    const percentage = (speed - minSpeed) / (maxSpeed - minSpeed)
    return startAngle + percentage * (endAngle - startAngle)
}

// Función para convertir ángulo a radianes
const angleToRadians = (angle: number): number => {
    return angle * Math.PI / 180
}

// Calcular posición de la aguja
const needleX = computed(() => {
    const angle = speedToAngle(currentSpeed.value)
    const radians = angleToRadians(angle)
    return centerX + needleLength * Math.cos(radians)
})

const needleY = computed(() => {
    const angle = speedToAngle(currentSpeed.value)
    const radians = angleToRadians(angle)
    return centerY + needleLength * Math.sin(radians)
})

// Calcular el arco de progreso
const arcPath = computed(() => {
    const angle = speedToAngle(currentSpeed.value)
    const radius = 78
    
    const startX = centerX + radius * Math.cos(angleToRadians(startAngle))
    const startY = centerY + radius * Math.sin(angleToRadians(startAngle))
    const endX = centerX + radius * Math.cos(angleToRadians(angle))
    const endY = centerY + radius * Math.sin(angleToRadians(angle))
    
    const largeArcFlag = Math.abs(angle - startAngle) <= 180 ? "0" : "1"
    
    return `M ${startX} ${startY} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`
})

// Generar marcas del velocímetro
const generateTicks = (): TickMark[] => {
    const ticks: TickMark[] = []
    
    // Marcas principales cada 20 km/h
    for (let speed = minSpeed; speed <= maxSpeed; speed += 20) {
        const angle = speedToAngle(speed)
        const radians = angleToRadians(angle)
        
        ticks.push({
            value: speed,
            x1: centerX + innerRadius * Math.cos(radians),
            y1: centerY + innerRadius * Math.sin(radians),
            x2: centerX + outerRadius * Math.cos(radians),
            y2: centerY + outerRadius * Math.sin(radians),
            textX: centerX + textRadius * Math.cos(radians),
            textY: centerY + textRadius * Math.sin(radians),
            type: 'major'
        })
        
        // Marcas menores entre las principales (cada 10 km/h)
        if (speed + 10 <= maxSpeed) {
            const minorAngle = speedToAngle(speed + 10)
            const minorRadians = angleToRadians(minorAngle)
            
            ticks.push({
                value: speed + 10,
                x1: centerX + (innerRadius + 5) * Math.cos(minorRadians),
                y1: centerY + (innerRadius + 5) * Math.sin(minorRadians),
                x2: centerX + outerRadius * Math.cos(minorRadians),
                y2: centerY + outerRadius * Math.sin(minorRadians),
                textX: centerX + textRadius * Math.cos(minorRadians),
                textY: centerY + textRadius * Math.sin(minorRadians),
                type: 'minor'
            })
        }
    }
    
    return ticks
}

const ticks = generateTicks()

// Función para obtener color basado en velocidad
const getSpeedColor = (speed: number): string => {
    if (speed < 60) return '#06b6d4' // cyan
    if (speed < 100) return '#10b981' // green
    if (speed < 120) return '#f59e0b' // yellow
    return '#ef4444' // red
}

const speedColor = computed(() => getSpeedColor(currentSpeed.value))

// Animación suave para cambios de valor
const animateValue = (targetValue: number, duration = 800) => {
    const startValue = currentSpeed.value
    const startTime = Date.now()
    
    const animate = () => {
        const elapsed = Date.now() - startTime
        const progress = Math.min(elapsed / duration, 1)
        
        // Easing function (ease-out-cubic)
        const easeOut = 1 - Math.pow(1 - progress, 3)
        
        currentSpeed.value = startValue + (targetValue - startValue) * easeOut
        
        if (progress < 1) {
            requestAnimationFrame(animate)
        }
    }
    
    requestAnimationFrame(animate)
}

// Observar cambios en el valor del sensor
watch(() => props.sensor?.value, (newValue) => {
    const targetValue = typeof newValue === 'number' ? newValue : 0
    animateValue(targetValue)
}, { immediate: true })

// Inicializar en 0
onMounted(() => {
    currentSpeed.value = 0
})
</script>

<template>
    <div class="w-full h-full -py-2 -my-2">
        <svg viewBox="0 0 200 160" class="w-full h-full">
            <!-- Fondo del velocímetro -->
            <circle 
                cx="100" 
                cy="90" 
                r="85" 
                class="fill-slate-800/80 stroke-slate-700/50 stroke-1"
            />
            
            <!-- Arco de progreso -->
            <path
                :d="arcPath"
                :stroke="speedColor"
                class="fill-none stroke-[8] stroke-linecap-round transition-colors duration-300"
                style="filter: drop-shadow(0 0 2px currentColor)"
            />
            
            <!-- Marcas del velocímetro -->
            <g v-for="tick in ticks" :key="tick.value">
                <!-- Líneas de las marcas -->
                <line
                    :x1="tick.x1" 
                    :y1="tick.y1"
                    :x2="tick.x2" 
                    :y2="tick.y2"
                    :stroke="tick.type === 'major' ? '#e2e8f0' : '#64748b'"
                    :stroke-width="tick.type === 'major' ? 2 : 1"
                />
                
                <!-- Números en las marcas principales -->
                <text
                    v-if="tick.type === 'major'"
                    :x="tick.textX" 
                    :y="tick.textY"
                    text-anchor="middle" 
                    dominant-baseline="middle"
                    class="text-xs fill-slate-300 font-bold"
                >
                    {{ tick.value }}
                </text>
            </g>
            
            <!-- Aguja del velocímetro -->
            <g class="transition-transform duration-300">
                <line
                    x1="100" 
                    y1="90"
                    :x2="needleX" 
                    :y2="needleY"
                    :stroke="speedColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    class="transition-colors duration-300"
                    style="filter: drop-shadow(0 0 3px currentColor)"
                />
                <circle 
                    cx="100" 
                    cy="90" 
                    r="4" 
                    :fill="speedColor" 
                    class="transition-colors duration-300"
                />
            </g>
            
            <!-- Centro decorativo -->
            <circle cx="100" cy="90" r="2" fill="#1e293b"/>
            
            <!-- Valor digital -->
            <text 
                x="100" 
                y="120" 
                text-anchor="middle" 
                class="fill-white text-2xl font-bold"
            >
                {{ Math.round(currentSpeed) }}
            </text>
            
            <!-- Unidad -->
            <text 
                x="100" 
                y="135" 
                text-anchor="middle" 
                class="fill-slate-400 text-sm"
            >
                {{ sensor.sensor.sensor.unit }}
            </text>
        </svg>
    </div>
</template>
