<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import axios from 'axios'

const props = defineProps({
  open: Boolean,
  prefill: { type: Object, default: () => ({}) },
  stylists: Array,
  categories: Array,
})
const emit = defineEmits(['close', 'created'])

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const step = ref(1)
const saving = ref(false)

// Step 1 - Client
const clientSearch = ref('')
const clientResults = ref([])
const selectedClient = ref(null)
const showNewClient = ref(false)
const newClient = ref({ first_name: '', last_name: '', phone: '', email: '', source: 'walk_in' })
let searchTimer = null

watch(clientSearch, (val) => {
  clearTimeout(searchTimer)
  if (val.length < 2) { clientResults.value = []; return }
  searchTimer = setTimeout(async () => {
    const { data } = await axios.get(`${base}/agenda/search-clients`, { params: { q: val } })
    clientResults.value = data
  }, 300)
})

const selectClient = (c) => {
  selectedClient.value = c
  clientSearch.value = ''
  clientResults.value = []
}

const createClient = async () => {
  const { data } = await axios.post(`${base}/agenda/store-client`, newClient.value)
  selectedClient.value = data
  showNewClient.value = false
  newClient.value = { first_name: '', last_name: '', phone: '', email: '', source: 'walk_in' }
}

// Step 2 - Service OR Package
const step2Tab = ref('service')
const selectedService = ref(null)

// Package data
const clientActivePackages = ref([])
const availablePackages = ref([])
const loadingPackages = ref(false)
const selectedPackageItem = ref(null) // ClientPackageItem selected from active package
const purchasePackageId = ref(null)   // Package to buy

// Mode tracking for Step 4 summary
const appointmentMode = ref('service') // 'service' | 'package_use' | 'package_buy'

// Load packages when client is selected and step 2 opens
const loadPackages = async () => {
  if (!selectedClient.value?.id) return
  loadingPackages.value = true
  try {
    const { data } = await axios.get(`${base}/packages/for-appointment`, {
      params: { client_id: selectedClient.value.id },
    })
    clientActivePackages.value = data.active || []
    availablePackages.value = data.available || []
  } catch { }
  finally { loadingPackages.value = false }
}

watch(step, (s) => {
  if (s === 2 && selectedClient.value?.id) loadPackages()
})

const hasPackageTab = computed(() =>
  clientActivePackages.value.length > 0 || availablePackages.value.length > 0
)

// Select a service from an active package item
const selectFromPackage = (pkg, item) => {
  // Find the actual service from categories to get duration
  let svc = null
  for (const cat of props.categories) {
    svc = cat.services?.find(s => s.id === item.service_id)
    if (svc) break
  }
  if (!svc) {
    // Fallback: create a minimal service object
    svc = { id: item.service_id, name: item.service_name, base_price: 0, duration_minutes: 30 }
  }
  selectedService.value = svc
  selectedPackageItem.value = { ...item, package_name: pkg.package_name, package_id: pkg.id, expires_at: pkg.expires_at }
  purchasePackageId.value = null
  appointmentMode.value = 'package_use'
}

// Select a package to buy (first session today)
const selectBuyPackage = (pkg) => {
  // Use the first service in the package items
  const firstItem = pkg.items[0]
  let svc = null
  for (const cat of props.categories) {
    svc = cat.services?.find(s => s.id === firstItem.service_id)
    if (svc) break
  }
  if (!svc) {
    svc = { id: firstItem.service_id, name: firstItem.service_name, base_price: Number(pkg.price), duration_minutes: 30 }
  }
  selectedService.value = { ...svc, base_price: Number(pkg.price) }
  purchasePackageId.value = pkg.id
  selectedPackageItem.value = null
  appointmentMode.value = 'package_buy'
}

// Select a normal service
const selectNormalService = (svc) => {
  selectedService.value = svc
  selectedPackageItem.value = null
  purchasePackageId.value = null
  appointmentMode.value = 'service'
}

// Step 3 - Schedule
const selectedStylist = ref(props.prefill?.stylist_id || '')
const selectedDate = ref(props.prefill?.starts_at ? new Date(props.prefill.starts_at).toISOString().slice(0, 10) : new Date().toISOString().slice(0, 10))
const selectedTime = ref('')
const availableSlots = ref([])
const loadingSlots = ref(false)

watch([selectedStylist, selectedDate, selectedService], async () => {
  if (!selectedStylist.value || !selectedService.value || !selectedDate.value) return
  loadingSlots.value = true
  try {
    const { data } = await axios.get(`${base}/agenda/availability`, {
      params: { stylist_id: selectedStylist.value, service_id: selectedService.value.id, date: selectedDate.value },
    })
    availableSlots.value = data
  } finally { loadingSlots.value = false }
})

