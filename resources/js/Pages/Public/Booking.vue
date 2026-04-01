<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent } from '@/components/ui/card'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import axios from 'axios'

defineOptions({ layout: PublicLayout })

const page = usePage()
const tenantId = page.props.tenant?.id || page.props.tenant?.slug
const base = `/salon/${tenantId}`

const step = ref(1)
const categories = ref([])
const stylists = ref([])
const availableSlots = ref([])
const loadingSlots = ref(false)
const saving = ref(false)
const confirmation = ref(null)

// Selections
const selectedService = ref(null)
const selectedStylist = ref(null)
const selectedDate = ref('')
const selectedTime = ref('')
const clientData = ref({ first_name: '', last_name: '', phone: '', email: '', notes: '' })
const acceptedPolicy = ref(false)

// Load services on mount
const loadServices = async () => {
  const { data } = await axios.get(`${base}/reservar/services`)
  categories.value = data
}
loadServices()

// Load stylists when service selected
watch(selectedService, async (svc) => {
  if (!svc) return
  const { data } = await axios.get(`${base}/reservar/stylists`, { params: { service_id: svc.id } })
  stylists.value = data
})

// Load availability when stylist + date selected
watch([selectedStylist, selectedDate], async () => {
  if (!selectedStylist.value || !selectedDate.value || !selectedService.value) return
  loadingSlots.value = true
  try {
    const { data } = await axios.get(`${base}/reservar/availability`, {
      params: { service_id: selectedService.value.id, stylist_id: selectedStylist.value.id, date: selectedDate.value },
    })
    availableSlots.value = data
  } finally { loadingSlots.value = false }
})

const endTime = computed(() => {
  if (!selectedTime.value || !selectedService.value) return ''
  const [h, m] = selectedTime.value.split(':').map(Number)
  const total = h * 60 + m + selectedService.value.duration_minutes
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
})

const canSubmit = computed(() =>
  selectedService.value && selectedStylist.value && selectedTime.value &&
  clientData.value.first_name && clientData.value.last_name &&
  clientData.value.phone && acceptedPolicy.value
)

const submit = async () => {
  if (!canSubmit.value) return
  saving.value = true
  try {
    const { data } = await axios.post(`${base}/reservar/appointments`, {
      service_id: selectedService.value.id,
      stylist_id: selectedStylist.value.id,
      starts_at: `${selectedDate.value}T${selectedTime.value}:00`,
      ...clientData.value,
    })
    confirmation.value = data.appointment
    step.value = 5
  } finally { saving.value = false }
}

const minDate = computed(() => new Date().toISOString().slice(0, 10))

const formatDuration = (mins) => {
  if (mins < 60) return `${mins}min`
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m ? `${h}h ${m}min` : `${h}h`
}
</script>

