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
    minVoltage?: number
    maxVoltage?: number
}>()

const minVoltage = props.minVoltage ?? 10.5
const maxVoltage = props.maxVoltage ?? 14.8

const currentVoltage = ref(0)

const batteryPercentage = computed(() => {
    const percentage = ((currentVoltage.value - minVoltage) / (maxVoltage - minVoltage)) * 100
    return Math.max(0, Math.min(100, percentage))
})

const getBatteryStatus = (voltage: number) => {
    if (voltage < 11.8) {
        return { color: '#ef4444', label: 'CRÍTICO' }
    } else if (voltage < 12.4) {
        return { color: '#f59e0b', label: 'BAJO' }
    } else if (voltage < 12.7) {
        return { color: '#fbbf24', label: 'BUENO' }
    } else if (voltage <= 13.2) {
        return { color: '#10b981', label: 'ÓPTIMO' }
    } else {
        return { color: '#06b6d4', label: 'CARGANDO' }
    }
}

const batteryStatus = computed(() => getBatteryStatus(currentVoltage.value))

const animateValue = (targetValue: number, duration = 600) => {
    const startValue = currentVoltage.value
    const startTime = Date.now()
    
    const animate = () => {
        const elapsed = Date.now() - startTime
        const progress = Math.min(elapsed / duration, 1)
        
        const easeOut = 1 - Math.pow(1 - progress, 3)
        currentVoltage.value = startValue + (targetValue - startValue) * easeOut
        
        if (progress < 1) {
            requestAnimationFrame(animate)
        }
    }
    
    requestAnimationFrame(animate)
}

watch(() => props.sensor?.value, (newValue) => {
    const targetValue = typeof newValue === 'number' ? newValue : 0
    animateValue(targetValue)
}, { immediate: true })

onMounted(() => {
    currentVoltage.value = 0
})

const displayVoltage = computed(() => currentVoltage.value.toFixed(1))

// Calcular ángulo para el arco circular (de 0° a 270°)
const arcAngle = computed(() => {
    return (batteryPercentage.value / 100) * 270
})
</script>

