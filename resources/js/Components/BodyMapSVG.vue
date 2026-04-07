<script setup>
const props = defineProps({
  view: { type: String, default: 'front' },
  zoneStates: { type: Object, default: () => ({}) },
  inheritedZones: { type: Array, default: () => [] },
  currentMode: { type: String, default: 'worked' },
})
const emit = defineEmits(['zone-click'])

const COLORS = {
  worked:  { fill: '#EAF3DE', stroke: '#97C459' },
  tension: { fill: '#FAEEDA', stroke: '#EF9F27' },
  avoided: { fill: '#FCEBEB', stroke: '#E24B4A' },
}

function isInherited(zoneId) {
  return props.inheritedZones.some(z => z.zone_id === zoneId)
}

function zoneFill(zoneId) {
  if (isInherited(zoneId)) return '#FCEBEB'
  const state = props.zoneStates[zoneId]
  return state ? COLORS[state].fill : '#F1EFE8'
}

function zoneStroke(zoneId) {
  if (isInherited(zoneId)) return '#E24B4A'
  const state = props.zoneStates[zoneId]
  return state ? COLORS[state].stroke : '#B4B2A9'
}

function zoneSW(zoneId) {
  return (isInherited(zoneId) || props.zoneStates[zoneId]) ? 2 : 1.2
}

function zoneDash(zoneId) {
  return isInherited(zoneId) ? '4,3' : 'none'
}

function onClick(zoneId) {
  if (!isInherited(zoneId)) emit('zone-click', zoneId)
}
</script>