// Step 4 - Notes
const notes = ref('')

// Pre-fill from calendar click
watch(() => props.prefill, (pf) => {
  if (pf?.stylist_id) selectedStylist.value = pf.stylist_id
  if (pf?.starts_at) {
    const d = new Date(pf.starts_at)
    selectedDate.value = d.toISOString().slice(0, 10)
    selectedTime.value = d.toTimeString().slice(0, 5)
  }
}, { immediate: true })

const endTime = computed(() => {
  if (!selectedTime.value || !selectedService.value) return ''
  const [h, m] = selectedTime.value.split(':').map(Number)
  const total = h * 60 + m + selectedService.value.duration_minutes
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
})

const canSubmit = computed(() => selectedClient.value && selectedService.value && selectedStylist.value && selectedTime.value)

const summaryPrice = computed(() => {
  if (appointmentMode.value === 'package_use') return '$0.00'
  return `$${Number(selectedService.value?.base_price || 0).toFixed(2)}`
})

const submit = async () => {
  if (!canSubmit.value) return
  saving.value = true
  try {
    const payload = {
      client_id: selectedClient.value.id,
      service_id: selectedService.value.id,
      stylist_id: selectedStylist.value,
      starts_at: `${selectedDate.value}T${selectedTime.value}:00`,
      notes: notes.value || null,
      source: 'manual',
    }
    if (appointmentMode.value === 'package_use' && selectedPackageItem.value) {
      payload.client_package_item_id = selectedPackageItem.value.id
    }
    if (appointmentMode.value === 'package_buy' && purchasePackageId.value) {
      payload.purchase_package_id = purchasePackageId.value
    }
    await axios.post(`${base}/agenda/appointments`, payload)
    emit('created')
    resetForm()
  } finally { saving.value = false }
}

const resetForm = () => {
  step.value = 1
  step2Tab.value = 'service'
  selectedClient.value = null
  selectedService.value = null
  selectedTime.value = ''
  notes.value = ''
  clientSearch.value = ''
  selectedPackageItem.value = null
  purchasePackageId.value = null
  appointmentMode.value = 'service'
  clientActivePackages.value = []
  availablePackages.value = []
}

