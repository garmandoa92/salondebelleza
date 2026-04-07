<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  open: Boolean,
  appointmentId: String,
  clientName: { type: String, default: '' },
  serviceName: { type: String, default: '' },
})
const emit = defineEmits(['close', 'saved'])

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`

const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const currentView = ref('front')
const currentMode = ref('worked') // worked | tension | avoided

const bodyMap = ref([])
const inheritedAvoidZones = ref([])
const techniques = ref([])
const productsUsed = ref([])
const actualDuration = ref(null)
const tensionLevel = ref(null)
const observations = ref('')
const nextRecommendation = ref('')
const clientRecommendation = ref('')
const sendWhatsapp = ref(true)
const whatsappSent = ref(false)
const whatsappSentAt = ref(null)
const existingNote = ref(null)

const customTechnique = ref('')
const customProduct = ref('')

const techniqueOptions = [
  'Effleurage', 'Petrissage', 'Friccion', 'Tapotement', 'Vibracion',
  'Puntos gatillo', 'Drenaje linfatico', 'Piedras calientes', 'Reflexologia',
  'Aromaterapia', 'Bambuterapia', 'Ventosas', 'Masaje con velas', 'Masaje tailandes', 'Shiatsu',
]

const ZONE_STATES = {
  worked: { fill: '#EAF3DE', stroke: '#97C459', label: 'Trabajado' },
  tension: { fill: '#FAEEDA', stroke: '#EF9F27', label: 'Tension' },
  avoided: { fill: '#FCEBEB', stroke: '#E24B4A', label: 'Evitado' },
}

const isInherited = (zoneId) => inheritedAvoidZones.value.some(z => z.zone_id === zoneId)

const getZoneState = (zoneId) => {
  if (isInherited(zoneId)) return 'avoided'
  const z = bodyMap.value.find(z => z.zone_id === zoneId)
  return z?.state || null
}

const getZoneFill = (zoneId) => {
  const state = getZoneState(zoneId)
  return state ? ZONE_STATES[state].fill : '#F1EFE8'
}

const getZoneStroke = (zoneId) => {
  const state = getZoneState(zoneId)
  return state ? ZONE_STATES[state].stroke : '#B4B2A9'
}

function paintZone(zoneId, label) {
  if (isInherited(zoneId)) return
  const existing = bodyMap.value.find(z => z.zone_id === zoneId)
  if (existing?.state === currentMode.value) {
    bodyMap.value = bodyMap.value.filter(z => z.zone_id !== zoneId)
  } else if (existing) {
    existing.state = currentMode.value
  } else {
    bodyMap.value.push({ zone_id: zoneId, label, state: currentMode.value, view: currentView.value })
  }
}

const allMarkedZones = computed(() => {
  const inherited = inheritedAvoidZones.value.map(z => ({ ...z, inherited: true }))
  const session = bodyMap.value.filter(z => z.view === currentView.value)
  return [...inherited.filter(z => z.view === currentView.value), ...session]
})

function toggleTechnique(t) {
  const idx = techniques.value.indexOf(t)
  if (idx >= 0) techniques.value.splice(idx, 1)
  else techniques.value.push(t)
}

function addCustomTechnique() {
  const val = customTechnique.value.trim()
  if (val && !techniques.value.includes(val)) techniques.value.push(val)
  customTechnique.value = ''
}

function addProduct() {
  const val = customProduct.value.trim()
  if (val) productsUsed.value.push(val)
  customProduct.value = ''
}

function removeProduct(idx) { productsUsed.value.splice(idx, 1) }

watch(() => props.open, async (val) => {
  if (val && props.appointmentId) {
    loading.value = true
    saved.value = false
    try {
      const { data } = await axios.get(`${base}/citas/${props.appointmentId}/nota-sesion`)
      inheritedAvoidZones.value = data.inherited_avoid_zones || []
      bodyMap.value = data.body_map || []
      techniques.value = data.techniques || []
      productsUsed.value = data.products_used || []
      existingNote.value = data.note
      if (data.note) {
        actualDuration.value = data.note.actual_duration_minutes
        tensionLevel.value = data.note.tension_level
        observations.value = data.note.observations || ''
        nextRecommendation.value = data.note.next_session_recommendation || ''
        clientRecommendation.value = data.note.client_recommendation || ''
        sendWhatsapp.value = data.note.send_whatsapp ?? true
        whatsappSent.value = data.note.whatsapp_sent || false
        whatsappSentAt.value = data.note.whatsapp_sent_at
      } else {
        actualDuration.value = null
        tensionLevel.value = null
        observations.value = ''
        nextRecommendation.value = ''
        clientRecommendation.value = ''
        sendWhatsapp.value = true
        whatsappSent.value = false
        whatsappSentAt.value = null
      }
    } catch (e) {
      console.error(e)
    } finally {
      loading.value = false
    }
  }
})

async function save() {
  saving.value = true
  try {
    await axios.post(`${base}/citas/${props.appointmentId}/nota-sesion`, {
      body_map: bodyMap.value,
      techniques: techniques.value,
      products_used: productsUsed.value,
      actual_duration_minutes: actualDuration.value,
      tension_level: tensionLevel.value,
      observations: observations.value || null,
      next_session_recommendation: nextRecommendation.value || null,
      client_recommendation: clientRecommendation.value || null,
      send_whatsapp: sendWhatsapp.value,
    })
    saved.value = true
    emit('saved')
    setTimeout(() => emit('close'), 1000)
  } catch (e) {
    console.error('Error guardando nota:', e.response?.data)
  } finally {
    saving.value = false
  }
}

const modeButtons = [
  { value: 'worked', label: 'Trabajado', color: 'bg-green-100 text-green-700 border-green-300', dot: 'bg-green-500' },
  { value: 'tension', label: 'Tension', color: 'bg-amber-100 text-amber-700 border-amber-300', dot: 'bg-amber-500' },
  { value: 'avoided', label: 'Evitar', color: 'bg-red-100 text-red-700 border-red-300', dot: 'bg-red-500' },
]
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="open" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
        <div class="relative w-full max-w-lg bg-white shadow-xl flex flex-col">
          <!-- Header -->
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <div>
              <h2 class="font-semibold text-gray-900">Nota de sesion</h2>
              <p class="text-xs text-gray-500">{{ clientName }} · {{ serviceName }}</p>
            </div>
            <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
          </div>

          <!-- Body -->
          <div v-if="loading" class="flex-1 flex items-center justify-center">
            <span class="text-sm text-gray-400">Cargando...</span>
          </div>

          <div v-else class="flex-1 overflow-y-auto p-5 space-y-6">
            <!-- MAPA CORPORAL -->
            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Mapa corporal</h3>

              <!-- Mode buttons -->
              <div class="flex gap-2 mb-3">
                <button v-for="m in modeButtons" :key="m.value" type="button" @click="currentMode = m.value"
                  class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
                  :class="currentMode === m.value ? m.color : 'bg-white text-gray-500 border-gray-200'">
                  <span class="inline-block w-2 h-2 rounded-full mr-1" :class="m.dot" />
                  {{ m.label }}
                </button>
              </div>

              <!-- View toggle -->
              <div class="flex gap-2 mb-3">
                <button type="button" @click="currentView = 'front'"
                  class="px-3 py-1 text-xs rounded-lg"
                  :class="currentView === 'front' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600'">Frontal</button>
                <button type="button" @click="currentView = 'back'"
                  class="px-3 py-1 text-xs rounded-lg"
                  :class="currentView === 'back' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600'">Dorsal</button>
              </div>

              <div class="flex gap-4">
                <!-- SVG -->
                <div class="flex-shrink-0">
                  <!-- FRONTAL -->
                  <svg v-if="currentView === 'front'" width="120" height="240" viewBox="0 0 160 290" class="border border-gray-100 rounded-lg bg-gray-50/50">
                    <ellipse @click="paintZone('cabeza', 'Cabeza')" :class="isInherited('cabeza') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="80" cy="22" rx="18" ry="20" :fill="getZoneFill('cabeza')" :stroke="getZoneStroke('cabeza')" :stroke-width="getZoneState('cabeza') ? 2 : 1.2" :stroke-dasharray="isInherited('cabeza') ? '4,3' : 'none'" />
                    <rect @click="paintZone('cuello', 'Cuello')" :class="isInherited('cuello') ? 'cursor-not-allowed' : 'cursor-pointer'" x="72" y="42" width="16" height="14" rx="3" :fill="getZoneFill('cuello')" :stroke="getZoneStroke('cuello')" :stroke-width="getZoneState('cuello') ? 2 : 1.2" :stroke-dasharray="isInherited('cuello') ? '4,3' : 'none'" />
                    <ellipse @click="paintZone('hombro-izq', 'Hombro izq')" :class="isInherited('hombro-izq') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="48" cy="64" rx="14" ry="10" :fill="getZoneFill('hombro-izq')" :stroke="getZoneStroke('hombro-izq')" :stroke-width="getZoneState('hombro-izq') ? 2 : 1.2" :stroke-dasharray="isInherited('hombro-izq') ? '4,3' : 'none'" />
                    <ellipse @click="paintZone('hombro-der', 'Hombro der')" :class="isInherited('hombro-der') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="112" cy="64" rx="14" ry="10" :fill="getZoneFill('hombro-der')" :stroke="getZoneStroke('hombro-der')" :stroke-width="getZoneState('hombro-der') ? 2 : 1.2" :stroke-dasharray="isInherited('hombro-der') ? '4,3' : 'none'" />
                    <rect @click="paintZone('pecho', 'Pecho')" :class="isInherited('pecho') ? 'cursor-not-allowed' : 'cursor-pointer'" x="56" y="56" width="48" height="28" rx="4" :fill="getZoneFill('pecho')" :stroke="getZoneStroke('pecho')" :stroke-width="getZoneState('pecho') ? 2 : 1.2" :stroke-dasharray="isInherited('pecho') ? '4,3' : 'none'" />
                    <rect @click="paintZone('abdomen', 'Abdomen')" :class="isInherited('abdomen') ? 'cursor-not-allowed' : 'cursor-pointer'" x="58" y="86" width="44" height="28" rx="4" :fill="getZoneFill('abdomen')" :stroke="getZoneStroke('abdomen')" :stroke-width="getZoneState('abdomen') ? 2 : 1.2" :stroke-dasharray="isInherited('abdomen') ? '4,3' : 'none'" />
                    <rect @click="paintZone('brazo-izq', 'Brazo izq')" :class="isInherited('brazo-izq') ? 'cursor-not-allowed' : 'cursor-pointer'" x="34" y="70" width="14" height="36" rx="6" :fill="getZoneFill('brazo-izq')" :stroke="getZoneStroke('brazo-izq')" :stroke-width="getZoneState('brazo-izq') ? 2 : 1.2" />
                    <rect @click="paintZone('brazo-der', 'Brazo der')" :class="isInherited('brazo-der') ? 'cursor-not-allowed' : 'cursor-pointer'" x="112" y="70" width="14" height="36" rx="6" :fill="getZoneFill('brazo-der')" :stroke="getZoneStroke('brazo-der')" :stroke-width="getZoneState('brazo-der') ? 2 : 1.2" />
                    <rect @click="paintZone('antebrazo-izq', 'Antebrazo izq')" class="cursor-pointer" x="26" y="108" width="12" height="34" rx="5" :fill="getZoneFill('antebrazo-izq')" :stroke="getZoneStroke('antebrazo-izq')" :stroke-width="getZoneState('antebrazo-izq') ? 2 : 1.2" />
                    <rect @click="paintZone('antebrazo-der', 'Antebrazo der')" class="cursor-pointer" x="122" y="108" width="12" height="34" rx="5" :fill="getZoneFill('antebrazo-der')" :stroke="getZoneStroke('antebrazo-der')" :stroke-width="getZoneState('antebrazo-der') ? 2 : 1.2" />
                    <ellipse @click="paintZone('mano-izq', 'Mano izq')" class="cursor-pointer" cx="20" cy="148" rx="8" ry="6" :fill="getZoneFill('mano-izq')" :stroke="getZoneStroke('mano-izq')" :stroke-width="getZoneState('mano-izq') ? 2 : 1.2" />
                    <ellipse @click="paintZone('mano-der', 'Mano der')" class="cursor-pointer" cx="140" cy="148" rx="8" ry="6" :fill="getZoneFill('mano-der')" :stroke="getZoneStroke('mano-der')" :stroke-width="getZoneState('mano-der') ? 2 : 1.2" />
                    <rect @click="paintZone('caderas', 'Caderas')" class="cursor-pointer" x="54" y="116" width="52" height="22" rx="8" :fill="getZoneFill('caderas')" :stroke="getZoneStroke('caderas')" :stroke-width="getZoneState('caderas') ? 2 : 1.2" />
                    <rect @click="paintZone('muslo-izq', 'Muslo izq')" class="cursor-pointer" x="57" y="140" width="20" height="44" rx="8" :fill="getZoneFill('muslo-izq')" :stroke="getZoneStroke('muslo-izq')" :stroke-width="getZoneState('muslo-izq') ? 2 : 1.2" />
                    <rect @click="paintZone('muslo-der', 'Muslo der')" class="cursor-pointer" x="83" y="140" width="20" height="44" rx="8" :fill="getZoneFill('muslo-der')" :stroke="getZoneStroke('muslo-der')" :stroke-width="getZoneState('muslo-der') ? 2 : 1.2" />
                    <ellipse @click="paintZone('rodilla-izq', 'Rodilla izq')" :class="isInherited('rodilla-izq') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="67" cy="190" rx="10" ry="8" :fill="getZoneFill('rodilla-izq')" :stroke="getZoneStroke('rodilla-izq')" :stroke-width="getZoneState('rodilla-izq') ? 2 : 1.2" :stroke-dasharray="isInherited('rodilla-izq') ? '4,3' : 'none'" />
                    <ellipse @click="paintZone('rodilla-der', 'Rodilla der')" :class="isInherited('rodilla-der') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="93" cy="190" rx="10" ry="8" :fill="getZoneFill('rodilla-der')" :stroke="getZoneStroke('rodilla-der')" :stroke-width="getZoneState('rodilla-der') ? 2 : 1.2" :stroke-dasharray="isInherited('rodilla-der') ? '4,3' : 'none'" />
                    <rect @click="paintZone('pantorrilla-izq', 'Pantorrilla izq')" class="cursor-pointer" x="59" y="200" width="16" height="42" rx="7" :fill="getZoneFill('pantorrilla-izq')" :stroke="getZoneStroke('pantorrilla-izq')" :stroke-width="getZoneState('pantorrilla-izq') ? 2 : 1.2" />
                    <rect @click="paintZone('pantorrilla-der', 'Pantorrilla der')" class="cursor-pointer" x="85" y="200" width="16" height="42" rx="7" :fill="getZoneFill('pantorrilla-der')" :stroke="getZoneStroke('pantorrilla-der')" :stroke-width="getZoneState('pantorrilla-der') ? 2 : 1.2" />
                    <ellipse @click="paintZone('pie-izq', 'Pie izq')" class="cursor-pointer" cx="67" cy="248" rx="10" ry="7" :fill="getZoneFill('pie-izq')" :stroke="getZoneStroke('pie-izq')" :stroke-width="getZoneState('pie-izq') ? 2 : 1.2" />
                    <ellipse @click="paintZone('pie-der', 'Pie der')" class="cursor-pointer" cx="93" cy="248" rx="10" ry="7" :fill="getZoneFill('pie-der')" :stroke="getZoneStroke('pie-der')" :stroke-width="getZoneState('pie-der') ? 2 : 1.2" />
                    <text x="80" y="275" text-anchor="middle" fill="#9CA3AF" font-size="9">FRONTAL</text>
                  </svg>
                  <!-- DORSAL -->
                  <svg v-else width="120" height="240" viewBox="0 0 160 290" class="border border-gray-100 rounded-lg bg-gray-50/50">
                    <ellipse cx="80" cy="22" rx="18" ry="20" fill="#F1EFE8" stroke="#B4B2A9" stroke-width="1.2" />
                    <rect @click="paintZone('b-cuello', 'Cuello posterior')" :class="isInherited('b-cuello') ? 'cursor-not-allowed' : 'cursor-pointer'" x="72" y="42" width="16" height="14" rx="3" :fill="getZoneFill('b-cuello')" :stroke="getZoneStroke('b-cuello')" :stroke-width="getZoneState('b-cuello') ? 2 : 1.2" :stroke-dasharray="isInherited('b-cuello') ? '4,3' : 'none'" />
                    <ellipse @click="paintZone('b-hombro-izq', 'Hombro izq dorsal')" :class="isInherited('b-hombro-izq') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="48" cy="64" rx="14" ry="10" :fill="getZoneFill('b-hombro-izq')" :stroke="getZoneStroke('b-hombro-izq')" :stroke-width="getZoneState('b-hombro-izq') ? 2 : 1.2" :stroke-dasharray="isInherited('b-hombro-izq') ? '4,3' : 'none'" />
                    <ellipse @click="paintZone('b-hombro-der', 'Hombro der dorsal')" :class="isInherited('b-hombro-der') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="112" cy="64" rx="14" ry="10" :fill="getZoneFill('b-hombro-der')" :stroke="getZoneStroke('b-hombro-der')" :stroke-width="getZoneState('b-hombro-der') ? 2 : 1.2" :stroke-dasharray="isInherited('b-hombro-der') ? '4,3' : 'none'" />
                    <rect @click="paintZone('b-espalda-alta', 'Espalda alta')" :class="isInherited('b-espalda-alta') ? 'cursor-not-allowed' : 'cursor-pointer'" x="56" y="56" width="48" height="28" rx="4" :fill="getZoneFill('b-espalda-alta')" :stroke="getZoneStroke('b-espalda-alta')" :stroke-width="getZoneState('b-espalda-alta') ? 2 : 1.2" :stroke-dasharray="isInherited('b-espalda-alta') ? '4,3' : 'none'" />
                    <rect @click="paintZone('b-lumbar', 'Zona lumbar')" :class="isInherited('b-lumbar') ? 'cursor-not-allowed' : 'cursor-pointer'" x="58" y="86" width="44" height="32" rx="4" :fill="getZoneFill('b-lumbar')" :stroke="getZoneStroke('b-lumbar')" :stroke-width="getZoneState('b-lumbar') ? 2 : 1.2" :stroke-dasharray="isInherited('b-lumbar') ? '4,3' : 'none'" />
                    <rect @click="paintZone('b-gluteos', 'Gluteos')" :class="isInherited('b-gluteos') ? 'cursor-not-allowed' : 'cursor-pointer'" x="54" y="120" width="52" height="26" rx="8" :fill="getZoneFill('b-gluteos')" :stroke="getZoneStroke('b-gluteos')" :stroke-width="getZoneState('b-gluteos') ? 2 : 1.2" />
                    <rect @click="paintZone('b-brazo-izq', 'Brazo izq dorsal')" class="cursor-pointer" x="34" y="70" width="14" height="36" rx="6" :fill="getZoneFill('b-brazo-izq')" :stroke="getZoneStroke('b-brazo-izq')" :stroke-width="getZoneState('b-brazo-izq') ? 2 : 1.2" />
                    <rect @click="paintZone('b-brazo-der', 'Brazo der dorsal')" class="cursor-pointer" x="112" y="70" width="14" height="36" rx="6" :fill="getZoneFill('b-brazo-der')" :stroke="getZoneStroke('b-brazo-der')" :stroke-width="getZoneState('b-brazo-der') ? 2 : 1.2" />
                    <rect @click="paintZone('b-muslo-izq', 'Muslo post izq')" class="cursor-pointer" x="57" y="148" width="20" height="44" rx="8" :fill="getZoneFill('b-muslo-izq')" :stroke="getZoneStroke('b-muslo-izq')" :stroke-width="getZoneState('b-muslo-izq') ? 2 : 1.2" />
                    <rect @click="paintZone('b-muslo-der', 'Muslo post der')" class="cursor-pointer" x="83" y="148" width="20" height="44" rx="8" :fill="getZoneFill('b-muslo-der')" :stroke="getZoneStroke('b-muslo-der')" :stroke-width="getZoneState('b-muslo-der') ? 2 : 1.2" />
                    <ellipse @click="paintZone('b-corva-izq', 'Corva izq')" class="cursor-pointer" cx="67" cy="198" rx="10" ry="8" :fill="getZoneFill('b-corva-izq')" :stroke="getZoneStroke('b-corva-izq')" :stroke-width="getZoneState('b-corva-izq') ? 2 : 1.2" />
                    <ellipse @click="paintZone('b-corva-der', 'Corva der')" class="cursor-pointer" cx="93" cy="198" rx="10" ry="8" :fill="getZoneFill('b-corva-der')" :stroke="getZoneStroke('b-corva-der')" :stroke-width="getZoneState('b-corva-der') ? 2 : 1.2" />
                    <rect @click="paintZone('b-gemelo-izq', 'Gemelo izq')" class="cursor-pointer" x="59" y="208" width="16" height="42" rx="7" :fill="getZoneFill('b-gemelo-izq')" :stroke="getZoneStroke('b-gemelo-izq')" :stroke-width="getZoneState('b-gemelo-izq') ? 2 : 1.2" />
                    <rect @click="paintZone('b-gemelo-der', 'Gemelo der')" class="cursor-pointer" x="85" y="208" width="16" height="42" rx="7" :fill="getZoneFill('b-gemelo-der')" :stroke="getZoneStroke('b-gemelo-der')" :stroke-width="getZoneState('b-gemelo-der') ? 2 : 1.2" />
                    <ellipse @click="paintZone('b-talon-izq', 'Talon izq')" class="cursor-pointer" cx="67" cy="256" rx="10" ry="7" :fill="getZoneFill('b-talon-izq')" :stroke="getZoneStroke('b-talon-izq')" :stroke-width="getZoneState('b-talon-izq') ? 2 : 1.2" />
                    <ellipse @click="paintZone('b-talon-der', 'Talon der')" class="cursor-pointer" cx="93" cy="256" rx="10" ry="7" :fill="getZoneFill('b-talon-der')" :stroke="getZoneStroke('b-talon-der')" :stroke-width="getZoneState('b-talon-der') ? 2 : 1.2" />
                    <text x="80" y="275" text-anchor="middle" fill="#9CA3AF" font-size="9">DORSAL</text>
                  </svg>
                </div>

                <!-- Zones list -->
                <div class="flex-1 min-w-0 space-y-1">
                  <div v-for="z in allMarkedZones" :key="z.zone_id"
                    class="flex items-center gap-2 px-2 py-1 rounded text-xs"
                    :class="z.inherited ? 'bg-red-50 border border-dashed border-red-200' : ''">
                    <span class="w-2 h-2 rounded-full flex-shrink-0"
                      :class="{
                        'bg-green-500': z.state === 'worked',
                        'bg-amber-500': z.state === 'tension',
                        'bg-red-500': z.state === 'avoided',
                      }" />
                    <span class="text-gray-700">{{ z.label }}</span>
                    <span v-if="z.inherited" class="text-[10px] text-red-400 ml-auto">ficha</span>
                    <span v-if="z.note" class="text-[10px] text-gray-400 ml-auto truncate max-w-[100px]">{{ z.note }}</span>
                  </div>
                  <p v-if="!allMarkedZones.length" class="text-xs text-gray-400 italic pt-2">
                    Selecciona un modo y toca las zonas
                  </p>
                </div>
              </div>

              <!-- Legend -->
              <div class="flex gap-4 mt-3 text-[10px] text-gray-500">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500" /> Trabajado</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500" /> Tension</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500" /> Evitado</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 border border-dashed border-red-400 rounded-sm" style="width:8px;height:8px;" /> Ficha salud</span>
              </div>
            </div>

            <!-- TECNICAS -->
            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-2">Tecnicas aplicadas</h3>
              <div class="flex flex-wrap gap-1.5 mb-2">
                <button v-for="t in techniqueOptions" :key="t" type="button" @click="toggleTechnique(t)"
                  class="px-2.5 py-1 text-xs rounded-full border transition-colors"
                  :class="techniques.includes(t) ? 'bg-[var(--color-primary-10)] text-[var(--color-primary)] border-[var(--color-primary)]' : 'bg-white text-gray-600 border-gray-200'">
                  {{ t }}
                </button>
              </div>
              <div class="flex gap-2">
                <input v-model="customTechnique" type="text" placeholder="Agregar otra..." class="flex-1 border border-gray-200 rounded px-2 py-1 text-sm" @keyup.enter="addCustomTechnique" />
                <button type="button" @click="addCustomTechnique" class="px-3 py-1 text-xs bg-gray-100 rounded">+</button>
              </div>
            </div>

            <!-- PRODUCTOS -->
            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-2">Productos utilizados</h3>
              <div v-for="(p, i) in productsUsed" :key="i" class="flex items-center gap-2 mb-1">
                <span class="text-sm text-gray-700 flex-1">{{ p }}</span>
                <button type="button" @click="removeProduct(i)" class="text-xs text-red-400 hover:text-red-600">&times;</button>
              </div>
              <div class="flex gap-2">
                <input v-model="customProduct" type="text" placeholder="Ej: Aceite lavanda 30ml" class="flex-1 border border-gray-200 rounded px-2 py-1 text-sm" @keyup.enter="addProduct" />
                <button type="button" @click="addProduct" class="px-3 py-1 text-xs bg-gray-100 rounded">+</button>
              </div>
            </div>

            <!-- DURACION Y TENSION -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Duracion real (min)</label>
                <input v-model.number="actualDuration" type="number" min="1" max="480" class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" />
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nivel de tension</label>
                <select v-model="tensionLevel" class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm">
                  <option :value="null">Sin evaluar</option>
                  <option value="low">Baja</option>
                  <option value="medium">Media</option>
                  <option value="high">Alta</option>
                </select>
              </div>
            </div>

            <!-- OBSERVACIONES -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Observaciones (privado)</label>
              <textarea v-model="observations" rows="3" class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" placeholder="Notas sobre la sesion..." />
            </div>

            <!-- RECOMENDACION PROXIMA SESION -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Recomendacion proxima sesion (privado)</label>
              <textarea v-model="nextRecommendation" rows="2" class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" placeholder="Que trabajar la proxima vez..." />
            </div>

            <!-- RECOMENDACION AL CLIENTE -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Recomendacion al cliente</label>
              <textarea v-model="clientRecommendation" rows="2" maxlength="500" class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" placeholder="Mensaje que se envia al cliente..." />
              <div class="flex items-center justify-between mt-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="sendWhatsapp" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-green-600" />
                  <span class="text-xs text-gray-600">Enviar por WhatsApp al guardar</span>
                </label>
                <span class="text-[10px] text-gray-400">{{ clientRecommendation.length }}/500</span>
              </div>
              <div v-if="whatsappSent" class="mt-2 flex items-center gap-2 px-3 py-1.5 bg-green-50 rounded-lg text-xs text-green-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                WhatsApp enviado {{ whatsappSentAt ? new Date(whatsappSentAt).toLocaleString('es-EC') : '' }}
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between flex-shrink-0">
            <button type="button" @click="emit('close')" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
            <div class="flex items-center gap-3">
              <span v-if="saved" class="text-xs text-green-600 font-medium">Guardado</span>
              <button type="button" @click="save" :disabled="saving"
                class="px-5 py-2 bg-[var(--color-primary)] text-white rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-50">
                {{ saving ? 'Guardando...' : 'Guardar nota' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: all 0.3s ease; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from > div:last-child, .drawer-leave-to > div:last-child { transform: translateX(100%); }
</style>
