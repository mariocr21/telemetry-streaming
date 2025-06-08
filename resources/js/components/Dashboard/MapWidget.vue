<script setup lang="ts">
interface Vehicle {
    id: number
    make: string
    model: string
    nickname?: string
}

interface ConnectionStatus {
    is_online: boolean
    status: string
    last_seen: string | null
    minutes_since_last_reading: number | null
    human_readable_last_seen: string
}

const props = defineProps<{
    selectedVehicle: Vehicle | null
    isLoading: boolean
    isRealTimeActive: boolean
    connectionStatus: ConnectionStatus | null
}>()
</script>

<template>
    <!-- Map Area (65%) -->
    <div class="relative w-[65%] border-r border-cyan-500/20 bg-slate-900/50">
        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-900">
            <div class="text-center">
                <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full border-2 border-cyan-500/40 bg-cyan-500/20">
                    <svg class="h-12 w-12 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="mb-2 text-xl font-semibold text-cyan-400">Mapa de Seguimiento GPS</h3>
                <p class="text-sm text-slate-400">
                    <span v-if="selectedVehicle && !isLoading">
                        Rastreando: {{ selectedVehicle.nickname || `${selectedVehicle.make} ${selectedVehicle.model}` }}
                    </span>
                    <span v-else-if="isLoading">Cargando datos del vehículo...</span>
                    <span v-else>Selecciona un dispositivo para comenzar el seguimiento</span>
                </p>
            </div>
            
            <!-- Map Controls -->
            <div class="absolute top-6 right-6 flex flex-col gap-2">
                <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-cyan-500/30 bg-slate-800/80 text-cyan-400 transition-all duration-200 hover:bg-slate-700/80">
                    <span class="text-lg font-bold">+</span>
                </button>
                <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-cyan-500/30 bg-slate-800/80 text-cyan-400 transition-all duration-200 hover:bg-slate-700/80">
                    <span class="text-lg font-bold">-</span>
                </button>
            </div>

            <!-- Real-time indicator -->
            <div v-if="isRealTimeActive" class="absolute top-6 left-6">
                <div class="flex items-center gap-2 rounded-lg border border-green-500/30 bg-green-500/10 px-3 py-2">
                    <div class="h-2 w-2 rounded-full bg-green-400 animate-pulse"/>
                    <span class="text-xs text-green-400 font-medium">DATOS EN VIVO</span>
                </div>
            </div>
            
            <!-- Historical data indicator -->
            <div v-else-if="connectionStatus?.is_online && !isRealTimeActive" class="absolute top-6 left-6">
                <div class="flex items-center gap-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-2">
                    <div class="h-2 w-2 rounded-full bg-cyan-400"/>
                    <span class="text-xs text-cyan-400 font-medium">DATOS HISTÓRICOS</span>
                </div>
            </div>
            
            <!-- Offline indicator -->
            <div v-else-if="connectionStatus?.status === 'offline'" class="absolute top-6 left-6">
                <div class="flex items-center gap-2 rounded-lg border border-orange-500/30 bg-orange-500/10 px-3 py-2">
                    <div class="h-2 w-2 rounded-full bg-orange-400"/>
                    <span class="text-xs text-orange-400 font-medium">FUERA DE LÍNEA</span>
                </div>
            </div>
        </div>
    </div>
</template>