<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
interface LogEntry {
    message: string;
    level: string;
    context: Record<string, unknown>;
    datetime: string;
    expanded?: boolean;
}

const logs = ref<LogEntry[]>([]);

onMounted(() => {
    window.Echo.private('log-monitoring').listen('LogEntryCreated', (e: LogEntry) => {
        console.log('Nuevo log recibido:', e);

        // Agregar propiedad expanded para controlar el JSON viewer
        logs.value.unshift({ ...e, expanded: false });

        if (logs.value.length > 50) {
            logs.value.pop();
        }
    });
});

onUnmounted(() => {
    window.Echo.leave('log-monitoring');
});

const toggleExpand = (index: number) => {
    logs.value[index].expanded = !logs.value[index].expanded;
};

const getLevelClass = (level: string) => {
    const classes: Record<string, string> = {
        ERROR: 'text-red-400 bg-red-900/50 border-red-700',
        WARNING: 'text-yellow-400 bg-yellow-900/50 border-yellow-700',
        INFO: 'text-blue-400 bg-blue-900/50 border-blue-700',
        DEBUG: 'text-gray-400 bg-gray-700/50 border-gray-600',
    };
    return classes[level] || 'text-white bg-gray-700 border-gray-600';
};

const getLevelIcon = (level: string) => {
    const icons: Record<string, string> = {
        ERROR: '❌',
        WARNING: '⚠️',
        INFO: 'ℹ️',
        DEBUG: '🔍',
    };
    return icons[level] || '📝';
};

const formatDateTime = (datetime: string) => {
    try {
        const date = new Date(datetime);
        return date.toLocaleString('es-MX', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            day: '2-digit',
            month: 'short',
        });
    } catch {
        return datetime;
    }
};

const formatJson = (obj: unknown): string => {
    return JSON.stringify(obj, null, 2);
};

const hasContext = (log: LogEntry): boolean => {
    return log.context && Object.keys(log.context).length > 0;
};

const clearLogs = () => {
    logs.value = [];
};
</script>

<template>
    <AppLayout>
    <Head title="Monitoreo de logs" />
    <div class="mx-4 p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">👁️ Monitoreo de Logs en Tiempo Real</h1>
                <p class="mt-1 text-gray-400">
                    Canal: <code class="rounded bg-gray-700 px-2 py-0.5 text-green-400">log-monitoring</code>
                    <span class="ml-3 inline-flex items-center">
                        <span class="mr-1.5 h-2 w-2 animate-pulse rounded-full bg-green-500"></span>
                        Conectado
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-4">
                <span class="rounded-full bg-gray-700 px-3 py-1 text-sm text-gray-300"> {{ logs.length }} logs </span>
                <button @click="clearLogs" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">
                    🗑️ Limpiar
                </button>
            </div>
        </div>

        <!-- Log Container -->
        <div class="max-h-[75vh] overflow-y-auto rounded-xl bg-gray-900 shadow-2xl">
            <!-- Empty State -->
            <div v-if="logs.length === 0" class="flex flex-col items-center justify-center py-20 text-gray-500">
                <div class="mb-4 text-6xl">📭</div>
                <p class="text-lg">Esperando la llegada de nuevos logs...</p>
                <p class="mt-2 text-sm text-gray-600">Los logs aparecerán aquí en tiempo real</p>
            </div>

            <!-- Log Entries -->
            <div v-else class="divide-y divide-gray-800">
                <div v-for="(log, index) in logs" :key="index" class="transition-colors hover:bg-gray-800/50">
                    <!-- Log Header -->
                    <div class="flex cursor-pointer items-start gap-3 p-4" @click="toggleExpand(index)">
                        <!-- Expand Icon -->
                        <button class="mt-0.5 text-gray-500 transition hover:text-white">
                            <svg
                                :class="['h-4 w-4 transition-transform', { 'rotate-90': log.expanded }]"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <!-- Level Badge -->
                        <span :class="['inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs font-semibold', getLevelClass(log.level)]">
                            {{ getLevelIcon(log.level) }} {{ log.level }}
                        </span>

                        <!-- Timestamp -->
                        <span class="text-sm whitespace-nowrap text-gray-500">
                            {{ formatDateTime(log.datetime) }}
                        </span>

                        <!-- Message -->
                        <span class="flex-1 font-mono text-sm text-gray-200">
                            {{ log.message }}
                        </span>

                        <!-- Context Indicator -->
                        <span v-if="hasContext(log)" class="rounded bg-purple-900/50 px-2 py-0.5 text-xs text-purple-400"> + context </span>
                    </div>

                    <!-- Expanded JSON View -->
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-[500px]"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-from-class="opacity-100 max-h-[500px]"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div v-if="log.expanded" class="overflow-hidden">
                            <div class="mx-4 mb-4 rounded-lg bg-gray-950 p-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-semibold tracking-wider text-gray-500 uppercase"> Raw JSON </span>
                                    <button
                                        @click.stop="navigator.clipboard.writeText(formatJson(log))"
                                        class="rounded bg-gray-800 px-2 py-1 text-xs text-gray-400 transition hover:bg-gray-700 hover:text-white"
                                    >
                                        📋 Copiar
                                    </button>
                                </div>
                                <pre class="overflow-x-auto text-xs leading-relaxed"><code class="text-green-400">{{ formatJson(log) }}</code></pre>

                                <!-- Context Section (if exists) -->
                                <div v-if="hasContext(log)" class="mt-4 border-t border-gray-800 pt-4">
                                    <span class="mb-2 block text-xs font-semibold tracking-wider text-purple-400 uppercase"> Context </span>
                                    <pre
                                        class="overflow-x-auto text-xs leading-relaxed"
                                    ><code class="text-purple-300">{{ formatJson(log.context) }}</code></pre>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>

        <!-- Footer Stats -->
        <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
            <div class="flex gap-4">
                <span> ❌ Errores: {{ logs.filter((l) => l.level === 'ERROR').length }} </span>
                <span> ⚠️ Warnings: {{ logs.filter((l) => l.level === 'WARNING').length }} </span>
                <span> ℹ️ Info: {{ logs.filter((l) => l.level === 'INFO').length }} </span>
            </div>
            <span>Máximo: 50 logs</span>
        </div>
    </div>
    </AppLayout>
</template>

<style scoped>
/* Scrollbar personalizado */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #1f2937;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #4b5563;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}

/* Syntax highlighting básico para JSON */
code {
    font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
}
</style>
