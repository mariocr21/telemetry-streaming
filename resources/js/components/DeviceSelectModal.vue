<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n';

// Tipos requeridos para props
interface Device {
    id: number;
    device_name: string;
    status: string;
    active_vehicle: {
        vin: string | null;
    } | null;
}

interface devicesInterface {
    data: Device[];
}

// Props esperados
defineProps<{
    show: boolean; // Controla la visibilidad
    devices: devicesInterface;
    selectedDeviceId: number | null;
}>();

// Eventos emitidos
const emit = defineEmits(['close', 'select']);

// Hook i18n
const { t } = useI18n();

// Lógica para la selección
const selectDevice = (deviceId: number) => {
    emit('select', deviceId);
};
</script>

<template>
    <div 
        v-if="show"
        class="bg-opacity-70 fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black backdrop-blur-sm transition-opacity duration-300"
    >
        <div
            class="mx-4 w-full max-w-lg scale-100 transform rounded-xl border border-cyan-500/30 bg-gray-800 opacity-100 shadow-2xl transition-all duration-300"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-gray-700 p-4 sm:p-6">
                <h3 class="text-xl font-bold text-cyan-400">{{ t('selectDeviceModalTitle') }}</h3>
                <button @click="$emit('close')" class="text-gray-400 transition hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="p-4 sm:p-6">
                <p class="mb-4 text-sm text-gray-300">{{ t('manageDevices') }}</p>

                <div class="max-h-72 space-y-3 overflow-y-auto pr-2">
                    <div
                        v-for="device in devices.data"
                        :key="device.id"
                        class="flex cursor-pointer items-center rounded-lg border p-3 transition duration-150"
                        :class="{
                            'border-cyan-500/70 bg-cyan-600 text-white shadow-md': selectedDeviceId === device.id,
                            'border-gray-600 bg-gray-700 text-gray-200 hover:bg-gray-600': selectedDeviceId !== device.id,
                        }"
                        @click="selectDevice(device.id)"
                    >
                        <div class="min-w-0 flex-grow">
                            <p class="truncate font-semibold">{{ device.device_name }}</p>
                            <p class="text-xs opacity-75">VIN: {{ device.active_vehicle?.vin || t('none') }}</p>
                        </div>
                        <span class="rounded-full p-1 px-2 text-xs" :class="{ 'bg-green-700 text-white': true, 'bg-gray-500': false }">
                            {{ t('connectionStatusOnline') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-700 p-4 sm:p-6">
                <button
                    @click="$emit('close')"
                    class="rounded-lg bg-gray-600 px-4 py-2 text-white transition hover:bg-gray-500 focus:ring-2 focus:ring-gray-500 focus:outline-none"
                >
                    {{ t('close') }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Estilo para la barra de desplazamiento en el modal */
.max-h-72::-webkit-scrollbar {
    width: 8px;
}
.max-h-72::-webkit-scrollbar-thumb {
    background-color: #06b6d4; /* Color cian */
    border-radius: 4px;
}
.max-h-72::-webkit-scrollbar-track {
    background: #1f2937; /* Color gris oscuro */
}
</style>