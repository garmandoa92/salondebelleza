<script setup>
import { ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import BodyMap from '@/Components/BodyMap.vue'

const props = defineProps({
  clientId: String,
})

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`

const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const isOutdated = ref(false)

const form = ref({
  allergies: [],
  allergies_notes: '',
  medical_conditions: [],
  medical_notes: '',
  current_medications: '',
  contraindications: '',
  avoid_zones: [],
  pressure_preference: 2,
  personal_preferences: [],
  therapist_notes: '',
})

const lastUpdated = ref(null)

// Predefined options
const allergyOptions = [
  'Latex', 'Frutos secos', 'Mariscos', 'Productos quimicos',
  'Perfumes y fragancias', 'Polen', 'Polvo', 'Metales (niquel)',
  'Aceites esenciales', 'Peroxido de hidrogeno',
]

const conditionOptions = [
  'Hipertension', 'Hipotension', 'Diabetes', 'Embarazo',
  'Problemas de piel', 'Marcapasos', 'Epilepsia', 'Osteoporosis',
  'Varices', 'Problemas cardiacos', 'Cancer (en tratamiento)',
  'Enfermedades autoinmunes', 'Tiroides', 'Lesiones recientes',
]

const preferenceOptions = [
  'Musica relajante', 'Silencio total', 'Sin conversacion',
  'Temperatura calida', 'Temperatura fresca', 'Aromas citricos',
  'Aromas florales', 'Sin aromaterapia', 'Luz tenue',
]

const pressureLabels = ['', 'Muy suave', 'Suave', 'Media', 'Fuerte', 'Muy fuerte']

const customAllergy = ref('')
const customCondition = ref('')
const customPreference = ref('')

onMounted(async () => {
  try {
    const { data } = await axios.get(`${base}/clientes/${props.clientId}/ficha-salud`)
    const p = data.profile
    form.value = {
      allergies: p.allergies || [],
      allergies_notes: p.allergies_notes || '',
      medical_conditions: p.medical_conditions || [],
      medical_notes: p.medical_notes || '',
      current_medications: p.current_medications || '',
      contraindications: p.contraindications || '',
      avoid_zones: p.avoid_zones || [],
      pressure_preference: p.pressure_preference ?? 2,
      personal_preferences: p.personal_preferences || [],
      therapist_notes: p.therapist_notes || '',
    }
    isOutdated.value = data.is_outdated
    lastUpdated.value = p.last_updated_by_client
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

function toggleTag(arr, tag) {
  const idx = arr.indexOf(tag)
  if (idx >= 0) arr.splice(idx, 1)
  else arr.push(tag)
}

function addCustomTag(arr, refVal) {
  const val = refVal.value.trim()
  if (val && !arr.includes(val)) {
    arr.push(val)
    refVal.value = ''
  }
}

async function save() {
  saving.value = true
  saved.value = false
  try {
    await axios.put(`${base}/clientes/${props.clientId}/ficha-salud`, form.value)
    saved.value = true
    isOutdated.value = false
    lastUpdated.value = new Date().toISOString()
    setTimeout(() => saved.value = false, 3000)
  } catch (e) {
    console.error(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center py-12">
    <span class="text-sm text-gray-400">Cargando ficha de salud...</span>
  </div>

  <div v-else class="space-y-6">
    <!-- Header con badges -->
    <div class="flex items-center gap-2 flex-wrap">
      <span v-if="form.allergies.length" class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">
        {{ form.allergies.length }} alergia{{ form.allergies.length > 1 ? 's' : '' }}
      </span>
      <span v-if="form.medical_conditions.length" class="px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-700">
        {{ form.medical_conditions.length }} condicion{{ form.medical_conditions.length > 1 ? 'es' : '' }}
      </span>
      <span v-if="form.avoid_zones.length" class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">
        {{ form.avoid_zones.length }} zona{{ form.avoid_zones.length > 1 ? 's' : '' }} a evitar
      </span>
      <span v-if="isOutdated" class="px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-700">
        Ficha desactualizada (+6 meses)
      </span>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
      <!-- Columna izquierda -->
      <div class="space-y-5">
        <!-- Alergias -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Alergias</label>
          <div class="flex flex-wrap gap-1.5 mb-2">
            <button v-for="opt in allergyOptions" :key="opt" type="button" @click="toggleTag(form.allergies, opt)"
              class="px-2.5 py-1 text-xs rounded-full border transition-colors"
              :class="form.allergies.includes(opt)
                ? 'bg-red-100 text-red-700 border-red-300'
                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              {{ opt }}
            </button>
          </div>
          <div class="flex gap-2">
            <input v-model="customAllergy" type="text" placeholder="Agregar otra..."
              class="flex-1 border border-gray-200 rounded px-2 py-1 text-sm" @keyup.enter="addCustomTag(form.allergies, customAllergy)" />
            <button type="button" @click="addCustomTag(form.allergies, customAllergy)"
              class="px-3 py-1 text-xs bg-gray-100 rounded hover:bg-gray-200">+</button>
          </div>
          <!-- Custom tags -->
          <div v-if="form.allergies.filter(a => !allergyOptions.includes(a)).length" class="flex flex-wrap gap-1 mt-2">
            <span v-for="a in form.allergies.filter(a => !allergyOptions.includes(a))" :key="a"
              class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 flex items-center gap-1">
              {{ a }}
              <button type="button" @click="form.allergies = form.allergies.filter(x => x !== a)" class="text-red-400 hover:text-red-600">&times;</button>
            </span>
          </div>
          <textarea v-model="form.allergies_notes" rows="2" placeholder="Notas sobre alergias..."
            class="w-full mt-2 border border-gray-200 rounded px-2 py-1.5 text-sm" />
        </div>

        <!-- Condiciones medicas -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Condiciones medicas</label>
          <div class="flex flex-wrap gap-1.5 mb-2">
            <button v-for="opt in conditionOptions" :key="opt" type="button" @click="toggleTag(form.medical_conditions, opt)"
              class="px-2.5 py-1 text-xs rounded-full border transition-colors"
              :class="form.medical_conditions.includes(opt)
                ? 'bg-amber-100 text-amber-700 border-amber-300'
                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              {{ opt }}
            </button>
          </div>
          <textarea v-model="form.medical_notes" rows="2" placeholder="Notas medicas adicionales..."
            class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" />
        </div>

        <!-- Medicamentos -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Medicamentos actuales</label>
          <textarea v-model="form.current_medications" rows="2" placeholder="Lista de medicamentos..."
            class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" />
        </div>

        <!-- Contraindicaciones -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Contraindicaciones generales</label>
          <textarea v-model="form.contraindications" rows="2" placeholder="Contraindicaciones a tener en cuenta..."
            class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" />
        </div>

        <!-- Presion -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Preferencia de presion: <span class="text-[var(--color-primary)]">{{ pressureLabels[form.pressure_preference] }}</span>
          </label>
          <input v-model.number="form.pressure_preference" type="range" min="1" max="5" step="1"
            class="w-full accent-[var(--color-primary)]" />
          <div class="flex justify-between text-[10px] text-gray-400 mt-1">
            <span>Muy suave</span><span>Suave</span><span>Media</span><span>Fuerte</span><span>Muy fuerte</span>
          </div>
        </div>

        <!-- Preferencias personales -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Preferencias personales</label>
          <div class="flex flex-wrap gap-1.5 mb-2">
            <button v-for="opt in preferenceOptions" :key="opt" type="button" @click="toggleTag(form.personal_preferences, opt)"
              class="px-2.5 py-1 text-xs rounded-full border transition-colors"
              :class="form.personal_preferences.includes(opt)
                ? 'bg-blue-100 text-blue-700 border-blue-300'
                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              {{ opt }}
            </button>
          </div>
          <div class="flex gap-2">
            <input v-model="customPreference" type="text" placeholder="Agregar otra..."
              class="flex-1 border border-gray-200 rounded px-2 py-1 text-sm" @keyup.enter="addCustomTag(form.personal_preferences, customPreference)" />
            <button type="button" @click="addCustomTag(form.personal_preferences, customPreference)"
              class="px-3 py-1 text-xs bg-gray-100 rounded hover:bg-gray-200">+</button>
          </div>
        </div>
      </div>

      <!-- Columna derecha -->
      <div class="space-y-5">
        <!-- Mapa corporal -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Zonas a evitar (mapa corporal)</label>
          <BodyMap v-model="form.avoid_zones" />
        </div>

        <!-- Notas terapeuta -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Notas privadas del terapeuta</label>
          <textarea v-model="form.therapist_notes" rows="4" placeholder="Observaciones internas..."
            class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" />
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
      <div class="text-xs text-gray-400">
        <span v-if="lastUpdated">Actualizada: {{ new Date(lastUpdated).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
        <span v-else>Sin actualizaciones previas</span>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="saved" class="text-xs text-green-600 font-medium">Guardado</span>
        <button type="button" @click="save" :disabled="saving"
          class="px-5 py-2 bg-[var(--color-primary)] text-white rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-50">
          {{ saving ? 'Guardando...' : 'Guardar ficha' }}
        </button>
      </div>
    </div>
  </div>
</template>
