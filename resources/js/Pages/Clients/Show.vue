<script setup>
import { ref, onMounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  client: Object,
  pastAppointments: Array,
  futureAppointments: Array,
  sales: Array,
  metrics: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`
const activeTab = ref('historial')
const clientPackages = ref([])
const advances = ref([])
const clientBalance = ref(Number(props.client.balance) || 0)

const loadPackages = async () => {
  try {
    const { data } = await axios.get(`${base}/packages/client/${props.client.id}`)
    clientPackages.value = data
  } catch {}
}

const loadAdvances = async () => {
  try {
    const { data } = await axios.get(`${base}/advances/client/${props.client.id}`)
    advances.value = data.advances
    clientBalance.value = Number(data.balance) || 0
  } catch {}
}

// Advance modal
const showAdvanceModal = ref(false)
const advanceForm = ref({ amount: '', payment_method: 'cash', reference: '', notes: '' })
const savingAdvance = ref(false)

const submitAdvance = async () => {
  savingAdvance.value = true
  try {
    await axios.post(`${base}/advances`, {
      client_id: props.client.id,
      type: 'advance',
      amount: Number(advanceForm.value.amount),
      payment_method: advanceForm.value.payment_method,
      reference: advanceForm.value.reference || null,
      notes: advanceForm.value.notes || null,
    })
    showAdvanceModal.value = false
    advanceForm.value = { amount: '', payment_method: 'cash', reference: '', notes: '' }
    loadAdvances()
  } finally { savingAdvance.value = false }
}

const refundAdvance = async (id) => {
  if (!confirm('Devolver este anticipo?')) return
  await axios.post(`${base}/advances/${id}/refund`, { notes: 'Devolucion desde ficha cliente' })
  loadAdvances()
}

onMounted(() => { loadPackages(); loadAdvances() })

const initials = ((props.client.first_name?.[0] || '') + (props.client.last_name?.[0] || '')).toUpperCase()

const formatDate = (d) => new Date(d).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' })
const formatTime = (d) => new Date(d).toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' })

const statusLabels = {
  pending: 'Pendiente', confirmed: 'Confirmada', in_progress: 'En progreso',
  completed: 'Completada', cancelled: 'Cancelada', no_show: 'No show',
}
const statusColors = {
  completed: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700',
  no_show: 'bg-orange-100 text-orange-700', pending: 'bg-gray-100 text-gray-700',
  confirmed: 'bg-blue-100 text-blue-700', in_progress: 'bg-emerald-100 text-emerald-700',
}

const daysToBirthday = () => {
  if (!props.client.birthday) return null
  const today = new Date()
  const bday = new Date(props.client.birthday)
  bday.setFullYear(today.getFullYear())
  if (bday < today) bday.setFullYear(today.getFullYear() + 1)
  return Math.ceil((bday - today) / 86400000)
}
</script>

<template>
  <Head :title="`${client.first_name} ${client.last_name}`" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">{{ client.first_name }} {{ client.last_name }}</h1>
      <div class="flex gap-2">
        <Link :href="`/salon/${tenantId}/clientes/${client.id}/edit`">
          <Button variant="outline">Editar</Button>
        </Link>
        <Link :href="`/salon/${tenantId}/clientes`">
          <Button variant="outline">Volver</Button>
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left panel - Client info -->
      <Card>
        <CardContent class="pt-6 space-y-4">
          <div class="text-center">
            <Avatar class="h-20 w-20 mx-auto">
              <AvatarFallback class="text-2xl">{{ initials }}</AvatarFallback>
            </Avatar>
            <h2 class="mt-3 font-semibold text-lg">{{ client.first_name }} {{ client.last_name }}</h2>
            <div class="flex justify-center gap-1 mt-1">
              <Badge v-for="tag in (client.tags || [])" :key="tag" variant="secondary" class="text-xs">{{ tag }}</Badge>
            </div>
          </div>

          <div class="space-y-3 text-sm border-t pt-4">
            <div class="flex justify-between">
              <span class="text-gray-500">Telefono</span>
              <a :href="`https://wa.me/593${client.phone?.replace(/^0/, '')}`" target="_blank" class="text-primary hover:underline">{{ client.phone }}</a>
            </div>
            <div v-if="client.email" class="flex justify-between">
              <span class="text-gray-500">Email</span>
              <span>{{ client.email }}</span>
            </div>
            <div v-if="client.cedula" class="flex justify-between">
              <span class="text-gray-500">Cedula</span>
              <span>{{ client.cedula }}</span>
            </div>
            <div v-if="client.birthday" class="flex justify-between">
              <span class="text-gray-500">Cumpleanos</span>
              <span>
                {{ formatDate(client.birthday) }}
                <span v-if="daysToBirthday() <= 30" class="text-primary text-xs ml-1">(en {{ daysToBirthday() }}d)</span>
              </span>
            </div>
            <div v-if="client.preferred_stylist" class="flex justify-between">
              <span class="text-gray-500">Estilista favorito</span>
              <span>{{ client.preferred_stylist.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Fuente</span>
              <span>{{ client.source }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Puntos fidelidad</span>
              <span class="font-medium">{{ client.loyalty_points }}</span>
            </div>
          </div>

          <!-- Balance -->
          <div v-if="clientBalance > 0" class="bg-green-50 border border-green-200 rounded-lg p-3">
            <p class="text-xs text-green-600 mb-1">Saldo a favor</p>
            <p class="text-xl font-bold text-green-700">${{ clientBalance.toFixed(2) }}</p>
            <button @click="activeTab = 'saldo'" class="text-xs text-green-600 hover:underline mt-1">Ver movimientos</button>
          </div>

          <!-- Allergies alert -->
          <div v-if="client.allergies" class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-xs font-medium text-red-700 flex items-center gap-1">⚠ Alergias</p>
            <p class="text-sm text-red-600 mt-1">{{ client.allergies }}</p>
          </div>

          <!-- Notes -->
          <div v-if="client.notes" class="text-sm">
            <p class="text-gray-500 text-xs mb-1">Notas del equipo</p>
            <p class="text-gray-700">{{ client.notes }}</p>
          </div>

          <!-- Metrics -->
          <div class="grid grid-cols-2 gap-3 border-t pt-4">
            <div class="text-center">
              <p class="text-2xl font-bold">{{ metrics.total_visits }}</p>
              <p class="text-xs text-gray-500">Visitas</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold">${{ Number(metrics.total_spent).toFixed(0) }}</p>
              <p class="text-xs text-gray-500">Total gastado</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold">${{ Number(metrics.avg_ticket).toFixed(0) }}</p>
              <p class="text-xs text-gray-500">Ticket promedio</p>
            </div>
            <div class="text-center">
              <p class="text-sm font-medium truncate">{{ metrics.favorite_service || '-' }}</p>
              <p class="text-xs text-gray-500">Servicio favorito</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Right panel - Tabs -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Tab nav -->
        <div class="flex border-b">
          <button
            v-for="tab in [{ key: 'historial', label: 'Historial' }, { key: 'compras', label: 'Compras' }, { key: 'saldo', label: 'Saldo' }, { key: 'paquetes', label: 'Paquetes' }, { key: 'futuras', label: 'Citas futuras' }]"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors',
              activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700']"
          >{{ tab.label }}</button>
        </div>

        <!-- Tab: Historial -->
        <Card v-if="activeTab === 'historial'">
          <CardContent class="pt-4">
            <div v-if="pastAppointments?.length" class="space-y-3">
              <div v-for="apt in pastAppointments" :key="apt.id" class="flex items-start gap-3 py-2 border-b last:border-0">
                <div class="w-2 h-2 rounded-full mt-2" :style="{ backgroundColor: apt.stylist?.color || '#94a3b8' }" />
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium">{{ apt.service?.name }}</p>
                    <span :class="['text-xs px-1.5 py-0.5 rounded-full', statusColors[apt.status?.value || apt.status]]">
                      {{ statusLabels[apt.status?.value || apt.status] }}
                    </span>
                  </div>
                  <p class="text-xs text-gray-500">
                    {{ formatDate(apt.starts_at) }} {{ formatTime(apt.starts_at) }} — {{ apt.stylist?.name }}
                  </p>
                  <p v-if="apt.notes" class="text-xs text-gray-400 mt-1">{{ apt.notes }}</p>
                </div>
                <p class="text-sm font-medium">${{ Number(apt.service?.base_price || 0).toFixed(2) }}</p>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-6">Sin historial de citas</p>
          </CardContent>
        </Card>

        <!-- Tab: Compras -->
        <Card v-if="activeTab === 'compras'">
          <CardContent class="pt-4">
            <div v-if="sales?.length" class="space-y-3">
              <div v-for="sale in sales" :key="sale.id" class="py-2 border-b last:border-0">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-500">{{ formatDate(sale.created_at) }}</span>
                  <span class="font-semibold">${{ Number(sale.total).toFixed(2) }}</span>
                </div>
                <div v-for="item in sale.items" :key="item.id" class="text-xs text-gray-500 ml-2">
                  {{ item.name }} x{{ item.quantity }} — ${{ Number(item.subtotal).toFixed(2) }}
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-6">Sin compras registradas</p>
          </CardContent>
        </Card>

        <!-- Tab: Saldo -->
        <Card v-if="activeTab === 'saldo'">
          <CardHeader>
            <div class="flex items-center justify-between">
              <CardTitle class="text-base">Saldo y anticipos</CardTitle>
              <Button size="sm" @click="showAdvanceModal = true">+ Registrar anticipo</Button>
            </div>
          </CardHeader>
          <CardContent class="pt-0">
            <div v-if="advances.length" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b text-left text-gray-500 text-xs">
                    <th class="pb-2 font-medium">Fecha</th>
                    <th class="pb-2 font-medium">Tipo</th>
                    <th class="pb-2 font-medium text-right">Monto</th>
                    <th class="pb-2 font-medium">Metodo</th>
                    <th class="pb-2 font-medium">Cita</th>
                    <th class="pb-2 font-medium text-center">Estado</th>
                    <th class="pb-2 font-medium text-right">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="a in advances" :key="a.id" class="border-b last:border-0">
                    <td class="py-2 text-gray-600">{{ new Date(a.created_at).toLocaleDateString('es-EC', { day: '2-digit', month: 'short' }) }}</td>
                    <td class="py-2">
                      <Badge :class="a.type === 'advance' ? 'bg-blue-100 text-blue-700' : a.type === 'payment' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="text-xs">
                        {{ a.type === 'advance' ? 'Anticipo' : a.type === 'payment' ? 'Abono' : 'Devolucion' }}
                      </Badge>
                    </td>
                    <td class="py-2 text-right font-medium" :class="a.type === 'refund' ? 'text-red-600' : 'text-green-600'">
                      {{ a.type === 'refund' ? '-' : '+' }}${{ Number(a.amount).toFixed(2) }}
                    </td>
                    <td class="py-2 text-gray-500 text-xs">
                      {{ { cash: 'Efectivo', transfer: 'Transferencia', card_debit: 'T. Debito', card_credit: 'T. Credito', other: 'Otro' }[a.payment_method?.value || a.payment_method] || '-' }}
                    </td>
                    <td class="py-2 text-xs text-gray-500">{{ a.appointment?.service?.name || '-' }}</td>
                    <td class="py-2 text-center">
                      <Badge :class="a.status === 'pending' || a.status?.value === 'pending' ? 'bg-amber-100 text-amber-700' : (a.status === 'applied' || a.status?.value === 'applied') ? 'bg-gray-100 text-gray-500' : 'bg-red-100 text-red-700'" class="text-xs">
                        {{ { pending: 'Pendiente', applied: 'Aplicado', refunded: 'Devuelto' }[a.status?.value || a.status] }}
                      </Badge>
                    </td>
                    <td class="py-2 text-right">
                      <Button v-if="(a.status?.value || a.status) === 'pending'" variant="ghost" size="sm" class="text-xs text-red-500" @click="refundAdvance(a.id)">
                        Devolver
                      </Button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-6">Sin movimientos de anticipos</p>
            <div v-if="advances.length" class="border-t pt-3 mt-3 flex justify-between items-center">
              <span class="text-sm text-gray-500">Saldo actual:</span>
              <span class="text-lg font-bold text-green-700">${{ clientBalance.toFixed(2) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Advance modal for client page -->
        <div v-if="showAdvanceModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="showAdvanceModal = false">
          <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-5 space-y-4">
            <h3 class="text-lg font-bold">Registrar anticipo</h3>
            <p class="text-sm text-gray-500">Cliente: {{ client.first_name }} {{ client.last_name }}</p>
            <div class="space-y-3">
              <div class="space-y-1">
                <label class="text-sm font-medium">Monto</label>
                <input v-model="advanceForm.amount" type="number" min="0.01" step="0.01" placeholder="$0.00"
                  class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" />
              </div>
              <div class="space-y-1">
                <label class="text-sm font-medium">Metodo de pago</label>
                <select v-model="advanceForm.payment_method" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                  <option value="cash">Efectivo</option>
                  <option value="transfer">Transferencia</option>
                  <option value="card_debit">T. Debito</option>
                  <option value="card_credit">T. Credito</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="text-sm font-medium">Referencia</label>
                <input v-model="advanceForm.reference" placeholder="Opcional" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" />
              </div>
            </div>
            <div class="flex gap-2 pt-2">
              <Button variant="outline" class="flex-1" @click="showAdvanceModal = false">Cancelar</Button>
              <Button class="flex-1 bg-green-600 hover:bg-green-700" :disabled="savingAdvance || !advanceForm.amount" @click="submitAdvance">
                {{ savingAdvance ? 'Guardando...' : `Registrar $${Number(advanceForm.amount || 0).toFixed(2)}` }}
              </Button>
            </div>
          </div>
        </div>

        <!-- Tab: Paquetes -->
        <Card v-if="activeTab === 'paquetes'">
          <CardContent class="pt-4 space-y-4">
            <div v-if="clientPackages.length" class="space-y-3">
              <div v-for="cp in clientPackages" :key="cp.id" class="border rounded-lg p-4 space-y-3">
                <div class="flex items-start justify-between">
                  <div>
                    <h4 class="font-semibold">{{ cp.package_name }}</h4>
                    <p class="text-xs text-gray-400 font-mono">Recibo: {{ cp.receipt_number || '-' }}</p>
                    <p class="text-xs text-gray-500">Comprado: {{ new Date(cp.purchased_at).toLocaleDateString('es-EC') }} · Vence: {{ cp.expires_at ? new Date(cp.expires_at).toLocaleDateString('es-EC') : 'Sin vencimiento' }}</p>
                  </div>
                  <Badge :class="cp.status === 'active' ? 'bg-green-100 text-green-700' : cp.status === 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'" class="text-xs">
                    {{ cp.status === 'active' ? 'Activo' : cp.status === 'completed' ? 'Completado' : 'Vencido' }}
                  </Badge>
                </div>

                <div v-for="item in cp.items" :key="item.id" class="space-y-1">
                  <div class="flex items-center justify-between text-sm">
                    <span>{{ item.service_name }}</span>
                    <span class="text-gray-500">{{ item.used_quantity }}/{{ item.total_quantity }} usadas</span>
                  </div>
                  <div class="w-full h-2 bg-gray-100 rounded-full">
                    <div class="h-2 rounded-full transition-all" :class="item.used_quantity >= item.total_quantity ? 'bg-blue-500' : 'bg-green-500'"
                      :style="{ width: `${Math.min(100, (item.used_quantity / item.total_quantity) * 100)}%` }" />
                  </div>
                  <p class="text-xs text-gray-400">
                    {{ item.total_quantity - item.used_quantity > 0 ? `Quedan ${item.total_quantity - item.used_quantity} sesiones` : 'Todas usadas' }}
                  </p>
                </div>

                <!-- Usage log -->
                <div v-if="cp.usage_logs?.length" class="border-t pt-2">
                  <p class="text-xs text-gray-500 mb-1">Historial de uso</p>
                  <div class="space-y-0.5 max-h-32 overflow-y-auto">
                    <div v-for="(log, i) in cp.usage_logs" :key="i" class="text-xs text-gray-500 flex justify-between">
                      <span>{{ log.date }} · {{ log.used_by }} · {{ log.sessions_used }} sesion</span>
                      <span>{{ log.sessions_after }}/{{ log.total }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-6">Este cliente no tiene paquetes</p>
          </CardContent>
        </Card>

        <!-- Tab: Futuras -->
        <Card v-if="activeTab === 'futuras'">
          <CardContent class="pt-4">
            <div v-if="futureAppointments?.length" class="space-y-3">
              <div v-for="apt in futureAppointments" :key="apt.id" class="flex items-center justify-between py-2 border-b last:border-0">
                <div>
                  <p class="text-sm font-medium">{{ apt.service?.name }}</p>
                  <p class="text-xs text-gray-500">{{ formatDate(apt.starts_at) }} {{ formatTime(apt.starts_at) }} — {{ apt.stylist?.name }}</p>
                </div>
                <Badge variant="secondary">{{ statusLabels[apt.status?.value || apt.status] }}</Badge>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-6">Sin citas futuras</p>
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
