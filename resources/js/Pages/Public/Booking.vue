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

const selectedService = ref(null)
const selectedStylist = ref(null)
const selectedDate = ref('')
const selectedTime = ref('')
const clientData = ref({ first_name: '', last_name: '', phone: '', email: '', notes: '' })
const acceptedPolicy = ref(false)

const loadServices = async () => {
  const { data } = await axios.get(`${base}/reservar/services`)
  categories.value = data
}
loadServices()

watch(selectedService, async (svc) => {
  if (!svc) return
  const { data } = await axios.get(`${base}/reservar/stylists`, { params: { service_id: svc.id } })
  stylists.value = data
})

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

const steps = [
  { n: 1, label: 'Servicio' },
  { n: 2, label: 'Estilista' },
  { n: 3, label: 'Horario' },
  { n: 4, label: 'Confirmar' },
]

// Step 1: search + accordion
const serviceSearch = ref('')
const openCategory = ref(null)

const filteredCategories = computed(() => {
  const q = serviceSearch.value.toLowerCase().trim()
  if (!q) return categories.value.filter(c => c.services?.length)
  return categories.value
    .map(c => ({
      ...c,
      services: (c.services || []).filter(s => s.name.toLowerCase().includes(q)),
    }))
    .filter(c => c.services.length)
})

// Open first category by default when loaded
watch(categories, (cats) => {
  if (cats.length && !openCategory.value) openCategory.value = cats[0].id
})

const toggleCategory = (id) => {
  openCategory.value = openCategory.value === id ? null : id
}

// When searching, open all matching categories
watch(serviceSearch, (q) => {
  if (q.trim()) openCategory.value = '__all__'
  else if (categories.value.length) openCategory.value = categories.value[0].id
})

const isCatOpen = (id) => openCategory.value === '__all__' || openCategory.value === id
</script>

