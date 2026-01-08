<script setup lang="ts">
/**
 * VehicleSelectorFloat.vue
 * 
 * Floating vehicle selector for the dynamic dashboard.
 * Shows current vehicle and allows switching between available vehicles.
 */
import { ref, computed } from 'vue';
import { Car, ChevronDown, ChevronUp, User, Wifi, WifiOff, X, Search } from 'lucide-vue-next';

interface Vehicle {
    id: number;
    name: string;
    make: string | null;
    model: string | null;
    year: number | null;
    nickname: string | null;
    license_plate: string | null;
    vin: string | null;
    client?: {
        id: number;
        full_name: string;
        company: string | null;
    } | null;
}

const props = defineProps<{
    vehicles: Vehicle[];
    currentVehicleId: number;
    currentVehicle: Vehicle | null;
    isSuperAdmin?: boolean;
    isConnected?: boolean;
}>();

const emit = defineEmits<{
    (e: 'select', vehicleId: number): void;
}>();

// State
const isExpanded = ref(false);
const searchQuery = ref('');
const expandedClients = ref<Set<number>>(new Set());

// Toggle panel
const togglePanel = () => {
    isExpanded.value = !isExpanded.value;
    if (isExpanded.value) {
        // Expand the client of current vehicle if SA
        if (props.isSuperAdmin && props.currentVehicle?.client?.id) {
            expandedClients.value.add(props.currentVehicle.client.id);
        }
    } else {
        searchQuery.value = '';
    }
};

// Filtered vehicles
const filteredVehicles = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.vehicles;
    }

    const query = searchQuery.value.toLowerCase();
    return props.vehicles.filter(v => {
        const searchableText = [
            v.name,
            v.make,
            v.model,
            v.license_plate,
            v.vin,
            v.client?.full_name,
            v.client?.company
        ].filter(Boolean).join(' ').toLowerCase();

        return searchableText.includes(query);
    });
});

// Group vehicles by client (for SA)
const groupedVehicles = computed(() => {
    if (!props.isSuperAdmin) {
        return [{
            clientId: 0,
            clientName: 'Mis Vehículos',
            company: null,
            vehicles: filteredVehicles.value
        }];
    }

    const groups: Record<number, {
        clientId: number;
        clientName: string;
        company: string | null;
        vehicles: Vehicle[];
    }> = {};

    filteredVehicles.value.forEach(vehicle => {
        const clientId = vehicle.client?.id || 0;
        const clientName = vehicle.client?.full_name || 'Sin cliente';
        const company = vehicle.client?.company || null;

        if (!groups[clientId]) {
            groups[clientId] = { clientId, clientName, company, vehicles: [] };
        }
        groups[clientId].vehicles.push(vehicle);
    });

    return Object.values(groups).sort((a, b) => a.clientName.localeCompare(b.clientName));
});

// Toggle client expansion
const toggleClient = (clientId: number) => {
    if (expandedClients.value.has(clientId)) {
        expandedClients.value.delete(clientId);
    } else {
        expandedClients.value.add(clientId);
    }
};

// Select vehicle
const selectVehicle = (vehicleId: number) => {
    if (vehicleId !== props.currentVehicleId) {
        isExpanded.value = false;
        searchQuery.value = '';
        emit('select', vehicleId);
    }
};

// Get display name for a vehicle
const getDisplayName = (vehicle: Vehicle | null): string => {
    if (!vehicle) return 'Sin vehículo';
    if (vehicle.nickname) return vehicle.nickname;
    
    const parts = [vehicle.make, vehicle.model].filter(Boolean);
    if (parts.length > 0) {
        return vehicle.year ? `${parts.join(' ')} (${vehicle.year})` : parts.join(' ');
    }
    
    return vehicle.license_plate || vehicle.vin || 'Vehículo';
};
</script>

