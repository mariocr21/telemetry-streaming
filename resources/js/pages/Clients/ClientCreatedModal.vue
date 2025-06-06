<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import { 
  X, 
  Eye, 
  EyeOff, 
  Copy, 
  CheckCircle2, 
  AlertTriangle,
  User,
  Mail,
  Key,
  Download
} from 'lucide-vue-next'

interface Props {
  isOpen: boolean
  userEmail: string
  userPassword: string
  userName: string
  userRole?: string
  userRoleLabel?: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
}>()

const showPassword = ref(false)
const copied = ref<string | null>(null)

// Función para copiar al portapapeles
const copyToClipboard = async (text: string, type: string) => {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = type
    setTimeout(() => {
      copied.value = null
    }, 2000)
  } catch (err) {
    console.error('Error al copiar:', err)
  }
}

// Función para generar contenido de archivo de credenciales
const downloadCredentials = () => {
  const content = `CREDENCIALES DE ACCESO
======================

Usuario: ${props.userName}
Email: ${props.userEmail}
Contraseña: ${props.userPassword}
Rol: ${props.userRoleLabel || 'Administrador de Cliente'}

IMPORTANTE:
- Guarda esta información en un lugar seguro
- Cambia la contraseña en tu primer inicio de sesión
- No compartas estas credenciales con terceros
- Como ${props.userRoleLabel || 'Administrador de Cliente'}, tendrás permisos de gestión

Fecha de creación: ${new Date().toLocaleString('es-ES')}
`

  const blob = new Blob([content], { type: 'text/plain' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `credenciales-${props.userEmail}.txt`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)
}

// Cerrar modal con Escape
onMounted(() => {
  const handleEscape = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      emit('close')
    }
  }
  document.addEventListener('keydown', handleEscape)
  
  // Cleanup
  return () => {
    document.removeEventListener('keydown', handleEscape)
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="isOpen"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        @click.self="emit('close')"
      >
        <Transition
          enter-active-class="transition-all duration-300"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-300"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <Card v-if="isOpen" class="w-full max-w-md mx-auto">
            <CardHeader class="text-center">
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <div class="w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <CheckCircle2 class="w-8 h-8 text-green-600 dark:text-green-400" />
                  </div>
                  <CardTitle class="text-xl text-green-800 dark:text-green-200">
                    ¡Cliente y Usuario Creados!
                  </CardTitle>
                </div>
                <button
                  @click="emit('close')"
                  class="text-gray-400 hover:text-gray-600 transition-colors"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                Se han generado las credenciales de acceso para el nuevo usuario
              </p>
            </CardHeader>

            <CardContent class="space-y-6">
              <!-- Alerta de seguridad -->
              <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                <div class="flex items-start space-x-3">
                  <AlertTriangle class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                  <div>
                    <h4 class="text-sm font-medium text-amber-800 dark:text-amber-200">
                      ¡Importante!
                    </h4>
                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                      Estas credenciales solo se mostrarán una vez. Guárdalas en un lugar seguro.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Información del usuario -->
              <div class="space-y-4">
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center">
                    <User class="w-4 h-4 mr-2" />
                    Nombre de Usuario
                  </label>
                  <div class="flex items-center space-x-2">
                    <div class="flex-1 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg font-mono text-sm">
                      {{ userName }}
                    </div>
                    <button
                      @click="copyToClipboard(userName, 'name')"
                      class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                      title="Copiar nombre"
                    >
                      <CheckCircle2 v-if="copied === 'name'" class="w-4 h-4 text-green-500" />
                      <Copy v-else class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center">
                    <Mail class="w-4 h-4 mr-2" />
                    Email de Acceso
                  </label>
                  <div class="flex items-center space-x-2">
                    <div class="flex-1 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg font-mono text-sm">
                      {{ userEmail }}
                    </div>
                    <button
                      @click="copyToClipboard(userEmail, 'email')"
                      class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                      title="Copiar email"
                    >
                      <CheckCircle2 v-if="copied === 'email'" class="w-4 h-4 text-green-500" />
                      <Copy v-else class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center">
                    <Key class="w-4 h-4 mr-2" />
                    Rol Asignado
                  </label>
                  <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <div class="flex items-center space-x-2">
                      <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                      <span class="font-medium text-blue-800 dark:text-blue-200">
                        {{ userRoleLabel || 'Administrador de Cliente' }}
                      </span>
                    </div>
                    <p class="text-xs text-blue-600 dark:text-blue-300 mt-1">
                      Permisos de administración para gestionar el cliente
                    </p>
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center">
                    <Key class="w-4 h-4 mr-2" />
                    Contraseña Temporal
                  </label>
                  <div class="flex items-center space-x-2">
                    <div class="flex-1 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg font-mono text-sm">
                      {{ showPassword ? userPassword : '••••••••••••' }}
                    </div>
                    <button
                      @click="showPassword = !showPassword"
                      class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                      title="Mostrar/ocultar contraseña"
                    >
                      <EyeOff v-if="showPassword" class="w-4 h-4" />
                      <Eye v-else class="w-4 h-4" />
                    </button>
                    <button
                      @click="copyToClipboard(userPassword, 'password')"
                      class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                      title="Copiar contraseña"
                    >
                      <CheckCircle2 v-if="copied === 'password'" class="w-4 h-4 text-green-500" />
                      <Copy v-else class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>

              <!-- Notificación de copiado -->
              <div v-if="copied" class="text-center">
                <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                  <CheckCircle2 class="w-4 h-4 mr-1" />
                  {{ copied === 'name' ? 'Nombre copiado' : copied === 'email' ? 'Email copiado' : 'Contraseña copiada' }}
                </div>
              </div>

              <!-- Acciones -->
              <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <Button
                  @click="downloadCredentials"
                  variant="outline"
                  class="flex-1"
                >
                  <Download class="w-4 h-4 mr-2" />
                  Descargar Credenciales
                </Button>
                
                <Button
                  @click="emit('close')"
                  class="flex-1"
                >
                  Entendido
                </Button>
              </div>

              <!-- Instrucciones adicionales -->
              <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1 pt-2 border-t border-gray-200 dark:border-gray-700">
                <p>• El usuario debe cambiar la contraseña en su primer inicio de sesión</p>
                <p>• Las credenciales se pueden enviar por email de forma segura</p>
                <p>• Como {{ userRoleLabel || 'Administrador de Cliente' }}, puede gestionar otros usuarios del cliente</p>
                <p>• El rol se puede cambiar posteriormente desde la administración</p>
              </div>
            </CardContent>
          </Card>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>