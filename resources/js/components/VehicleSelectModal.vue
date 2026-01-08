<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from '@/i18n/useI18n';
import { ChevronDown, Car, Wifi, WifiOff, User, Search, X } from 'lucide-vue-next';

interface Vehicle {
    id: number;
    make: string | null;
    model: string | null;
    year: number | null;
    nickname: string | null;
    vin: string | null;
    license_plate: string | null;
}

interface Device {
    id: number;
    device_name: string;
    status: string;
    active_vehicle: Vehicle | null;
    client?: {
        id: number;
        full_name: string;
        company: string | null;
    };
}

interface DevicesInterface {
    data: Device[];
}

// Props
const props = defineProps<{
    show: boolean;
    devices: DevicesInterface;
    selectedDeviceId: number | null;
    isSuperAdmin?: boolean;
}>();

// Eventos
const emit = defineEmits(['close', 'select']);

// Estado
const { t } = useI18n();
const searchQuery = ref('');
const expandedClients = ref<Set<number>>(new Set());

// Computed: Agrupar dispositivos por cliente
const groupedDevices = computed(() => {
    if (!props.isSuperAdmin) {
        // Para clientes normales, retornar todos sin agrupar
        return [{
            clientId: 0,
            clientName: 'Mis Dispositivos',
            company: null,
            devices: filteredDevices.value
        }];
    }

    // Para Super Admin, agrupar por cliente
    const groups: Record<number, {
        clientId: number;
        clientName: string;
        company: string | null;
        devices: Device[];
    }> = {};

    filteredDevices.value.forEach(device => {
        const clientId = device.client?.id || 0;
        const clientName = device.client?.full_name || 'Sin cliente';
        const company = device.client?.company || null;

        if (!groups[clientId]) {
            groups[clientId] = {
                clientId,
                clientName,
                company,
                devices: []
            };
        }
        groups[clientId].devices.push(device);
    });

    return Object.values(groups).sort((a, b) => 
        a.clientName.localeCompare(b.clientName)
    );
});

// Computed: Filtrar dispositivos por búsqueda
const filteredDevices = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.devices.data;
    }

    const query = searchQuery.value.toLowerCase();
    return props.devices.data.filter(device => {
        const vehicleName = getVehicleDisplayName(device.active_vehicle);
        const searchableText = [
            device.device_name,
            vehicleName,
            device.active_vehicle?.vin,
            device.active_vehicle?.license_plate,
            device.client?.full_name,
            device.client?.company
        ].filter(Boolean).join(' ').toLowerCase();

        return searchableText.includes(query);
    });
});

// Computed: Dispositivo seleccionado actualmente
const currentDevice = computed(() => {
    return props.devices.data.find(d => d.id === props.selectedDeviceId);
});

// Obtener nombre legible del vehículo
const getVehicleDisplayName = (vehicle: Vehicle | null): string => {
    if (!vehicle) return 'Sin vehículo';
    
    if (vehicle.nickname) return vehicle.nickname;
    
    const parts = [
        vehicle.make,
        vehicle.model,
        vehicle.year ? `(${vehicle.year})` : null
    ].filter(Boolean);
    
    if (parts.length > 0) return parts.join(' ');
    if (vehicle.license_plate) return vehicle.license_plate;
    if (vehicle.vin) return vehicle.vin;
    
    return 'Vehículo sin nombre';
};

// Toggle expandir/colapsar grupo de cliente
const toggleClient = (clientId: number) => {
    if (expandedClients.value.has(clientId)) {
        expandedClients.value.delete(clientId);
    } else {
        expandedClients.value.add(clientId);
    }
};

// Seleccionar dispositivo
const selectDevice = (deviceId: number) => {
    emit('select', deviceId);
};

// Cerrar modal
const closeModal = () => {
    searchQuery.value = '';
    emit('close');
};

// Inicializar clientes expandidos para mostrar el seleccionado
const initExpandedClients = () => {
    if (props.isSuperAdmin && currentDevice.value?.client?.id) {
        expandedClients.value.add(currentDevice.value.client.id);
    }
};

