<script setup lang="ts">
import { Head, useForm, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import Label from '@/components/ui/Label.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { 
  ArrowLeft, 
  Save, 
  X,
  User,
  Mail,
  Phone,
  MapPin,
  Building,
  Briefcase,
  AlertCircle,
  CheckCircle2,
  Eye
} from 'lucide-vue-next'
import { BreadcrumbItem } from '@/types'

interface Props {
  client: {
    id: number
    first_name: string
    last_name: string
    full_name: string
    email: string
    phone?: string
    address?: string
    city?: string
    state?: string
    zip_code?: string
    country?: string
    company?: string
    job_title?: string
    created_at: string
    updated_at: string
    can?: {
      view?: boolean
      update?: boolean
      delete?: boolean
    }
  }
}

const props = defineProps<Props>()
const page = usePage()

// Estado de la página
const isSubmitting = ref(false)
const hasUnsavedChanges = ref(false)

// Formulario con Inertia pre-poblado con datos del cliente
const form = useForm({
  first_name: props.client.first_name,
  last_name: props.client.last_name,
  email: props.client.email,
  phone: props.client.phone || '',
  address: props.client.address || '',
  city: props.client.city || '',
  state: props.client.state || '',
  zip_code: props.client.zip_code || '',
  country: props.client.country || '',
  company: props.client.company || '',
  job_title: props.client.job_title || '',
})

// Watch para detectar cambios
const originalData = JSON.stringify({
  first_name: props.client.first_name,
  last_name: props.client.last_name,
  email: props.client.email,
  phone: props.client.phone || '',
  address: props.client.address || '',
  city: props.client.city || '',
  state: props.client.state || '',
  zip_code: props.client.zip_code || '',
  country: props.client.country || '',
  company: props.client.company || '',
  job_title: props.client.job_title || '',
})

// Función para enviar el formulario
const submit = () => {
  isSubmitting.value = true
  form.put(route('clients.update', props.client.id), {
    onSuccess: () => {
      hasUnsavedChanges.value = false
    },
    onFinish: () => {
      isSubmitting.value = false
    },
  })
}

// Función para detectar cambios
const checkForChanges = () => {
  const currentData = JSON.stringify(form.data())
  hasUnsavedChanges.value = currentData !== originalData
}

// Computada para el mensaje flash
const flashMessage = computed(() => {
  const flash = page.props.flash as any
  return flash?.message
})

// Función para validar email en tiempo real
const isValidEmail = computed(() => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return !form.email || emailRegex.test(form.email)
})

// Función para resetear formulario
const resetForm = () => {
  form.reset()
  hasUnsavedChanges.value = false
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Clientes',
    href: '/clients',
  },
  {
    title: props.client.full_name,
    href: `/clients/${props.client.id}`,
  },
];
</script>