<template>
    <div class="vehicle-selector-container">
        <!-- Collapsed State: Current Vehicle Badge -->
        <button
            @click="togglePanel"
            class="vehicle-badge"
            :class="{ 'badge-connected': isConnected }"
        >
            <div class="badge-icon">
                <Car class="w-4 h-4" />
            </div>
            <div class="badge-info">
                <span class="badge-name">{{ getDisplayName(currentVehicle) }}</span>
                <span v-if="currentVehicle?.license_plate" class="badge-plate">
                    {{ currentVehicle.license_plate }}
                </span>
            </div>
            <div class="badge-status">
                <Wifi v-if="isConnected" class="w-3 h-3 text-green-400" />
                <WifiOff v-else class="w-3 h-3 text-red-400" />
            </div>
            <component :is="isExpanded ? ChevronUp : ChevronDown" class="w-4 h-4 text-gray-400" />
        </button>

        <!-- Expanded State: Vehicle List -->
        <Transition name="slide">
            <div v-if="isExpanded" class="vehicle-panel">
                <!-- Header -->
                <div class="panel-header">
                    <div class="flex items-center gap-2">
                        <Car class="w-5 h-5 text-cyan-400" />
                        <span class="font-semibold text-white">Cambiar Vehículo</span>
                    </div>
                    <button @click="togglePanel" class="text-gray-400 hover:text-white">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Search -->
                <div class="panel-search">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar vehículo..."
                        class="search-input"
                    />
                </div>

                <!-- Vehicle List -->
                <div class="panel-list">
                    <div v-if="filteredVehicles.length === 0" class="text-center py-8 text-gray-500">
                        No se encontraron vehículos
                    </div>

                    <div v-for="group in groupedVehicles" :key="group.clientId" class="mb-3">
                        <!-- Client Header (SA only) -->
                        <button
                            v-if="isSuperAdmin && groupedVehicles.length > 1"
                            @click="toggleClient(group.clientId)"
                            class="client-header"
                        >
                            <div class="flex items-center gap-2">
                                <User class="w-4 h-4 text-gray-400" />
                                <span class="text-gray-300">{{ group.clientName }}</span>
                                <span v-if="group.company" class="text-xs text-gray-500">· {{ group.company }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500 bg-gray-700 px-2 py-0.5 rounded-full">
                                    {{ group.vehicles.length }}
                                </span>
                                <ChevronDown 
                                    class="w-4 h-4 text-gray-500 transition-transform" 
                                    :class="{ 'rotate-180': expandedClients.has(group.clientId) }"
                                />
                            </div>
                        </button>

                        <!-- Vehicles -->
                        <div 
                            v-if="!isSuperAdmin || groupedVehicles.length === 1 || expandedClients.has(group.clientId)"
                            class="space-y-1"
                            :class="{ 'pl-4 mt-2': isSuperAdmin && groupedVehicles.length > 1 }"
                        >
                            <button
                                v-for="vehicle in group.vehicles"
                                :key="vehicle.id"
                                @click="selectVehicle(vehicle.id)"
                                class="vehicle-item"
                                :class="{ 'vehicle-item-active': vehicle.id === currentVehicleId }"
                            >
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div 
                                        class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                        :class="vehicle.id === currentVehicleId ? 'bg-cyan-500' : 'bg-gray-700'"
                                    >
                                        <Car class="w-4 h-4" :class="vehicle.id === currentVehicleId ? 'text-white' : 'text-gray-400'" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium truncate" :class="vehicle.id === currentVehicleId ? 'text-cyan-100' : 'text-white'">
                                            {{ getDisplayName(vehicle) }}
                                        </p>
                                        <div class="flex items-center gap-2 text-xs text-gray-400">
                                            <span v-if="vehicle.license_plate" class="text-cyan-500">{{ vehicle.license_plate }}</span>
                                            <span v-else-if="vehicle.vin" class="truncate">{{ vehicle.vin }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="vehicle.id === currentVehicleId" class="text-xs text-cyan-400 flex-shrink-0">
                                    Activo
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.vehicle-selector-container {
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 100;
}

/* Badge (collapsed) */
.vehicle-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: rgba(17, 24, 39, 0.95);
    border: 1px solid rgba(107, 114, 128, 0.3);
    border-radius: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    backdrop-filter: blur(8px);
}

.vehicle-badge:hover {
    border-color: rgba(6, 182, 212, 0.5);
    background: rgba(31, 41, 55, 0.95);
}

.badge-connected {
    border-color: rgba(34, 197, 94, 0.3);
    box-shadow: 0 0 20px rgba(34, 197, 94, 0.1);
}

.badge-icon {
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #06b6d4, #3b82f6);
    border-radius: 0.5rem;
    color: white;
}

.badge-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.badge-name {
    font-weight: 600;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

.badge-plate {
    font-size: 0.75rem;
    color: rgb(6, 182, 212);
    font-weight: 500;
}

.badge-status {
    display: flex;
    align-items: center;
}

/* Panel (expanded) */
.vehicle-panel {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    width: 320px;
    max-height: 400px;
    background: rgba(17, 24, 39, 0.98);
    border: 1px solid rgba(107, 114, 128, 0.3);
    border-radius: 1rem;
    overflow: hidden;
    backdrop-filter: blur(12px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid rgba(107, 114, 128, 0.2);
}

.panel-search {
    position: relative;
    padding: 0.5rem 1rem;
    border-bottom: 1px solid rgba(107, 114, 128, 0.2);
}

.search-input {
    width: 100%;
    padding: 0.5rem 0.5rem 0.5rem 2.5rem;
    background: rgba(31, 41, 55, 0.5);
    border: 1px solid rgba(107, 114, 128, 0.3);
    border-radius: 0.5rem;
    color: white;
    font-size: 0.875rem;
}

.search-input::placeholder {
    color: rgb(107, 114, 128);
}

.search-input:focus {
    outline: none;
    border-color: rgb(6, 182, 212);
}

.panel-list {
    max-height: 280px;
    overflow-y: auto;
    padding: 0.5rem;
}

/* Client Header */
.client-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.5rem;
    border-radius: 0.5rem;
    text-align: left;
    transition: background 0.2s;
}

.client-header:hover {
    background: rgba(55, 65, 81, 0.5);
}

/* Vehicle Item */
.vehicle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.75rem;
    border-radius: 0.75rem;
    border: 1px solid transparent;
    text-align: left;
    transition: all 0.2s;
}

.vehicle-item:hover {
    background: rgba(55, 65, 81, 0.5);
    border-color: rgba(107, 114, 128, 0.3);
}

.vehicle-item-active {
    background: rgba(6, 182, 212, 0.15);
    border-color: rgba(6, 182, 212, 0.4);
}

/* Scrollbar */
.panel-list::-webkit-scrollbar {
    width: 4px;
}

.panel-list::-webkit-scrollbar-thumb {
    background: rgb(75, 85, 99);
    border-radius: 2px;
}

.panel-list::-webkit-scrollbar-track {
    background: transparent;
}

/* Transitions */
.slide-enter-active,
.slide-leave-active {
    transition: all 0.2s ease;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Mobile adjustments */
@media (max-width: 640px) {
    .vehicle-selector-container {
        top: 0.5rem;
        left: 0.5rem;
    }

    .badge-name {
        max-width: 120px;
    }

    .vehicle-panel {
        width: calc(100vw - 1rem);
        max-width: 320px;
    }
}
</style>
