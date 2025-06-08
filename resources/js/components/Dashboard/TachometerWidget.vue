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
    minRpm?: number
    maxRpm?: number
    redlineStart?: number
}>()

// Valores por defecto
const minRpm = props.minRpm ?? 0
const maxRpm = props.maxRpm ?? 8000
const redlineStart = props.redlineStart ?? 6000

// Estado animado
const currentRpm = ref(0)

// Constantes para el tacómetro
const centerX = 100
const centerY = 90
const startAngle = -140
const endAngle = 140
const outerRadius = 85
const innerRadius = 70
const textRadius = 60
const needleLength = 65

// Función para convertir RPM a ángulo
const rpmToAngle = (rpm: number): number => {
    // Dividir por 1000 para convertir RPM reales a la escala del tacómetro (0-8)
    const rpmInThousands = rpm / 1000
    const percentage = (rpmInThousands - (minRpm / 1000)) / ((maxRpm / 1000) - (minRpm / 1000))
    return startAngle + percentage * (endAngle - startAngle)
}

// Función para convertir ángulo a radianes
const angleToRadians = (angle: number): number => {
    return angle * Math.PI / 180
}

// Calcular posición de la aguja
const needleX = computed(() => {
    const angle = rpmToAngle(currentRpm.value)
    const radians = angleToRadians(angle)
    return centerX + needleLength * Math.cos(radians)
})

const needleY = computed(() => {
    const angle = rpmToAngle(currentRpm.value)
    const radians = angleToRadians(angle)
    return centerY + needleLength * Math.sin(radians)
})

// Calcular el arco de progreso
const arcPath = computed(() => {
    const angle = rpmToAngle(currentRpm.value)
    const radius = 78
    
    const startX = centerX + radius * Math.cos(angleToRadians(startAngle))
    const startY = centerY + radius * Math.sin(angleToRadians(startAngle))
    const endX = centerX + radius * Math.cos(angleToRadians(angle))
    const endY = centerY + radius * Math.sin(angleToRadians(angle))
    
    const largeArcFlag = Math.abs(angle - startAngle) <= 180 ? "0" : "1"
    
    return `M ${startX} ${startY} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`
})

// Calcular el arco de la zona roja (redline)
const redlineArcPath = computed(() => {
    const startAngleRedline = rpmToAngle(redlineStart)
    const radius = 78
    
    const startX = centerX + radius * Math.cos(angleToRadians(startAngleRedline))
    const startY = centerY + radius * Math.sin(angleToRadians(startAngleRedline))
    const endX = centerX + radius * Math.cos(angleToRadians(endAngle))
    const endY = centerY + radius * Math.sin(angleToRadians(endAngle))
    
    const largeArcFlag = Math.abs(endAngle - startAngleRedline) <= 180 ? "0" : "1"
    
    return `M ${startX} ${startY} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`
})

// Generar marcas del tacómetro
const generateTicks = (): TickMark[] => {
    const ticks: TickMark[] = []
    
    // Marcas principales cada 1000 RPM (1 en el tacómetro)
    for (let rpm = minRpm; rpm <= maxRpm; rpm += 1000) {
        const angle = rpmToAngle(rpm)
        const radians = angleToRadians(angle)
        
        ticks.push({
            value: rpm / 1000, // Mostrar como 1, 2, 3... en lugar de 1000, 2000, 3000...
            x1: centerX + innerRadius * Math.cos(radians),
            y1: centerY + innerRadius * Math.sin(radians),
            x2: centerX + outerRadius * Math.cos(radians),
            y2: centerY + outerRadius * Math.sin(radians),
            textX: centerX + textRadius * Math.cos(radians),
            textY: centerY + textRadius * Math.sin(radians),
            type: 'major'
        })
        
        // Marcas menores cada 500 RPM
        if (rpm + 500 <= maxRpm) {
            const minorAngle = rpmToAngle(rpm + 500)
            const minorRadians = angleToRadians(minorAngle)
            
            ticks.push({
                value: (rpm + 500) / 1000,
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

// Función para obtener color basado en RPM
const getRpmColor = (rpm: number): string => {
    if (rpm < 2000) return '#06b6d4' // cyan
    if (rpm < 4000) return '#10b981' // green
    if (rpm < redlineStart) return '#f59e0b' // yellow
    return '#ef4444' // red
}

const rpmColor = computed(() => getRpmColor(currentRpm.value))

// Animación suave para cambios de valor
const animateValue = (targetValue: number, duration = 800) => {
    const startValue = currentRpm.value
    const startTime = Date.now()
    
    const animate = () => {
        const elapsed = Date.now() - startTime
        const progress = Math.min(elapsed / duration, 1)
        
        // Easing function (ease-out-cubic)
        const easeOut = 1 - Math.pow(1 - progress, 3)
        
        currentRpm.value = startValue + (targetValue - startValue) * easeOut
        
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
    currentRpm.value = 0
})

// Valor formateado para mostrar (valor real, no dividido)
const displayRpm = computed(() => Math.round(currentRpm.value))
</script>

<template>
    <div class="w-full h-full">
        <svg viewBox="0 0 200 160" class="w-full h-full">
            <!-- Fondo del tacómetro -->
            <circle 
                cx="100" 
                cy="90" 
                r="85" 
                class="fill-slate-800/80 stroke-slate-700/50 stroke-1"
            />
            
            <!-- Zona roja (redline) -->
            <path
                :d="redlineArcPath"
                stroke="#ef4444"
                class="fill-none stroke-[8] stroke-linecap-round opacity-30"
            />
            
            <!-- Arco de progreso -->
            <path
                :d="arcPath"
                :stroke="rpmColor"
                class="fill-none stroke-[8] stroke-linecap-round transition-colors duration-300"
                style="filter: drop-shadow(0 0 2px currentColor)"
            />
            
            <!-- Marcas del tacómetro -->
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
            
            <!-- Aguja del tacómetro -->
            <g class="transition-transform duration-300">
                <line
                    x1="100" 
                    y1="90"
                    :x2="needleX" 
                    :y2="needleY"
                    :stroke="rpmColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    class="transition-colors duration-300"
                    style="filter: drop-shadow(0 0 3px currentColor)"
                />
                <circle 
                    cx="100" 
                    cy="90" 
                    r="4" 
                    :fill="rpmColor" 
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
                {{ displayRpm }}
            </text>
            
            <!-- Unidad -->
            <text 
                x="100" 
                y="135" 
                text-anchor="middle" 
                class="fill-slate-400 text-sm"
            >
                RPM
            </text>
        </svg>
    </div>
</template>