// Cuando se muestra el modal, expandir el cliente actual
import { watch } from 'vue';
watch(() => props.show, (show) => {
    if (show) {
        initExpandedClients();
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div 
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto"
            >
                <!-- Backdrop -->
                <div 
                    class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"
                    @click="closeModal"
                ></div>
                
                <!-- Modal Content -->
                <div
                    class="relative z-10 mx-4 w-full max-w-xl transform rounded-2xl border border-gray-700 bg-gray-900 shadow-2xl transition-all"
                    @click.stop
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-gray-700 px-6 py-4">
                        <div class="flex items-center space-x-3">
                            <div class="rounded-lg bg-cyan-900/50 p-2">
                                <Car class="h-5 w-5 text-cyan-400" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">Seleccionar Vehículo</h3>
                                <p class="text-sm text-gray-400">{{ devices.data.length }} dispositivos disponibles</p>
                            </div>
                        </div>
                        <button 
                            @click="closeModal" 
                            class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-800 hover:text-white"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Vehículo Actual -->
                    <div v-if="currentDevice" class="border-b border-gray-700 bg-gray-800/50 px-6 py-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Seleccionado actualmente</p>
                        <div class="flex items-center space-x-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600">
                                <Car class="h-6 w-6 text-white" />
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-white">
                                    {{ getVehicleDisplayName(currentDevice.active_vehicle) }}
                                </p>
                                <p class="text-sm text-gray-400">
                                    {{ currentDevice.device_name }}
                                    <span v-if="currentDevice.active_vehicle?.license_plate" class="ml-2 text-cyan-400">
                                        {{ currentDevice.active_vehicle.license_plate }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Wifi v-if="currentDevice.status === 'online'" class="h-4 w-4 text-green-400" />
                                <WifiOff v-else class="h-4 w-4 text-red-400" />
                            </div>
                        </div>
                    </div>

                    <!-- Búsqueda -->
                    <div class="border-b border-gray-700 px-6 py-3">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar vehículo, dispositivo o cliente..."
                                class="w-full rounded-lg border border-gray-600 bg-gray-800 py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Lista de Dispositivos -->
                    <div class="max-h-80 overflow-y-auto p-4">
                        <div v-if="groupedDevices.length === 0" class="py-8 text-center text-gray-500">
                            No se encontraron vehículos
                        </div>

                        <div v-for="group in groupedDevices" :key="group.clientId" class="mb-4">
                            <!-- Encabezado de Cliente (solo SA) -->
                            <button
                                v-if="isSuperAdmin && groupedDevices.length > 1"
                                @click="toggleClient(group.clientId)"
                                class="mb-2 flex w-full items-center justify-between rounded-lg bg-gray-800/50 px-3 py-2 text-left transition hover:bg-gray-800"
                            >
                                <div class="flex items-center space-x-2">
                                    <User class="h-4 w-4 text-gray-400" />
                                    <span class="font-medium text-gray-300">{{ group.clientName }}</span>
                                    <span v-if="group.company" class="text-xs text-gray-500">· {{ group.company }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="rounded-full bg-gray-700 px-2 py-0.5 text-xs text-gray-400">
                                        {{ group.devices.length }}
                                    </span>
                                    <ChevronDown 
                                        class="h-4 w-4 text-gray-500 transition-transform" 
                                        :class="{ 'rotate-180': expandedClients.has(group.clientId) }"
                                    />
                                </div>
                            </button>

                            <!-- Lista de Dispositivos del Grupo -->
                            <div 
                                v-if="!isSuperAdmin || groupedDevices.length === 1 || expandedClients.has(group.clientId)"
                                class="space-y-2"
                                :class="{ 'pl-4': isSuperAdmin && groupedDevices.length > 1 }"
                            >
                                <button
                                    v-for="device in group.devices"
                                    :key="device.id"
                                    @click="selectDevice(device.id)"
                                    class="flex w-full items-center rounded-xl border p-3 transition-all duration-200"
                                    :class="[
                                        selectedDeviceId === device.id
                                            ? 'border-cyan-500 bg-cyan-500/20 shadow-lg shadow-cyan-500/10'
                                            : 'border-gray-700 bg-gray-800/50 hover:border-gray-600 hover:bg-gray-800'
                                    ]"
                                >
                                    <!-- Icono -->
                                    <div 
                                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg"
                                        :class="selectedDeviceId === device.id ? 'bg-cyan-500' : 'bg-gray-700'"
                                    >
                                        <Car class="h-5 w-5" :class="selectedDeviceId === device.id ? 'text-white' : 'text-gray-400'" />
                                    </div>

                                    <!-- Info -->
                                    <div class="ml-3 flex-1 text-left">
                                        <p class="font-semibold" :class="selectedDeviceId === device.id ? 'text-cyan-100' : 'text-white'">
                                            {{ getVehicleDisplayName(device.active_vehicle) }}
                                        </p>
                                        <div class="flex items-center space-x-2 text-xs">
                                            <span class="text-gray-400">{{ device.device_name }}</span>
                                            <span v-if="device.active_vehicle?.license_plate" class="text-cyan-500">
                                                {{ device.active_vehicle.license_plate }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Estado -->
                                    <div class="flex items-center space-x-2">
                                        <span 
                                            class="rounded-full px-2 py-1 text-xs font-medium"
                                            :class="device.status === 'online' 
                                                ? 'bg-green-900/50 text-green-400' 
                                                : 'bg-gray-700 text-gray-400'"
                                        >
                                            {{ device.status === 'online' ? 'En línea' : 'Offline' }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end border-t border-gray-700 px-6 py-4">
                        <button
                            @click="closeModal"
                            class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-600"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Transición del modal */
.modal-enter-active,
.modal-leave-active {
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
}

/* Scrollbar personalizado */
.max-h-80::-webkit-scrollbar {
    width: 6px;
}

.max-h-80::-webkit-scrollbar-thumb {
    background-color: #06b6d4;
    border-radius: 3px;
}

.max-h-80::-webkit-scrollbar-track {
    background: #1f2937;
}
</style>
