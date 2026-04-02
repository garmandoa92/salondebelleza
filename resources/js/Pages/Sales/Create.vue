<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  services: Array,
  products: Array,
  packages: Array,
  stylists: Array,
  appointmentId: { type: String, default: null },
  preClient: { type: Object, default: null },
  preItems: { type: Array, default: () => [] },
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`
const globalIva = computed(() => page.props.tenantIva ?? 15)

// State
const items = ref(props.preItems.length ? props.preItems.map(i => ({ ...i })) : [])
const discount = ref({ enabled: false, type: 'percentage', amount: 0, reason: '' })
const tip = ref({ amount: 0, stylist_id: '' })
const payments = ref([{ method: 'cash', amount: 0, received: 0 }])
const saving = ref(false)

// Client
const selectedClientId = ref(props.preClient?.id || null)
const selectedClientName = ref(props.preClient ? `${props.preClient.first_name} ${props.preClient.last_name}` : null)
const selectedClientPhone = ref(props.preClient?.phone || null)
const clientSearch = ref('')
const clientResults = ref([])
const showNewClient = ref(false)
const newClient = ref({ first_name: '', last_name: '', phone: '' })
let searchTimer = null

// Balance
const clientBalance = ref(0)
const applyAdvance = ref(false)

// Invoice
const invoiceRequired = ref(false)
const invoiceData = ref({ buyer_identification_type: 'final_consumer', buyer_identification: '', buyer_name: '', buyer_email: '', buyer_phone: '', buyer_address: '', buyer_commercial_name: '', update_client: true })
const invoiceIdValid = ref(null)
const invoiceClientFound = ref(null)
const showDiscount = ref(false)

// Computed
const getItemIva = (item) => item.iva_rate ?? globalIva.value
const subtotal = computed(() => items.value.reduce((sum, i) => sum + Number(i.subtotal || 0), 0))
const discountAmount = computed(() => {
  if (!discount.value.enabled) return 0
  if (discount.value.type === 'percentage') return Math.round(subtotal.value * Number(discount.value.amount) / 100 * 100) / 100
  return Number(discount.value.amount)
})
const baseImponible = computed(() => Math.max(0, subtotal.value - discountAmount.value))
const subtotal0 = computed(() => items.value.filter(i => getItemIva(i) === 0).reduce((s, i) => s + Number(i.subtotal || 0), 0))
const subtotalIva = computed(() => items.value.filter(i => getItemIva(i) > 0).reduce((s, i) => s + Number(i.subtotal || 0), 0))
const hasMixedIva = computed(() => subtotal0.value > 0 && subtotalIva.value > 0)
const ivaAmount = computed(() => items.value.reduce((sum, i) => {
  const rate = getItemIva(i)
  return sum + Math.round(Number(i.subtotal || 0) * rate / 100 * 100) / 100
}, 0))
const total = computed(() => Math.round((baseImponible.value + ivaAmount.value) * 100) / 100)
const advanceApplied = computed(() => applyAdvance.value ? Math.min(clientBalance.value, total.value) : 0)
const totalAfterAdvance = computed(() => Math.max(0, Math.round((total.value - advanceApplied.value) * 100) / 100))
const totalWithTip = computed(() => totalAfterAdvance.value + Number(tip.value.amount || 0))
const paymentTotal = computed(() => payments.value.reduce((sum, p) => sum + Number(p.amount || 0), 0))
const paymentDiff = computed(() => Math.round((totalWithTip.value - paymentTotal.value) * 100) / 100)
const change = computed(() => {
  const cp = payments.value.find(p => p.method === 'cash')
  if (!cp) return 0
  return Math.max(0, Number(cp.received || 0) - Number(cp.amount || 0))
})

// Watchers
watch(selectedClientId, async (id) => {
  clientBalance.value = 0
  applyAdvance.value = false
  if (!id) return
  try {
    const { data } = await axios.get(`${base}/advances/client/${id}`)
    clientBalance.value = Number(data.balance) || 0
  } catch {}
})

watch([totalAfterAdvance, () => tip.value.amount], () => {
  if (payments.value.length === 1) payments.value[0].amount = totalAfterAdvance.value + Number(tip.value.amount || 0)
})

watch(clientSearch, (val) => {
  clearTimeout(searchTimer)
  if (val.length < 2) { clientResults.value = []; return }
  searchTimer = setTimeout(async () => {
    const { data } = await axios.get(`${base}/agenda/search-clients`, { params: { q: val } })
    clientResults.value = data
  }, 300)
})

// Invoice validation
const validateCedula = (c) => {
  if (!c || c.length !== 10 || !/^\d{10}$/.test(c)) return false
  const d = c.split('').map(Number)
  const province = d[0] * 10 + d[1]
  if (province < 1 || (province > 24 && province !== 30)) return false
  let sum = 0
  for (let i = 0; i < 9; i++) { let v = d[i] * (i % 2 === 0 ? 2 : 1); if (v > 9) v -= 9; sum += v }
  return (10 - (sum % 10)) % 10 === d[9]
}
const validateRuc = (r) => {
  if (!r || r.length !== 13 || !/^\d{13}$/.test(r)) return false
  return r.endsWith('001') && validateCedula(r.substring(0, 10))
}

watch(() => invoiceData.value.buyer_identification, async (val) => {
  invoiceIdValid.value = null; invoiceClientFound.value = null
  if (!val || invoiceData.value.buyer_identification_type === 'final_consumer') return
  const t = invoiceData.value.buyer_identification_type
  if (t === 'cedula' && val.length === 10) invoiceIdValid.value = validateCedula(val)
  else if (t === 'RUC' && val.length === 13) invoiceIdValid.value = validateRuc(val)
  else if (t === 'passport' && val.length >= 5) invoiceIdValid.value = true
  if (invoiceIdValid.value && val.length >= 5) {
    try {
      const { data } = await axios.get(`${base}/agenda/search-clients`, { params: { q: val } })
      if (data.length) {
        const c = data[0]; invoiceClientFound.value = `${c.first_name} ${c.last_name}`
        if (!invoiceData.value.buyer_name) invoiceData.value.buyer_name = `${c.first_name} ${c.last_name}`
        if (!invoiceData.value.buyer_email && c.email) invoiceData.value.buyer_email = c.email
        if (!invoiceData.value.buyer_phone && c.phone) invoiceData.value.buyer_phone = c.phone
      }
    } catch {}
  }
})
watch(() => invoiceData.value.buyer_identification_type, () => {
  invoiceData.value.buyer_identification = ''; invoiceData.value.buyer_name = ''
  invoiceData.value.buyer_email = ''; invoiceData.value.buyer_phone = ''
  invoiceData.value.buyer_address = ''; invoiceData.value.buyer_commercial_name = ''
  invoiceIdValid.value = null; invoiceClientFound.value = null
})
watch(invoiceRequired, (val) => {
  if (val && selectedClientName.value) invoiceData.value.buyer_name = selectedClientName.value
})

// Actions
const selectClient = (c) => {
  selectedClientId.value = c.id
  selectedClientName.value = `${c.first_name} ${c.last_name}`
  selectedClientPhone.value = c.phone
  clientSearch.value = ''; clientResults.value = []
}
const createQuickClient = async () => {
  const { data } = await axios.post(`${base}/agenda/store-client`, { ...newClient.value, source: 'walk_in' })
  selectedClientId.value = data.id; selectedClientName.value = `${data.first_name} ${data.last_name}`
  selectedClientPhone.value = data.phone; showNewClient.value = false
  newClient.value = { first_name: '', last_name: '', phone: '' }
}
const clearClient = () => { selectedClientId.value = null; selectedClientName.value = null; selectedClientPhone.value = null }
const skipClient = () => { selectedClientId.value = null; selectedClientName.value = 'Sin cliente'; selectedClientPhone.value = null }

const addItem = (type, item) => {
  items.value.push({
    type, reference_id: item.id, name: item.name, quantity: 1,
    unit_price: type === 'service' ? Number(item.base_price) : Number(item.sale_price),
    subtotal: type === 'service' ? Number(item.base_price) : Number(item.sale_price),
    iva_rate: item.iva_rate ?? undefined, iva_amount: 0, discount_amount: 0,
    stylist_id: props.stylists[0]?.id || null,
  })
}
const addPackage = (pkg) => {
  items.value.push({ type: 'package', reference_id: pkg.id, name: pkg.name, quantity: 1,
    unit_price: Number(pkg.price), subtotal: Number(pkg.price), iva_amount: 0, discount_amount: 0, stylist_id: null })
}
const removeItem = (i) => items.value.splice(i, 1)
const updateItemSubtotal = (item) => { item.subtotal = Math.round(Number(item.quantity) * Number(item.unit_price) * 100) / 100 }
const addPaymentMethod = () => payments.value.push({ method: 'transfer', amount: 0 })
const removePayment = (i) => payments.value.splice(i, 1)
const setPaymentMethod = (key) => { if (payments.value.length === 1) payments.value[0].method = key }

const paymentMethods = [
  { key: 'cash', label: 'Efectivo', icon: '💵' },
  { key: 'transfer', label: 'Transferencia', icon: '🏦' },
  { key: 'card_debit', label: 'Debito', icon: '💳' },
  { key: 'card_credit', label: 'Credito', icon: '💳' },
]

const submit = async () => {
  if (paymentDiff.value > 0.01) return
  saving.value = true
  try {
    const { data } = await axios.post(`${base}/ventas`, {
      appointment_id: props.appointmentId || null,
      client_id: selectedClientId.value || null,
      items: items.value.map(i => {
        const rate = getItemIva(i)
        return { ...i, iva_rate: rate, iva_amount: Math.round(Number(i.subtotal) * rate / 100 * 100) / 100 }
      }),
      subtotal: subtotal.value, discount_amount: discountAmount.value,
      discount_type: discount.value.enabled ? discount.value.type : null,
      discount_reason: discount.value.reason || null,
      iva_amount: ivaAmount.value, total: total.value,
      tip: tip.value.amount || 0, tip_stylist_id: tip.value.stylist_id || null,
      payment_methods: payments.value.map(p => ({ method: p.method, amount: Number(p.amount) })),
      advance_applied: advanceApplied.value,
    })

    if (invoiceRequired.value && invoiceData.value.buyer_identification_type !== 'final_consumer') {
      await axios.post(`${base}/ventas/${data.sale_id}/invoice`, invoiceData.value)
    }

    router.visit(`${base}/ventas`, { preserveState: false })
  } finally { saving.value = false }
}

const initials = (name) => name?.split(' ').map(n => n?.[0]).filter(Boolean).join('').toUpperCase().slice(0, 2) || '?'
const typeBadge = (t) => ({ service: 'bg-blue-100 text-blue-700', product: 'bg-emerald-100 text-emerald-700', package: 'bg-purple-100 text-purple-700' }[t] || 'bg-gray-100')
const typeLabel = (t) => ({ service: 'Servicio', product: 'Producto', package: 'Paquete' }[t] || t)

// Load balance if pre-client
onMounted(() => {
  if (selectedClientId.value) {
    axios.get(`${base}/advances/client/${selectedClientId.value}`).then(({ data }) => {
      clientBalance.value = Number(data.balance) || 0
    }).catch(() => {})
  }
})
</script>

<template>
  <Head title="Nueva venta" />

  <div class="flex flex-col h-[calc(100vh-4rem)]">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b bg-white shrink-0">
      <div class="flex items-center gap-3">
        <Link :href="`${base}/ventas`" class="text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </Link>
        <h1 class="text-xl font-bold text-gray-900">Nueva venta</h1>
        <Badge v-if="props.appointmentId" variant="secondary" class="text-xs">Desde cita</Badge>
      </div>
      <Link :href="`${base}/ventas`"><Button variant="outline" size="sm">Cancelar</Button></Link>
    </div>

    <!-- Content: two columns -->
    <div class="flex-1 overflow-hidden flex">
      <!-- LEFT COLUMN (scrollable) -->
      <div class="flex-1 overflow-y-auto p-6 space-y-5">

        <!-- 1. CLIENT -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
          <p class="text-sm font-semibold text-gray-700 mb-3">Cliente</p>

          <div v-if="selectedClientName && selectedClientName !== 'Sin cliente'" class="flex items-center gap-3 bg-gray-50 rounded-lg p-3">
            <Avatar class="h-10 w-10 ring-2 ring-gray-200"><AvatarFallback class="text-sm">{{ initials(selectedClientName) }}</AvatarFallback></Avatar>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-sm text-gray-900 truncate">{{ selectedClientName }}</p>
              <p v-if="selectedClientPhone" class="text-xs text-gray-500">{{ selectedClientPhone }}</p>
              <span v-if="clientBalance > 0" class="text-xs text-green-600">${{ clientBalance.toFixed(2) }} a favor</span>
            </div>
            <button @click="clearClient" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-200 text-gray-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <template v-else-if="selectedClientName !== 'Sin cliente'">
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              <Input v-model="clientSearch" placeholder="Buscar cliente..." class="pl-9" />
            </div>
            <div v-if="clientResults.length" class="border rounded-lg shadow-sm overflow-hidden max-h-48 overflow-y-auto mt-2">
              <button v-for="c in clientResults" :key="c.id" @click="selectClient(c)" class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-blue-50 border-b last:border-0">
                <Avatar class="h-8 w-8"><AvatarFallback class="text-xs">{{ initials(c.first_name + ' ' + c.last_name) }}</AvatarFallback></Avatar>
                <div class="text-left"><p class="text-sm font-medium">{{ c.first_name }} {{ c.last_name }}</p><p class="text-xs text-gray-500">{{ c.phone }}</p></div>
              </button>
            </div>
            <div class="flex gap-2 mt-3">
              <button @click="skipClient" class="text-xs px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200">Sin cliente</button>
              <button @click="showNewClient = !showNewClient" class="text-xs px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200">{{ showNewClient ? 'Cancelar' : '+ Nuevo cliente' }}</button>
            </div>
            <div v-if="showNewClient" class="border rounded-lg p-4 space-y-2 bg-gray-50 mt-3">
              <div class="grid grid-cols-2 gap-2"><Input v-model="newClient.first_name" placeholder="Nombre" /><Input v-model="newClient.last_name" placeholder="Apellido" /></div>
              <Input v-model="newClient.phone" placeholder="Telefono (09...)" />
              <Button size="sm" @click="createQuickClient" :disabled="!newClient.first_name || !newClient.phone">Crear y seleccionar</Button>
            </div>
          </template>

          <p v-else class="text-sm text-gray-400">Sin cliente <button @click="clearClient" class="text-primary hover:underline ml-1">Cambiar</button></p>
        </div>

        <!-- Balance banner -->
        <div v-if="clientBalance > 0" class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 font-bold">$</span>
            <p class="text-sm font-semibold text-green-800">Saldo a favor: ${{ clientBalance.toFixed(2) }}</p>
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="applyAdvance" class="rounded border-green-300" />
            <span class="text-sm text-green-800">Aplicar</span>
          </label>
        </div>

        <!-- 2. ITEMS -->
        <div class="bg-white rounded-xl shadow-sm border">
          <div class="px-5 pt-5 pb-3 border-b flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Servicios y productos</h3>
            <div class="flex gap-1.5 flex-wrap">
              <select class="text-xs font-medium border border-blue-200 bg-blue-50 text-blue-700 rounded-lg px-2.5 py-1.5 cursor-pointer" @change="e => { const s = services.find(x => x.id === e.target.value); if(s) addItem('service', s); e.target.value='' }">
                <option value="">+ Servicio</option>
                <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }} - ${{ Number(s.base_price).toFixed(2) }}</option>
              </select>
              <select class="text-xs font-medium border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-lg px-2.5 py-1.5 cursor-pointer" @change="e => { const p = products.find(x => x.id === e.target.value); if(p) addItem('product', p); e.target.value='' }">
                <option value="">+ Producto</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} - ${{ Number(p.sale_price).toFixed(2) }}</option>
              </select>
              <select v-if="packages.length" class="text-xs font-medium border border-purple-200 bg-purple-50 text-purple-700 rounded-lg px-2.5 py-1.5 cursor-pointer" @change="e => { const pk = packages.find(x => x.id === e.target.value); if(pk) addPackage(pk); e.target.value='' }">
                <option value="">+ Paquete</option>
                <option v-for="pk in packages" :key="pk.id" :value="pk.id">{{ pk.name }} - ${{ Number(pk.price).toFixed(2) }}</option>
              </select>
            </div>
          </div>
          <div class="p-5">
            <table v-if="items.length" class="w-full text-sm">
              <thead><tr class="text-xs text-gray-400 uppercase tracking-wider"><th class="text-left pb-3">Item</th><th class="w-16 pb-3 text-center">Cant</th><th class="w-24 pb-3 text-center">Precio</th><th class="w-24 pb-3 text-right">Subtotal</th><th class="w-8 pb-3"></th></tr></thead>
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
                  <td class="py-3 text-right font-semibold">${{ Number(item.subtotal).toFixed(2) }}</td>
                  <td class="py-3"><button @click="removeItem(i)" class="w-6 h-6 flex items-center justify-center rounded-full hover:bg-red-50 text-gray-300 hover:text-red-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></td>
                </tr>
              </tbody>
            </table>
            <div v-else class="text-center py-10">
              <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
              <p class="text-sm text-gray-400">Agrega servicios o productos para comenzar</p>
            </div>
          </div>
        </div>

        <!-- 3. DISCOUNT -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <button @click="showDiscount = !showDiscount" class="w-full px-5 py-3.5 flex items-center justify-between hover:bg-gray-50">
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-gray-700">Descuento</span>
              <span v-if="discount.enabled && discountAmount > 0" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700">-${{ discountAmount.toFixed(2) }}</span>
            </div>
            <svg :class="['w-4 h-4 text-gray-400 transition-transform', showDiscount && 'rotate-180']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div v-if="showDiscount" class="px-5 pb-4 space-y-3 border-t">
            <label class="flex items-center gap-2 cursor-pointer pt-3"><input type="checkbox" v-model="discount.enabled" class="rounded border-gray-300" /><span class="text-sm">Aplicar descuento</span></label>
            <div v-if="discount.enabled" class="grid grid-cols-3 gap-2">
              <select v-model="discount.type" class="text-sm border rounded-lg px-3 py-2 bg-gray-50"><option value="percentage">Porcentaje %</option><option value="fixed">Monto fijo $</option></select>
              <Input v-model="discount.amount" type="number" min="0" step="0.01" placeholder="0" />
              <Input v-model="discount.reason" placeholder="Motivo" />
            </div>
          </div>
        </div>

        <!-- 4. INVOICE -->
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
                <label class="text-xs text-gray-500 mb-1 block">{{ invoiceData.buyer_identification_type === 'RUC' ? 'RUC' : invoiceData.buyer_identification_type === 'cedula' ? 'Cedula' : 'Pasaporte' }}</label>
                <div class="relative">
                  <Input v-model="invoiceData.buyer_identification" :maxlength="invoiceData.buyer_identification_type === 'RUC' ? 13 : invoiceData.buyer_identification_type === 'cedula' ? 10 : 20"
                    :class="[invoiceIdValid === true ? 'border-green-400 ring-1 ring-green-200' : invoiceIdValid === false ? 'border-red-400 ring-1 ring-red-200' : '']" />
                  <div v-if="invoiceIdValid === true" class="absolute right-2.5 top-1/2 -translate-y-1/2"><svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                  <div v-else-if="invoiceIdValid === false" class="absolute right-2.5 top-1/2 -translate-y-1/2"><svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></div>
                </div>
                <p v-if="invoiceIdValid === false" class="text-[11px] text-red-500 mt-1">Numero invalido</p>
                <p v-if="invoiceClientFound" class="text-[11px] text-green-600 mt-1">Cliente: {{ invoiceClientFound }}</p>
              </div>
            </div>
            <template v-if="invoiceData.buyer_identification_type !== 'final_consumer'">
              <div><label class="text-xs text-gray-500 mb-1 block">{{ invoiceData.buyer_identification_type === 'RUC' ? 'Razon social' : 'Nombre completo' }}</label><Input v-model="invoiceData.buyer_name" /></div>
              <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs text-gray-500 mb-1 block">Email</label><Input v-model="invoiceData.buyer_email" type="email" placeholder="correo@ejemplo.com" /></div>
                <div><label class="text-xs text-gray-500 mb-1 block">Telefono</label><Input v-model="invoiceData.buyer_phone" placeholder="09..." /></div>
              </div>
              <div><label class="text-xs text-gray-500 mb-1 block">Direccion {{ invoiceData.buyer_identification_type === 'RUC' ? '' : '(opcional)' }}</label><Input v-model="invoiceData.buyer_address" /></div>
            </template>
          </div>
        </div>

        <!-- 5. PAYMENT -->
        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Metodo de pago</h3>
            <button v-if="payments.length < 4" @click="addPaymentMethod" class="text-xs px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200">+ Agregar metodo</button>
          </div>
          <div v-if="payments.length === 1" class="grid grid-cols-4 gap-2">
            <button v-for="m in paymentMethods" :key="m.key" @click="setPaymentMethod(m.key)"
              :class="['px-2 py-2.5 rounded-xl text-xs font-medium border-2 transition-all text-center',
                payments[0].method === m.key ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-500 hover:border-gray-300']">
              <span class="mr-1">{{ m.icon }}</span>{{ m.label }}
            </button>
          </div>
          <div class="space-y-3">
            <div v-for="(p, i) in payments" :key="i" class="space-y-1.5">
              <div class="flex items-center gap-2">
                <select v-if="payments.length > 1" v-model="p.method" class="flex-1 text-sm border rounded-lg px-3 py-2 bg-gray-50">
                  <option v-for="m in paymentMethods" :key="m.key" :value="m.key">{{ m.icon }} {{ m.label }}</option>
                </select>
                <div class="relative" :class="payments.length > 1 ? 'w-32' : 'flex-1'">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                  <Input v-model="p.amount" type="number" step="0.01" class="pl-7" placeholder="0.00" />
                </div>
                <button v-if="payments.length > 1" @click="removePayment(i)" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-red-50 text-gray-300 hover:text-red-500">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
              <div v-if="p.method === 'cash'" class="flex items-center gap-3">
                <div class="relative flex-1">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Recibido $</span>
                  <Input v-model="p.received" type="number" step="0.01" class="pl-20 text-sm" placeholder="0.00" />
                </div>
                <p v-if="change > 0" class="text-sm font-semibold text-green-600 whitespace-nowrap">Vuelto: ${{ change.toFixed(2) }}</p>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-1.5">
            <span v-if="paymentDiff > 0.01" class="flex items-center gap-1.5 text-sm font-medium text-red-500"><span class="w-2 h-2 rounded-full bg-red-500"></span>Falta: ${{ paymentDiff.toFixed(2) }}</span>
            <span v-else-if="paymentDiff < -0.01" class="flex items-center gap-1.5 text-sm font-medium text-amber-500"><span class="w-2 h-2 rounded-full bg-amber-500"></span>Exceso: ${{ Math.abs(paymentDiff).toFixed(2) }}</span>
            <span v-else class="flex items-center gap-1.5 text-sm font-medium text-green-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Correcto</span>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN (sticky) -->
      <div class="w-80 shrink-0 border-l bg-white p-5 overflow-y-auto hidden lg:block">
        <div class="sticky top-0 space-y-5">
          <!-- Summary -->
          <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Resumen</h3>
            <div class="space-y-2.5 text-sm">
              <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="font-medium">${{ subtotal.toFixed(2) }}</span></div>
              <div v-if="discountAmount > 0" class="flex justify-between text-red-500"><span>Descuento</span><span>-${{ discountAmount.toFixed(2) }}</span></div>
              <template v-if="hasMixedIva">
                <div class="flex justify-between"><span class="text-gray-400">Base IVA {{ globalIva }}%</span><span>${{ subtotalIva.toFixed(2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">Base IVA 0%</span><span>${{ subtotal0.toFixed(2) }}</span></div>
              </template>
              <div class="flex justify-between"><span class="text-gray-500">IVA {{ globalIva }}%</span><span class="font-medium">${{ ivaAmount.toFixed(2) }}</span></div>
              <div v-if="advanceApplied > 0" class="flex justify-between text-green-600"><span>Anticipo</span><span>-${{ advanceApplied.toFixed(2) }}</span></div>
            </div>
            <div class="border-t pt-3 mt-3">
              <div class="flex justify-between items-baseline"><span class="text-gray-700 font-semibold">Total</span><span class="text-2xl font-bold text-gray-900">${{ totalAfterAdvance.toFixed(2) }}</span></div>
              <div v-if="Number(tip.amount) > 0" class="flex justify-between text-sm text-gray-400 mt-1"><span>+ Propina</span><span>${{ Number(tip.amount).toFixed(2) }}</span></div>
            </div>
          </div>

          <!-- Tip -->
          <div class="border-t pt-4 space-y-2">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Propina</p>
            <div class="flex gap-2">
              <div class="relative flex-1"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span><Input v-model="tip.amount" type="number" min="0" step="0.5" class="pl-7 h-9 text-sm" placeholder="0" /></div>
              <select v-model="tip.stylist_id" class="text-xs border rounded-lg px-2 bg-gray-50"><option value="">Para...</option><option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option></select>
            </div>
          </div>

          <!-- Submit -->
          <button @click="submit" :disabled="saving || !items.length || paymentDiff > 0.01"
            :class="['w-full py-3.5 rounded-xl text-sm font-bold transition-all shadow-sm',
              saving || !items.length || paymentDiff > 0.01 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-primary text-primary-foreground hover:opacity-90 active:scale-[0.98] shadow-md']">
            {{ saving ? 'Procesando...' : `Completar cobro · $${totalWithTip.toFixed(2)}` }}
          </button>

          <p v-if="paymentDiff > 0.01" class="text-xs text-red-500 text-center">Falta asignar ${{ paymentDiff.toFixed(2) }}</p>
          <p v-else-if="items.length" class="text-xs text-green-600 text-center flex items-center justify-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Listo para cobrar
          </p>
        </div>
      </div>

      <!-- MOBILE: sticky bottom summary -->
      <div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t p-4 shadow-lg z-10">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm text-gray-500">Total</span>
          <span class="text-xl font-bold">${{ totalAfterAdvance.toFixed(2) }}</span>
        </div>
        <button @click="submit" :disabled="saving || !items.length || paymentDiff > 0.01"
          :class="['w-full py-3 rounded-xl text-sm font-bold', saving || !items.length || paymentDiff > 0.01 ? 'bg-gray-100 text-gray-400' : 'bg-primary text-primary-foreground']">
          {{ saving ? 'Procesando...' : 'Completar cobro' }}
        </button>
      </div>
    </div>
  </div>
</template>
