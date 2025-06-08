<script setup lang="ts">
interface Sensor {
    id: number
    sensor: {
        pid: string
        name: string
        unit: string
    }
}

interface Vehicle {
    id: number
}

const props = defineProps<{
    selectedVehicle: Vehicle | null
    isDebugMode: boolean
    activeSensors: Sensor[]
    sensorReadings: Record<string, number>
    getSensorValue: (pid: string) => number
}>()

const emit = defineEmits<{
    'fetch-latest-telemetry': []
    'initialize-sensor-readings': []
    'test-websocket': []
    'simulate-data': []
}>()

const handleFetchLatestTelemetry = () => {
    emit('fetch-latest-telemetry')
}

const handleInitializeSensorReadings = () => {
    emit('initialize-sensor-readings')
}

const handleTestWebSocket = () => {
    emit('test-websocket')
}

const handleSimulateData = () => {
    emit('simulate-data')
}

const getSensorValueProxy = (pid: string): number => {
    return props.getSensorValue(pid)
}
</script>

<template>
    <div v-if="selectedVehicle">
        <!-- Debug Info (Development Only) -->
        <div v-if="activeSensors.length > 0 && isDebugMode" class="rounded-xl border border-slate-700/30 bg-slate-800/30 p-4 mb-6">
            <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                🔧 Debug Info - Sensores Activos ({{ activeSensors.length }})
            </h3>
            <div class="space-y-1 max-h-32 overflow-y-auto">
                <div v-for="sensor in activeSensors" :key="sensor.id" class="text-xs flex justify-between items-center">
                    <span class="text-cyan-400 font-mono">{{ sensor.sensor.pid }}</span>
                    <span class="text-slate-300">{{ sensor.sensor.name }}</span>
                    <span class="text-slate-500">({{ sensor.sensor.unit }})</span>
                    <span class="font-mono text-green-400">{{ getSensorValueProxy(sensor.sensor.pid) }}</span>
                </div>
            </div>
            
            <!-- Raw data toggle -->
            <div class="mt-3 pt-3 border-t border-slate-700/50">
                <details class="cursor-pointer">
                    <summary class="text-xs text-slate-500 hover:text-slate-400">Ver datos raw</summary>
                    <pre class="mt-2 text-xs text-slate-600 bg-slate-900/50 p-2 rounded overflow-auto max-h-32">{{ JSON.stringify(sensorReadings, null, 2) }}</pre>
                </details>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="rounded-xl border border-slate-700/30 bg-slate-800/30 p-4">
            <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                ⚡ Acciones Rápidas
            </h3>
            <div class="grid grid-cols-2 gap-2">
                <button 
                    @click="handleFetchLatestTelemetry"
                    class="px-3 py-2 text-xs font-medium rounded-lg border border-cyan-500/30 bg-cyan-500/10 text-cyan-400 hover:bg-cyan-500/20 transition-all duration-200"
                >
                    🔄 Actualizar Datos
                </button>
                <button 
                    @click="handleInitializeSensorReadings"
                    class="px-3 py-2 text-xs font-medium rounded-lg border border-slate-500/30 bg-slate-500/10 text-slate-400 hover:bg-slate-500/20 transition-all duration-200"
                >
                    🔄 Reset Sensores
                </button>
            </div>
            
            <!-- Test actions (development only) -->
            <div v-if="isDebugMode" class="mt-2 grid grid-cols-2 gap-2">
                <button 
                    @click="handleTestWebSocket"
                    class="px-3 py-2 text-xs font-medium rounded-lg border border-purple-500/30 bg-purple-500/10 text-purple-400 hover:bg-purple-500/20 transition-all duration-200"
                >
                    📡 Test WebSocket
                </button>
                <button 
                    @click="handleSimulateData"
                    class="px-3 py-2 text-xs font-medium rounded-lg border border-yellow-500/30 bg-yellow-500/10 text-yellow-400 hover:bg-yellow-500/20 transition-all duration-200"
                >
                    🎲 Simular Datos
                </button>
            </div>
        </div>
    </div>
</template>