<template>
  <Head title="Reservar cita" />

  <div class="max-w-lg mx-auto px-4 py-8">
    <!-- Progress bar -->
    <div v-if="step < 5" class="flex mb-8">
      <div
        v-for="s in [{ n: 1, l: 'Servicio' }, { n: 2, l: 'Estilista' }, { n: 3, l: 'Horario' }, { n: 4, l: 'Datos' }]"
        :key="s.n"
        :class="['flex-1 text-center text-xs pb-2 border-b-2 font-medium transition-colors',
          step === s.n ? 'border-primary text-primary' : step > s.n ? 'border-green-500 text-green-600' : 'border-gray-200 text-gray-400']"
      >{{ s.l }}</div>
    </div>

    <!-- Step 1: Service -->
    <div v-if="step === 1" class="space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">Elige tu servicio</h2>
      <div v-for="cat in categories" :key="cat.id">
        <div v-if="cat.services?.length" class="space-y-2 mb-4">
          <p class="text-xs font-medium text-gray-500 flex items-center gap-1">
            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: cat.color }" />
            {{ cat.name }}
          </p>
          <button
            v-for="svc in cat.services"
            :key="svc.id"
            @click="selectedService = svc; step = 2"
            :class="['w-full text-left border rounded-xl p-4 transition-all',
              selectedService?.id === svc.id ? 'border-primary bg-primary/5 shadow-sm' : 'hover:bg-gray-50']"
          >
            <div class="flex justify-between items-center">
              <div>
                <p class="font-medium text-gray-900">{{ svc.name }}</p>
                <p class="text-sm text-gray-500">{{ formatDuration(svc.duration_minutes) }}</p>
              </div>
              <p class="font-semibold text-gray-900">${{ Number(svc.base_price).toFixed(2) }}</p>
            </div>
          </button>
        </div>
      </div>
    </div>

    <!-- Step 2: Stylist -->
    <div v-if="step === 2" class="space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">Elige tu estilista</h2>

      <button
        @click="selectedStylist = stylists[0]; step = 3"
        class="w-full text-left border rounded-xl p-4 hover:bg-gray-50"
      >
        <p class="font-medium text-gray-900">Sin preferencia</p>
        <p class="text-sm text-gray-500">Cualquier estilista disponible</p>
      </button>

      <button
        v-for="s in stylists"
        :key="s.id"
        @click="selectedStylist = s; step = 3"
        class="w-full text-left border rounded-xl p-4 hover:bg-gray-50 flex items-center gap-3"
      >
        <div
          class="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold text-sm"
          :style="{ backgroundColor: s.color }"
        >{{ s.name.split(' ').map(n => n[0]).join('') }}</div>
        <div>
          <p class="font-medium text-gray-900">{{ s.name }}</p>
          <p v-if="s.bio" class="text-sm text-gray-500 line-clamp-1">{{ s.bio }}</p>
        </div>
      </button>

      <button @click="step = 1" class="text-sm text-gray-500 hover:underline">&larr; Cambiar servicio</button>
    </div>

    <!-- Step 3: Date/Time -->
    <div v-if="step === 3" class="space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">Elige fecha y hora</h2>

      <div class="space-y-2">
        <Label>Fecha</Label>
        <Input v-model="selectedDate" type="date" :min="minDate" />
      </div>

      <div v-if="selectedDate" class="space-y-2">
        <Label>Horarios disponibles</Label>
        <div v-if="loadingSlots" class="text-sm text-gray-400 py-4 text-center">Cargando...</div>
        <div v-else-if="availableSlots.length" class="grid grid-cols-4 gap-2">
          <button
            v-for="slot in availableSlots"
            :key="slot.time"
            @click="selectedTime = slot.time"
            :class="['py-2.5 text-sm rounded-lg border font-medium transition-all',
              selectedTime === slot.time ? 'bg-primary text-white border-primary shadow-sm' : 'hover:bg-gray-50']"
          >{{ slot.time }}</button>
        </div>
        <p v-else class="text-sm text-gray-400 py-4 text-center">No hay horarios disponibles este dia</p>

        <p v-if="selectedTime" class="text-xs text-gray-500 text-center">
          {{ selectedTime }} — {{ endTime }} ({{ formatDuration(selectedService.duration_minutes) }})
        </p>
      </div>

      <div class="flex justify-between pt-2">
        <button @click="step = 2" class="text-sm text-gray-500 hover:underline">&larr; Cambiar estilista</button>
        <Button :disabled="!selectedTime" @click="step = 4">Siguiente</Button>
      </div>
    </div>

    <!-- Step 4: Client data -->
    <div v-if="step === 4" class="space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">Tus datos</h2>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <Label>Nombre</Label>
          <Input v-model="clientData.first_name" required />
        </div>
        <div class="space-y-1">
          <Label>Apellido</Label>
          <Input v-model="clientData.last_name" required />
        </div>
      </div>

      <div class="space-y-1">
        <Label>Telefono</Label>
        <Input v-model="clientData.phone" type="tel" placeholder="09XXXXXXXX" required />
      </div>

      <div class="space-y-1">
        <Label>Email (opcional)</Label>
        <Input v-model="clientData.email" type="email" />
      </div>

      <div class="space-y-1">
        <Label>Notas (opcional)</Label>
        <textarea v-model="clientData.notes" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" rows="2" placeholder="Algo que debamos saber..." />
      </div>

      <!-- Summary -->
      <Card>
        <CardContent class="pt-4 space-y-1 text-sm">
          <p><span class="text-gray-500">Servicio:</span> <span class="font-medium">{{ selectedService?.name }}</span></p>
          <p><span class="text-gray-500">Estilista:</span> <span class="font-medium">{{ selectedStylist?.name }}</span></p>
          <p><span class="text-gray-500">Fecha:</span> <span class="font-medium">{{ selectedDate }}</span></p>
          <p><span class="text-gray-500">Hora:</span> <span class="font-medium">{{ selectedTime }} — {{ endTime }}</span></p>
          <p><span class="text-gray-500">Precio:</span> <span class="font-semibold">${{ Number(selectedService?.base_price || 0).toFixed(2) }}</span></p>
        </CardContent>
      </Card>

      <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" v-model="acceptedPolicy" class="rounded border-gray-300 mt-0.5" />
        <span class="text-xs text-gray-500">Acepto la politica de cancelacion. Puedo cancelar mi cita hasta 2 horas antes sin costo.</span>
      </label>

      <div class="flex justify-between pt-2">
        <button @click="step = 3" class="text-sm text-gray-500 hover:underline">&larr; Cambiar horario</button>
        <Button :disabled="!canSubmit || saving" @click="submit">
          {{ saving ? 'Reservando...' : 'Confirmar cita' }}
        </Button>
      </div>
    </div>

    <!-- Step 5: Confirmation -->
    <div v-if="step === 5 && confirmation" class="text-center space-y-6 py-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>

      <div>
        <h2 class="text-xl font-bold text-gray-900">Cita reservada</h2>
        <p class="text-gray-500 mt-1">Te enviaremos una confirmacion por WhatsApp</p>
      </div>

      <Card>
        <CardContent class="pt-4 space-y-2 text-sm text-left">
          <p><span class="text-gray-500">Servicio:</span> <span class="font-medium">{{ confirmation.service }}</span></p>
          <p><span class="text-gray-500">Estilista:</span> <span class="font-medium">{{ confirmation.stylist }}</span></p>
          <p><span class="text-gray-500">Fecha:</span> <span class="font-medium">{{ confirmation.date }}</span></p>
          <p><span class="text-gray-500">Hora:</span> <span class="font-medium">{{ confirmation.time }}</span> ({{ confirmation.duration }}min)</p>
        </CardContent>
      </Card>

      <p class="text-xs text-gray-400">Puedes cancelar tu cita hasta 2 horas antes sin costo</p>
    </div>
  </div>
</template>
