<script setup>
import { ref, computed } from 'vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppointmentHealthAlert from '@/Components/AppointmentHealthAlert.vue'
import SessionNoteForm from '@/Components/SessionNoteForm.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  appointment: Object,
  healthProfile: Object,
  alertSummary: Object,
  hasAlerts: Boolean,
  isConfirmed: Boolean,
  confirmations: Array,
  sessionNote: Object,
  recentHistory: Array,
  constants: Object,
})

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`

const confirmed = ref(props.isConfirmed)
const fichaOpen = ref(!props.isConfirmed)

const apt = props.appointment
const client = apt.client || {}
const clientName = `${client.first_name || ''} ${client.last_name || ''}`
const serviceName = apt.service?.name || ''
const status = apt.status?.value || apt.status || 'pending'

const formatDate = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}
const formatTime = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' })
}

const statusColors = {
  pending: 'bg-orange-100 text-orange-700',
  confirmed: 'bg-blue-100 text-blue-700',
  in_progress: 'bg-green-100 text-green-700',
  completed: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-red-100 text-red-700',
}
const statusLabels = {
  pending: 'Pendiente', confirmed: 'Confirmada', in_progress: 'En curso', completed: 'Completada', cancelled: 'Cancelada',
}

function onFichaConfirmed() {
  confirmed.value = true
  fichaOpen.value = false
}

function onNoteSaved() {
  router.reload({ only: ['sessionNote'] })
}

function completarYCobrar() {
  router.visit(`${base}/ventas/nueva?appointment_id=${apt.id}`)
}

function goBack() {
  router.visit(`${base}/agenda`)
}
</script>

<template>
  <Head :title="`${clientName} · ${serviceName}`" />

  <div>
    <!-- Topbar breadcrumb -->
    <div class="flex items-center gap-3 px-6 py-3 bg-white border-b border-gray-100">
      <button @click="goBack"
        class="flex items-center gap-1.5 text-sm text-[var(--color-primary)] hover:underline">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        Volver a agenda
      </button>
      <span class="text-gray-300">/</span>
      <span class="text-sm font-medium text-gray-800">{{ clientName }}</span>
      <span class="text-gray-300">/</span>
      <span class="text-sm text-gray-500">{{ serviceName }} · {{ formatDate(apt.starts_at) }}</span>
      <div class="ml-auto">
        <span :class="['text-xs px-2.5 py-1 rounded-full font-medium', statusColors[status]]">
          {{ statusLabels[status] }}
        </span>
      </div>
    </div>

    <!-- Layout dos columnas -->
    <div class="flex gap-4 p-6 items-start max-w-7xl mx-auto">
      <!-- COLUMNA IZQUIERDA -->
      <div class="flex-1 min-w-0 flex flex-col gap-4">
        <!-- FICHA DE SALUD (colapsable) -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50"
            @click="fichaOpen = !fichaOpen">
            <div class="flex items-center gap-3">
              <p class="text-sm font-medium text-gray-800">Ficha de salud</p>
              <span v-if="alertSummary.allergies?.length" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-red-100 text-red-700">
                {{ alertSummary.allergies.length }} alergias
              </span>
              <span v-if="alertSummary.avoid_zones?.length" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-amber-100 text-amber-700">
                {{ alertSummary.avoid_zones.length }} zonas
              </span>
            </div>
            <div class="flex items-center gap-3">
              <span v-if="confirmed" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-green-100 text-green-700">Confirmada</span>
              <span v-else class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-red-100 text-red-700">Pendiente confirmar</span>
              <svg class="w-4 h-4 text-gray-400 transition-transform" :class="fichaOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </div>
          </div>
          <div v-show="fichaOpen" class="px-5 pb-5 border-t border-gray-100 pt-4">
            <AppointmentHealthAlert
              :appointmentId="apt.id"
              @confirmed="onFichaConfirmed"
            />
          </div>
        </div>

        <!-- NOTA DE SESION -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-800">Nota de sesion</p>
            <span v-if="sessionNote?.note" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-green-100 text-green-700">Completada</span>
            <span v-else class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-gray-100 text-gray-500">En curso</span>
          </div>
          <div class="p-5">
            <SessionNoteForm
              :appointment="apt"
              :session-note="sessionNote"
              :inherited-avoid-zones="sessionNote?.inherited_avoid_zones || []"
              :techniques="constants?.techniques || []"
              @saved="onNoteSaved"
            />
          </div>
        </div>
      </div>

      <!-- COLUMNA DERECHA (sticky) -->
      <div class="w-80 flex-shrink-0 flex flex-col gap-4" style="position: sticky; top: 80px;">
        <!-- Detalle de la cita -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-800">Detalle de la cita</p>
          </div>
          <div class="px-4 py-3 divide-y divide-gray-50">
            <div class="flex justify-between py-2 text-sm">
              <span class="text-gray-500">Cliente</span>
              <Link :href="`${base}/clientes/${client.id}`" class="font-medium text-[var(--color-primary)] hover:underline">{{ clientName }}</Link>
            </div>
            <div class="flex justify-between py-2 text-sm">
              <span class="text-gray-500">Servicio</span>
              <span class="font-medium">{{ serviceName }}</span>
            </div>
            <div class="flex justify-between py-2 text-sm">
              <span class="text-gray-500">Terapeuta</span>
              <span class="font-medium">{{ apt.stylist?.name }}</span>
            </div>
            <div class="flex justify-between py-2 text-sm">
              <span class="text-gray-500">Hora</span>
              <span class="font-medium">{{ formatTime(apt.starts_at) }} → {{ formatTime(apt.ends_at) }}</span>
            </div>
            <div class="flex justify-between py-2 text-sm">
              <span class="text-gray-500">Precio</span>
              <span class="font-medium text-[var(--color-primary)]">${{ apt.service?.base_price }}</span>
            </div>
          </div>
        </div>

        <!-- Acciones -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-800">Acciones</p>
          </div>
          <div class="px-4 py-3 flex flex-col gap-2">
            <button v-if="status !== 'completed' && status !== 'cancelled'" @click="completarYCobrar"
              class="w-full py-2 rounded-lg text-sm font-medium bg-[var(--color-primary)] text-white hover:opacity-90">
              Completar y cobrar
            </button>
            <button v-if="status === 'completed'" @click="completarYCobrar"
              class="w-full py-2 rounded-lg text-sm font-medium bg-[var(--color-primary)] text-white hover:opacity-90">
              Ir a cobrar
            </button>
            <button @click="goBack"
              class="w-full py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">
              Volver a agenda
            </button>
          </div>
        </div>

        <!-- Historial reciente -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-800">Historial reciente</p>
            <Link :href="`${base}/clientes/${client.id}`" class="text-xs text-[var(--color-primary)] hover:underline">Ver todo</Link>
          </div>
          <div class="divide-y divide-gray-50">
            <div v-for="past in recentHistory" :key="past.id" class="px-4 py-3">
              <p class="text-xs text-gray-400 mb-1">{{ formatDate(past.starts_at) }}</p>
              <p class="text-sm font-medium text-gray-800 mb-1">{{ past.service?.name }}</p>
              <div v-if="past.session_note" class="space-y-1">
                <div class="flex flex-wrap gap-1">
                  <span v-for="z in (past.session_note.body_map || []).filter(b => b.state === 'worked').slice(0, 3)"
                    :key="z.zone_id"
                    class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-50 border border-emerald-200 text-emerald-700">
                    {{ z.label }}
                  </span>
                  <span v-for="z in (past.session_note.body_map || []).filter(b => b.state === 'tension').slice(0, 2)"
                    :key="z.zone_id"
                    class="px-1.5 py-0.5 rounded text-[10px] bg-amber-50 border border-amber-200 text-amber-700">
                    {{ z.label }}
                  </span>
                </div>
                <p v-if="past.session_note.observations" class="text-xs text-gray-500 line-clamp-2">
                  {{ past.session_note.observations }}
                </p>
                <div v-if="past.session_note.next_session_recommendation"
                  class="px-2 py-1 bg-blue-50 border border-blue-100 rounded text-xs text-blue-700">
                  Proxima sesion: {{ past.session_note.next_session_recommendation }}
                </div>
              </div>
            </div>
            <div v-if="!recentHistory?.length" class="px-4 py-6 text-center text-xs text-gray-400 italic">
              Primera visita del cliente
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
