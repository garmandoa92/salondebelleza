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
const invoiceData = ref({ buyer_identification_type: 'final_consumer', buyer_identification: '', buyer_name: '', buyer_email: '' })
const saving = ref(false)
const completed = ref(false)
const completedSaleId = ref(null)

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
    items.value = props.preItems.length ? props.preItems.map(i => ({ ...i })) : []
    discount.value = { enabled: false, type: 'percentage', amount: 0, reason: '' }
    tip.value = { amount: 0, stylist_id: '' }
    payments.value = [{ method: 'cash', amount: 0, received: 0 }]
    invoiceRequired.value = false
    invoiceData.value = { buyer_identification_type: 'final_consumer', buyer_identification: '', buyer_name: '', buyer_email: '' }
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
const totalWithTip = computed(() => total.value + Number(tip.value.amount || 0))

const paymentTotal = computed(() => payments.value.reduce((sum, p) => sum + Number(p.amount || 0), 0))
const paymentDiff = computed(() => Math.round((totalWithTip.value - paymentTotal.value) * 100) / 100)
const change = computed(() => {
  const cashPayment = payments.value.find(p => p.method === 'cash')
  if (!cashPayment) return 0
  return Math.max(0, Number(cashPayment.received || 0) - Number(cashPayment.amount || 0))
})

watch(total, (val) => {
  if (payments.value.length === 1) payments.value[0].amount = val + Number(tip.value.amount || 0)
})

const addItem = (type, item) => {
  items.value.push({
    type,
    reference_id: item.id,
    name: item.name,
    quantity: 1,
    unit_price: type === 'service' ? Number(item.base_price) : Number(item.sale_price),
    subtotal: type === 'service' ? Number(item.base_price) : Number(item.sale_price),
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
    })

    completedSaleId.value = data.sale_id

    if (invoiceRequired.value) {
      await axios.post(`${base}/ventas/${data.sale_id}/invoice`, invoiceData.value)
    }

    completed.value = true
    emit('completed')
  } finally { saving.value = false }
}

const paymentLabels = { cash: 'Efectivo', transfer: 'Transferencia', card_debit: 'T. Debito', card_credit: 'T. Credito', other: 'Otro' }

