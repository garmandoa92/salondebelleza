<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import axios from 'axios'

const props = defineProps({
  open: Boolean,
  appointmentId: { type: String, default: null },
  clientId: { type: String, default: null },
  clientName: { type: String, default: null },
  preItems: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'completed'])

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const items = ref([])
const services = ref([])
const products = ref([])
const packages = ref([])
const stylists = ref([])
const discount = ref({ enabled: false, type: 'percentage', amount: 0, reason: '' })
const tip = ref({ amount: 0, stylist_id: '' })
const payments = ref([{ method: 'cash', amount: 0, received: 0 }])
const invoiceRequired = ref(false)
const invoiceData = ref({
  buyer_identification_type: 'final_consumer',
  buyer_identification: '',
  buyer_name: '',
  buyer_email: '',
  buyer_phone: '',
  buyer_address: '',
  buyer_commercial_name: '',
  update_client: true,
})
const invoiceIdValid = ref(null) // null=empty, true=valid, false=invalid
const invoiceClientFound = ref(null) // found client name or null
const saving = ref(false)
const completed = ref(false)
const completedSaleId = ref(null)

// Client balance / advances
const clientBalance = ref(0)
const applyAdvance = ref(false)

// Client selection (only when NOT from appointment)
const selectedClientId = ref(null)
const selectedClientName = ref(null)
const clientSearch = ref('')
const clientResults = ref([])
const showNewClient = ref(false)
const newClient = ref({ first_name: '', last_name: '', phone: '' })
let searchTimer = null

const isFromAppointment = computed(() => !!props.appointmentId)
const effectiveClientId = computed(() => isFromAppointment.value ? props.clientId : selectedClientId.value)

// Fetch client balance when client changes
watch(effectiveClientId, async (id) => {
  clientBalance.value = 0
  applyAdvance.value = false
  if (!id) return
  try {
    const { data } = await axios.get(`${base}/advances/client/${id}`)
    clientBalance.value = Number(data.balance) || 0
  } catch {}
})

// Ecuador cedula validation (modulo 10)
const validateCedula = (c) => {
  if (!c || c.length !== 10 || !/^\d{10}$/.test(c)) return false
  const d = c.split('').map(Number)
  const province = d[0] * 10 + d[1]
  if (province < 1 || (province > 24 && province !== 30)) return false
  let sum = 0
  for (let i = 0; i < 9; i++) {
    let v = d[i] * (i % 2 === 0 ? 2 : 1)
    if (v > 9) v -= 9
    sum += v
  }
  return (10 - (sum % 10)) % 10 === d[9]
}

// Ecuador RUC validation (modulo 11)
const validateRuc = (r) => {
  if (!r || r.length !== 13 || !/^\d{13}$/.test(r)) return false
  return r.endsWith('001') && validateCedula(r.substring(0, 10))
}

const isInvoiceIdType = (t) => t !== 'final_consumer'
const needsIdValidation = computed(() => isInvoiceIdType(invoiceData.value.buyer_identification_type))

// Watch identification number for validation + auto-complete
watch(() => invoiceData.value.buyer_identification, async (val) => {
  invoiceIdValid.value = null
  invoiceClientFound.value = null
  if (!val || !needsIdValidation.value) return

  const type = invoiceData.value.buyer_identification_type
  if (type === 'cedula' && val.length === 10) {
    invoiceIdValid.value = validateCedula(val)
  } else if (type === 'RUC' && val.length === 13) {
    invoiceIdValid.value = validateRuc(val)
  } else if (type === 'passport' && val.length >= 5) {
    invoiceIdValid.value = true
  }

  // Auto-complete from DB if valid
  if (invoiceIdValid.value && val.length >= 5) {
    try {
      const { data } = await axios.get(`${base}/agenda/search-clients`, { params: { q: val } })
      if (data.length) {
        const c = data[0]
        invoiceClientFound.value = `${c.first_name} ${c.last_name}`
        if (!invoiceData.value.buyer_name) invoiceData.value.buyer_name = `${c.first_name} ${c.last_name}`
        if (!invoiceData.value.buyer_email && c.email) invoiceData.value.buyer_email = c.email
        if (!invoiceData.value.buyer_phone && c.phone) invoiceData.value.buyer_phone = c.phone
      }
    } catch {}
  }
})

