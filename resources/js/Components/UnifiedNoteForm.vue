<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import BodyMapSVG from '@/Components/BodyMapSVG.vue'

const props = defineProps({
  appointment: Object,
  serviceType: { type: String, default: 'other' },
  fields: Object,
  techniques: { type: Array, default: () => [] },
  unifiedNote: Object,
  inheritedAvoidZones: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`

const saving = ref(false)
const savedMsg = ref(false)
const currentMode = ref('worked')
const bodyView = ref('front')
const newProduct = ref('')

const showUnconfiguredWarning = computed(() => props.serviceType === 'other')

const form = ref({
  initial_condition: props.unifiedNote?.initial_condition ?? props.unifiedNote?.observations ?? '',
  technique: props.unifiedNote?.technique ?? '',
  techniques_used: props.unifiedNote?.techniques ?? props.unifiedNote?.techniques_used ?? [],
  temperature: props.unifiedNote?.temperature ?? '',
  exposure_time: props.unifiedNote?.exposure_time ?? '',
  products_used: props.unifiedNote?.products_used ?? [],
  result: props.unifiedNote?.result ?? '',
  next_visit_notes: props.unifiedNote?.next_visit_notes ?? props.unifiedNote?.next_session_recommendation ?? '',
  internal_notes: props.unifiedNote?.internal_notes ?? '',
  body_map: props.unifiedNote?.body_map ?? [],
  actual_duration_minutes: props.unifiedNote?.actual_duration_minutes ?? 60,
  tension_level: props.unifiedNote?.tension_level ?? '',
  client_recommendation: props.unifiedNote?.client_recommendation ?? '',
  send_whatsapp: false,
  service_type: props.serviceType,
})

const zoneStates = ref(
  Object.fromEntries(
    (props.unifiedNote?.body_map ?? []).map(z => [z.zone_id, z.state])
  )
)

const modes = [
  { key: 'worked', label: 'Trabajado', activeClass: 'bg-emerald-50 border-emerald-300 text-emerald-700' },
  { key: 'tension', label: 'Tension', activeClass: 'bg-amber-50 border-amber-300 text-amber-700' },
  { key: 'avoided', label: 'Evitar', activeClass: 'bg-red-50 border-red-300 text-red-700' },
]

function paintZone(zoneId) {
  const isInherited = (props.inheritedAvoidZones ?? []).some(z => z.zone_id === zoneId)
  if (isInherited) return
  if (zoneStates.value[zoneId] === currentMode.value) delete zoneStates.value[zoneId]
  else zoneStates.value[zoneId] = currentMode.value
  form.value.body_map = Object.entries(zoneStates.value)
    .filter(([, s]) => s)
    .map(([zone_id, state]) => ({ zone_id, state, view: zone_id.startsWith('b-') ? 'back' : 'front' }))
}

function toggleTechnique(t) {
  const i = form.value.techniques_used.indexOf(t)
  if (i >= 0) form.value.techniques_used.splice(i, 1)
  else form.value.techniques_used.push(t)
}

function addProduct() {
  const p = newProduct.value.trim()
  if (p) form.value.products_used.push(p)
  newProduct.value = ''
}

function removeProduct(i) {
  form.value.products_used.splice(i, 1)
}

const placeholders = {
  hair: { initial_condition: 'Ej: Cabello poroso, raiz oscura, puntas secas...', technique: 'Ej: Balayage, corte recto...', products: 'Ej: Tinte Wella 8/0, Oxidante 20vol...' },
  spa: { initial_condition: 'Ej: Tension alta en trapecios, zona lumbar contracturada...', technique: 'Ej: Effleurage + Petrissage + Puntos gatillo...', products: 'Ej: Aceite de lavanda 30ml...' },
  facial: { initial_condition: 'Ej: Piel mixta, poros dilatados...', technique: 'Ej: Limpieza profunda + Peeling...', products: 'Ej: Serum vitamina C...' },
  nails: { initial_condition: 'Ej: Unas quebradizas, cuticulas resecas...', technique: 'Ej: Manicure semipermanente...', products: 'Ej: Esmalte OPI #NL-L87...' },
  brows: { initial_condition: 'Ej: Cejas escasas en arco...', technique: 'Ej: Diseno con hilo + Henna...', products: 'Ej: Henna marron oscuro...' },
}
const ph = (field) => placeholders[props.serviceType]?.[field] ?? ''

async function save() {
  saving.value = true
  savedMsg.value = false
  try {
    await axios.post(`${base}/citas/${props.appointment.id}/nota-unificada`, form.value)
    savedMsg.value = true
    emit('saved')
    setTimeout(() => savedMsg.value = false, 3000)
  } catch (e) {
    console.error('Error guardando nota:', e.response?.data)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Aviso servicio sin tipo -->
    <div v-if="showUnconfiguredWarning"
      class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
      <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
      <div class="flex-1">
        <p class="text-sm font-medium text-amber-800">Servicio sin tipo configurado</p>
        <p class="text-xs text-amber-600">Configura el tipo del servicio para ver los campos correctos.</p>
      </div>
    </div>

    <!-- MAPA CORPORAL — solo spa/facial -->
    <div v-if="fields?.body_map">
      <p class="text-sm font-semibold text-gray-700 mb-2">Mapa corporal</p>
      <div class="flex gap-2 mb-3">
        <button v-for="m in modes" :key="m.key" type="button" @click="currentMode = m.key"
          class="flex-1 py-1.5 rounded-full text-xs border transition-all"
          :class="currentMode === m.key ? m.activeClass : 'border-gray-200 text-gray-500'">
          {{ m.label }}
        </button>
      </div>
      <div class="flex gap-2 mb-3">
        <button type="button" @click="bodyView = 'front'" class="px-3 py-1 rounded-lg text-xs border transition-all"
          :class="bodyView === 'front' ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'border-gray-200 text-gray-500'">Frontal</button>
        <button type="button" @click="bodyView = 'back'" class="px-3 py-1 rounded-lg text-xs border transition-all"
          :class="bodyView === 'back' ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'border-gray-200 text-gray-500'">Dorsal</button>
      </div>
      <BodyMapSVG :view="bodyView" :zone-states="zoneStates" :inherited-zones="inheritedAvoidZones" :current-mode="currentMode" @zone-click="paintZone" />
      <div class="flex gap-4 mt-2 text-xs text-gray-400">
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block" />Trabajado</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block" />Tension</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-red-400 inline-block" />Evitado</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full border-2 border-dashed border-red-400 inline-block" />Ficha</span>
      </div>
    </div>

    <!-- CONDICION INICIAL -->
    <div v-if="fields?.initial_condition">
      <p class="text-sm font-semibold text-gray-700 mb-1">{{ fields?.body_map ? 'Condicion inicial / Observaciones' : 'Condicion inicial' }}</p>
      <textarea v-model="form.initial_condition" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" :placeholder="ph('initial_condition')" />
    </div>

    <!-- TECNICA APLICADA -->
    <div v-if="fields?.technique">
      <p class="text-sm font-semibold text-gray-700 mb-2">Tecnica aplicada</p>
      <div class="flex flex-wrap gap-2 mb-2">
        <button v-for="t in techniques" :key="t" type="button" @click="toggleTechnique(t)"
          class="px-3 py-1 rounded-full text-xs border transition-all"
          :class="form.techniques_used.includes(t) ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'border-gray-200 text-gray-500'">
          {{ t }}
        </button>
      </div>
      <input v-model="form.technique" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" :placeholder="ph('technique')" />
    </div>

    <!-- TEMPERATURA -->
    <div v-if="fields?.temperature">
      <p class="text-sm font-semibold text-gray-700 mb-1">Temperatura</p>
      <input v-model="form.temperature" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Ej: 230 C, frio/caliente..." />
    </div>

    <!-- DURACION -->
    <div v-if="fields?.duration">
      <p class="text-sm font-semibold text-gray-700 mb-1">{{ fields?.body_map ? 'Duracion real' : 'Duracion del procedimiento' }}</p>
      <div v-if="fields?.body_map" class="flex gap-2">
        <select v-model.number="form.actual_duration_minutes" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
          <option :value="30">30 min</option>
          <option :value="45">45 min</option>
          <option :value="60">60 min</option>
          <option :value="75">75 min</option>
          <option :value="90">90 min</option>
        </select>
        <select v-model="form.tension_level" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
          <option value="">Sin evaluar tension</option>
          <option value="low">Tension baja</option>
          <option value="medium">Tension media</option>
          <option value="high">Tension alta</option>
        </select>
      </div>
      <input v-else v-model="form.exposure_time" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Ej: 45 min, 1h 30min..." />
    </div>

    <!-- PRODUCTOS -->
    <div v-if="fields?.products">
      <p class="text-sm font-semibold text-gray-700 mb-2">Productos utilizados</p>
      <div class="flex flex-wrap gap-2 mb-2">
        <span v-for="(prod, i) in form.products_used" :key="i"
          class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs bg-teal-50 border border-teal-200 text-teal-700">
          {{ prod }}
          <button type="button" @click="removeProduct(i)" class="text-teal-500 hover:text-teal-700">&times;</button>
        </span>
      </div>
      <div class="flex gap-2">
        <input v-model="newProduct" type="text" :placeholder="ph('products')" class="flex-1 text-sm px-3 py-1.5 border border-gray-200 rounded-lg" @keydown.enter="addProduct" />
        <button type="button" @click="addProduct" class="px-3 py-1.5 text-xs bg-[var(--color-primary)] text-white rounded-lg">+ Agregar</button>
      </div>
    </div>

    <!-- RESULTADO -->
    <div v-if="fields?.result">
      <p class="text-sm font-semibold text-gray-700 mb-1">Resultado obtenido</p>
      <textarea v-model="form.result" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Describe el resultado del servicio..." />
    </div>

    <!-- PROXIMA VISITA -->
    <div v-if="fields?.next_visit">
      <p class="text-sm font-semibold text-gray-700 mb-1">Nota para proxima visita</p>
      <textarea v-model="form.next_visit_notes" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Recomendaciones para la proxima vez..." />
    </div>

    <!-- MENSAJE CLIENTE — solo spa -->
    <div v-if="fields?.body_map">
      <p class="text-sm font-semibold text-gray-700 mb-1">Mensaje al cliente</p>
      <textarea v-model="form.client_recommendation" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Recuerda hidratarte bien hoy..." />
      <div class="flex items-center gap-2 mt-2 px-3 py-2 bg-teal-50 border border-teal-200 rounded-lg">
        <input type="checkbox" v-model="form.send_whatsapp" id="waToggleUnified" class="w-4 h-4" />
        <label for="waToggleUnified" class="text-xs text-teal-700 cursor-pointer">Enviar por WhatsApp al guardar</label>
      </div>
    </div>

    <!-- NOTAS INTERNAS -->
    <div v-if="fields?.internal_notes">
      <p class="text-sm font-semibold text-gray-700 mb-1">Notas internas</p>
      <textarea v-model="form.internal_notes" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Solo visible para el equipo..." />
    </div>

    <!-- GUARDAR -->
    <div class="flex items-center gap-3">
      <button type="button" @click="save" :disabled="saving"
        class="flex-1 py-2.5 rounded-lg text-sm font-medium bg-[var(--color-primary)] text-white hover:opacity-90 disabled:opacity-50">
        {{ saving ? 'Guardando...' : 'Guardar nota de visita' }}
      </button>
      <span v-if="savedMsg" class="text-sm text-green-600 font-medium">Guardado</span>
    </div>
  </div>
</template>
