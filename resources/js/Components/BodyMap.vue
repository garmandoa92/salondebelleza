<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

const currentView = ref('front')
const editingZone = ref(null)

const selectedZones = computed(() =>
  props.modelValue.filter(z => z.view === currentView.value)
)

const isSelected = (zoneId) => props.modelValue.some(z => z.zone_id === zoneId)

function toggleZone(zoneId, label) {
  const existing = props.modelValue.find(z => z.zone_id === zoneId)
  if (existing) {
    editingZone.value = existing
  } else {
    const newZone = { zone_id: zoneId, label, note: '', view: currentView.value }
    const updated = [...props.modelValue, newZone]
    emit('update:modelValue', updated)
    editingZone.value = newZone
  }
}

function removeZone(zone) {
  emit('update:modelValue', props.modelValue.filter(z => z.zone_id !== zone.zone_id))
  editingZone.value = null
}

function closeEditor() {
  editingZone.value = null
}

// Zone label map for click handlers
const frontZoneLabels = {
  'cabeza': 'Cabeza', 'cuello': 'Cuello', 'hombro-izq': 'Hombro izquierdo',
  'hombro-der': 'Hombro derecho', 'pecho': 'Pecho', 'abdomen': 'Abdomen',
  'brazo-izq': 'Brazo izquierdo', 'brazo-der': 'Brazo derecho',
  'antebrazo-izq': 'Antebrazo izquierdo', 'antebrazo-der': 'Antebrazo derecho',
  'mano-izq': 'Mano izquierda', 'mano-der': 'Mano derecha',
  'caderas': 'Caderas', 'muslo-izq': 'Muslo izquierdo', 'muslo-der': 'Muslo derecho',
  'rodilla-izq': 'Rodilla izquierda', 'rodilla-der': 'Rodilla derecha',
  'pantorrilla-izq': 'Pantorrilla izquierda', 'pantorrilla-der': 'Pantorrilla derecha',
  'pie-izq': 'Pie izquierdo', 'pie-der': 'Pie derecho',
}

const backZoneLabels = {
  'b-cuello': 'Cuello posterior', 'b-hombro-izq': 'Hombro izq (dorsal)',
  'b-hombro-der': 'Hombro der (dorsal)', 'b-espalda-alta': 'Espalda alta',
  'b-lumbar': 'Zona lumbar', 'b-gluteos': 'Gluteos',
  'b-brazo-izq': 'Brazo izq (dorsal)', 'b-brazo-der': 'Brazo der (dorsal)',
  'b-muslo-izq': 'Muslo posterior izq', 'b-muslo-der': 'Muslo posterior der',
  'b-corva-izq': 'Corva izquierda', 'b-corva-der': 'Corva derecha',
  'b-gemelo-izq': 'Gemelo izquierdo', 'b-gemelo-der': 'Gemelo derecho',
  'b-talon-izq': 'Talon izquierdo', 'b-talon-der': 'Talon derecho',
}

function zoneFill(zoneId) {
  return isSelected(zoneId) ? '#FCEBEB' : '#F1EFE8'
}
function zoneStroke(zoneId) {
  return isSelected(zoneId) ? '#E24B4A' : '#B4B2A9'
}
function zoneStrokeWidth(zoneId) {
  return isSelected(zoneId) ? '2' : '1.2'
}

function clickZone(zoneId) {
  const labels = currentView.value === 'front' ? frontZoneLabels : backZoneLabels
  toggleZone(zoneId, labels[zoneId] || zoneId)
}
</script>