// Reset invoice fields when type changes
watch(() => invoiceData.value.buyer_identification_type, () => {
  invoiceData.value.buyer_identification = ''
  invoiceData.value.buyer_name = ''
  invoiceData.value.buyer_email = ''
  invoiceData.value.buyer_phone = ''
  invoiceData.value.buyer_address = ''
  invoiceData.value.buyer_commercial_name = ''
  invoiceIdValid.value = null
  invoiceClientFound.value = null
})

// Auto-fill from appointment client when toggle activated
watch(invoiceRequired, (val) => {
  if (!val) return
  const clientId = effectiveClientId.value
  if (!clientId) return
  // Try to find client data from search results or props
  const name = isFromAppointment.value ? props.clientName : selectedClientName.value
  if (name) invoiceData.value.buyer_name = name
})

watch(clientSearch, (val) => {
  clearTimeout(searchTimer)
  if (val.length < 2) { clientResults.value = []; return }
  searchTimer = setTimeout(async () => {
    const { data } = await axios.get(`${base}/agenda/search-clients`, { params: { q: val } })
    clientResults.value = data
  }, 300)
})

const selectClient = (c) => {
  selectedClientId.value = c.id
  selectedClientName.value = `${c.first_name} ${c.last_name}`
  clientSearch.value = ''
  clientResults.value = []
}

const createQuickClient = async () => {
  const { data } = await axios.post(`${base}/agenda/store-client`, { ...newClient.value, source: 'walk_in' })
  selectedClientId.value = data.id
  selectedClientName.value = `${data.first_name} ${data.last_name}`
  showNewClient.value = false
  newClient.value = { first_name: '', last_name: '', phone: '' }
}

const skipClient = () => {
  selectedClientId.value = null
  selectedClientName.value = 'Sin cliente'
}

watch(() => props.open, (val) => {
  if (val) {
    completed.value = false
    completedSaleId.value = null
    clientBalance.value = 0
    applyAdvance.value = false
    items.value = props.preItems.length ? props.preItems.map(i => ({ ...i })) : []
    discount.value = { enabled: false, type: 'percentage', amount: 0, reason: '' }
    tip.value = { amount: 0, stylist_id: '' }
    payments.value = [{ method: 'cash', amount: 0, received: 0 }]
    invoiceRequired.value = false
    invoiceData.value = { buyer_identification_type: 'final_consumer', buyer_identification: '', buyer_name: '', buyer_email: '', buyer_phone: '', buyer_address: '', buyer_commercial_name: '', update_client: true }
    invoiceIdValid.value = null
    invoiceClientFound.value = null
    selectedClientId.value = props.clientId || null
    selectedClientName.value = props.clientName || null
    clientSearch.value = ''
    clientResults.value = []
    showNewClient.value = false
  }
})

onMounted(async () => {
  const { data } = await axios.get(`${base}/ventas/checkout-data`)
  services.value = data.services
  products.value = data.products
  packages.value = data.packages || []
  stylists.value = data.stylists
  if (props.preItems.length) items.value = props.preItems.map(i => ({ ...i }))
})

const globalIva = computed(() => page.props.tenantIva || 15)

const getItemIva = (item) => item.iva_rate ?? globalIva.value

const subtotal = computed(() => items.value.reduce((sum, i) => sum + Number(i.subtotal || 0), 0))
const discountAmount = computed(() => {
  if (!discount.value.enabled) return 0
  if (discount.value.type === 'percentage') return Math.round(subtotal.value * Number(discount.value.amount) / 100 * 100) / 100
  return Number(discount.value.amount)
})
const baseImponible = computed(() => Math.max(0, subtotal.value - discountAmount.value))
// Calculate IVA per item (supports mixed rates)
const subtotal0 = computed(() => items.value.filter(i => getItemIva(i) === 0).reduce((s, i) => s + Number(i.subtotal || 0), 0))
const subtotalIva = computed(() => items.value.filter(i => getItemIva(i) > 0).reduce((s, i) => s + Number(i.subtotal || 0), 0))
const hasMixedIva = computed(() => subtotal0.value > 0 && subtotalIva.value > 0)
const ivaAmount = computed(() => {
  return items.value.reduce((sum, i) => {
    const rate = getItemIva(i)
    return sum + Math.round(Number(i.subtotal || 0) * rate / 100 * 100) / 100
  }, 0)
})
const total = computed(() => Math.round((baseImponible.value + ivaAmount.value) * 100) / 100)
const advanceApplied = computed(() => applyAdvance.value ? Math.min(clientBalance.value, total.value) : 0)
const totalAfterAdvance = computed(() => Math.max(0, Math.round((total.value - advanceApplied.value) * 100) / 100))
const totalWithTip = computed(() => totalAfterAdvance.value + Number(tip.value.amount || 0))