const close = () => { resetForm(); emit('close') }
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="close">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
      <!-- Progress -->
      <div class="flex border-b">
        <button
          v-for="s in [{ n: 1, l: 'Cliente' }, { n: 2, l: 'Servicio' }, { n: 3, l: 'Horario' }, { n: 4, l: 'Confirmar' }]"
          :key="s.n"
          @click="s.n < step ? step = s.n : null"
          :class="['flex-1 py-3 text-xs font-medium text-center border-b-2 transition-colors',
            step === s.n ? 'border-primary text-primary' : step > s.n ? 'border-green-500 text-green-600' : 'border-transparent text-gray-400']"
        >{{ s.l }}</button>
      </div>

      <div class="p-5">
        <!-- Step 1: Client -->
        <div v-show="step === 1" class="space-y-4">
          <div class="space-y-2">
            <Label>Buscar cliente</Label>
            <Input v-model="clientSearch" placeholder="Nombre, telefono o cedula..." />
            <div v-if="clientResults.length" class="border rounded-md max-h-48 overflow-y-auto">
              <button v-for="c in clientResults" :key="c.id" @click="selectClient(c)"
                class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b last:border-0 text-sm">
                <span class="font-medium">{{ c.first_name }} {{ c.last_name }}</span>
                <span class="text-gray-500 ml-2">{{ c.phone }}</span>
                <span v-if="c.visit_count" class="text-xs text-gray-400 ml-2">{{ c.visit_count }} visitas</span>
              </button>
            </div>
          </div>

          <div v-if="selectedClient" class="bg-blue-50 rounded-lg p-3 text-sm">
            <p class="font-medium">{{ selectedClient.first_name }} {{ selectedClient.last_name }}</p>
            <p class="text-gray-600">{{ selectedClient.phone }}</p>
            <div v-if="selectedClient.allergies" class="mt-2 bg-red-100 rounded p-2 text-red-700 text-xs">{{ selectedClient.allergies }}</div>
          </div>

          <button @click="showNewClient = !showNewClient" class="text-sm text-primary hover:underline">
            {{ showNewClient ? 'Cancelar' : '+ Nuevo cliente' }}
          </button>
          <div v-if="showNewClient" class="space-y-3 border rounded-lg p-3">
            <div class="grid grid-cols-2 gap-2">
              <Input v-model="newClient.first_name" placeholder="Nombre" />
              <Input v-model="newClient.last_name" placeholder="Apellido" />
            </div>
            <Input v-model="newClient.phone" placeholder="Telefono (09...)" />
            <Input v-model="newClient.email" placeholder="Email (opcional)" type="email" />
            <Button size="sm" @click="createClient" :disabled="!newClient.first_name || !newClient.phone">Crear cliente</Button>
          </div>

          <div class="flex justify-end pt-2">
            <Button :disabled="!selectedClient" @click="step = 2">Siguiente</Button>
          </div>
        </div>

        <!-- Step 2: Service / Package tabs -->
        <div v-show="step === 2" class="space-y-4">
          <!-- Sub-tabs -->
          <div class="flex border-b">
            <button @click="step2Tab = 'service'"
              :class="['flex-1 py-2 text-sm font-medium border-b-2 transition-colors',
                step2Tab === 'service' ? 'border-primary text-primary' : 'border-transparent text-gray-400']"
            >Servicio</button>
            <button v-if="hasPackageTab || loadingPackages" @click="step2Tab = 'package'"
              :class="['flex-1 py-2 text-sm font-medium border-b-2 transition-colors',
                step2Tab === 'package' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-400']"
            >
              Paquete
              <Badge v-if="clientActivePackages.length" class="ml-1 bg-green-100 text-green-700 text-[10px] px-1.5 py-0">{{ clientActivePackages.length }}</Badge>
            </button>
          </div>

          <!-- Tab: Service (unchanged) -->
          <div v-if="step2Tab === 'service'">
            <div v-for="cat in categories" :key="cat.id" class="space-y-2 mb-3">
              <p class="text-xs font-medium text-gray-500 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: cat.color }" />
                {{ cat.name }}
              </p>
              <div class="grid grid-cols-2 gap-2">
                <button v-for="svc in cat.services" :key="svc.id" @click="selectNormalService(svc)"
                  :class="['text-left border rounded-lg p-3 text-sm transition-colors',
                    selectedService?.id === svc.id && appointmentMode === 'service' ? 'border-primary bg-primary/5' : 'hover:bg-gray-50']">
                  <p class="font-medium">{{ svc.name }}</p>
                  <p class="text-gray-500 text-xs">{{ svc.duration_minutes }}min — ${{ Number(svc.base_price).toFixed(2) }}</p>
                </button>
              </div>
            </div>
          </div>

          <!-- Tab: Package -->
          <div v-if="step2Tab === 'package'" class="space-y-4">
            <div v-if="loadingPackages" class="text-sm text-gray-400 text-center py-4">Cargando paquetes...</div>

            <!-- Section A: Active packages -->
            <div v-if="clientActivePackages.length">
              <p class="text-xs font-medium text-gray-500 mb-2">Paquetes activos de {{ selectedClient?.first_name }}</p>
              <div class="space-y-2">
                <div v-for="pkg in clientActivePackages" :key="pkg.id" class="border rounded-lg p-3 space-y-2">
                  <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold">{{ pkg.package_name }}</h4>
                    <span class="text-xs text-gray-400">Vence: {{ pkg.expires_at }}</span>
                  </div>
                  <div v-for="item in pkg.items" :key="item.id" class="space-y-1">
                    <div class="flex items-center justify-between text-xs">
                      <span>{{ item.service_name }}</span>
                      <span class="text-gray-500">{{ item.used }}/{{ item.total }} usadas</span>
                    </div>
                    <div class="w-full h-1.5 bg-gray-100 rounded-full">
                      <div class="h-1.5 rounded-full bg-green-500" :style="{ width: `${(item.used / item.total) * 100}%` }" />
                    </div>
                    <Button v-if="item.remaining > 0" size="sm" variant="outline"
                      :class="selectedPackageItem?.id === item.id ? 'border-green-500 bg-green-50 text-green-700' : ''"
                      class="w-full text-xs mt-1" @click="selectFromPackage(pkg, item)">
                      {{ selectedPackageItem?.id === item.id ? 'Seleccionado' : `Usar 1 sesion (quedan ${item.remaining})` }}
                    </Button>
                    <p v-else class="text-xs text-gray-400">Todas las sesiones usadas</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section B: Buy new package -->
            <div v-if="availablePackages.length">
              <p class="text-xs font-medium text-gray-500 mb-2">Comprar paquete nuevo</p>
              <div class="space-y-2">
                <div v-for="pkg in availablePackages" :key="pkg.id"
                  :class="['border rounded-lg p-3 transition-colors cursor-pointer',
                    purchasePackageId === pkg.id ? 'border-purple-500 bg-purple-50' : 'hover:bg-gray-50']"
                  @click="selectBuyPackage(pkg)">
                  <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold">{{ pkg.name }}</h4>
                    <span class="text-sm font-bold text-purple-700">${{ Number(pkg.price).toFixed(2) }}</span>
                  </div>
                  <div class="flex flex-wrap gap-1 mt-1">
                    <Badge v-for="item in pkg.items" :key="item.service_id" variant="secondary" class="text-[10px]">
                      {{ item.quantity }}x {{ item.service_name }}
                    </Badge>
                  </div>
                  <p class="text-xs text-gray-400 mt-1">Validez: {{ pkg.validity_days }} dias · {{ pkg.type === 'sessions' ? 'Bono sesiones' : 'Combo' }}</p>
                  <p v-if="purchasePackageId === pkg.id" class="text-xs text-purple-600 font-medium mt-1">Primera sesion incluida hoy</p>
                </div>
              </div>
            </div>

            <p v-if="!loadingPackages && !clientActivePackages.length && !availablePackages.length" class="text-sm text-gray-400 text-center py-4">
              No hay paquetes disponibles
            </p>
          </div>

          <div class="flex justify-between pt-2">
            <Button variant="outline" @click="step = 1">Atras</Button>
            <Button :disabled="!selectedService" @click="step = 3">Siguiente</Button>
          </div>
        </div>

        <!-- Step 3: Schedule -->
        <div v-show="step === 3" class="space-y-4">
          <div class="space-y-2">
            <Label>Estilista</Label>
            <select v-model="selectedStylist" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
              <option value="">Seleccionar...</option>
              <option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="space-y-2">
            <Label>Fecha</Label>
            <Input v-model="selectedDate" type="date" />
          </div>
          <div class="space-y-2">
            <Label>Hora disponible</Label>
            <div v-if="loadingSlots" class="text-sm text-gray-400">Cargando slots...</div>
            <div v-else-if="availableSlots.length" class="grid grid-cols-4 gap-1.5">
              <button v-for="slot in availableSlots" :key="slot.time" @click="selectedTime = slot.time"
                :class="['py-1.5 text-xs rounded border transition-colors',
                  selectedTime === slot.time ? 'bg-primary text-white border-primary' : 'hover:bg-gray-50']"
              >{{ slot.time }}</button>
            </div>
            <p v-else class="text-sm text-gray-400">No hay horarios disponibles</p>
            <p v-if="selectedTime && endTime" class="text-xs text-gray-500">Hora fin estimada: {{ endTime }}</p>
          </div>
          <div class="flex justify-between pt-2">
            <Button variant="outline" @click="step = 2">Atras</Button>
            <Button :disabled="!selectedTime" @click="step = 4">Siguiente</Button>
          </div>
        </div>

        <!-- Step 4: Confirm -->
        <div v-show="step === 4" class="space-y-4">
          <div class="space-y-2">
            <Label>Notas (opcional)</Label>
            <textarea v-model="notes" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" rows="2" placeholder="Notas para el estilista..." />
          </div>

          <div class="bg-gray-50 rounded-lg p-4 space-y-2 text-sm">
            <p><span class="text-gray-500">Cliente:</span> <span class="font-medium">{{ selectedClient?.first_name }} {{ selectedClient?.last_name }}</span></p>
            <p><span class="text-gray-500">Servicio:</span> <span class="font-medium">{{ selectedService?.name }}</span> — {{ selectedService?.duration_minutes }}min</p>
            <p><span class="text-gray-500">Precio:</span> <span class="font-medium">{{ summaryPrice }}</span></p>
            <p><span class="text-gray-500">Estilista:</span> <span class="font-medium">{{ stylists?.find(s => s.id === selectedStylist)?.name }}</span></p>
            <p><span class="text-gray-500">Fecha:</span> <span class="font-medium">{{ selectedDate }} a las {{ selectedTime }}</span></p>

            <!-- Package info in summary -->
            <div v-if="appointmentMode === 'package_use' && selectedPackageItem" class="bg-green-50 border border-green-200 rounded p-2 mt-2">
              <p class="text-green-700 text-xs font-medium">
                Sesion del paquete "{{ selectedPackageItem.package_name }}"
                (quedaran {{ selectedPackageItem.remaining - 1 }} sesiones)
              </p>
            </div>
            <div v-if="appointmentMode === 'package_buy'" class="bg-purple-50 border border-purple-200 rounded p-2 mt-2">
              <p class="text-purple-700 text-xs font-medium">
                Compra de paquete — primera sesion incluida
              </p>
            </div>
          </div>

          <div class="flex justify-between pt-2">
            <Button variant="outline" @click="step = 3">Atras</Button>
            <Button :disabled="!canSubmit || saving" @click="submit">
              {{ saving ? 'Creando...' : 'Crear cita' }}
            </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
