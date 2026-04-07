<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import SessionNoteForm from '@/Components/SessionNoteForm.vue'

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
const sessionNote = ref(null)
const inheritedAvoidZones = ref([])
const appointment = ref(null)

const techniqueOptions = [
  'Effleurage', 'Petrissage', 'Friccion', 'Tapotement', 'Vibracion',
  'Puntos gatillo', 'Drenaje linfatico', 'Piedras calientes', 'Reflexologia',
  'Aromaterapia', 'Bambuterapia', 'Ventosas', 'Masaje con velas', 'Masaje tailandes', 'Shiatsu',
]

watch(() => props.open, async (val) => {
  if (val && props.appointmentId) {
    loading.value = true
    try {
      const { data } = await axios.get(`${base}/citas/${props.appointmentId}/nota-sesion`)
      sessionNote.value = data
      inheritedAvoidZones.value = data.inherited_avoid_zones || []
      appointment.value = { id: props.appointmentId }
    } catch (e) {
      console.error(e)
    } finally {
      loading.value = false
    }
  }
})

function onSaved() {
  emit('saved')
  setTimeout(() => emit('close'), 1000)
}
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

          <div v-else class="flex-1 overflow-y-auto p-5">
            <SessionNoteForm
              :appointment="appointment"
              :session-note="sessionNote"
              :inherited-avoid-zones="inheritedAvoidZones"
              :techniques="techniqueOptions"
              @saved="onSaved"
            />
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