<template>
    <div class="battery-circular-widget">
        <!-- PID en esquina -->
        <div class="pid-corner">{{ sensor.sensor.sensor.pid }}</div>
        
        <!-- Display Circular Principal -->
        <div class="circular-container">
            <!-- Glow effect de fondo -->
            <div 
                class="circle-glow"
                :style="{ backgroundColor: `${batteryStatus.color}40` }"
            />
            
            <!-- SVG Circular Progress -->
            <svg class="progress-ring" viewBox="0 0 100 100">
                <!-- Círculo de fondo -->
                <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    stroke="rgba(55, 65, 81, 0.5)"
                    stroke-width="8"
                />
                
                <!-- Círculo de progreso -->
                <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    :stroke="batteryStatus.color"
                    stroke-width="8"
                    stroke-linecap="round"
                    :stroke-dasharray="`${arcAngle * 2.64} 264`"
                    :style="{
                        transform: 'rotate(-135deg)',
                        transformOrigin: '50% 50%',
                        transition: 'stroke-dasharray 0.6s ease',
                        filter: `drop-shadow(0 0 4px ${batteryStatus.color})`
                    }"
                />
            </svg>
            
            <!-- Contenido Central -->
            <div class="circle-content">
                <!-- Icono de rayo -->
                <div 
                    class="bolt-icon"
                    :style="{ color: batteryStatus.color }"
                >
                    ⚡
                </div>
                
                <!-- Voltaje -->
                <div 
                    class="voltage-display"
                    :style="{ color: batteryStatus.color }"
                >
                    {{ displayVoltage }}
                </div>
                
                <!-- Unidad -->
                <div class="unit-label">{{ sensor.sensor.sensor.unit }}</div>
            </div>
        </div>
        
        <!-- Status Badge - ABAJO DEL CÍRCULO -->
        <div class="status-label-bottom">
            <span 
                class="status-badge"
                :style="{ 
                    backgroundColor: `${batteryStatus.color}20`,
                    color: batteryStatus.color,
                    borderColor: `${batteryStatus.color}40`
                }"
            >
                {{ batteryStatus.label }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.battery-circular-widget {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

/* PID */
.pid-corner {
    position: absolute;
    top: 2px;
    right: 4px;
    font-size: 9px;
    color: rgb(100, 116, 139);
    opacity: 0.7;
}

@media (min-width: 640px) {
    .pid-corner {
        font-size: 10px;
        top: 4px;
        right: 6px;
    }
}

/* Contenedor Circular */
.circular-container {
    position: relative;
    width: 85px;
    height: 85px;
    flex-shrink: 0;
}

@media (min-width: 640px) {
    .circular-container {
        width: 95px;
        height: 95px;
    }
}

@media (min-width: 768px) {
    .circular-container {
        width: 105px;
        height: 105px;
    }
}

@media (min-width: 1024px) {
    .circular-container {
        width: 115px;
        height: 115px;
    }
}

/* Glow Effect */
.circle-glow {
    position: absolute;
    inset: -5px;
    border-radius: 50%;
    filter: blur(20px);
    opacity: 0.5;
    transition: all 0.4s ease;
}

/* SVG Ring */
.progress-ring {
    width: 100%;
    height: 100%;
    position: relative;
    z-index: 1;
}

/* Contenido Central */
.circle-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    z-index: 2;
}

/* Icono de Rayo */
.bolt-icon {
    font-size: 16px;
    line-height: 1;
    opacity: 0.9;
    transition: color 0.3s ease;
}

@media (min-width: 640px) {
    .bolt-icon {
        font-size: 18px;
    }
}

@media (min-width: 768px) {
    .bolt-icon {
        font-size: 20px;
    }
}

/* Voltaje */
.voltage-display {
    font-size: 22px;
    font-weight: 900;
    line-height: 1;
    transition: color 0.3s ease;
    margin-top: 2px;
}

@media (min-width: 640px) {
    .voltage-display {
        font-size: 24px;
    }
}

@media (min-width: 768px) {
    .voltage-display {
        font-size: 26px;
    }
}

@media (min-width: 1024px) {
    .voltage-display {
        font-size: 28px;
    }
}

/* Unidad */
.unit-label {
    font-size: 9px;
    color: rgb(156, 163, 175);
    font-weight: 600;
    line-height: 1;
    margin-top: 1px;
}

@media (min-width: 640px) {
    .unit-label {
        font-size: 10px;
    }
}

@media (min-width: 768px) {
    .unit-label {
        font-size: 11px;
    }
}

/* Status Badge - DEBAJO DEL CÍRCULO */
.status-label-bottom {
    display: flex;
    justify-content: center;
    width: 100%;
    margin-top: 2px;
}

.status-badge {
    font-size: 9px;
    padding: 3px 8px;
    border-radius: 10px;
    font-weight: 700;
    letter-spacing: 0.3px;
    border: 1px solid;
    transition: all 0.3s ease;
    display: inline-block;
    text-align: center;
    white-space: nowrap;
}

@media (min-width: 640px) {
    .status-badge {
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 12px;
    }
}

@media (min-width: 768px) {
    .status-badge {
        font-size: 11px;
        padding: 5px 12px;
    }
}

/* Pantallas muy pequeñas */
@media (max-width: 374px) {
    .circular-container {
        width: 75px;
        height: 75px;
    }
    
    .voltage-display {
        font-size: 20px;
    }
    
    .bolt-icon {
        font-size: 14px;
    }
    
    .status-badge {
        font-size: 8px;
        padding: 2px 6px;
    }
}

/* Landscape móvil */
@media (max-height: 500px) and (orientation: landscape) {
    .circular-container {
        width: 80px;
        height: 80px;
    }
    
    .voltage-display {
        font-size: 21px;
    }
    
    .battery-circular-widget {
        gap: 6px;
    }
}
</style>