const paymentTotal = computed(() => payments.value.reduce((sum, p) => sum + Number(p.amount || 0), 0))
const paymentDiff = computed(() => Math.round((totalWithTip.value - paymentTotal.value) * 100) / 100)
const change = computed(() => {
  const cashPayment = payments.value.find(p => p.method === 'cash')
  if (!cashPayment) return 0
  return Math.max(0, Number(cashPayment.received || 0) - Number(cashPayment.amount || 0))
})

watch([totalAfterAdvance, () => tip.value.amount], () => {
  if (payments.value.length === 1) payments.value[0].amount = totalAfterAdvance.value + Number(tip.value.amount || 0)
})

const addItem = (type, item) => {
  items.value.push({
    type,
    reference_id: item.id,
    name: item.name,
    quantity: 1,
    unit_price: type === 'service' ? Number(item.base_price) : Number(item.sale_price),
    subtotal: type === 'service' ? Number(item.base_price) : Number(item.sale_price),
    iva_rate: item.iva_rate ?? undefined, // null/undefined = global
    iva_amount: 0,
    discount_amount: 0,
    stylist_id: stylists.value[0]?.id || null,
  })
}

const addPackage = (pkg) => {
  items.value.push({
    type: 'package',
    reference_id: pkg.id,
    name: pkg.name,
    quantity: 1,
    unit_price: Number(pkg.price),
    subtotal: Number(pkg.price),
    iva_amount: 0,
    discount_amount: 0,
    stylist_id: null,
  })
}

const removeItem = (index) => items.value.splice(index, 1)

const updateItemSubtotal = (item) => {
  item.subtotal = Math.round(Number(item.quantity) * Number(item.unit_price) * 100) / 100
}

const addPaymentMethod = () => payments.value.push({ method: 'transfer', amount: 0 })
const removePayment = (i) => payments.value.splice(i, 1)

const submit = async () => {
  if (paymentDiff.value > 0.01) return
  saving.value = true
  try {
    const { data } = await axios.post(`${base}/ventas`, {
      appointment_id: props.appointmentId || null,
      client_id: effectiveClientId.value || null,
      items: items.value.map(i => {
        const rate = getItemIva(i)
        return { ...i, iva_rate: rate, iva_amount: Math.round(Number(i.subtotal) * rate / 100 * 100) / 100 }
      }),
      subtotal: subtotal.value,
      discount_amount: discountAmount.value,
      discount_type: discount.value.enabled ? discount.value.type : null,
      discount_reason: discount.value.reason || null,
      iva_amount: ivaAmount.value,
      total: total.value,
      tip: tip.value.amount || 0,
      tip_stylist_id: tip.value.stylist_id || null,
      payment_methods: payments.value.map(p => ({ method: p.method, amount: Number(p.amount) })),
      advance_applied: advanceApplied.value,
    })

    completedSaleId.value = data.sale_id

    if (invoiceRequired.value) {
      await axios.post(`${base}/ventas/${data.sale_id}/invoice`, invoiceData.value)
    }

    completed.value = true
    emit('completed')
  } finally { saving.value = false }
}

const paymentMethods = [
  { key: 'cash', label: 'Efectivo', icon: '💵' },
  { key: 'transfer', label: 'Transferencia', icon: '🏦' },
  { key: 'card_debit', label: 'Debito', icon: '💳' },
  { key: 'card_credit', label: 'Credito', icon: '💳' },
]
const paymentLabels = { cash: 'Efectivo', transfer: 'Transferencia', card_debit: 'T. Debito', card_credit: 'T. Credito', other: 'Otro' }

const setPaymentMethod = (key) => {
  if (payments.value.length === 1) payments.value[0].method = key
}

const showDiscount = ref(false)
watch(() => discount.value.enabled, (v) => { if (v) showDiscount.value = true })

const printReceipt = () => window.open(`${base}/print/sale/${completedSaleId.value}`, '_blank', 'width=400,height=600')

const initials = (name) => name?.split(' ').map(n => n?.[0]).filter(Boolean).join('').toUpperCase().slice(0, 2) || '?'

