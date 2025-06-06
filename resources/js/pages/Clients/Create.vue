<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import Label from '@/components/ui/Label.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { ArrowLeft, Save } from 'lucide-vue-next'
import { BreadcrumbItem } from '@/types'
// Formulario con Inertia
const form = useForm({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  state: '',
  zip_code: '',
  country: '',
  company: '',
  job_title: '',
})

// Función para enviar el formulario
const submit = () => {
  form.post(route('clients.store'), {
    onSuccess: () => {
      // El redirect se maneja en el backend
    },
  })
}


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: '/clients',
    },
    {
        title: 'Nuevo Cliente',
        href: '/clients/create',
    },
];
</script>

<template>
  <Head title="Nuevo Cliente" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex items-center space-x-4">
        <Link :href="route('clients.index')">
          <Button variant="ghost" size="sm">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Volver
          </Button>
        </Link>
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
          Nuevo Cliente
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <Card>
          <CardHeader>
            <CardTitle>Información del Cliente</CardTitle>
          </CardHeader>
          <CardContent>
            <form @submit.prevent="submit" class="space-y-6">
              <!-- Información personal -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <Label for="first_name" class="required">Nombre *</Label>
                  <Input
                    id="first_name"
                    v-model="form.first_name"
                    type="text"
                    placeholder="Ingrese el nombre"
                    :class="{ 'border-red-500': form.errors.first_name }"
                    required
                  />
                  <div v-if="form.errors.first_name" class="text-sm text-red-600">
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
                    :class="{ 'border-red-500': form.errors.last_name }"
                    required
                  />
                  <div v-if="form.errors.last_name" class="text-sm text-red-600">
                    {{ form.errors.last_name }}
                  </div>
                </div>
              </div>

              <!-- Información de contacto -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <Label for="email" class="required">Correo Electrónico *</Label>
                  <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="ejemplo@correo.com"
                    :class="{ 'border-red-500': form.errors.email }"
                    required
                  />
                  <div v-if="form.errors.email" class="text-sm text-red-600">
                    {{ form.errors.email }}
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="phone">Teléfono</Label>
                  <Input
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    placeholder="+1 (555) 123-4567"
                    :class="{ 'border-red-500': form.errors.phone }"
                  />
                  <div v-if="form.errors.phone" class="text-sm text-red-600">
                    {{ form.errors.phone }}
                  </div>
                </div>
              </div>

              <!-- Dirección -->
              <div class="space-y-4">
                <div class="space-y-2">
                  <Label for="address">Dirección</Label>
                  <Textarea
                    id="address"
                    v-model="form.address"
                    placeholder="Ingrese la dirección completa"
                    :class="{ 'border-red-500': form.errors.address }"
                    :rows="3"
                  />
                  <div v-if="form.errors.address" class="text-sm text-red-600">
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
                      :class="{ 'border-red-500': form.errors.city }"
                    />
                    <div v-if="form.errors.city" class="text-sm text-red-600">
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
                      :class="{ 'border-red-500': form.errors.state }"
                    />
                    <div v-if="form.errors.state" class="text-sm text-red-600">
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
                      :class="{ 'border-red-500': form.errors.zip_code }"
                    />
                    <div v-if="form.errors.zip_code" class="text-sm text-red-600">
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
                    :class="{ 'border-red-500': form.errors.country }"
                  />
                  <div v-if="form.errors.country" class="text-sm text-red-600">
                    {{ form.errors.country }}
                  </div>
                </div>
              </div>

              <!-- Información profesional -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <Label for="company">Empresa</Label>
                  <Input
                    id="company"
                    v-model="form.company"
                    type="text"
                    placeholder="Nombre de la empresa"
                    :class="{ 'border-red-500': form.errors.company }"
                  />
                  <div v-if="form.errors.company" class="text-sm text-red-600">
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
                    :class="{ 'border-red-500': form.errors.job_title }"
                  />
                  <div v-if="form.errors.job_title" class="text-sm text-red-600">
                    {{ form.errors.job_title }}
                  </div>
                </div>
              </div>

              <!-- Botones de acción -->
              <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                <Link :href="route('clients.index')">
                  <Button type="button" variant="outline">
                    Cancelar
                  </Button>
                </Link>
                <Button type="submit" :disabled="form.processing">
                  <Save class="mr-2 h-4 w-4" />
                  {{ form.processing ? 'Guardando...' : 'Crear Cliente' }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
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