<template>
  <div>
    <!-- Toggle vista -->
    <div class="flex gap-2 mb-3">
      <button type="button" @click="currentView = 'front'"
        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
        :class="currentView === 'front' ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
        Frontal
      </button>
      <button type="button" @click="currentView = 'back'"
        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
        :class="currentView === 'back' ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
        Dorsal
      </button>
    </div>

    <div class="flex gap-4">
      <!-- SVG del cuerpo -->
      <div class="flex-shrink-0">
        <!-- VISTA FRONTAL -->
        <svg v-if="currentView === 'front'" width="120" height="240" viewBox="0 0 160 290" class="border border-gray-100 rounded-lg bg-gray-50/50">
          <ellipse @click="clickZone('cabeza')" class="cursor-pointer" cx="80" cy="22" rx="18" ry="20" :fill="zoneFill('cabeza')" :stroke="zoneStroke('cabeza')" :stroke-width="zoneStrokeWidth('cabeza')" />
          <rect @click="clickZone('cuello')" class="cursor-pointer" x="72" y="42" width="16" height="14" rx="3" :fill="zoneFill('cuello')" :stroke="zoneStroke('cuello')" :stroke-width="zoneStrokeWidth('cuello')" />
          <ellipse @click="clickZone('hombro-izq')" class="cursor-pointer" cx="48" cy="64" rx="14" ry="10" :fill="zoneFill('hombro-izq')" :stroke="zoneStroke('hombro-izq')" :stroke-width="zoneStrokeWidth('hombro-izq')" />
          <ellipse @click="clickZone('hombro-der')" class="cursor-pointer" cx="112" cy="64" rx="14" ry="10" :fill="zoneFill('hombro-der')" :stroke="zoneStroke('hombro-der')" :stroke-width="zoneStrokeWidth('hombro-der')" />
          <rect @click="clickZone('pecho')" class="cursor-pointer" x="56" y="56" width="48" height="28" rx="4" :fill="zoneFill('pecho')" :stroke="zoneStroke('pecho')" :stroke-width="zoneStrokeWidth('pecho')" />
          <rect @click="clickZone('abdomen')" class="cursor-pointer" x="58" y="86" width="44" height="28" rx="4" :fill="zoneFill('abdomen')" :stroke="zoneStroke('abdomen')" :stroke-width="zoneStrokeWidth('abdomen')" />
          <rect @click="clickZone('brazo-izq')" class="cursor-pointer" x="34" y="70" width="14" height="36" rx="6" :fill="zoneFill('brazo-izq')" :stroke="zoneStroke('brazo-izq')" :stroke-width="zoneStrokeWidth('brazo-izq')" />
          <rect @click="clickZone('brazo-der')" class="cursor-pointer" x="112" y="70" width="14" height="36" rx="6" :fill="zoneFill('brazo-der')" :stroke="zoneStroke('brazo-der')" :stroke-width="zoneStrokeWidth('brazo-der')" />
          <rect @click="clickZone('antebrazo-izq')" class="cursor-pointer" x="26" y="108" width="12" height="34" rx="5" :fill="zoneFill('antebrazo-izq')" :stroke="zoneStroke('antebrazo-izq')" :stroke-width="zoneStrokeWidth('antebrazo-izq')" />
          <rect @click="clickZone('antebrazo-der')" class="cursor-pointer" x="122" y="108" width="12" height="34" rx="5" :fill="zoneFill('antebrazo-der')" :stroke="zoneStroke('antebrazo-der')" :stroke-width="zoneStrokeWidth('antebrazo-der')" />
          <ellipse @click="clickZone('mano-izq')" class="cursor-pointer" cx="20" cy="148" rx="8" ry="6" :fill="zoneFill('mano-izq')" :stroke="zoneStroke('mano-izq')" :stroke-width="zoneStrokeWidth('mano-izq')" />
          <ellipse @click="clickZone('mano-der')" class="cursor-pointer" cx="140" cy="148" rx="8" ry="6" :fill="zoneFill('mano-der')" :stroke="zoneStroke('mano-der')" :stroke-width="zoneStrokeWidth('mano-der')" />
          <rect @click="clickZone('caderas')" class="cursor-pointer" x="54" y="116" width="52" height="22" rx="8" :fill="zoneFill('caderas')" :stroke="zoneStroke('caderas')" :stroke-width="zoneStrokeWidth('caderas')" />
          <rect @click="clickZone('muslo-izq')" class="cursor-pointer" x="57" y="140" width="20" height="44" rx="8" :fill="zoneFill('muslo-izq')" :stroke="zoneStroke('muslo-izq')" :stroke-width="zoneStrokeWidth('muslo-izq')" />
          <rect @click="clickZone('muslo-der')" class="cursor-pointer" x="83" y="140" width="20" height="44" rx="8" :fill="zoneFill('muslo-der')" :stroke="zoneStroke('muslo-der')" :stroke-width="zoneStrokeWidth('muslo-der')" />
          <ellipse @click="clickZone('rodilla-izq')" class="cursor-pointer" cx="67" cy="190" rx="10" ry="8" :fill="zoneFill('rodilla-izq')" :stroke="zoneStroke('rodilla-izq')" :stroke-width="zoneStrokeWidth('rodilla-izq')" />
          <ellipse @click="clickZone('rodilla-der')" class="cursor-pointer" cx="93" cy="190" rx="10" ry="8" :fill="zoneFill('rodilla-der')" :stroke="zoneStroke('rodilla-der')" :stroke-width="zoneStrokeWidth('rodilla-der')" />
          <rect @click="clickZone('pantorrilla-izq')" class="cursor-pointer" x="59" y="200" width="16" height="42" rx="7" :fill="zoneFill('pantorrilla-izq')" :stroke="zoneStroke('pantorrilla-izq')" :stroke-width="zoneStrokeWidth('pantorrilla-izq')" />
          <rect @click="clickZone('pantorrilla-der')" class="cursor-pointer" x="85" y="200" width="16" height="42" rx="7" :fill="zoneFill('pantorrilla-der')" :stroke="zoneStroke('pantorrilla-der')" :stroke-width="zoneStrokeWidth('pantorrilla-der')" />
          <ellipse @click="clickZone('pie-izq')" class="cursor-pointer" cx="67" cy="248" rx="10" ry="7" :fill="zoneFill('pie-izq')" :stroke="zoneStroke('pie-izq')" :stroke-width="zoneStrokeWidth('pie-izq')" />
          <ellipse @click="clickZone('pie-der')" class="cursor-pointer" cx="93" cy="248" rx="10" ry="7" :fill="zoneFill('pie-der')" :stroke="zoneStroke('pie-der')" :stroke-width="zoneStrokeWidth('pie-der')" />
          <text x="80" y="275" text-anchor="middle" fill="#9CA3AF" font-size="9">FRONTAL</text>
        </svg>

        <!-- VISTA DORSAL -->
        <svg v-else width="120" height="240" viewBox="0 0 160 290" class="border border-gray-100 rounded-lg bg-gray-50/50">
          <ellipse @click="clickZone('b-cuello')" class="cursor-pointer" cx="80" cy="22" rx="18" ry="20" fill="#F1EFE8" stroke="#B4B2A9" stroke-width="1.2" />
          <rect @click="clickZone('b-cuello')" class="cursor-pointer" x="72" y="42" width="16" height="14" rx="3" :fill="zoneFill('b-cuello')" :stroke="zoneStroke('b-cuello')" :stroke-width="zoneStrokeWidth('b-cuello')" />
          <ellipse @click="clickZone('b-hombro-izq')" class="cursor-pointer" cx="48" cy="64" rx="14" ry="10" :fill="zoneFill('b-hombro-izq')" :stroke="zoneStroke('b-hombro-izq')" :stroke-width="zoneStrokeWidth('b-hombro-izq')" />
          <ellipse @click="clickZone('b-hombro-der')" class="cursor-pointer" cx="112" cy="64" rx="14" ry="10" :fill="zoneFill('b-hombro-der')" :stroke="zoneStroke('b-hombro-der')" :stroke-width="zoneStrokeWidth('b-hombro-der')" />
          <rect @click="clickZone('b-espalda-alta')" class="cursor-pointer" x="56" y="56" width="48" height="28" rx="4" :fill="zoneFill('b-espalda-alta')" :stroke="zoneStroke('b-espalda-alta')" :stroke-width="zoneStrokeWidth('b-espalda-alta')" />
          <rect @click="clickZone('b-lumbar')" class="cursor-pointer" x="58" y="86" width="44" height="32" rx="4" :fill="zoneFill('b-lumbar')" :stroke="zoneStroke('b-lumbar')" :stroke-width="zoneStrokeWidth('b-lumbar')" />
          <rect @click="clickZone('b-gluteos')" class="cursor-pointer" x="54" y="120" width="52" height="26" rx="8" :fill="zoneFill('b-gluteos')" :stroke="zoneStroke('b-gluteos')" :stroke-width="zoneStrokeWidth('b-gluteos')" />
          <rect @click="clickZone('b-brazo-izq')" class="cursor-pointer" x="34" y="70" width="14" height="36" rx="6" :fill="zoneFill('b-brazo-izq')" :stroke="zoneStroke('b-brazo-izq')" :stroke-width="zoneStrokeWidth('b-brazo-izq')" />
          <rect @click="clickZone('b-brazo-der')" class="cursor-pointer" x="112" y="70" width="14" height="36" rx="6" :fill="zoneFill('b-brazo-der')" :stroke="zoneStroke('b-brazo-der')" :stroke-width="zoneStrokeWidth('b-brazo-der')" />
          <rect @click="clickZone('b-muslo-izq')" class="cursor-pointer" x="57" y="148" width="20" height="44" rx="8" :fill="zoneFill('b-muslo-izq')" :stroke="zoneStroke('b-muslo-izq')" :stroke-width="zoneStrokeWidth('b-muslo-izq')" />
          <rect @click="clickZone('b-muslo-der')" class="cursor-pointer" x="83" y="148" width="20" height="44" rx="8" :fill="zoneFill('b-muslo-der')" :stroke="zoneStroke('b-muslo-der')" :stroke-width="zoneStrokeWidth('b-muslo-der')" />
          <ellipse @click="clickZone('b-corva-izq')" class="cursor-pointer" cx="67" cy="198" rx="10" ry="8" :fill="zoneFill('b-corva-izq')" :stroke="zoneStroke('b-corva-izq')" :stroke-width="zoneStrokeWidth('b-corva-izq')" />
          <ellipse @click="clickZone('b-corva-der')" class="cursor-pointer" cx="93" cy="198" rx="10" ry="8" :fill="zoneFill('b-corva-der')" :stroke="zoneStroke('b-corva-der')" :stroke-width="zoneStrokeWidth('b-corva-der')" />
          <rect @click="clickZone('b-gemelo-izq')" class="cursor-pointer" x="59" y="208" width="16" height="42" rx="7" :fill="zoneFill('b-gemelo-izq')" :stroke="zoneStroke('b-gemelo-izq')" :stroke-width="zoneStrokeWidth('b-gemelo-izq')" />
          <rect @click="clickZone('b-gemelo-der')" class="cursor-pointer" x="85" y="208" width="16" height="42" rx="7" :fill="zoneFill('b-gemelo-der')" :stroke="zoneStroke('b-gemelo-der')" :stroke-width="zoneStrokeWidth('b-gemelo-der')" />
          <ellipse @click="clickZone('b-talon-izq')" class="cursor-pointer" cx="67" cy="256" rx="10" ry="7" :fill="zoneFill('b-talon-izq')" :stroke="zoneStroke('b-talon-izq')" :stroke-width="zoneStrokeWidth('b-talon-izq')" />
          <ellipse @click="clickZone('b-talon-der')" class="cursor-pointer" cx="93" cy="256" rx="10" ry="7" :fill="zoneFill('b-talon-der')" :stroke="zoneStroke('b-talon-der')" :stroke-width="zoneStrokeWidth('b-talon-der')" />
          <text x="80" y="275" text-anchor="middle" fill="#9CA3AF" font-size="9">DORSAL</text>
        </svg>
      </div>

      <!-- Lista de zonas marcadas -->
      <div class="flex-1 min-w-0">
        <div v-for="zone in selectedZones" :key="zone.zone_id"
          class="flex items-start gap-2 p-2 mb-2 rounded-lg border cursor-pointer hover:bg-gray-50 transition-colors"
          :class="editingZone?.zone_id === zone.zone_id ? 'border-red-300 bg-red-50' : 'border-gray-100'"
          @click="editingZone = zone">
          <div class="w-2.5 h-2.5 rounded-full bg-red-500 mt-1 flex-shrink-0" />
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-800">{{ zone.label }}</p>
            <p class="text-xs text-gray-400 truncate">{{ zone.note || 'Sin nota — toca para agregar' }}</p>
          </div>
        </div>

        <!-- Editor de nota -->
        <div v-if="editingZone" class="mt-3 p-3 bg-white border border-gray-200 rounded-lg shadow-sm">
          <p class="text-sm font-medium text-gray-800 mb-2">{{ editingZone.label }}</p>
          <textarea v-model="editingZone.note" rows="2"
            class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-[var(--color-primary)] outline-none"
            placeholder="Nota clinica: Ej: Hernia discal L4-L5, no aplicar presion directa" />
          <div class="flex gap-2 justify-end mt-2">
            <button type="button" @click="removeZone(editingZone)"
              class="px-3 py-1 text-xs text-red-600 hover:bg-red-50 rounded">Quitar zona</button>
            <button type="button" @click="closeEditor"
              class="px-3 py-1 text-xs bg-[var(--color-primary)] text-white rounded hover:opacity-90">Guardar nota</button>
          </div>
        </div>

        <p v-if="!selectedZones.length && !editingZone" class="text-xs text-gray-400 italic mt-4">
          Toca una zona en la silueta para marcarla como zona a evitar
        </p>
      </div>
    </div>
  </div>
</template>
