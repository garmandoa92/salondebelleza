<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import axios from 'axios'

const props = defineProps({
  appointmentId: String,
  open: Boolean,
})
const emit = defineEmits(['close', 'updated', 'checkout'])

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`
const apt = ref(null)
const loading = ref(false)
const cancelReason = ref('')
const showCancel = ref(false)

const statusLabels = {
  pending: 'Pendiente', confirmed: 'Confirmada',
  in_progress: 'En progreso', completed: 'Completada',
  cancelled: 'Cancelada', no_show: 'No show',
}
const statusColors = {
  pending: 'bg-gray-100 text-gray-700', confirmed: 'bg-blue-100 text-blue-700',
  in_progress: 'bg-green-100 text-green-700', completed: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-red-100 text-red-700', no_show: 'bg-orange-100 text-orange-700',
}

watch(() => props.appointmentId, async (id) => {
  if (!id) return
  loading.value = true
  try {
    const { data } = await axios.get(`${base}/agenda/appointments/${id}`)
    apt.value = data
  } finally { loading.value = false }
})

const doAction = async (action) => {
  await axios.post(`${base}/agenda/appointments/${apt.value.id}/${action}`)
  const { data } = await axios.get(`${base}/agenda/appointments/${apt.value.id}`)
  apt.value = data
  emit('updated')
}

const isPackageSession = computed(() => !!apt.value?.client_package_item_id)

const openCheckout = () => {
  emit('checkout', {
    appointmentId: apt.value.id,
    clientId: apt.value.client_id,
    clientName: apt.value.client ? `${apt.value.client.first_name} ${apt.value.client.last_name}` : null,
    preItems: [{
      type: 'service',
      reference_id: apt.value.service?.id,
      name: apt.value.service?.name,
      quantity: 1,
      unit_price: Number(apt.value.service?.base_price || 0),
      subtotal: Number(apt.value.service?.base_price || 0),
      iva_amount: 0,
      discount_amount: 0,
      stylist_id: apt.value.stylist_id,
    }],
  })
  emit('close')
}

const completingPackage = ref(false)
const completePackageSession = async () => {
  completingPackage.value = true
  try {
    // Complete the appointment
    await axios.post(`${base}/agenda/appointments/${apt.value.id}/complete`)
    // Use the package session
    await axios.post(`${base}/packages/use-session`, {
      client_package_item_id: apt.value.client_package_item_id,
      appointment_id: apt.value.id,
    })
    const { data } = await axios.get(`${base}/agenda/appointments/${apt.value.id}`)
    apt.value = data
    emit('updated')
  } finally { completingPackage.value = false }
}

const doCancel = async () => {
  await axios.delete(`${base}/agenda/appointments/${apt.value.id}`, {
    data: { reason: cancelReason.value || 'Sin motivo', cancelled_by: 'staff' },
  })
  const { data } = await axios.get(`${base}/agenda/appointments/${apt.value.id}`)
  apt.value = data
  showCancel.value = false
  cancelReason.value = ''
  emit('updated')
}

const formatDate = (d) => new Date(d).toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const formatTime = (d) => new Date(d).toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' })
const initials = (name) => name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '?'

const status = computed(() => apt.value?.status?.value || apt.value?.status || 'pending')
</script>

<template>
  <Transition name="drawer">
    <div v-if="open" class="fixed inset-0 z-50 flex justify-end">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <div class="relative w-full max-w-md bg-white shadow-xl overflow-y-auto">
        <div v-if="loading" class="flex items-center justify-center h-64">
          <span class="text-gray-400">Cargando...</span>
        </div>

        <div v-else-if="apt" class="p-5 space-y-5">
          <!-- Header -->
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
              <Avatar class="h-10 w-10">
                <AvatarFallback class="text-sm">{{ initials(apt.client?.first_name + ' ' + apt.client?.last_name) }}</AvatarFallback>
              </Avatar>
              <div>
                <h2 class="font-semibold text-gray-900">{{ apt.client?.first_name }} {{ apt.client?.last_name }}</h2>
                <p class="text-sm text-gray-500">{{ apt.client?.phone }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span :class="['text-xs px-2 py-1 rounded-full font-medium', statusColors[status]]">
                {{ statusLabels[status] }}
              </span>
              <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
          </div>

          <!-- Date/Time -->
          <div class="text-sm text-gray-600">
            <p class="capitalize">{{ formatDate(apt.starts_at) }}</p>
            <p class="font-medium">{{ formatTime(apt.starts_at) }} → {{ formatTime(apt.ends_at) }} ({{ apt.service?.duration_minutes }}min)</p>
          </div>

          <!-- Service/Stylist/Price -->
          <div class="grid grid-cols-3 gap-3 text-center text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-gray-500 text-xs">Servicio</p>
              <p class="font-medium truncate">{{ apt.service?.name }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-gray-500 text-xs">Estilista</p>
              <p class="font-medium truncate">{{ apt.stylist?.name }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-gray-500 text-xs">Precio</p>
              <p v-if="isPackageSession" class="font-medium text-green-600">Paquete</p>
              <p v-else class="font-medium">${{ Number(apt.service?.base_price || 0).toFixed(2) }}</p>
            </div>
          </div>

          <!-- Allergies alert -->
          <div v-if="apt.client?.allergies" class="bg-red-50 border border-red-200 rounded-lg p-3 flex gap-2">
            <span class="text-red-500 text-lg">⚠</span>
            <div>
              <p class="text-sm font-medium text-red-700">Alergias</p>
              <p class="text-sm text-red-600">{{ apt.client.allergies }}</p>
            </div>
          </div>

          <!-- Notes -->
          <div v-if="apt.notes" class="text-sm">
            <p class="text-gray-500 text-xs mb-1">Notas</p>
            <p class="text-gray-700">{{ apt.notes }}</p>
          </div>
          <div v-if="apt.internal_notes" class="text-sm">
            <p class="text-gray-500 text-xs mb-1">Notas internas</p>
            <p class="text-gray-700">{{ apt.internal_notes }}</p>
          </div>

          <!-- Cancellation info -->
          <div v-if="status === 'cancelled'" class="bg-red-50 rounded-lg p-3 text-sm">
            <p class="font-medium text-red-700">Cancelada por {{ apt.cancelled_by }}</p>
            <p v-if="apt.cancellation_reason" class="text-red-600">{{ apt.cancellation_reason }}</p>
          </div>

          <!-- Action buttons based on status -->
          <div class="space-y-2 pt-2 border-t">
            <template v-if="status === 'pending'">
              <Button class="w-full bg-green-600 hover:bg-green-700" @click="doAction('confirm')">Confirmar cita</Button>
              <Button class="w-full bg-blue-600 hover:bg-blue-700" @click="doAction('start')">Llego → Iniciar</Button>
              <Button variant="outline" class="w-full text-orange-600 border-orange-300" @click="doAction('no-show')">No se presento</Button>
              <Button variant="outline" class="w-full text-red-600 border-red-300" @click="showCancel = true">Cancelar</Button>
            </template>

            <template v-else-if="status === 'confirmed'">
              <Button class="w-full bg-green-600 hover:bg-green-700" @click="doAction('start')">Llego → Iniciar servicio</Button>
              <Button variant="outline" class="w-full text-orange-600 border-orange-300" @click="doAction('no-show')">No se presento</Button>
              <Button variant="outline" class="w-full text-red-600 border-red-300" @click="showCancel = true">Cancelar</Button>
            </template>

            <template v-else-if="status === 'in_progress'">
              <div v-if="isPackageSession" class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm mb-2">
                <p class="font-medium text-green-800">Sesion de paquete — ya pagada</p>
                <p class="text-green-600 text-xs">Al completar se descuenta 1 sesion automaticamente</p>
              </div>
              <Button v-if="isPackageSession" class="w-full bg-green-600 hover:bg-green-700" :disabled="completingPackage" @click="completePackageSession">
                {{ completingPackage ? 'Completando...' : 'Completar sesion de paquete' }}
              </Button>
              <Button v-else class="w-full bg-green-600 hover:bg-green-700" @click="openCheckout">Completar y cobrar →</Button>
              <Button variant="outline" class="w-full text-red-600 border-red-300" @click="showCancel = true">Cancelar</Button>
            </template>

            <template v-else-if="status === 'completed'">
              <p class="text-sm text-center text-gray-400">Servicio completado</p>
            </template>

            <template v-else-if="status === 'cancelled' || status === 'no_show'">
              <p class="text-sm text-center text-gray-400">Cita {{ statusLabels[status]?.toLowerCase() }}</p>
            </template>
          </div>

          <!-- Cancel form -->
          <div v-if="showCancel" class="border-t pt-3 space-y-2">
            <textarea
              v-model="cancelReason"
              placeholder="Motivo de cancelacion..."
              class="w-full rounded-md border border-input px-3 py-2 text-sm"
              rows="2"
            />
            <div class="flex gap-2">
              <Button variant="outline" class="flex-1" @click="showCancel = false">Volver</Button>
              <Button class="flex-1 bg-red-600 hover:bg-red-700" @click="doCancel">Confirmar cancelacion</Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: all 0.25s ease-out; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from > div:last-child, .drawer-leave-to > div:last-child { transform: translateX(100%); }
</style>
