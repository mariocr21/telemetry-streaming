<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import { ArrowLeft, Save, Cpu, Zap, HelpCircle } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Props {
    categories: string[];
    dataTypes: string[];
    sourceTypes: string[];
}

const props = defineProps<Props>();

const form = useForm({
    pid: '',
    name: '',
    description: '',
    category: '',
    unit: '',
    data_type: 'numeric',
    min_value: null as number | null,
    max_value: null as number | null,
    requires_calculation: false,
    calculation_formula: '',
    data_bytes: 1,
    is_standard: true,
    notes: '',
});

const showCustomCategory = ref(false);
const customCategory = ref('');

const submit = () => {
    // Si hay categoría custom, usarla
    if (showCustomCategory.value && customCategory.value) {
        form.category = customCategory.value.toLowerCase();
    }
    
    form.post(route('admin.sensors.store'));
};

const selectCategory = (cat: string) => {
    if (cat === 'custom') {
        showCustomCategory.value = true;
        form.category = '';
    } else {
        showCustomCategory.value = false;
        form.category = cat;
    }
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Sensores', href: '/admin/sensors' },
    { title: 'Crear', href: '/admin/sensors/create' },
];
</script>

<template>
    <Head title="Crear Sensor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header -->
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('admin.sensors.index')">
                    <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Volver
                    </Button>
                </Link>

                <div class="flex items-center space-x-4">
                    <div class="rounded-lg bg-cyan-100 p-3 dark:bg-cyan-900/50">
                        <Cpu class="h-8 w-8 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Nuevo Sensor</h1>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">Agrega un nuevo sensor al catálogo</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Información Básica -->
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <h3 class="mb-6 flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                <Zap class="mr-2 h-5 w-5 text-cyan-500" />
                                Información Básica
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <!-- PID -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        PID / Identificador
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <Input
                                        v-model="form.pid"
                                        placeholder="Ej: 0x0C, RPM_CAN, TEMP_1"
                                        class="font-mono"
                                        :class="{ 'border-red-500': form.errors.pid }"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">Identificador único que envía el dispositivo</p>
                                    <p v-if="form.errors.pid" class="mt-1 text-sm text-red-500">{{ form.errors.pid }}</p>
                                </div>

                                <!-- Nombre -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Nombre del Sensor
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <Input
                                        v-model="form.name"
                                        placeholder="Ej: RPM del Motor"
                                        :class="{ 'border-red-500': form.errors.name }"
                                    />
                                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                                </div>

                                <!-- Descripción -->
                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Descripción
                                    </label>
                                    <textarea
                                        v-model="form.description"
                                        placeholder="Descripción detallada del sensor..."
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    ></textarea>
                                </div>

                                <!-- Categoría -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Categoría
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="cat in categories"
                                            :key="cat"
                                            type="button"
                                            @click="selectCategory(cat)"
                                            :class="[
                                                'rounded-full px-3 py-1 text-sm font-medium transition-all',
                                                form.category === cat && !showCustomCategory
                                                    ? 'bg-cyan-600 text-white'
                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300',
                                            ]"
                                        >
                                            {{ cat }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="selectCategory('custom')"
                                            :class="[
                                                'rounded-full px-3 py-1 text-sm font-medium transition-all',
                                                showCustomCategory
                                                    ? 'bg-cyan-600 text-white'
                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300',
                                            ]"
                                        >
                                            + Nueva
                                        </button>
                                    </div>
                                    <Input
                                        v-if="showCustomCategory"
                                        v-model="customCategory"
                                        placeholder="Nombre de la nueva categoría"
                                        class="mt-2"
                                    />
                                    <p v-if="form.errors.category" class="mt-1 text-sm text-red-500">{{ form.errors.category }}</p>
                                </div>

                                <!-- Unidad -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Unidad de Medida
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <Input
                                        v-model="form.unit"
                                        placeholder="Ej: rpm, °C, km/h, %"
                                        :class="{ 'border-red-500': form.errors.unit }"
                                    />
                                    <p v-if="form.errors.unit" class="mt-1 text-sm text-red-500">{{ form.errors.unit }}</p>
                                </div>

                                <!-- Tipo de Datos -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Tipo de Datos
                                    </label>
                                    <select
                                        v-model="form.data_type"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    >
                                        <option v-for="dt in dataTypes" :key="dt" :value="dt">
                                            {{ dt }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Es Estándar -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Tipo de Sensor
                                    </label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="form.is_standard"
                                                :value="true"
                                                class="h-4 w-4 border-gray-300 text-cyan-600 focus:ring-cyan-500"
                                            />
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">OBD2 Estándar</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="form.is_standard"
                                                :value="false"
                                                class="h-4 w-4 border-gray-300 text-cyan-600 focus:ring-cyan-500"
                                            />
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Custom/CAN Bus</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Configuración de Valores -->
                    <Card class="border border-gray-200 dark:border-gray-700">
                        <CardContent class="p-6">
                            <h3 class="mb-6 flex items-center text-lg font-semibold text-gray-900 dark:text-gray-100">
                                <HelpCircle class="mr-2 h-5 w-5 text-cyan-500" />
                                Configuración de Valores
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <!-- Valor Mínimo -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Valor Mínimo
                                    </label>
                                    <Input v-model.number="form.min_value" type="number" step="any" placeholder="Ej: 0" />
                                </div>

                                <!-- Valor Máximo -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Valor Máximo
                                    </label>
                                    <Input v-model.number="form.max_value" type="number" step="any" placeholder="Ej: 8000" />
                                </div>

                                <!-- Bytes de Datos -->
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Bytes de Datos
                                    </label>
                                    <Input v-model.number="form.data_bytes" type="number" min="1" max="8" placeholder="1" />
                                </div>

                                <!-- Fórmula de Cálculo -->
                                <div class="md:col-span-3">
                                    <label class="mb-2 flex items-center text-sm font-medium text-gray-700 dark:text-gray-300">
                                        <input
                                            type="checkbox"
                                            v-model="form.requires_calculation"
                                            class="mr-2 h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500"
                                        />
                                        Requiere Fórmula de Conversión
                                    </label>
                                    <textarea
                                        v-if="form.requires_calculation"
                                        v-model="form.calculation_formula"
                                        placeholder="Ej: (A * 256 + B) / 4"
                                        rows="2"
                                        class="w-full rounded-lg border border-gray-300 font-mono text-sm px-3 py-2 focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    ></textarea>
                                </div>

                                <!-- Notas -->
                                <div class="md:col-span-3">
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Notas Adicionales
                                    </label>
                                    <textarea
                                        v-model="form.notes"
                                        placeholder="Notas técnicas, consideraciones especiales..."
                                        rows="2"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    ></textarea>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Botones -->
                    <div class="flex items-center justify-end space-x-4">
                        <Link :href="route('admin.sensors.index')">
                            <Button variant="outline" type="button">Cancelar</Button>
                        </Link>
                        <Button type="submit" :disabled="form.processing" class="bg-cyan-600 text-white hover:bg-cyan-700">
                            <Save class="mr-2 h-4 w-4" />
                            {{ form.processing ? 'Guardando...' : 'Crear Sensor' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