const initials = (name) => name?.split(' ').map(n => n?.[0]).filter(Boolean).join('').toUpperCase().slice(0, 2) || '?'
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 overflow-y-auto py-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[95vh] overflow-y-auto mx-4">
      <div class="p-5">
        <!-- Completed state -->
        <div v-if="completed" class="text-center py-12 space-y-4">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 class="text-xl font-bold">Cobro completado</h2>
          <p class="text-2xl font-bold text-green-600">${{ total.toFixed(2) }}</p>
          <p v-if="change > 0" class="text-lg text-gray-600">Vuelto: ${{ change.toFixed(2) }}</p>
          <Button @click="emit('close')">Cerrar</Button>
        </div>

        <!-- Checkout form -->
        <div v-else class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold">Cobro</h2>
            <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
          </div>

          <!-- Client header (from appointment) -->
          <div v-if="isFromAppointment && clientName" class="flex items-center gap-3 bg-blue-50 rounded-lg p-3">
            <Avatar class="h-9 w-9"><AvatarFallback class="text-xs">{{ initials(clientName) }}</AvatarFallback></Avatar>
            <div>
              <p class="font-medium text-sm">{{ clientName }}</p>
              <p class="text-xs text-blue-600">Cobro desde cita</p>
            </div>
          </div>

          <!-- Client selector (NOT from appointment) -->
          <Card v-if="!isFromAppointment">
            <CardHeader class="pb-2"><CardTitle class="text-sm">Cliente</CardTitle></CardHeader>
            <CardContent class="space-y-3">
              <div v-if="selectedClientName" class="flex items-center justify-between bg-blue-50 rounded-lg p-3">
                <div class="flex items-center gap-2">
                  <Avatar class="h-8 w-8"><AvatarFallback class="text-xs">{{ initials(selectedClientName) }}</AvatarFallback></Avatar>
                  <span class="text-sm font-medium">{{ selectedClientName }}</span>
                </div>
                <Button variant="ghost" size="sm" class="text-xs" @click="selectedClientId = null; selectedClientName = null">Cambiar</Button>
              </div>

              <template v-else>
                <Input v-model="clientSearch" placeholder="Buscar cliente por nombre o telefono..." />
                <div v-if="clientResults.length" class="border rounded-md max-h-40 overflow-y-auto">
                  <button v-for="c in clientResults" :key="c.id" @click="selectClient(c)"
                    class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b last:border-0 text-sm">
                    <span class="font-medium">{{ c.first_name }} {{ c.last_name }}</span>
                    <span class="text-gray-500 ml-2">{{ c.phone }}</span>
                  </button>
                </div>

                <div class="flex gap-2">
                  <Button variant="outline" size="sm" class="text-xs" @click="skipClient">Sin cliente</Button>
                  <Button variant="outline" size="sm" class="text-xs" @click="showNewClient = !showNewClient">
                    {{ showNewClient ? 'Cancelar' : '+ Nuevo cliente' }}
                  </Button>
                </div>

                <div v-if="showNewClient" class="border rounded-lg p-3 space-y-2">
                  <div class="grid grid-cols-2 gap-2">
                    <Input v-model="newClient.first_name" placeholder="Nombre" />
                    <Input v-model="newClient.last_name" placeholder="Apellido" />
                  </div>
                  <Input v-model="newClient.phone" placeholder="Telefono (09...)" />
                  <Button size="sm" @click="createQuickClient" :disabled="!newClient.first_name || !newClient.phone">Crear y seleccionar</Button>
                </div>
              </template>
            </CardContent>
          </Card>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Items -->
            <div class="lg:col-span-2 space-y-4">
              <Card>
                <CardHeader class="pb-2">
                  <div class="flex items-center justify-between">
                    <CardTitle class="text-sm">Items</CardTitle>
                    <div class="flex gap-1 flex-wrap">
                      <select class="text-xs border rounded px-2 py-1" @change="e => { const s = services.find(x => x.id === e.target.value); if(s) addItem('service', s); e.target.value='' }">
                        <option value="">+ Servicio</option>
                        <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }} - ${{ Number(s.base_price).toFixed(2) }}</option>
                      </select>
                      <select class="text-xs border rounded px-2 py-1" @change="e => { const p = products.find(x => x.id === e.target.value); if(p) addItem('product', p); e.target.value='' }">
                        <option value="">+ Producto</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} - ${{ Number(p.sale_price).toFixed(2) }} ({{ p.stock }})</option>
                      </select>
                      <select v-if="packages.length" class="text-xs border rounded px-2 py-1" @change="e => { const pk = packages.find(x => x.id === e.target.value); if(pk) addPackage(pk); e.target.value='' }">
                        <option value="">+ Paquete</option>
                        <option v-for="pk in packages" :key="pk.id" :value="pk.id">{{ pk.name }} - ${{ Number(pk.price).toFixed(2) }}</option>
                      </select>
                    </div>
                  </div>
                </CardHeader>
                <CardContent>
                  <table v-if="items.length" class="w-full text-sm">
                    <thead><tr class="border-b text-gray-500"><th class="text-left pb-2">Desc.</th><th class="w-16 pb-2">Cant</th><th class="w-20 pb-2">Precio</th><th class="w-20 pb-2 text-right">Subtotal</th><th class="w-8"></th></tr></thead>
                    <tbody>
                      <tr v-for="(item, i) in items" :key="i" class="border-b last:border-0">
                        <td class="py-2">
                          <span class="font-medium">{{ item.name }}</span>
                          <select v-if="item.type === 'service'" v-model="item.stylist_id" class="block text-xs border rounded px-1 py-0.5 mt-1">
                            <option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option>
                          </select>
                        </td>
                        <td><Input v-model="item.quantity" type="number" min="0.01" step="1" class="h-7 text-xs" @input="updateItemSubtotal(item)" /></td>
                        <td><Input v-model="item.unit_price" type="number" step="0.01" class="h-7 text-xs" @input="updateItemSubtotal(item)" /></td>
                        <td class="text-right font-medium">${{ Number(item.subtotal).toFixed(2) }}</td>
                        <td><button @click="removeItem(i)" class="text-red-400 hover:text-red-600 text-xs">X</button></td>
                      </tr>
                    </tbody>
                  </table>
                  <p v-else class="text-sm text-gray-400 text-center py-4">Agrega servicios o productos</p>
                </CardContent>
              </Card>

              <!-- Discount -->
              <Card>
                <CardContent class="pt-4">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="discount.enabled" class="rounded" />
                    <span class="text-sm font-medium">Aplicar descuento</span>
                  </label>
                  <div v-if="discount.enabled" class="grid grid-cols-3 gap-2 mt-3">
                    <select v-model="discount.type" class="text-sm border rounded px-2 py-1">
                      <option value="percentage">Porcentaje %</option>
                      <option value="fixed">Monto fijo $</option>
                    </select>
                    <Input v-model="discount.amount" type="number" min="0" step="0.01" class="h-8 text-sm" />
                    <Input v-model="discount.reason" placeholder="Motivo" class="h-8 text-sm" />
                  </div>
                </CardContent>
              </Card>

              <!-- Payment methods -->
              <Card>
                <CardHeader class="pb-2">
                  <div class="flex items-center justify-between">
                    <CardTitle class="text-sm">Metodo de pago</CardTitle>
                    <Button variant="ghost" size="sm" class="text-xs" @click="addPaymentMethod">+ Agregar</Button>
                  </div>
                </CardHeader>
                <CardContent class="space-y-2">
                  <div v-for="(p, i) in payments" :key="i" class="flex items-center gap-2">
                    <select v-model="p.method" class="flex-1 text-sm border rounded px-2 py-1.5">
                      <option v-for="(label, key) in paymentLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <Input v-model="p.amount" type="number" step="0.01" class="w-28 h-8 text-sm" placeholder="Monto" />
                    <Input v-if="p.method === 'cash'" v-model="p.received" type="number" step="0.01" class="w-28 h-8 text-sm" placeholder="Recibido" />
                    <button v-if="payments.length > 1" @click="removePayment(i)" class="text-red-400 text-xs">X</button>
                  </div>
                  <div class="text-sm mt-2">
                    <span v-if="paymentDiff > 0.01" class="text-red-500 font-medium">Falta: ${{ paymentDiff.toFixed(2) }}</span>
                    <span v-else-if="paymentDiff < -0.01" class="text-amber-500 font-medium">Exceso: ${{ Math.abs(paymentDiff).toFixed(2) }}</span>
                    <span v-else class="text-green-600 font-medium">Correcto</span>
                  </div>
                  <p v-if="change > 0" class="text-sm font-medium text-blue-600">Vuelto: ${{ change.toFixed(2) }}</p>
                </CardContent>
              </Card>

              <!-- Invoice -->
              <Card>
                <CardContent class="pt-4 space-y-3">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="invoiceRequired" class="rounded" />
                    <span class="text-sm font-medium">Requiere comprobante electronico</span>
                  </label>
                  <div v-if="invoiceRequired" class="space-y-2">
                    <select v-model="invoiceData.buyer_identification_type" class="w-full text-sm border rounded px-2 py-1.5">
                      <option value="final_consumer">Consumidor final</option>
                      <option value="cedula">Cedula</option>
                      <option value="RUC">RUC</option>
                      <option value="passport">Pasaporte</option>
                    </select>
                    <Input v-if="invoiceData.buyer_identification_type !== 'final_consumer'" v-model="invoiceData.buyer_identification" placeholder="Identificacion" class="h-8 text-sm" />
                    <Input v-if="invoiceData.buyer_identification_type !== 'final_consumer'" v-model="invoiceData.buyer_name" placeholder="Nombre / Razon social" class="h-8 text-sm" />
                    <Input v-model="invoiceData.buyer_email" type="email" placeholder="Email para enviar RIDE" class="h-8 text-sm" />
                  </div>
                </CardContent>
              </Card>
            </div>

            <!-- Right: Summary -->
            <div>
              <Card class="sticky top-4">
                <CardHeader><CardTitle class="text-sm">Resumen</CardTitle></CardHeader>
                <CardContent class="space-y-2 text-sm">
                  <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>${{ subtotal.toFixed(2) }}</span></div>
                  <div v-if="discountAmount > 0" class="flex justify-between text-red-500"><span>Descuento</span><span>-${{ discountAmount.toFixed(2) }}</span></div>
                  <template v-if="hasMixedIva">
                    <div class="flex justify-between"><span class="text-gray-500">Base IVA {{ globalIva }}%</span><span>${{ subtotalIva.toFixed(2) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Base IVA 0%</span><span>${{ subtotal0.toFixed(2) }}</span></div>
                  </template>
                  <div class="flex justify-between"><span class="text-gray-500">IVA {{ globalIva }}%</span><span>${{ ivaAmount.toFixed(2) }}</span></div>
                  <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Total</span><span>${{ total.toFixed(2) }}</span></div>
                  <div v-if="Number(tip.amount) > 0" class="flex justify-between text-gray-500"><span>Propina</span><span>+${{ Number(tip.amount).toFixed(2) }}</span></div>

                  <div class="border-t pt-3 mt-3 space-y-2">
                    <Label class="text-xs">Propina</Label>
                    <div class="flex gap-2">
                      <Input v-model="tip.amount" type="number" min="0" step="0.5" class="h-7 text-xs flex-1" placeholder="$0" />
                      <select v-model="tip.stylist_id" class="text-xs border rounded px-1">
                        <option value="">Para...</option>
                        <option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option>
                      </select>
                    </div>
                  </div>

                  <Button class="w-full mt-4" :disabled="saving || !items.length || paymentDiff > 0.01" @click="submit">
                    {{ saving ? 'Procesando...' : 'Completar cobro' }}
                  </Button>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
