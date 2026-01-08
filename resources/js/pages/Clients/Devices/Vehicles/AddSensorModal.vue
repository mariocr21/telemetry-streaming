<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Badge from '@/components/ui/Badge.vue';
import {
    X,
    Search,
    Plus,
    Check,
    Cpu,
    Activity,
    Gauge,
    Zap,
    Database,
    AlertTriangle,
} from 'lucide-vue-next';

interface Sensor {
    id: number;
    pid: string;
    name: string;
    description: string | null;
    category: string;
    unit: string;
    is_standard: boolean;
}

interface Props {
    show: boolean;
    vehicleId: number;
    clientId: number;
    deviceId: number;
    availableSensors: Sensor[];
    existingSensorIds: number[];
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'added']);

const searchQuery = ref('');
const selectedCategory = ref('');
const selectedSensors = ref<number[]>([]);
const isSubmitting = ref(false);
const mappingKeys = ref<Record<number, string>>({});

// Categorías únicas
const categories = computed(() => {
    const cats = new Set(props.availableSensors.map(s => s.category));
    return Array.from(cats).sort();
});

// Sensores filtrados
const filteredSensors = computed(() => {
    let result = props.availableSensors.filter(s => !props.existingSensorIds.includes(s.id));
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(s => 
            s.name.toLowerCase().includes(query) ||
            s.pid.toLowerCase().includes(query) ||
            s.category.toLowerCase().includes(query)
        );
    }
    
    if (selectedCategory.value) {
        result = result.filter(s => s.category === selectedCategory.value);
    }
    
    return result;
});

// Toggle sensor selection
const toggleSensor = (sensorId: number) => {
    const index = selectedSensors.value.indexOf(sensorId);
    if (index > -1) {
        selectedSensors.value.splice(index, 1);
        delete mappingKeys.value[sensorId];
    } else {
        selectedSensors.value.push(sensorId);
        // Pre-populate mapping key with PID
        const sensor = props.availableSensors.find(s => s.id === sensorId);
        if (sensor) {
            mappingKeys.value[sensorId] = sensor.pid;
        }
    }
};

const isSensorSelected = (sensorId: number) => selectedSensors.value.includes(sensorId);

// Get icon for category
const getCategoryIcon = (category: string) => {
    const icons: Record<string, any> = {
        engine: Activity,
        fuel: Gauge,
        diagnostics: Cpu,
        vehicle: Zap,
        temperature: AlertTriangle,
    };
    return icons[category] || Database;
};

const getCategoryColor = (category: string) => {
    const colors: Record<string, string> = {
        engine: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        fuel: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        diagnostics: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        vehicle: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        temperature: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        electrical: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        transmission: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    };
    return colors[category] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};

