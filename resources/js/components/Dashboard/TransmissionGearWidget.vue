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
}>()

// Mapeo de valores decimales a posiciones de marcha
const GEAR_POSITIONS = {
    46: { code: 'P', label: 'PARKING', color: '#ef4444' },     // Rojo
    47: { code: 'R', label: 'REVERSE', color: '#f59e0b' },     // Naranja
    32: { code: 'N', label: 'NEUTRAL', color: '#06b6d4' },     // Cyan
    33: { code: 'L', label: 'LOW', color: '#10b981' },         // Verde
    34: { code: 'H', label: 'HIGH', color: '#8b5cf6' },        // Violeta
} as const

type GearValue = keyof typeof GEAR_POSITIONS

// Estado animado
const currentGear = ref<number>(32) // Default a Neutral
const isTransitioning = ref(false)

// Obtener información de la marcha actual
const gearInfo = computed(() => {
    const gearValue = currentGear.value as GearValue
    return GEAR_POSITIONS[gearValue] || { 
        code: '?', 
        label: 'UNKNOWN', 
        color: '#64748b'
    }
})

// Animación de transición
const animateGearChange = (newGear: number) => {
    isTransitioning.value = true
    
    setTimeout(() => {
        currentGear.value = newGear
        isTransitioning.value = false
    }, 150)
}

// Observar cambios en el sensor
watch(() => props.sensor?.value, (newValue) => {
    const targetGear = typeof newValue === 'number' ? newValue : 32
    if (targetGear !== currentGear.value) {
        animateGearChange(targetGear)
    }
}, { immediate: true })

onMounted(() => {
    currentGear.value = props.sensor?.value || 32
})
</script>

<template>
    <div class="gear-widget-container">
        <!-- PID en esquina superior derecha -->
        <div class="gear-pid">
            {{ sensor.sensor.sensor.pid }}
        </div>
        
        <!-- Estado de transmisión en esquina inferior izquierda -->
        <div class="gear-status-badge">
            <span 
                class="status-text"
                :style="{ 
                    backgroundColor: `${gearInfo.color}20`,
                    color: gearInfo.color 
                }"
            >
                {{ gearInfo.label }}
            </span>
        </div>
        
        <!-- Display principal - solo la letra -->
        <div class="gear-display">
            <!-- Glow effect -->
            <div 
                class="gear-glow"
                :style="{ 
                    backgroundColor: `${gearInfo.color}40`,
                    opacity: isTransitioning ? 0.5 : 1
                }"
            />
            
            <!-- Círculo principal -->
            <div 
                class="gear-circle"
                :class="{ 'transitioning': isTransitioning }"
                :style="{ 
                    backgroundColor: `${gearInfo.color}20`,
                    borderColor: gearInfo.color,
                    boxShadow: `0 0 15px ${gearInfo.color}40`
                }"
            >
                <!-- Letra de la marcha -->
                <div 
                    class="gear-letter"
                    :style="{ color: gearInfo.color }"
                >
                    {{ gearInfo.code }}
                </div>
            </div>
            
            <!-- Pulso de animación cuando cambia -->
            <div 
                v-if="isTransitioning"
                class="gear-pulse"
                :style="{ borderColor: gearInfo.color }"
            />
        </div>
    </div>
</template>

<style scoped>
.gear-widget-container {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* PID */
.gear-pid {
    position: absolute;
    top: 0;
    right: 0;
    font-size: 10px;
    color: rgb(100, 116, 139);
}

@media (min-width: 640px) {
    .gear-pid {
        font-size: 11px;
    }
}

/* Status Badge */
.gear-status-badge {
    position: absolute;
    bottom: 0;
    left: 0;
}

.status-text {
    font-size: 9px;
    padding: 3px 6px;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
}

@media (min-width: 640px) {
    .status-text {
        font-size: 10px;
        padding: 4px 8px;
    }
}

@media (min-width: 768px) {
    .status-text {
        font-size: 11px;
    }
}

/* Display Principal */
.gear-display {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Glow Effect */
.gear-glow {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    filter: blur(12px);
    transition: all 0.3s ease;
    pointer-events: none;
}

/* Círculo Principal */
.gear-circle {
    position: relative;
    width: 70px;
    height: 70px;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-width: 3px;
    border-style: solid;
    transition: all 0.3s ease;
    z-index: 1;
}

@media (min-width: 640px) {
    .gear-circle {
        width: 80px;
        height: 80px;
    }
}

@media (min-width: 768px) {
    .gear-circle {
        width: 90px;
        height: 90px;
        border-width: 4px;
    }
}

@media (min-width: 1024px) {
    .gear-circle {
        width: 100px;
        height: 100px;
    }
}

.gear-circle.transitioning {
    transform: scale(0.95);
}

/* Letra */
.gear-letter {
    font-size: 36px;
    font-weight: 900;
    transition: all 0.2s ease;
    line-height: 1;
}

@media (min-width: 640px) {
    .gear-letter {
        font-size: 42px;
    }
}

@media (min-width: 768px) {
    .gear-letter {
        font-size: 48px;
    }
}

@media (min-width: 1024px) {
    .gear-letter {
        font-size: 54px;
    }
}

/* Pulso de animación */
.gear-pulse {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    border-width: 2px;
    border-style: solid;
    animation: pulse-ring 0.6s ease-out;
    pointer-events: none;
}

@keyframes pulse-ring {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    100% {
        transform: scale(1.3);
        opacity: 0;
    }
}

/* Ajustes para pantallas muy pequeñas */
@media (max-width: 374px) {
    .gear-circle {
        width: 60px;
        height: 60px;
    }
    
    .gear-letter {
        font-size: 32px;
    }
    
    .status-text {
        font-size: 8px;
        padding: 2px 5px;
    }
}

/* Modo landscape móvil */
@media (max-height: 500px) and (orientation: landscape) {
    .gear-circle {
        width: 65px;
        height: 65px;
    }
    
    .gear-letter {
        font-size: 34px;
    }
}
</style>