<template>
  <Head title="Reservar cita" />

  <div class="max-w-lg mx-auto px-4 py-6">

    <!-- Hero text -->
    <div v-if="step < 5" class="text-center mb-6">
      <h1 class="text-[22px] font-bold text-gray-900">Reserva tu cita</h1>
      <p class="text-sm text-gray-500 mt-1">Selecciona servicio, estilista y horario</p>
    </div>

    <!-- Progress -->
    <div v-if="step < 5" class="flex items-center justify-between mb-8 px-2">
      <template v-for="(s, i) in steps" :key="s.n">
        <div class="flex flex-col items-center gap-1">
          <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all',
            step === s.n ? 'border-[var(--color-primary)] text-white' : step > s.n ? 'border-green-500 bg-green-500 text-white' : 'border-gray-200 text-gray-400 bg-white']"
            :style="step === s.n ? { backgroundColor: 'var(--color-primary)', borderColor: 'var(--color-primary)' } : {}">
            <svg v-if="step > s.n" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span v-else>{{ s.n }}</span>
          </div>
          <span :class="['text-[10px] font-medium', step >= s.n ? 'text-gray-700' : 'text-gray-400']">{{ s.label }}</span>
        </div>
        <div v-if="i < steps.length - 1" :class="['flex-1 h-0.5 mx-1 -mt-4 rounded-full transition-colors', step > s.n ? 'bg-green-500' : 'bg-gray-200']" />
      </template>
    </div>

    <!-- ===== STEP 1: SERVICE ===== -->
    <div v-if="step === 1" class="space-y-3">
      <!-- Search -->
      <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <Input v-model="serviceSearch" placeholder="Buscar servicio..." class="pl-9 bg-white" />
      </div>

      <!-- Accordions -->
      <div v-for="cat in filteredCategories" :key="cat.id" class="bg-white border border-gray-100 rounded-xl overflow-hidden">
        <!-- Category header -->
        <button @click="toggleCategory(cat.id)" class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: cat.color }" />
            <span class="text-sm font-semibold text-gray-900">{{ cat.name }}</span>
            <span class="text-[10px] font-medium text-gray-400 bg-gray-100 rounded-full px-1.5 py-0.5">{{ cat.services.length }}</span>
          </div>
          <svg :class="['w-4 h-4 text-gray-400 transition-transform', isCatOpen(cat.id) && 'rotate-180']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>

        <!-- Services (collapsible) -->
        <div v-if="isCatOpen(cat.id)" class="border-t border-gray-50">
          <button
            v-for="svc in cat.services"
            :key="svc.id"
            @click="selectedService = svc; serviceSearch = ''; step = 2"
            class="w-full text-left px-4 py-3 border-b border-gray-50 last:border-0 hover:bg-[#F4F9F7] transition-all group"
          >
            <div class="flex justify-between items-center">
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-[14px] text-gray-900 group-hover:text-[var(--color-primary)] transition-colors">{{ svc.name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ formatDuration(svc.duration_minutes) }}</p>
              </div>
              <div class="flex items-center gap-2 shrink-0 ml-3">
                <p class="font-bold text-[15px] text-gray-900">${{ Number(svc.base_price).toFixed(2) }}</p>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-[var(--color-primary)] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- No results -->
      <p v-if="serviceSearch && !filteredCategories.length" class="text-sm text-gray-400 text-center py-6">No se encontraron servicios para "{{ serviceSearch }}"</p>
    </div>

    <!-- ===== STEP 2: STYLIST ===== -->
    <div v-if="step === 2" class="space-y-3">
      <!-- Selected service pill -->
      <div class="flex items-center gap-2 bg-white border border-gray-100 rounded-lg px-3 py-2 mb-2">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span class="text-sm text-gray-600">{{ selectedService?.name }}</span>
        <span class="text-xs text-gray-400">· {{ formatDuration(selectedService?.duration_minutes) }} · ${{ Number(selectedService?.base_price || 0).toFixed(2) }}</span>
        <button @click="step = 1" class="ml-auto text-xs text-gray-400 hover:text-gray-600">Cambiar</button>
      </div>

      <button
        @click="selectedStylist = stylists[0]; step = 3"
        class="w-full text-left bg-white border border-gray-100 rounded-xl p-4 hover:border-gray-300 hover:shadow-sm transition-all"
      >
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          </div>
          <div>
            <p class="font-semibold text-gray-900">Sin preferencia</p>
            <p class="text-xs text-gray-500">Cualquier estilista disponible</p>
          </div>
        </div>
      </button>

      <button
        v-for="s in stylists"
        :key="s.id"
        @click="selectedStylist = s; step = 3"
        class="w-full text-left bg-white border border-gray-100 rounded-xl p-4 hover:border-gray-300 hover:shadow-sm transition-all"
      >
        <div class="flex items-center gap-3">
          <img v-if="s.photo_path" :src="`/storage/${s.photo_path}`" class="w-12 h-12 rounded-full object-cover" />
          <div v-else class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm" :style="{ backgroundColor: s.color }">
            {{ s.name.split(' ').map(n => n[0]).join('') }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-gray-900">{{ s.name }}</p>
            <p v-if="s.bio" class="text-xs text-gray-500 line-clamp-1 mt-0.5">{{ s.bio }}</p>
          </div>
        </div>
      </button>
    </div>

    <!-- ===== STEP 3: DATE/TIME ===== -->
    <div v-if="step === 3" class="space-y-4">
      <!-- Selected service + stylist pills -->
      <div class="space-y-1.5 mb-2">
        <div class="flex items-center gap-2 bg-white border border-gray-100 rounded-lg px-3 py-2">
          <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span class="text-sm text-gray-600">{{ selectedService?.name }}</span>
          <span class="text-xs text-gray-400">· ${{ Number(selectedService?.base_price || 0).toFixed(2) }}</span>
        </div>
        <div class="flex items-center gap-2 bg-white border border-gray-100 rounded-lg px-3 py-2">
          <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span class="text-sm text-gray-600">{{ selectedStylist?.name }}</span>
          <button @click="step = 2" class="ml-auto text-xs text-gray-400 hover:text-gray-600">Cambiar</button>
        </div>
      </div>

      <div class="bg-white border border-gray-100 rounded-xl p-4 space-y-4">
        <div class="space-y-1.5">
          <Label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha</Label>
          <Input v-model="selectedDate" type="date" :min="minDate" class="text-center font-medium" />
        </div>

        <div v-if="selectedDate" class="space-y-2">
          <Label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Horarios disponibles</Label>
          <div v-if="loadingSlots" class="text-sm text-gray-400 py-6 text-center">
            <div class="w-6 h-6 border-2 border-gray-300 border-t-[var(--color-primary)] rounded-full animate-spin mx-auto mb-2" />
            Buscando horarios...
          </div>
          <div v-else-if="availableSlots.length" class="grid grid-cols-4 gap-2">
            <button
              v-for="slot in availableSlots"
              :key="slot.time"
              @click="selectedTime = slot.time"
              :class="['py-2.5 text-sm rounded-lg border-2 font-semibold transition-all',
                selectedTime === slot.time
                  ? 'text-white shadow-sm'
                  : 'border-gray-100 bg-white text-gray-700 hover:border-gray-300']"
              :style="selectedTime === slot.time ? { backgroundColor: 'var(--color-primary)', borderColor: 'var(--color-primary)' } : {}"
            >{{ slot.time }}</button>
          </div>
          <p v-else class="text-sm text-gray-400 py-6 text-center">No hay horarios disponibles este dia</p>
        </div>

        <div v-if="selectedTime" class="text-center pt-2 border-t border-gray-50">
          <p class="text-sm font-medium text-gray-900">{{ selectedTime }} — {{ endTime }}</p>
          <p class="text-xs text-gray-500">{{ formatDuration(selectedService.duration_minutes) }} con {{ selectedStylist?.name }}</p>
        </div>
      </div>

      <Button :disabled="!selectedTime" @click="step = 4" class="w-full py-3 text-sm font-semibold">
        Continuar
      </Button>
    </div>

    <!-- ===== STEP 4: CLIENT DATA + CONFIRM ===== -->
    <div v-if="step === 4" class="space-y-4">
      <!-- Summary ticket -->
      <div class="bg-white border border-gray-100 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-50" style="background: var(--color-primary); color: #fff;">
          <p class="text-xs font-semibold uppercase tracking-wider opacity-80">Resumen de tu cita</p>
        </div>
        <div class="p-4 space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-gray-500">Servicio</span><span class="font-semibold text-gray-900">{{ selectedService?.name }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Estilista</span><span class="font-medium text-gray-900">{{ selectedStylist?.name }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Fecha</span><span class="font-medium text-gray-900">{{ new Date(selectedDate + 'T12:00').toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long' }) }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Hora</span><span class="font-medium text-gray-900">{{ selectedTime }} — {{ endTime }}</span></div>
          <div class="flex justify-between border-t border-gray-50 pt-2 mt-1"><span class="text-gray-500">Precio</span><span class="font-bold text-lg" style="color: var(--color-primary);">${{ Number(selectedService?.base_price || 0).toFixed(2) }}</span></div>
        </div>
      </div>

      <!-- Client form -->
      <div class="bg-white border border-gray-100 rounded-xl p-4 space-y-3">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Tus datos</p>
        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <Label class="text-xs text-gray-500">Nombre</Label>
            <Input v-model="clientData.first_name" required />
          </div>
          <div class="space-y-1">
            <Label class="text-xs text-gray-500">Apellido</Label>
            <Input v-model="clientData.last_name" required />
          </div>
        </div>
        <div class="space-y-1">
          <Label class="text-xs text-gray-500">Telefono</Label>
          <Input v-model="clientData.phone" type="tel" placeholder="09XXXXXXXX" required />
        </div>
        <div class="space-y-1">
          <Label class="text-xs text-gray-500">Email (opcional)</Label>
          <Input v-model="clientData.email" type="email" placeholder="correo@ejemplo.com" />
        </div>
        <div class="space-y-1">
          <Label class="text-xs text-gray-500">Notas (opcional)</Label>
          <textarea v-model="clientData.notes" class="flex min-h-[50px] w-full rounded-lg border border-input bg-transparent px-3 py-2 text-sm" rows="2" placeholder="Algo que debamos saber..." />
        </div>
      </div>

      <label class="flex items-start gap-2.5 cursor-pointer px-1">
        <input type="checkbox" v-model="acceptedPolicy" class="rounded border-gray-300 mt-0.5" style="accent-color: var(--color-primary);" />
        <span class="text-xs text-gray-500 leading-relaxed">Acepto la politica de cancelacion. Puedo cancelar mi cita hasta 2 horas antes sin costo.</span>
      </label>

      <div class="flex gap-3 pt-1">
        <button @click="step = 3" class="flex-1 py-3 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">Atras</button>
        <Button :disabled="!canSubmit || saving" @click="submit" class="flex-1 py-3 text-sm font-semibold">
          {{ saving ? 'Reservando...' : 'Confirmar cita' }}
        </Button>
      </div>
    </div>

    <!-- ===== STEP 5: CONFIRMATION ===== -->
    <div v-if="step === 5 && confirmation" class="text-center space-y-6 py-8">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-full" style="background-color: var(--color-primary); opacity: 0.1;">
        <div class="absolute w-20 h-20 rounded-full flex items-center justify-center">
          <svg class="w-10 h-10" style="color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
      </div>

      <div>
        <h2 class="text-xl font-bold text-gray-900">Cita reservada</h2>
        <p class="text-sm text-gray-500 mt-1">Te enviaremos una confirmacion por WhatsApp</p>
      </div>

      <div class="bg-white border border-gray-100 rounded-xl overflow-hidden text-left">
        <div class="px-4 py-3 border-b border-gray-50" style="background: var(--color-primary); color: #fff;">
          <p class="text-xs font-semibold uppercase tracking-wider opacity-80">Detalles de tu cita</p>
        </div>
        <div class="p-4 space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-gray-500">Servicio</span><span class="font-semibold">{{ confirmation.service }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Estilista</span><span class="font-medium">{{ confirmation.stylist }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Fecha</span><span class="font-medium">{{ confirmation.date }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Hora</span><span class="font-medium">{{ confirmation.time }} ({{ confirmation.duration }}min)</span></div>
        </div>
      </div>

      <p class="text-xs text-gray-400">Puedes cancelar tu cita hasta 2 horas antes sin costo</p>
    </div>
  </div>
</template>
