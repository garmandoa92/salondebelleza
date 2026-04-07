<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import BodyMapSVG from '@/Components/BodyMapSVG.vue'

const props = defineProps({
  appointment: Object,
  sessionNote: Object,
  inheritedAvoidZones: { type: Array, default: () => [] },
  techniques: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`

const saving = ref(false)
const savedMsg = ref(false)
const currentMode = ref('worked')
const bodyView = ref('front')
const newTechnique = ref('')
const newProduct = ref('')

const form = ref({
  body_map: props.sessionNote?.body_map ?? [],
  techniques: props.sessionNote?.techniques ?? [],
  products_used: props.sessionNote?.products_used ?? [],
  actual_duration_minutes: props.sessionNote?.note?.actual_duration_minutes ?? 60,
  tension_level: props.sessionNote?.note?.tension_level ?? '',
  observations: props.sessionNote?.note?.observations ?? '',
  next_session_recommendation: props.sessionNote?.note?.next_session_recommendation ?? '',
  client_recommendation: props.sessionNote?.note?.client_recommendation ?? '',
  send_whatsapp: true,
})

const zoneStates = ref(
  Object.fromEntries(
    (props.sessionNote?.body_map ?? []).map(z => [z.zone_id, z.state])
  )
)

const modes = [
  { key: 'worked', label: 'Trabajado', activeClass: 'bg-emerald-50 border-emerald-300 text-emerald-700' },
  { key: 'tension', label: 'Tension', activeClass: 'bg-amber-50 border-amber-300 text-amber-700' },
  { key: 'avoided', label: 'Evitar', activeClass: 'bg-red-50 border-red-300 text-red-700' },
]

const activeZones = computed(() => {
  const own = Object.entries(zoneStates.value)
    .filter(([, s]) => s)
    .map(([zone_id, state]) => ({ zone_id, label: getLabel(zone_id), state, inherited: false }))
  const inh = (props.inheritedAvoidZones ?? []).map(z => ({ ...z, inherited: true }))
  return [...own, ...inh]
})

function paintZone(zoneId) {
  const isInherited = (props.inheritedAvoidZones ?? []).some(z => z.zone_id === zoneId)
  if (isInherited) return
  if (zoneStates.value[zoneId] === currentMode.value) {
    delete zoneStates.value[zoneId]
  } else {
    zoneStates.value[zoneId] = currentMode.value
  }
  syncBodyMap()
}

function syncBodyMap() {
  form.value.body_map = Object.entries(zoneStates.value)
    .filter(([, s]) => s)
    .map(([zone_id, state]) => ({
      zone_id, label: getLabel(zone_id), state, view: getZoneView(zone_id),
    }))
}

function getZoneView(zoneId) {
  return zoneId.startsWith('b-') ? 'back' : 'front'
}

function getLabel(id) {
  const map = {
    'cabeza':'Cabeza','cuello':'Cuello','hombro-izq':'Hombro izq','hombro-der':'Hombro der',
    'pecho':'Pecho','abdomen':'Abdomen','brazo-izq':'Brazo izq','brazo-der':'Brazo der',
    'antebrazo-izq':'Antebrazo izq','antebrazo-der':'Antebrazo der',
    'mano-izq':'Mano izq','mano-der':'Mano der','caderas':'Caderas',
    'muslo-izq':'Muslo izq','muslo-der':'Muslo der',
    'rodilla-izq':'Rodilla izq','rodilla-der':'Rodilla der',
    'pantorrilla-izq':'Pantorrilla izq','pantorrilla-der':'Pantorrilla der',
    'pie-izq':'Pie izq','pie-der':'Pie der',
    'b-cuello':'Cuello posterior','b-espalda-alta':'Espalda alta',
    'b-lumbar':'Zona lumbar','b-gluteos':'Gluteos',
    'b-hombro-izq':'Hombro izq (dorsal)','b-hombro-der':'Hombro der (dorsal)',
    'b-brazo-izq':'Brazo izq (dorsal)','b-brazo-der':'Brazo der (dorsal)',
    'b-muslo-izq':'Muslo posterior izq','b-muslo-der':'Muslo posterior der',
    'b-corva-izq':'Corva izq','b-corva-der':'Corva der',
    'b-gemelo-izq':'Gemelo izq','b-gemelo-der':'Gemelo der',
    'b-talon-izq':'Talon izq','b-talon-der':'Talon der',
  }
  return map[id] ?? id
}

function getZoneClass(state) {
  return { worked: 'bg-emerald-50 text-emerald-700 border border-emerald-200', tension: 'bg-amber-50 text-amber-700 border border-amber-200', avoided: 'bg-red-50 text-red-700 border border-red-200' }[state] ?? ''
}
function getZoneDotClass(state) {
  return { worked: 'bg-emerald-500', tension: 'bg-amber-400', avoided: 'bg-red-400' }[state] ?? 'bg-gray-300'
}
function toggleTechnique(t) {
  const i = form.value.techniques.indexOf(t)
  if (i >= 0) form.value.techniques.splice(i, 1)
  else form.value.techniques.push(t)
}
function addCustomTechnique() {
  const t = newTechnique.value.trim()
  if (t && !form.value.techniques.includes(t)) form.value.techniques.push(t)
  newTechnique.value = ''
}
function addProduct() {
  const p = newProduct.value.trim()
  if (p) form.value.products_used.push(p)
  newProduct.value = ''
}
function removeProduct(i) { form.value.products_used.splice(i, 1) }

async function save() {
  saving.value = true
  savedMsg.value = false
  try {
    await axios.post(`${base}/citas/${props.appointment.id}/nota-sesion`, form.value)
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
    <!-- MAPA CORPORAL -->
    <div>
      <p class="text-sm font-semibold text-gray-700 mb-2">Mapa corporal</p>
      <div class="flex gap-2 mb-3">
        <button v-for="m in modes" :key="m.key" type="button" @click="currentMode = m.key"
          class="flex-1 py-1.5 rounded-full text-xs border transition-all"
          :class="currentMode === m.key ? m.activeClass : 'border-gray-200 text-gray-500'">
          {{ m.label }}
        </button>
      </div>
      <div class="flex gap-2 mb-3">
        <button type="button" @click="bodyView = 'front'"
          class="px-3 py-1 rounded-lg text-xs border transition-all"
          :class="bodyView === 'front' ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'border-gray-200 text-gray-500'">
          Frontal
        </button>
        <button type="button" @click="bodyView = 'back'"
          class="px-3 py-1 rounded-lg text-xs border transition-all"
          :class="bodyView === 'back' ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'border-gray-200 text-gray-500'">
          Dorsal
        </button>
      </div>

      <div class="flex gap-4">
        <BodyMapSVG
          :view="bodyView"
          :zone-states="zoneStates"
          :inherited-zones="inheritedAvoidZones"
          :current-mode="currentMode"
          @zone-click="paintZone"
        />
        <div class="flex-1 flex flex-col gap-1 max-h-56 overflow-y-auto">
          <div v-for="zone in activeZones" :key="zone.zone_id"
            class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
            :class="zone.inherited ? 'bg-red-50 text-red-700 border border-dashed border-red-200' : getZoneClass(zone.state)">
            <span class="w-2 h-2 rounded-full flex-shrink-0"
              :class="zone.inherited ? 'border-2 border-dashed border-red-400' : getZoneDotClass(zone.state)" />
            {{ zone.label }}
            <span v-if="zone.inherited" class="ml-auto px-1.5 py-0.5 bg-red-100 rounded text-[10px] text-red-600">ficha</span>
          </div>
          <p v-if="!activeZones.length" class="text-xs text-gray-400 italic mt-2 px-2">
            Toca una zona en el mapa para registrarla
          </p>
        </div>
      </div>

      <div class="flex gap-4 mt-2 text-xs text-gray-400">
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block" />Trabajado</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block" />Tension</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-red-400 inline-block" />Evitado</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full border-2 border-dashed border-red-400 inline-block" />Ficha</span>
      </div>
    </div>

    <!-- TECNICAS -->
    <div>
      <p class="text-sm font-semibold text-gray-700 mb-2">Tecnicas aplicadas</p>
      <div class="flex flex-wrap gap-2">
        <button v-for="t in techniques" :key="t" type="button" @click="toggleTechnique(t)"
          class="px-3 py-1 rounded-full text-xs border transition-all"
          :class="form.techniques.includes(t) ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'border-gray-200 text-gray-500'">
          {{ t }}
        </button>
        <div class="flex gap-1">
          <input v-model="newTechnique" type="text" placeholder="Otra..."
            class="text-xs px-2 py-1 border border-gray-200 rounded-lg w-28"
            @keydown.enter="addCustomTechnique" />
          <button type="button" @click="addCustomTechnique"
            class="px-2 py-1 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">+</button>
        </div>
      </div>
    </div>

    <!-- PRODUCTOS -->
    <div>
      <p class="text-sm font-semibold text-gray-700 mb-2">Productos utilizados</p>
      <div class="flex flex-wrap gap-2 mb-2">
        <span v-for="(prod, i) in form.products_used" :key="i"
          class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs bg-teal-50 border border-teal-200 text-teal-700">
          {{ prod }}
          <button type="button" @click="removeProduct(i)" class="text-teal-500 hover:text-teal-700">&times;</button>
        </span>
      </div>
      <div class="flex gap-2">
        <input v-model="newProduct" type="text" placeholder="Ej: Aceite lavanda 30ml"
          class="flex-1 text-sm px-3 py-1.5 border border-gray-200 rounded-lg"
          @keydown.enter="addProduct" />
        <button type="button" @click="addProduct"
          class="px-3 py-1.5 text-xs bg-[var(--color-primary)] text-white rounded-lg">+ Agregar</button>
      </div>
    </div>

    <!-- DURACION Y TENSION -->
    <div class="grid grid-cols-2 gap-3">
      <div>
        <p class="text-sm font-semibold text-gray-700 mb-1">Duracion real</p>
        <select v-model.number="form.actual_duration_minutes" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
          <option :value="30">30 min</option>
          <option :value="45">45 min</option>
          <option :value="60">60 min</option>
          <option :value="75">75 min</option>
          <option :value="90">90 min</option>
        </select>
      </div>
      <div>
        <p class="text-sm font-semibold text-gray-700 mb-1">Nivel de tension</p>
        <select v-model="form.tension_level" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
          <option value="">Sin evaluar</option>
          <option value="low">Baja</option>
          <option value="medium">Media</option>
          <option value="high">Alta</option>
        </select>
      </div>
    </div>

    <!-- OBSERVACIONES -->
    <div>
      <p class="text-sm font-semibold text-gray-700 mb-1">Observaciones (privado)</p>
      <textarea v-model="form.observations" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Contractura en trapecio, cliente reporto alivio en lumbar..." />
    </div>

    <!-- PROXIMA SESION -->
    <div>
      <p class="text-sm font-semibold text-gray-700 mb-1">Recomendacion proxima sesion</p>
      <textarea v-model="form.next_session_recommendation" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Enfocarse en trapecio, considerar piedras calientes..." />
    </div>

    <!-- MENSAJE CLIENTE -->
    <div>
      <p class="text-sm font-semibold text-gray-700 mb-1">Mensaje al cliente</p>
      <textarea v-model="form.client_recommendation" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm" placeholder="Recuerda hidratarte bien hoy y evitar esfuerzo fisico..." />
      <div class="flex items-center gap-2 mt-2 px-3 py-2 bg-teal-50 border border-teal-200 rounded-lg">
        <input type="checkbox" v-model="form.send_whatsapp" id="waToggle" class="w-4 h-4" />
        <label for="waToggle" class="text-xs text-teal-700 cursor-pointer">
          Enviar recomendacion por WhatsApp al guardar
        </label>
      </div>
    </div>

    <!-- GUARDAR -->
    <div class="flex items-center gap-3">
      <button type="button" @click="save" :disabled="saving"
        class="flex-1 py-2.5 rounded-lg text-sm font-medium bg-[var(--color-primary)] text-white hover:opacity-90 disabled:opacity-50">
        {{ saving ? 'Guardando...' : 'Guardar nota de sesion' }}
      </button>
      <span v-if="savedMsg" class="text-sm text-green-600 font-medium">Guardado</span>
    </div>
  </div>
</template>
