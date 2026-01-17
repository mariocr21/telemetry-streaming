<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { onMounted } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Force Dark Mode logic purely for consistency with the rest of the app now
onMounted(() => {
    document.documentElement.classList.add('dark');
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login - Neurona Telemetry">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    </Head>

    <div class="min-h-screen bg-[#050505] flex flex-col justify-center items-center p-4 selection:bg-[#00e1ff] selection:text-black font-sans relative overflow-hidden">
        
        <!-- Subtle Ambient Background -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[600px] h-[600px] bg-[#00e1ff] opacity-[0.03] rounded-full blur-[120px]"></div>
        </div>

        <div class="w-full max-w-sm relative z-10">
            
            <!-- Logo & Header -->
            <div class="flex flex-col items-center mb-8">
                <Link href="/" class="group mb-6">
                    <div class="w-16 h-16 bg-[#ffffff05] rounded-2xl flex items-center justify-center text-[#00e1ff] border border-[#ffffff10] transition-all duration-300 group-hover:border-[#00e1ff]/30 group-hover:shadow-[0_0_20px_rgba(0,225,255,0.1)]">
                        <AppLogoIcon class="size-8" />
                    </div>
                </Link>
                
                <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-1">
                    <span class="text-[#00e1ff]"></span><span>NEURONA</span>
                </h1>
                <p class="text-[0.6rem] font-bold tracking-[3px] text-[#00e1ff] uppercase mt-1 glow-text opacity-90">
                    OFF ROAD TELEMETRY
                </p>
            </div>

            <!-- Login Container -->
            <div class="bg-[#0a0c10] border border-[#ffffff10] rounded-xl shadow-2xl p-8 backdrop-blur-sm">
                
                <div v-if="status" class="mb-6 p-3 text-sm text-[#00e1ff] bg-[#00e1ff]/10 border border-[#00e1ff]/20 rounded text-center">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    
                    <!-- Email -->
                    <div class="space-y-2">
                        <Label for="email" class="text-xs uppercase font-semibold text-gray-500 tracking-wider">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            v-model="form.email"
                            class="bg-[#050505] border-[#ffffff15] text-white focus:border-[#00e1ff] focus:ring-1 focus:ring-[#00e1ff] h-11 transition-all"
                            placeholder="admin@neurona.com"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <Label for="password" class="text-xs uppercase font-semibold text-gray-500 tracking-wider">Password</Label>
                             <TextLink 
                                v-if="canResetPassword" 
                                :href="route('password.request')" 
                                class="text-xs text-[#00e1ff] hover:text-[#33e7ff] transition-colors"
                            >
                                ¿Recuperar?
                            </TextLink>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            v-model="form.password"
                            class="bg-[#050505] border-[#ffffff15] text-white focus:border-[#00e1ff] focus:ring-1 focus:ring-[#00e1ff] h-11 transition-all"
                            placeholder="••••••••"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Remember -->
                    <div class="flex items-center">
                        <Checkbox 
                            id="remember" 
                            v-model="form.remember" 
                            class="border-gray-600 data-[state=checked]:bg-[#00e1ff] data-[state=checked]:text-black data-[state=checked]:border-[#00e1ff]"
                        />
                        <label for="remember" class="ml-2 text-sm text-gray-400 cursor-pointer hover:text-white transition-colors">Recordar sesión</label>
                    </div>

                    <Button 
                        type="submit" 
                        class="w-full h-11 bg-[#00e1ff] hover:bg-[#33e7ff] text-black font-bold uppercase tracking-wide transition-all shadow-[0_0_15px_rgba(0,225,255,0.1)] hover:shadow-[0_0_25px_rgba(0,225,255,0.3)]" 
                        :disabled="form.processing"
                    >
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                        {{ form.processing ? 'Accediendo...' : 'Iniciar Sesión' }}
                    </Button>
                </form>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-xs text-gray-600">
                    &copy; 2026 Neurona Telemetry System
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glow-text {
    text-shadow: 0 0 10px rgba(0, 225, 255, 0.4);
}
</style>