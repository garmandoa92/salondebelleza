<script setup>
import { ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  appointmentId: String,
})

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`

const loaded = ref(false)
const hasAlerts = ref(false)
const isOutdated = ref(false)
const isConfirmed = ref(false)
const summary = ref({})
const clientName = ref('')
const confirmations = ref([])
const confirming = ref(false)
const lastConfirmation = ref(null)

onMounted(async () => {
  if (!props.appointmentId) return
  try {
    const { data } = await axios.get(`${base}/citas/${props.appointmentId}/alerta-salud`)
    hasAlerts.value = data.has_alerts
    isOutdated.value = data.is_outdated
    isConfirmed.value = data.is_confirmed
    summary.value = data.alert_summary || {}
    clientName.value = data.client?.name || ''
    confirmations.value = data.confirmations || []
    lastConfirmation.value = data.confirmations?.[0] || null
    loaded.value = true
  } catch (e) {
    // No health profile — no alert needed
    loaded.value = true
  }
})

async function confirm() {
  confirming.value = true
  try {
    const { data } = await axios.post(`${base}/citas/${props.appointmentId}/confirmar-ficha`)
    isConfirmed.value = true
    lastConfirmation.value = { user_name: data.confirmed_by, confirmed_at: data.confirmed_at }
  } finally {
    confirming.value = false
  }
}
</script>

<template>
  <div v-if="loaded && hasAlerts" class="rounded-xl border-2 border-red-200 bg-red-50/50 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center gap-3 px-4 py-3 bg-red-100/60 border-b border-red-200">
      <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
      </svg>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-red-800">Revisa la ficha antes de iniciar</p>
        <p class="text-xs text-red-600">{{ clientName }}</p>
      </div>
      <span v-if="isOutdated" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-amber-100 text-amber-700">Ficha desactualizada</span>
    </div>

    <div class="px-4 py-3 space-y-3">
      <!-- Alergias -->
      <div v-if="summary.allergies?.length">
        <p class="text-[10px] font-semibold text-red-700 uppercase tracking-wide mb-1">Alergias</p>
        <div class="flex flex-wrap gap-1">
          <span v-for="a in summary.allergies" :key="a" class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">{{ a }}</span>
        </div>
      </div>

      <!-- Condiciones -->
      <div v-if="summary.medical_conditions?.length">
        <p class="text-[10px] font-semibold text-amber-700 uppercase tracking-wide mb-1">Condiciones medicas</p>
        <div class="flex flex-wrap gap-1">
          <span v-for="c in summary.medical_conditions" :key="c" class="px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-700">{{ c }}</span>
        </div>
      </div>

      <!-- Zonas a evitar -->
      <div v-if="summary.avoid_zones?.length">
        <p class="text-[10px] font-semibold text-red-700 uppercase tracking-wide mb-1">Zonas a evitar</p>
        <div class="space-y-1">
          <div v-for="zone in summary.avoid_zones" :key="zone.zone_id" class="flex items-start gap-2">
            <div class="w-2 h-2 rounded-full bg-red-500 mt-1 flex-shrink-0" />
            <div>
              <span class="text-xs font-medium text-gray-800">{{ zone.label }}</span>
              <span v-if="zone.note" class="text-xs text-gray-500 ml-1">— {{ zone.note }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Contraindicaciones -->
      <div v-if="summary.contraindications">
        <p class="text-[10px] font-semibold text-red-700 uppercase tracking-wide mb-1">Contraindicaciones</p>
        <p class="text-xs text-gray-700">{{ summary.contraindications }}</p>
      </div>

      <!-- Preferencias -->
      <div class="flex flex-wrap gap-1">
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Presion: {{ summary.pressure_label }}</span>
        <span v-for="p in summary.personal_preferences" :key="p" class="px-2 py-0.5 text-xs rounded-full bg-blue-50 text-blue-700">{{ p }}</span>
      </div>

      <!-- Notas terapeuta -->
      <div v-if="summary.therapist_notes">
        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Notas del terapeuta</p>
        <p class="text-xs text-gray-600 italic">{{ summary.therapist_notes }}</p>
      </div>
    </div>

    <!-- Confirmacion -->
    <div class="px-4 py-3 border-t border-red-200">
      <div v-if="!isConfirmed" class="flex items-center justify-between">
        <p class="text-xs text-gray-600">Confirma que leiste la ficha completa</p>
        <button @click="confirm" :disabled="confirming"
          class="px-4 py-1.5 text-xs font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">
          {{ confirming ? 'Guardando...' : 'Confirmar lectura' }}
        </button>
      </div>
      <div v-else class="flex items-center gap-2">
        <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="text-sm font-medium text-green-800">Leida y confirmada</p>
          <p class="text-xs text-green-600">{{ lastConfirmation?.user_name }} · {{ lastConfirmation?.confirmed_at }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
