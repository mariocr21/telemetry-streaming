<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Settings, Save, Loader2, X } from 'lucide-vue-next';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

interface VehicleSensor {
    id: number;
    is_active: boolean;
    frequency_seconds: number;
    min_value?: number;
    max_value?: number;
    mapping_key?: string;
    source_type?: string;
    sensor: {
        id: number;
        name: string;
        pid: string;
        unit: string;
    }
}

interface Props {
    open: boolean;
    sensor: VehicleSensor | null;
    clientId: number;
    deviceId: number;
    vehicleId: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'updated'): void;
}>();

const form = useForm({
    is_active: true,
    frequency_seconds: 5,
    min_value: null as number | null,
    max_value: null as number | null,
    mapping_key: '',
    source_type: 'OBD2',
});

// Sync form with sensor when opened
watch(() => props.sensor, (newSensor) => {
    if (newSensor) {
        form.is_active = !!newSensor.is_active;
        form.frequency_seconds = newSensor.frequency_seconds;
        form.min_value = newSensor.min_value ?? null;
        form.max_value = newSensor.max_value ?? null;
        form.mapping_key = newSensor.mapping_key ?? '';
        form.source_type = newSensor.source_type ?? 'OBD2';
    }
}, { immediate: true });

const submit = () => {
    if (!props.sensor) return;

    form.transform((data) => ({
        ...data,
        vehicle_sensor_id: props.sensor?.id, // Añadir ID al payload
    })).post(route('clients.devices.vehicles.update-sensor-config', [
        props.clientId, 
        props.deviceId, 
        props.vehicleId
    ]), {
        onSuccess: () => {
            emit('update:open', false);
            emit('updated');
        },
    });
};

const closeModal = () => {
    emit('update:open', false);
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 bg-black/50 dark:bg-black/70 backdrop-blur-sm" @click="closeModal" />
        </Transition>

        <Transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition-all duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="open && sensor" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModal">
                <div class="w-full max-w-lg bg-white dark:bg-gray-900 rounded-xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700">
                    <!-- Header with gradient -->
                    <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-white/20 rounded-lg">
                                    <Settings class="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-white">
                                        {{ sensor.sensor.name }}
                                    </h2>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <span class="px-2 py-0.5 bg-white/20 rounded text-xs text-white font-mono">
                                            {{ sensor.sensor.pid }}
                                        </span>
                                        <span class="text-white/80 text-sm">{{ sensor.sensor.unit }}</span>
                                    </div>
                                </div>
                            </div>
                            <button @click="closeModal" class="p-2 hover:bg-white/20 rounded-lg transition-colors">
                                <X class="h-5 w-5 text-white" />
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Estado del sensor -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <div>
                                <label class="text-sm font-semibold text-gray-900 dark:text-gray-100">Sensor Activo</label>
                                <p class="text-xs text-gray-500 mt-0.5">Habilitar o deshabilitar este sensor</p>
                            </div>
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" v-model="form.is_active" class="peer sr-only">
                                <div class="peer h-7 w-14 rounded-full bg-gray-200 after:absolute after:left-[4px] after:top-[4px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-green-500 peer-checked:after:translate-x-7 peer-checked:after:border-white peer-focus:outline-none dark:bg-gray-700"></div>
                            </label>
                        </div>

                        <!-- Configuración de Datos -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex items-center">
                                <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                Configuración de Datos
                            </h3>
                            
                            <!-- Mapping Key -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Cloud ID (Mapping Key)
                                </label>
                                <input 
                                    v-model="form.mapping_key"
                                    type="text"
                                    placeholder="Ej: engine_temp_custom, o PID Hex"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow"
                                />
                                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    ⚡ La clave exacta que envía el firmware (campo 'cloud_id' en JSON)
                                </p>
                            </div>

                            <!-- Source Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Origen de Datos
                                </label>
                                <select 
                                    v-model="form.source_type"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow"
                                >
                                    <option value="OBD2">🔌 OBD2 (Estándar)</option>
                                    <option value="CAN_CUSTOM">🔧 CAN Bus (Custom)</option>
                                    <option value="GPS">📍 Calculado GPS</option>
                                    <option value="VIRTUAL">⚙️ Virtual / Calculado</option>
                                </select>
                            </div>

                            <!-- Frecuencia -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Frecuencia de Lectura
                                </label>
                                <div class="relative">
                                    <input 
                                        v-model.number="form.frequency_seconds"
                                        type="number"
                                        min="1"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 pr-20 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow"
                                    />
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-gray-500">segundos</span>
                                </div>
                            </div>
                        </div>

                        <!-- Alertas -->
                        <div class="space-y-4">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex items-center">
                                <span class="w-2 h-2 bg-orange-500 rounded-full mr-2"></span>
                                Alertas (Opcional)
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Valor Mínimo
                                    </label>
                                    <div class="relative">
                                        <input 
                                            v-model.number="form.min_value"
                                            type="number"
                                            step="any"
                                            placeholder="Min"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 pr-12 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow"
                                        />
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">{{ sensor.sensor.unit }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Valor Máximo
                                    </label>
                                    <div class="relative">
                                        <input 
                                            v-model.number="form.max_value"
                                            type="number"
                                            step="any"
                                            placeholder="Max"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3 pr-12 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow"
                                        />
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">{{ sensor.sensor.unit }}</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                🔔 Se generará una alerta si el valor sale de estos límites
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <Button type="button" variant="outline" @click="closeModal" class="px-6">
                                Cancelar
                            </Button>
                            <Button type="submit" :disabled="form.processing" class="px-6 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700">
                                <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                <Save v-else class="mr-2 h-4 w-4" />
                                Guardar Cambios
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
