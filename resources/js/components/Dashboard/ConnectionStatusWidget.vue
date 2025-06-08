<script setup lang="ts">
import { computed } from 'vue'

interface Vehicle {
    id: number
}

interface ConnectionStatus {
    is_online: boolean
    status: string
    last_seen: string | null
    minutes_since_last_reading: number | null
    human_readable_last_seen: string
}

const props = defineProps<{
    isConnected: boolean
    isRealTimeActive: boolean
    lastUpdateFormatted: string
    connectionRetries: number
    maxRetries: number
    selectedVehicle: Vehicle | null
    connectionStatus: ConnectionStatus | null
    lastDataSource: string
}>()

const emit = defineEmits<{
    reconnect: []
}>()

const handleReconnect = () => {
    emit('reconnect')
}

// Computed para determinar el estado visual
const statusDisplay = computed(() => {
    if (props.isRealTimeActive) {
        return {
            color: 'green',
            title: 'Tiempo Real Activo',
            status: 'Recibiendo datos en vivo',
            showReconnect: false,
            bgClass: 'border-green-500/20 bg-gradient-to-br from-green-800/20 to-green-900/20',
            textClass: 'text-green-300',
            statusClass: 'text-green-400',
            dotClass: 'bg-green-400 animate-pulse'
        }
    }
    
    if (props.connectionStatus?.is_online) {
        return {
            color: 'cyan',
            title: 'Conectado',
            status: 'Datos históricos disponibles',
            showReconnect: false,
            bgClass: 'border-cyan-500/20 bg-gradient-to-br from-cyan-800/20 to-cyan-900/20',
            textClass: 'text-cyan-300',
            statusClass: 'text-cyan-400',
            dotClass: 'bg-cyan-400'
        }
    }
    
    if (props.connectionStatus?.status === 'offline') {
        return {
            color: 'orange',
            title: 'Fuera de Línea',
            status: `Sin datos desde hace ${props.connectionStatus.minutes_since_last_reading || 0}min`,
            showReconnect: true,
            bgClass: 'border-orange-500/20 bg-gradient-to-br from-orange-800/20 to-orange-900/20',
            textClass: 'text-orange-300',
            statusClass: 'text-orange-400',
            dotClass: 'bg-orange-400'
        }
    }
    
    return {
        color: 'red',
        title: 'Desconectado',
        status: 'Sin conexión disponible',
        showReconnect: true,
        bgClass: 'border-red-500/20 bg-gradient-to-br from-red-800/20 to-red-900/20',
        textClass: 'text-red-300',
        statusClass: 'text-red-400',
        dotClass: 'bg-red-400'
    }
})

const dataSourceDisplay = computed(() => {
    switch (props.lastDataSource) {
        case 'realtime': return 'Tiempo Real'
        case 'cache': return 'Caché'
        case 'database': return 'Base de Datos'
        case 'simulation': return 'Simulado'
        default: return 'Desconocido'
    }
})

const reconnectButtonClass = computed(() => {
    if (statusDisplay.value.color === 'orange') {
        return 'border-orange-500/30 bg-orange-500/10 text-orange-400 hover:bg-orange-500/20'
    }
    return 'border-red-500/30 bg-red-500/10 text-red-400 hover:bg-red-500/20'
})
</script>

<template>
    <!-- Connection Status Card -->
    <div class="rounded-xl border p-4 backdrop-blur-xl transition-all duration-300" :class="statusDisplay.bgClass">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold tracking-wide uppercase" :class="statusDisplay.textClass">
                {{ statusDisplay.title }}
            </h3>
            <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full" :class="statusDisplay.dotClass" />
                <span class="text-xs font-medium" :class="statusDisplay.statusClass">
                    {{ statusDisplay.status }}
                </span>
            </div>
        </div>
        
        <div class="space-y-2 text-xs text-slate-400">
            <div class="flex justify-between">
                <span>Estado actual:</span>
                <span class="font-mono">{{ lastUpdateFormatted }}</span>
            </div>
            
            <div v-if="connectionStatus && connectionStatus.last_seen" class="flex justify-between">
                <span>Última conexión:</span>
                <span class="font-mono">{{ connectionStatus.human_readable_last_seen }}</span>
            </div>
            
            <div class="flex justify-between">
                <span>Fuente de datos:</span>
                <span class="font-mono">{{ dataSourceDisplay }}</span>
            </div>
            
            <div v-if="!isConnected && connectionRetries > 0" class="flex justify-between">
                <span>Reintentos:</span>
                <span class="font-mono">{{ connectionRetries }}/{{ maxRetries }}</span>
            </div>
            
            <div v-if="selectedVehicle" class="flex justify-between">
                <span>Vehículo ID:</span>
                <span class="font-mono">#{{ selectedVehicle.id }}</span>
            </div>
            
            <div v-if="connectionStatus && connectionStatus.minutes_since_last_reading !== null && connectionStatus.minutes_since_last_reading !== undefined" class="flex justify-between">
                <span>Inactividad:</span>
                <span class="font-mono">{{ connectionStatus.minutes_since_last_reading }}min</span>
            </div>
        </div>
        
        <!-- Reconnect button when needed -->
        <div v-if="statusDisplay.showReconnect && selectedVehicle" class="mt-3">
            <button 
                @click="handleReconnect"
                class="w-full px-3 py-2 text-xs font-medium rounded-lg border transition-all duration-200"
                :class="reconnectButtonClass"
            >
                🔄 Reconectar WebSocket
            </button>
        </div>
        
        <!-- Real-time status indicator -->
        <div v-if="isRealTimeActive" class="mt-3 p-2 rounded-lg bg-green-500/10 border border-green-500/20">
            <div class="flex items-center gap-2">
                <div class="h-1 w-1 rounded-full bg-green-400 animate-pulse"/>
                <span class="text-xs text-green-400 font-medium">Modo tiempo real activo</span>
            </div>
        </div>
    </div>
</template>