<template>
  <Head :title="`Editar ${client.first_name} ${client.last_name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <Link :href="route('clients.index')">
            <Button variant="ghost" size="sm" class="text-gray-600 hover:text-gray-900">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Volver a Clientes
            </Button>
          </Link>
          
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                <span class="text-lg font-medium text-blue-600 dark:text-blue-400">
                  {{ client.first_name.charAt(0).toUpperCase() }}{{ client.last_name.charAt(0).toUpperCase() }}
                </span>
              </div>
            </div>
            <div>
              <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Editar Cliente
              </h2>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ client.full_name }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex items-center space-x-3">
          <Link :href="route('clients.show', client.id)">
            <Button variant="outline" size="sm">
              <Eye class="mr-2 h-4 w-4" />
              Ver Detalles
            </Button>
          </Link>
          
          <div v-if="hasUnsavedChanges" class="flex items-center text-amber-600">
            <AlertCircle class="mr-2 h-4 w-4" />
            <span class="text-sm">Cambios sin guardar</span>
          </div>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Mensaje flash -->
        <div 
          v-if="flashMessage" 
          class="mb-6 rounded-lg bg-green-50 p-4 border border-green-200 dark:bg-green-900/20 dark:border-green-800 shadow-sm"
        >
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <CheckCircle2 class="h-5 w-5 text-green-400" />
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800 dark:text-green-200">
                {{ flashMessage }}
              </p>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" @input="checkForChanges" class="space-y-6">
          
          <!-- Información Personal -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <User class="mr-2 h-5 w-5 text-blue-600" />
                Información Personal
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <Label for="first_name" class="required flex items-center">
                    <span>Nombre *</span>
                  </Label>
                  <Input
                    id="first_name"
                    v-model="form.first_name"
                    type="text"
                    placeholder="Ingrese el nombre"
                    :class="{ 'border-red-500 ring-red-500': form.errors.first_name }"
                    required
                  />
                  <div v-if="form.errors.first_name" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.first_name }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="last_name" class="required">Apellido *</Label>
                  <Input
                    id="last_name"
                    v-model="form.last_name"
                    type="text"
                    placeholder="Ingrese el apellido"
                    :class="{ 'border-red-500 ring-red-500': form.errors.last_name }"
                    required
                  />
                  <div v-if="form.errors.last_name" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.last_name }}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Información de Contacto -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Mail class="mr-2 h-5 w-5 text-green-600" />
                Información de Contacto
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <Label for="email" class="required">Correo Electrónico *</Label>
                  <div class="relative">
                    <Mail class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                      id="email"
                      v-model="form.email"
                      type="email"
                      placeholder="ejemplo@correo.com"
                      class="pl-10"
                      :class="{ 
                        'border-red-500 ring-red-500': form.errors.email || !isValidEmail,
                        'border-green-500 ring-green-500': form.email && isValidEmail && !form.errors.email
                      }"
                      required
                    />
                  </div>
                  <div v-if="form.errors.email" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.email }}
                  </div>
                  <div v-else-if="form.email && !isValidEmail" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    Formato de email inválido
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="phone">Teléfono</Label>
                  <div class="relative">
                    <Phone class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                      id="phone"
                      v-model="form.phone"
                      type="tel"
                      placeholder="+52 (664) 123-4567"
                      class="pl-10"
                      :class="{ 'border-red-500 ring-red-500': form.errors.phone }"
                    />
                  </div>
                  <div v-if="form.errors.phone" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.phone }}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Dirección -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <MapPin class="mr-2 h-5 w-5 text-red-600" />
                Dirección
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="space-y-2">
                <Label for="address">Dirección Completa</Label>
                <Textarea
                  id="address"
                  v-model="form.address"
                  placeholder="Ingrese la dirección completa"
                  :class="{ 'border-red-500 ring-red-500': form.errors.address }"
                  :rows="3"
                />
                <div v-if="form.errors.address" class="flex items-center text-sm text-red-600">
                  <AlertCircle class="mr-1 h-4 w-4" />
                  {{ form.errors.address }}
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-2">
                  <Label for="city">Ciudad</Label>
                  <Input
                    id="city"
                    v-model="form.city"
                    type="text"
                    placeholder="Ciudad"
                    :class="{ 'border-red-500 ring-red-500': form.errors.city }"
                  />
                  <div v-if="form.errors.city" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.city }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="state">Estado/Provincia</Label>
                  <Input
                    id="state"
                    v-model="form.state"
                    type="text"
                    placeholder="Estado"
                    :class="{ 'border-red-500 ring-red-500': form.errors.state }"
                  />
                  <div v-if="form.errors.state" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.state }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="zip_code">Código Postal</Label>
                  <Input
                    id="zip_code"
                    v-model="form.zip_code"
                    type="text"
                    placeholder="12345"
                    :class="{ 'border-red-500 ring-red-500': form.errors.zip_code }"
                  />
                  <div v-if="form.errors.zip_code" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.zip_code }}
                  </div>
                </div>
              </div>

              <div class="space-y-2">
                <Label for="country">País</Label>
                <Input
                  id="country"
                  v-model="form.country"
                  type="text"
                  placeholder="País"
                  :class="{ 'border-red-500 ring-red-500': form.errors.country }"
                />
                <div v-if="form.errors.country" class="flex items-center text-sm text-red-600">
                  <AlertCircle class="mr-1 h-4 w-4" />
                  {{ form.errors.country }}
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Información Profesional -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center text-lg">
                <Briefcase class="mr-2 h-5 w-5 text-purple-600" />
                Información Profesional
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <Label for="company">Empresa</Label>
                  <div class="relative">
                    <Building class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                      id="company"
                      v-model="form.company"
                      type="text"
                      placeholder="Nombre de la empresa"
                      class="pl-10"
                      :class="{ 'border-red-500 ring-red-500': form.errors.company }"
                    />
                  </div>
                  <div v-if="form.errors.company" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.company }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="job_title">Cargo</Label>
                  <Input
                    id="job_title"
                    v-model="form.job_title"
                    type="text"
                    placeholder="Cargo o posición"
                    :class="{ 'border-red-500 ring-red-500': form.errors.job_title }"
                  />
                  <div v-if="form.errors.job_title" class="flex items-center text-sm text-red-600">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.job_title }}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Botones de acción -->
          <Card>
            <CardContent class="pt-6">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                  <Link :href="route('clients.index')">
                    <Button type="button" variant="outline">
                      <X class="mr-2 h-4 w-4" />
                      Cancelar
                    </Button>
                  </Link>
                  
                  <Button 
                    type="button" 
                    variant="outline" 
                    @click="resetForm"
                    :disabled="!hasUnsavedChanges"
                  >
                    Deshacer Cambios
                  </Button>
                </div>

                <div class="flex items-center space-x-4">
                  <div v-if="hasUnsavedChanges" class="flex items-center text-sm text-amber-600">
                    <AlertCircle class="mr-2 h-4 w-4" />
                    <span>Tienes cambios sin guardar</span>
                  </div>
                  
                  <Button 
                    type="submit" 
                    :disabled="isSubmitting || !isValidEmail || !form.first_name || !form.last_name || !form.email"
                    class="bg-blue-600 hover:bg-blue-700 min-w-[140px]"
                  >
                    <Save class="mr-2 h-4 w-4" />
                    {{ isSubmitting ? 'Guardando...' : 'Guardar Cambios' }}
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.required::after {
  content: " *";
  color: rgb(239 68 68);
}
</style>