const typeBadge = (t) => ({
  service: 'bg-blue-100 text-blue-700',
  product: 'bg-emerald-100 text-emerald-700',
  package: 'bg-purple-100 text-purple-700',
}[t] || 'bg-gray-100 text-gray-700')

const typeLabel = (t) => ({ service: 'Servicio', product: 'Producto', package: 'Paquete' }[t] || t)
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 overflow-y-auto py-4">
    <div class="bg-gray-50 rounded-2xl shadow-2xl w-full max-w-5xl max-h-[95vh] overflow-y-auto mx-4">

      <!-- ========== COMPLETED STATE ========== -->
      <div v-if="completed" class="bg-white rounded-2xl">
        <div class="text-center py-16 px-8 space-y-5">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-900">Cobro completado</h2>
          <p class="text-4xl font-bold text-green-600">${{ total.toFixed(2) }}</p>
          <p v-if="change > 0" class="text-lg text-gray-500">Vuelto: <span class="font-semibold text-green-600">${{ change.toFixed(2) }}</span></p>
          <div class="flex gap-3 justify-center pt-4">
            <Button variant="outline" size="lg" @click="printReceipt">Imprimir recibo</Button>
            <Button size="lg" @click="emit('close')">Cerrar</Button>
          </div>
        </div>
      </div>

      <!-- ========== CHECKOUT FORM ========== -->
      <div v-else>
        <!-- Header -->
        <div class="bg-white rounded-t-2xl border-b px-6 py-4 flex items-center justify-between sticky top-0 z-10">
          <h2 class="text-xl font-bold text-gray-900">Nueva venta</h2>
          <button @click="emit('close')" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="p-6 space-y-6">

          <!-- ===== CLIENT SECTION ===== -->
          <div class="bg-white rounded-xl shadow-sm border p-5">
            <!-- From appointment -->
            <div v-if="isFromAppointment && clientName" class="flex items-center gap-4">
              <Avatar class="h-11 w-11 ring-2 ring-blue-100"><AvatarFallback class="text-sm bg-blue-50 text-blue-700">{{ initials(clientName) }}</AvatarFallback></Avatar>
              <div class="flex-1">
                <p class="font-semibold text-gray-900">{{ clientName }}</p>
                <p class="text-xs text-blue-600">Cobro desde cita</p>
              </div>
              <span v-if="clientBalance > 0" class="text-xs font-medium text-green-700 bg-green-50 px-2.5 py-1 rounded-full">${{ clientBalance.toFixed(2) }} a favor</span>
            </div>

            <!-- Client selector -->
            <div v-else class="space-y-3">
              <p class="text-sm font-semibold text-gray-700">Cliente</p>

              <!-- Selected client mini-card -->
              <div v-if="selectedClientName" class="flex items-center gap-3 bg-gray-50 rounded-lg p-3">
                <Avatar class="h-10 w-10 ring-2 ring-gray-200"><AvatarFallback class="text-sm">{{ initials(selectedClientName) }}</AvatarFallback></Avatar>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-sm text-gray-900 truncate">{{ selectedClientName }}</p>
                  <span v-if="clientBalance > 0" class="text-xs text-green-600">${{ clientBalance.toFixed(2) }} a favor</span>
                </div>
                <button @click="selectedClientId = null; selectedClientName = null" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-200 text-gray-400 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>

              <template v-else>
                <!-- Search input with icon -->
                <div class="relative">
                  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                  <Input v-model="clientSearch" placeholder="Buscar cliente por nombre o telefono..." class="pl-9" />
                </div>

                <!-- Search results dropdown -->
                <div v-if="clientResults.length" class="border rounded-lg shadow-sm overflow-hidden max-h-48 overflow-y-auto">
                  <button v-for="c in clientResults" :key="c.id" @click="selectClient(c)"
                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-blue-50 border-b last:border-0 transition">
                    <Avatar class="h-8 w-8"><AvatarFallback class="text-xs">{{ initials(c.first_name + ' ' + c.last_name) }}</AvatarFallback></Avatar>
                    <div class="text-left">
                      <p class="text-sm font-medium text-gray-900">{{ c.first_name }} {{ c.last_name }}</p>
                      <p class="text-xs text-gray-500">{{ c.phone }}</p>
                    </div>
                  </button>
                </div>

                <!-- Pills -->
                <div class="flex gap-2">
                  <button @click="skipClient" class="text-xs px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">Sin cliente</button>
                  <button @click="showNewClient = !showNewClient" class="text-xs px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                    {{ showNewClient ? 'Cancelar' : '+ Nuevo cliente' }}
                  </button>
                </div>

                <!-- New client form -->
                <div v-if="showNewClient" class="border rounded-lg p-4 space-y-2 bg-gray-50">
                  <div class="grid grid-cols-2 gap-2">
                    <Input v-model="newClient.first_name" placeholder="Nombre" />
                    <Input v-model="newClient.last_name" placeholder="Apellido" />
                  </div>
                  <Input v-model="newClient.phone" placeholder="Telefono (09...)" />
                  <Button size="sm" @click="createQuickClient" :disabled="!newClient.first_name || !newClient.phone">Crear y seleccionar</Button>
                </div>
              </template>
            </div>
          </div>

          <!-- ===== TWO COLUMNS ===== -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT COLUMN -->
            <div class="lg:col-span-2 space-y-5">

              <!-- Items -->
              <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-5 pt-5 pb-3 border-b flex items-center justify-between">
                  <h3 class="text-sm font-semibold text-gray-900">Servicios y productos</h3>
                  <div class="flex gap-1.5 flex-wrap">
                    <select class="text-xs font-medium border border-blue-200 bg-blue-50 text-blue-700 rounded-lg px-2.5 py-1.5 cursor-pointer hover:bg-blue-100 transition"
                      @change="e => { const s = services.find(x => x.id === e.target.value); if(s) addItem('service', s); e.target.value='' }">
                      <option value="">+ Servicio</option>
                      <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }} - ${{ Number(s.base_price).toFixed(2) }}</option>
                    </select>
                    <select class="text-xs font-medium border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-lg px-2.5 py-1.5 cursor-pointer hover:bg-emerald-100 transition"
                      @change="e => { const p = products.find(x => x.id === e.target.value); if(p) addItem('product', p); e.target.value='' }">
                      <option value="">+ Producto</option>
                      <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} - ${{ Number(p.sale_price).toFixed(2) }}</option>
                    </select>
                    <select v-if="packages.length" class="text-xs font-medium border border-purple-200 bg-purple-50 text-purple-700 rounded-lg px-2.5 py-1.5 cursor-pointer hover:bg-purple-100 transition"
                      @change="e => { const pk = packages.find(x => x.id === e.target.value); if(pk) addPackage(pk); e.target.value='' }">
                      <option value="">+ Paquete</option>
                      <option v-for="pk in packages" :key="pk.id" :value="pk.id">{{ pk.name }} - ${{ Number(pk.price).toFixed(2) }}</option>
                    </select>
                  </div>
                </div>
                <div class="p-5">
                  <table v-if="items.length" class="w-full text-sm">
                    <thead>
                      <tr class="text-xs text-gray-400 uppercase tracking-wider">
                        <th class="text-left pb-3 font-medium">Item</th>
                        <th class="w-16 pb-3 font-medium text-center">Cant</th>
                        <th class="w-24 pb-3 font-medium text-center">Precio</th>
                        <th class="w-24 pb-3 font-medium text-right">Subtotal</th>
                        <th class="w-8 pb-3"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, i) in items" :key="i" class="border-t border-gray-100">
                        <td class="py-3">
                          <div class="flex items-start gap-2">
                            <span :class="[typeBadge(item.type), 'text-[10px] font-semibold px-1.5 py-0.5 rounded-md mt-0.5 whitespace-nowrap']">{{ typeLabel(item.type) }}</span>
                            <div class="min-w-0">
                              <p class="font-medium text-gray-900 truncate">{{ item.name }}</p>
                              <select v-if="item.type === 'service'" v-model="item.stylist_id" class="mt-1 text-xs border rounded-md px-1.5 py-1 text-gray-500 bg-gray-50">
                                <option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option>
                              </select>
                            </div>
                          </div>
                        </td>
                        <td class="py-3"><Input v-model="item.quantity" type="number" min="0.01" step="1" class="h-8 text-xs text-center" @input="updateItemSubtotal(item)" /></td>
                        <td class="py-3"><Input v-model="item.unit_price" type="number" step="0.01" class="h-8 text-xs text-center" @input="updateItemSubtotal(item)" /></td>
                        <td class="py-3 text-right font-semibold text-gray-900">${{ Number(item.subtotal).toFixed(2) }}</td>
                        <td class="py-3">
                          <button @click="removeItem(i)" class="w-6 h-6 flex items-center justify-center rounded-full hover:bg-red-50 text-gray-300 hover:text-red-500 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <div v-else class="text-center py-10">
                    <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                    <p class="text-sm text-gray-400">Agrega servicios o productos para comenzar</p>
                  </div>
                </div>
              </div>

              <!-- Discount (collapsible) -->
              <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <button @click="showDiscount = !showDiscount" class="w-full px-5 py-3.5 flex items-center justify-between hover:bg-gray-50 transition">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">Descuento</span>
                    <span v-if="discount.enabled && discountAmount > 0" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700">-${{ discountAmount.toFixed(2) }}</span>
                  </div>
                  <svg :class="['w-4 h-4 text-gray-400 transition-transform', showDiscount && 'rotate-180']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div v-if="showDiscount" class="px-5 pb-4 space-y-3 border-t">
                  <label class="flex items-center gap-2 cursor-pointer pt-3">
                    <input type="checkbox" v-model="discount.enabled" class="rounded border-gray-300" />
                    <span class="text-sm">Aplicar descuento</span>
                  </label>
                  <div v-if="discount.enabled" class="grid grid-cols-3 gap-2">
                    <select v-model="discount.type" class="text-sm border rounded-lg px-3 py-2 bg-gray-50">
                      <option value="percentage">Porcentaje %</option>
                      <option value="fixed">Monto fijo $</option>
                    </select>
                    <Input v-model="discount.amount" type="number" min="0" step="0.01" placeholder="0" />
                    <Input v-model="discount.reason" placeholder="Motivo" />
                  </div>
                </div>
              </div>

              <!-- Client balance / Advance -->
              <div v-if="clientBalance > 0" class="bg-green-50 border border-green-200 rounded-xl p-5 space-y-3">
                <div class="flex items-center gap-2">
                  <span class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 text-lg">$</span>
                  <div>
                    <p class="text-sm font-semibold text-green-800">Saldo a favor: ${{ clientBalance.toFixed(2) }}</p>
                  </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="applyAdvance" class="rounded border-green-300" />
                  <span class="text-sm text-green-800">Aplicar saldo al cobro</span>
                </label>
                <p v-if="applyAdvance" class="text-sm text-green-700 font-medium">
                  ${{ total.toFixed(2) }} → <span class="text-lg">${{ totalAfterAdvance.toFixed(2) }}</span>
                </p>
              </div>

              <!-- Invoice toggle -->
              <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="p-5">
                  <label class="flex items-center gap-3 cursor-pointer">
                    <div :class="['relative w-11 h-6 rounded-full transition-colors', invoiceRequired ? 'bg-primary' : 'bg-gray-200']" @click="invoiceRequired = !invoiceRequired">
                      <div :class="['absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform', invoiceRequired ? 'translate-x-[22px]' : 'translate-x-0.5']"></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-700">Comprobante electronico</span>
                  </label>
                </div>

                <div v-if="invoiceRequired" class="border-t px-5 pb-5 pt-4 space-y-4">
                  <!-- Row 1: Tipo + Numero -->
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="text-xs text-gray-500 mb-1 block">Tipo identificacion</label>
                      <select v-model="invoiceData.buyer_identification_type" class="w-full text-sm border rounded-lg px-3 py-2 bg-gray-50">
                        <option value="final_consumer">Consumidor final</option>
                        <option value="cedula">Cedula (05)</option>
                        <option value="RUC">RUC (04)</option>
                        <option value="passport">Pasaporte (06)</option>
                      </select>
                    </div>
                    <div v-if="invoiceData.buyer_identification_type !== 'final_consumer'">
                      <label class="text-xs text-gray-500 mb-1 block">
                        {{ invoiceData.buyer_identification_type === 'RUC' ? 'Numero de RUC' : invoiceData.buyer_identification_type === 'cedula' ? 'Numero de cedula' : 'Numero de pasaporte' }}
                      </label>
                      <div class="relative">
                        <Input v-model="invoiceData.buyer_identification"
                          :placeholder="invoiceData.buyer_identification_type === 'RUC' ? '0912345678001' : invoiceData.buyer_identification_type === 'cedula' ? '0912345678' : 'AB1234567'"
                          :maxlength="invoiceData.buyer_identification_type === 'RUC' ? 13 : invoiceData.buyer_identification_type === 'cedula' ? 10 : 20"
                          :class="[invoiceIdValid === true ? 'border-green-400 ring-1 ring-green-200' : invoiceIdValid === false ? 'border-red-400 ring-1 ring-red-200' : '']" />
                        <!-- Validation icon -->
                        <div v-if="invoiceIdValid === true" class="absolute right-2.5 top-1/2 -translate-y-1/2">
                          <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div v-else-if="invoiceIdValid === false" class="absolute right-2.5 top-1/2 -translate-y-1/2">
                          <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                      </div>
                      <p v-if="invoiceIdValid === false" class="text-[11px] text-red-500 mt-1">Numero invalido</p>
                      <p v-if="invoiceClientFound" class="text-[11px] text-green-600 mt-1">Cliente encontrado: {{ invoiceClientFound }}</p>
                    </div>
                  </div>

                  <!-- Consumidor final: no more fields -->
                  <template v-if="invoiceData.buyer_identification_type !== 'final_consumer'">
                    <!-- Row 2: Nombre / Razon social -->
                    <div>
                      <label class="text-xs text-gray-500 mb-1 block">
                        {{ invoiceData.buyer_identification_type === 'RUC' ? 'Razon social' : 'Nombre completo' }}
                      </label>
                      <Input v-model="invoiceData.buyer_name"
                        :placeholder="invoiceData.buyer_identification_type === 'RUC' ? 'EMPRESA S.A.' : 'Nombre y apellido'" />
                    </div>

                    <!-- Nombre comercial (solo RUC) -->
                    <div v-if="invoiceData.buyer_identification_type === 'RUC'">
                      <label class="text-xs text-gray-500 mb-1 block">Nombre comercial (opcional)</label>
                      <Input v-model="invoiceData.buyer_commercial_name" placeholder="Nombre comercial" />
                    </div>

                    <!-- Row 3: Email + Telefono -->
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="text-xs text-gray-500 mb-1 block">Email (para enviar RIDE)</label>
                        <Input v-model="invoiceData.buyer_email" type="email" placeholder="correo@ejemplo.com" />
                      </div>
                      <div>
                        <label class="text-xs text-gray-500 mb-1 block">Telefono</label>
                        <Input v-model="invoiceData.buyer_phone" placeholder="09..." />
                      </div>
                    </div>

                    <!-- Row 4: Direccion -->
                    <div>
                      <label class="text-xs text-gray-500 mb-1 block">
                        Direccion {{ invoiceData.buyer_identification_type === 'RUC' ? '' : '(opcional)' }}
                      </label>
                      <Input v-model="invoiceData.buyer_address" placeholder="Direccion del comprador" />
                    </div>

                    <!-- Update client checkbox -->
                    <label v-if="effectiveClientId && (invoiceData.buyer_email || invoiceData.buyer_phone || invoiceData.buyer_address)" class="flex items-center gap-2 cursor-pointer pt-1">
                      <input type="checkbox" v-model="invoiceData.update_client" class="rounded border-gray-300" />
                      <span class="text-xs text-gray-500">Actualizar estos datos en la ficha del cliente</span>
                    </label>
                  </template>
                </div>
              </div>
            </div>

            <!-- RIGHT COLUMN: Summary -->
            <div>
              <div class="bg-white rounded-xl shadow-sm border p-5 sticky top-20 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900">Resumen</h3>

                <div class="space-y-2.5 text-sm">
                  <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="font-medium">${{ subtotal.toFixed(2) }}</span></div>
                  <div v-if="discountAmount > 0" class="flex justify-between text-red-500"><span>Descuento</span><span class="font-medium">-${{ discountAmount.toFixed(2) }}</span></div>
                  <template v-if="hasMixedIva">
                    <div class="flex justify-between"><span class="text-gray-400">Base IVA {{ globalIva }}%</span><span>${{ subtotalIva.toFixed(2) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Base IVA 0%</span><span>${{ subtotal0.toFixed(2) }}</span></div>
                  </template>
                  <div class="flex justify-between"><span class="text-gray-500">IVA {{ globalIva }}%</span><span class="font-medium">${{ ivaAmount.toFixed(2) }}</span></div>
                  <div v-if="advanceApplied > 0" class="flex justify-between text-green-600"><span>Anticipo</span><span class="font-medium">-${{ advanceApplied.toFixed(2) }}</span></div>
                </div>

                <div class="border-t pt-3">
                  <div class="flex justify-between items-baseline">
                    <span class="text-gray-700 font-semibold">Total</span>
                    <span class="text-2xl font-bold text-gray-900">${{ totalAfterAdvance.toFixed(2) }}</span>
                  </div>
                  <div v-if="Number(tip.amount) > 0" class="flex justify-between text-sm text-gray-400 mt-1"><span>+ Propina</span><span>${{ Number(tip.amount).toFixed(2) }}</span></div>
                </div>

                <!-- Payment method -->
                <div class="border-t pt-3 space-y-3">
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Metodo de pago</p>
                    <button v-if="payments.length < 4" @click="addPaymentMethod" class="text-[10px] px-2 py-1 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition">+ Agregar</button>
                  </div>

                  <!-- Chips -->
                  <div v-if="payments.length === 1" class="grid grid-cols-2 gap-1.5">
                    <button v-for="m in paymentMethods" :key="m.key" @click="setPaymentMethod(m.key)"
                      :class="['px-2 py-2 rounded-lg text-xs font-medium border transition-all text-center',
                        payments[0].method === m.key
                          ? 'border-primary bg-primary/5 text-primary'
                          : 'border-gray-200 text-gray-500 hover:border-gray-300']">
                      <span class="mr-1">{{ m.icon }}</span>{{ m.label }}
                    </button>
                  </div>

                  <!-- Amount rows -->
                  <div class="space-y-2">
                    <div v-for="(p, i) in payments" :key="i" class="space-y-1.5">
                      <div class="flex items-center gap-1.5">
                        <select v-if="payments.length > 1" v-model="p.method" class="flex-1 text-xs border rounded-lg px-2 py-1.5 bg-gray-50">
                          <option v-for="(label, key) in paymentLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <div class="relative" :class="payments.length > 1 ? 'w-24' : 'flex-1'">
                          <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                          <Input v-model="p.amount" type="number" step="0.01" class="pl-6 h-9 text-sm" placeholder="0.00" />
                        </div>
                        <button v-if="payments.length > 1" @click="removePayment(i)" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-red-50 text-gray-300 hover:text-red-500 transition">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </div>
                      <div v-if="p.method === 'cash'" class="space-y-1">
                        <div class="relative">
                          <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">Recibido $</span>
                          <Input v-model="p.received" type="number" step="0.01" class="pl-[72px] h-8 text-sm" placeholder="0.00" />
                        </div>
                        <p v-if="change > 0" class="text-sm font-semibold text-green-600">Vuelto: ${{ change.toFixed(2) }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- Status -->
                  <div class="flex items-center gap-1.5">
                    <span v-if="paymentDiff > 0.01" class="flex items-center gap-1 text-xs font-medium text-red-500">
                      <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Falta: ${{ paymentDiff.toFixed(2) }}
                    </span>
                    <span v-else-if="paymentDiff < -0.01" class="flex items-center gap-1 text-xs font-medium text-amber-500">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Exceso: ${{ Math.abs(paymentDiff).toFixed(2) }}
                    </span>
                    <span v-else class="flex items-center gap-1 text-xs font-medium text-green-600">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Correcto
                    </span>
                  </div>
                </div>

                <!-- Tip -->
                <div class="border-t pt-3 space-y-2">
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Propina</p>
                  <div class="flex gap-2">
                    <div class="relative flex-1">
                      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                      <Input v-model="tip.amount" type="number" min="0" step="0.5" class="pl-7 h-9 text-sm" placeholder="0" />
                    </div>
                    <select v-model="tip.stylist_id" class="text-xs border rounded-lg px-2 bg-gray-50">
                      <option value="">Para...</option>
                      <option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                  </div>
                </div>

                <!-- Submit button -->
                <button @click="submit"
                  :disabled="saving || !items.length || paymentDiff > 0.01"
                  :class="['w-full py-3.5 rounded-xl text-sm font-bold transition-all shadow-sm',
                    saving || !items.length || paymentDiff > 0.01
                      ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                      : 'bg-primary text-primary-foreground hover:opacity-90 active:scale-[0.98] shadow-md']">
                  {{ saving ? 'Procesando...' : `Completar cobro · $${totalWithTip.toFixed(2)}` }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