<template>
  <!-- FRONTAL -->
  <svg v-if="view === 'front'" width="120" height="240" viewBox="0 0 160 290" class="border border-gray-100 rounded-lg bg-gray-50/50">
    <ellipse id="sn-cabeza" @click="onClick('cabeza')" :class="isInherited('cabeza') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="80" cy="22" rx="18" ry="20" :fill="zoneFill('cabeza')" :stroke="zoneStroke('cabeza')" :stroke-width="zoneSW('cabeza')" :stroke-dasharray="zoneDash('cabeza')" :pointer-events="isInherited('cabeza') ? 'none' : 'auto'" />
    <rect id="sn-cuello" @click="onClick('cuello')" :class="isInherited('cuello') ? 'cursor-not-allowed' : 'cursor-pointer'" x="72" y="42" width="16" height="14" rx="3" :fill="zoneFill('cuello')" :stroke="zoneStroke('cuello')" :stroke-width="zoneSW('cuello')" :stroke-dasharray="zoneDash('cuello')" :pointer-events="isInherited('cuello') ? 'none' : 'auto'" />
    <ellipse id="sn-hombro-izq" @click="onClick('hombro-izq')" :class="isInherited('hombro-izq') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="48" cy="64" rx="14" ry="10" :fill="zoneFill('hombro-izq')" :stroke="zoneStroke('hombro-izq')" :stroke-width="zoneSW('hombro-izq')" :stroke-dasharray="zoneDash('hombro-izq')" :pointer-events="isInherited('hombro-izq') ? 'none' : 'auto'" />
    <ellipse id="sn-hombro-der" @click="onClick('hombro-der')" :class="isInherited('hombro-der') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="112" cy="64" rx="14" ry="10" :fill="zoneFill('hombro-der')" :stroke="zoneStroke('hombro-der')" :stroke-width="zoneSW('hombro-der')" :stroke-dasharray="zoneDash('hombro-der')" :pointer-events="isInherited('hombro-der') ? 'none' : 'auto'" />
    <rect id="sn-pecho" @click="onClick('pecho')" :class="isInherited('pecho') ? 'cursor-not-allowed' : 'cursor-pointer'" x="56" y="56" width="48" height="28" rx="4" :fill="zoneFill('pecho')" :stroke="zoneStroke('pecho')" :stroke-width="zoneSW('pecho')" :stroke-dasharray="zoneDash('pecho')" :pointer-events="isInherited('pecho') ? 'none' : 'auto'" />
    <rect id="sn-abdomen" @click="onClick('abdomen')" :class="isInherited('abdomen') ? 'cursor-not-allowed' : 'cursor-pointer'" x="58" y="86" width="44" height="28" rx="4" :fill="zoneFill('abdomen')" :stroke="zoneStroke('abdomen')" :stroke-width="zoneSW('abdomen')" :stroke-dasharray="zoneDash('abdomen')" :pointer-events="isInherited('abdomen') ? 'none' : 'auto'" />
    <rect id="sn-brazo-izq" @click="onClick('brazo-izq')" class="cursor-pointer" x="34" y="70" width="14" height="36" rx="6" :fill="zoneFill('brazo-izq')" :stroke="zoneStroke('brazo-izq')" :stroke-width="zoneSW('brazo-izq')" />
    <rect id="sn-brazo-der" @click="onClick('brazo-der')" class="cursor-pointer" x="112" y="70" width="14" height="36" rx="6" :fill="zoneFill('brazo-der')" :stroke="zoneStroke('brazo-der')" :stroke-width="zoneSW('brazo-der')" />
    <rect id="sn-antebrazo-izq" @click="onClick('antebrazo-izq')" class="cursor-pointer" x="26" y="108" width="12" height="34" rx="5" :fill="zoneFill('antebrazo-izq')" :stroke="zoneStroke('antebrazo-izq')" :stroke-width="zoneSW('antebrazo-izq')" />
    <rect id="sn-antebrazo-der" @click="onClick('antebrazo-der')" class="cursor-pointer" x="122" y="108" width="12" height="34" rx="5" :fill="zoneFill('antebrazo-der')" :stroke="zoneStroke('antebrazo-der')" :stroke-width="zoneSW('antebrazo-der')" />
    <ellipse id="sn-mano-izq" @click="onClick('mano-izq')" class="cursor-pointer" cx="20" cy="148" rx="8" ry="6" :fill="zoneFill('mano-izq')" :stroke="zoneStroke('mano-izq')" :stroke-width="zoneSW('mano-izq')" />
    <ellipse id="sn-mano-der" @click="onClick('mano-der')" class="cursor-pointer" cx="140" cy="148" rx="8" ry="6" :fill="zoneFill('mano-der')" :stroke="zoneStroke('mano-der')" :stroke-width="zoneSW('mano-der')" />
    <rect id="sn-caderas" @click="onClick('caderas')" class="cursor-pointer" x="54" y="116" width="52" height="22" rx="8" :fill="zoneFill('caderas')" :stroke="zoneStroke('caderas')" :stroke-width="zoneSW('caderas')" />
    <rect id="sn-muslo-izq" @click="onClick('muslo-izq')" class="cursor-pointer" x="57" y="140" width="20" height="44" rx="8" :fill="zoneFill('muslo-izq')" :stroke="zoneStroke('muslo-izq')" :stroke-width="zoneSW('muslo-izq')" />
    <rect id="sn-muslo-der" @click="onClick('muslo-der')" class="cursor-pointer" x="83" y="140" width="20" height="44" rx="8" :fill="zoneFill('muslo-der')" :stroke="zoneStroke('muslo-der')" :stroke-width="zoneSW('muslo-der')" />
    <ellipse id="sn-rodilla-izq" @click="onClick('rodilla-izq')" :class="isInherited('rodilla-izq') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="67" cy="190" rx="10" ry="8" :fill="zoneFill('rodilla-izq')" :stroke="zoneStroke('rodilla-izq')" :stroke-width="zoneSW('rodilla-izq')" :stroke-dasharray="zoneDash('rodilla-izq')" :pointer-events="isInherited('rodilla-izq') ? 'none' : 'auto'" />
    <ellipse id="sn-rodilla-der" @click="onClick('rodilla-der')" :class="isInherited('rodilla-der') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="93" cy="190" rx="10" ry="8" :fill="zoneFill('rodilla-der')" :stroke="zoneStroke('rodilla-der')" :stroke-width="zoneSW('rodilla-der')" :stroke-dasharray="zoneDash('rodilla-der')" :pointer-events="isInherited('rodilla-der') ? 'none' : 'auto'" />
    <rect id="sn-pantorrilla-izq" @click="onClick('pantorrilla-izq')" class="cursor-pointer" x="59" y="200" width="16" height="42" rx="7" :fill="zoneFill('pantorrilla-izq')" :stroke="zoneStroke('pantorrilla-izq')" :stroke-width="zoneSW('pantorrilla-izq')" />
    <rect id="sn-pantorrilla-der" @click="onClick('pantorrilla-der')" class="cursor-pointer" x="85" y="200" width="16" height="42" rx="7" :fill="zoneFill('pantorrilla-der')" :stroke="zoneStroke('pantorrilla-der')" :stroke-width="zoneSW('pantorrilla-der')" />
    <ellipse id="sn-pie-izq" @click="onClick('pie-izq')" class="cursor-pointer" cx="67" cy="248" rx="10" ry="7" :fill="zoneFill('pie-izq')" :stroke="zoneStroke('pie-izq')" :stroke-width="zoneSW('pie-izq')" />
    <ellipse id="sn-pie-der" @click="onClick('pie-der')" class="cursor-pointer" cx="93" cy="248" rx="10" ry="7" :fill="zoneFill('pie-der')" :stroke="zoneStroke('pie-der')" :stroke-width="zoneSW('pie-der')" />
    <text x="80" y="275" text-anchor="middle" fill="#9CA3AF" font-size="9">FRONTAL</text>
  </svg>

  <!-- DORSAL -->
  <svg v-else width="120" height="240" viewBox="0 0 160 290" class="border border-gray-100 rounded-lg bg-gray-50/50">
    <ellipse cx="80" cy="22" rx="18" ry="20" fill="#F1EFE8" stroke="#B4B2A9" stroke-width="1.2" />
    <rect id="snb-cuello" @click="onClick('b-cuello')" :class="isInherited('b-cuello') ? 'cursor-not-allowed' : 'cursor-pointer'" x="72" y="42" width="16" height="14" rx="3" :fill="zoneFill('b-cuello')" :stroke="zoneStroke('b-cuello')" :stroke-width="zoneSW('b-cuello')" :stroke-dasharray="zoneDash('b-cuello')" :pointer-events="isInherited('b-cuello') ? 'none' : 'auto'" />
    <ellipse id="snb-hombro-izq" @click="onClick('b-hombro-izq')" :class="isInherited('b-hombro-izq') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="48" cy="64" rx="14" ry="10" :fill="zoneFill('b-hombro-izq')" :stroke="zoneStroke('b-hombro-izq')" :stroke-width="zoneSW('b-hombro-izq')" :stroke-dasharray="zoneDash('b-hombro-izq')" :pointer-events="isInherited('b-hombro-izq') ? 'none' : 'auto'" />
    <ellipse id="snb-hombro-der" @click="onClick('b-hombro-der')" :class="isInherited('b-hombro-der') ? 'cursor-not-allowed' : 'cursor-pointer'" cx="112" cy="64" rx="14" ry="10" :fill="zoneFill('b-hombro-der')" :stroke="zoneStroke('b-hombro-der')" :stroke-width="zoneSW('b-hombro-der')" :stroke-dasharray="zoneDash('b-hombro-der')" :pointer-events="isInherited('b-hombro-der') ? 'none' : 'auto'" />
    <rect id="snb-espalda-alta" @click="onClick('b-espalda-alta')" :class="isInherited('b-espalda-alta') ? 'cursor-not-allowed' : 'cursor-pointer'" x="56" y="56" width="48" height="28" rx="4" :fill="zoneFill('b-espalda-alta')" :stroke="zoneStroke('b-espalda-alta')" :stroke-width="zoneSW('b-espalda-alta')" :stroke-dasharray="zoneDash('b-espalda-alta')" :pointer-events="isInherited('b-espalda-alta') ? 'none' : 'auto'" />
    <rect id="snb-lumbar" @click="onClick('b-lumbar')" :class="isInherited('b-lumbar') ? 'cursor-not-allowed' : 'cursor-pointer'" x="58" y="86" width="44" height="32" rx="4" :fill="zoneFill('b-lumbar')" :stroke="zoneStroke('b-lumbar')" :stroke-width="zoneSW('b-lumbar')" :stroke-dasharray="zoneDash('b-lumbar')" :pointer-events="isInherited('b-lumbar') ? 'none' : 'auto'" />
    <rect id="snb-gluteos" @click="onClick('b-gluteos')" class="cursor-pointer" x="54" y="120" width="52" height="26" rx="8" :fill="zoneFill('b-gluteos')" :stroke="zoneStroke('b-gluteos')" :stroke-width="zoneSW('b-gluteos')" />
    <rect id="snb-brazo-izq" @click="onClick('b-brazo-izq')" class="cursor-pointer" x="34" y="70" width="14" height="36" rx="6" :fill="zoneFill('b-brazo-izq')" :stroke="zoneStroke('b-brazo-izq')" :stroke-width="zoneSW('b-brazo-izq')" />
    <rect id="snb-brazo-der" @click="onClick('b-brazo-der')" class="cursor-pointer" x="112" y="70" width="14" height="36" rx="6" :fill="zoneFill('b-brazo-der')" :stroke="zoneStroke('b-brazo-der')" :stroke-width="zoneSW('b-brazo-der')" />
    <rect id="snb-muslo-izq" @click="onClick('b-muslo-izq')" class="cursor-pointer" x="57" y="148" width="20" height="44" rx="8" :fill="zoneFill('b-muslo-izq')" :stroke="zoneStroke('b-muslo-izq')" :stroke-width="zoneSW('b-muslo-izq')" />
    <rect id="snb-muslo-der" @click="onClick('b-muslo-der')" class="cursor-pointer" x="83" y="148" width="20" height="44" rx="8" :fill="zoneFill('b-muslo-der')" :stroke="zoneStroke('b-muslo-der')" :stroke-width="zoneSW('b-muslo-der')" />
    <ellipse id="snb-corva-izq" @click="onClick('b-corva-izq')" class="cursor-pointer" cx="67" cy="198" rx="10" ry="8" :fill="zoneFill('b-corva-izq')" :stroke="zoneStroke('b-corva-izq')" :stroke-width="zoneSW('b-corva-izq')" />
    <ellipse id="snb-corva-der" @click="onClick('b-corva-der')" class="cursor-pointer" cx="93" cy="198" rx="10" ry="8" :fill="zoneFill('b-corva-der')" :stroke="zoneStroke('b-corva-der')" :stroke-width="zoneSW('b-corva-der')" />
    <rect id="snb-gemelo-izq" @click="onClick('b-gemelo-izq')" class="cursor-pointer" x="59" y="208" width="16" height="42" rx="7" :fill="zoneFill('b-gemelo-izq')" :stroke="zoneStroke('b-gemelo-izq')" :stroke-width="zoneSW('b-gemelo-izq')" />
    <rect id="snb-gemelo-der" @click="onClick('b-gemelo-der')" class="cursor-pointer" x="85" y="208" width="16" height="42" rx="7" :fill="zoneFill('b-gemelo-der')" :stroke="zoneStroke('b-gemelo-der')" :stroke-width="zoneSW('b-gemelo-der')" />
    <ellipse id="snb-talon-izq" @click="onClick('b-talon-izq')" class="cursor-pointer" cx="67" cy="256" rx="10" ry="7" :fill="zoneFill('b-talon-izq')" :stroke="zoneStroke('b-talon-izq')" :stroke-width="zoneSW('b-talon-izq')" />
    <ellipse id="snb-talon-der" @click="onClick('b-talon-der')" class="cursor-pointer" cx="93" cy="256" rx="10" ry="7" :fill="zoneFill('b-talon-der')" :stroke="zoneStroke('b-talon-der')" :stroke-width="zoneSW('b-talon-der')" />
    <text x="80" y="275" text-anchor="middle" fill="#9CA3AF" font-size="9">DORSAL</text>
  </svg>
</template>