// Submit selection
const submitSelection = () => {
    if (selectedSensors.value.length === 0) return;
    
    isSubmitting.value = true;
    
    // Prepare data
    const sensorsToAdd = selectedSensors.value.map(sensorId => ({
        sensor_id: sensorId,
        mapping_key: mappingKeys.value[sensorId] || null,
        source_type: props.availableSensors.find(s => s.id === sensorId)?.is_standard ? 'OBD2' : 'CAN_CUSTOM',
    }));
    
    router.post(
        `/clients/${props.clientId}/devices/${props.deviceId}/vehicles/${props.vehicleId}/add-sensors`,
        { sensors: sensorsToAdd },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedSensors.value = [];
                mappingKeys.value = {};
                emit('added');
                emit('close');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
};

const closeModal = () => {
    selectedSensors.value = [];
    mappingKeys.value = {};
    searchQuery.value = '';
    selectedCategory.value = '';
    emit('close');
};

// Reset when modal opens
watch(() => props.show, (newVal) => {
    if (newVal) {
        selectedSensors.value = [];
        mappingKeys.value = {};
        searchQuery.value = '';
        selectedCategory.value = '';
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal"></div>
                
                <!-- Modal -->
                <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center space-x-3">
                            <div class="rounded-lg bg-cyan-100 p-2 dark:bg-cyan-900/50">
                                <Plus class="h-5 w-5 text-cyan-600 dark:text-cyan-400" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Agregar Sensores al Vehículo
                                </h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Selecciona sensores del catálogo global
                                </p>
                            </div>
                        </div>
                        <button
                            @click="closeModal"
                            class="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    
                    <!-- Search & Filters -->
                    <div class="border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Buscar por nombre o PID..."
                                    class="pl-10"
                                />
                            </div>
                            <select
                                v-model="selectedCategory"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                            >
                                <option value="">Todas las categorías</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">
                                    {{ cat.charAt(0).toUpperCase() + cat.slice(1) }}
                                </option>
                            </select>
                        </div>
                        
                        <!-- Selected count -->
                        <div v-if="selectedSensors.length > 0" class="mt-3 flex items-center space-x-2">
                            <Badge class="bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-400">
                                {{ selectedSensors.length }} sensor{{ selectedSensors.length !== 1 ? 'es' : '' }} seleccionado{{ selectedSensors.length !== 1 ? 's' : '' }}
                            </Badge>
                        </div>
                    </div>
                    
                    <!-- Sensor List -->
                    <div class="max-h-[50vh] overflow-y-auto px-6 py-4">
                        <div v-if="filteredSensors.length === 0" class="py-12 text-center">
                            <Gauge class="mx-auto h-12 w-12 text-gray-300" />
                            <p class="mt-4 text-gray-500 dark:text-gray-400">
                                {{ searchQuery || selectedCategory ? 'No se encontraron sensores con esos filtros' : 'Todos los sensores ya están asignados' }}
                            </p>
                        </div>
                        
                        <div v-else class="space-y-2">
                            <div
                                v-for="sensor in filteredSensors"
                                :key="sensor.id"
                                @click="toggleSensor(sensor.id)"
                                :class="[
                                    'cursor-pointer rounded-lg border-2 p-4 transition-all',
                                    isSensorSelected(sensor.id)
                                        ? 'border-cyan-500 bg-cyan-50 dark:bg-cyan-900/20'
                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                ]"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div
                                            :class="[
                                                'flex h-10 w-10 items-center justify-center rounded-lg',
                                                isSensorSelected(sensor.id)
                                                    ? 'bg-cyan-600 text-white'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700'
                                            ]"
                                        >
                                            <Check v-if="isSensorSelected(sensor.id)" class="h-5 w-5" />
                                            <component v-else :is="getCategoryIcon(sensor.category)" class="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ sensor.name }}
                                                </span>
                                                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-cyan-600 dark:bg-gray-700 dark:text-cyan-400">
                                                    {{ sensor.pid }}
                                                </code>
                                            </div>
                                            <div class="mt-1 flex items-center space-x-2">
                                                <Badge :class="getCategoryColor(sensor.category)" class="text-xs">
                                                    {{ sensor.category }}
                                                </Badge>
                                                <Badge
                                                    :class="sensor.is_standard
                                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'"
                                                    class="text-xs"
                                                >
                                                    {{ sensor.is_standard ? 'OBD' : 'Custom' }}
                                                </Badge>
                                                <span class="text-xs text-gray-500">{{ sensor.unit }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Mapping key input (when selected) -->
                                <div v-if="isSensorSelected(sensor.id)" class="mt-3" @click.stop>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                        Mapping Key (ID que envía el firmware)
                                    </label>
                                    <Input
                                        v-model="mappingKeys[sensor.id]"
                                        :placeholder="sensor.pid"
                                        class="font-mono text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800">
                        <Button variant="outline" @click="closeModal">
                            Cancelar
                        </Button>
                        <Button 
                            @click="submitSelection"
                            :disabled="selectedSensors.length === 0 || isSubmitting"
                            class="bg-cyan-600 text-white hover:bg-cyan-700"
                        >
                            <Plus v-if="!isSubmitting" class="mr-2 h-4 w-4" />
                            <span v-if="isSubmitting">Agregando...</span>
                            <span v-else>Agregar {{ selectedSensors.length }} Sensor{{ selectedSensors.length !== 1 ? 'es' : '' }}</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
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
</style>
