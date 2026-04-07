<script setup>
import { ref, computed, watch, onMounted } from 'vue'
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

// Photos
import { useImageCompressor } from '@/Composables/useImageCompressor'
const { compress } = useImageCompressor()
const clientPhotos = ref({})
const lightboxPhoto = ref(null)

const loadClientPhotos = async () => {
  try {
    const { data } = await axios.get(`${base}/clientes/${props.client.id}/photos`)
    clientPhotos.value = data
  } catch {}
}
const totalPhotos = computed(() => Object.values(clientPhotos.value).reduce((s, arr) => s + arr.length, 0))
const typeBadgeColor = (t) => ({ before: 'bg-amber-100 text-amber-700', after: 'bg-green-100 text-green-700', reference: 'bg-blue-100 text-blue-700', other: 'bg-gray-100 text-gray-600' }[t] || 'bg-gray-100')
const typePhotoLabel = (t) => ({ before: 'ANTES', after: 'DESPUES', reference: 'REF', other: 'OTRA' }[t] || t)

// Historial: grouped by month, expandable
const expandedApt = ref(null)
const toggleExpand = (id) => { expandedApt.value = expandedApt.value === id ? null : id }

const groupedByMonth = computed(() => {
  if (!props.pastAppointments?.length) return []
  const groups = {}
  for (const apt of props.pastAppointments) {
    const d = new Date(apt.starts_at)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    const label = d.toLocaleDateString('es-EC', { month: 'long', year: 'numeric' })
    if (!groups[key]) groups[key] = { key, label, apts: [], total: 0 }
    groups[key].apts.push(apt)
    groups[key].total += Number(apt.service?.base_price || 0)
  }
  return Object.values(groups).sort((a, b) => b.key.localeCompare(a.key))
})

// Diagnosis inline editing
import { useDiagnosis } from '@/Composables/useDiagnosis'
const diagState = useDiagnosis(base)
const diagForm = ref({})

const loadDiagForApt = async (aptId) => {
  await diagState.load(aptId)
  if (diagState.diagnosis.value) {
    diagForm.value = { ...diagState.diagnosis.value }
  } else {
    diagForm.value = { initial_condition: '', products_used: [], technique: '', temperature: '', exposure_time: '', result: '', next_visit_notes: '', internal_notes: '' }
  }
}

const saveDiag = async (aptId) => {
  await diagState.save(aptId, diagForm.value)
}

watch(expandedApt, (id) => { if (id) loadDiagForApt(id) })

// Upload photo from historial
const uploadingHistPhoto = ref(false)
const uploadHistPhoto = async (aptId, type) => {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/*'
  input.onchange = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    uploadingHistPhoto.value = true
    try {
      const compressed = await compress(file)
      const fd = new FormData()
      fd.append('photo', compressed)
      fd.append('type', type)
      await axios.post(`${base}/agenda/appointments/${aptId}/photos`, fd)
      loadClientPhotos()
    } finally { uploadingHistPhoto.value = false }
  }
  input.click()
}

// Previous visit notes
const getPrevVisitNotes = (aptId) => {
  const apts = props.pastAppointments || []
  const idx = apts.findIndex(a => a.id === aptId)
  if (idx < 0 || idx >= apts.length - 1) return null
  return apts[idx + 1]?.diagnosis?.next_visit_notes || null
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

// Warranties
const warranties = ref([])
const loadWarranties = async () => {
  try {
    const { data } = await axios.get(`${base}/warranties/client/${props.client.id}`)
    warranties.value = data
  } catch {}
}

const voidWarranty = async (id) => {
  const reason = prompt('Motivo de anulacion:')
  if (!reason) return
  await axios.post(`${base}/warranties/${id}/void`, { reason })
  loadWarranties()
}

onMounted(() => { loadPackages(); loadAdvances(); loadClientPhotos(); loadWarranties() })

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

const openWhatsapp = () => window.open(`https://wa.me/593${props.client.phone?.replace(/^0/, '').replace(/\D/g, '')}`, '_blank')
const printInvoice = (invoiceId) => window.open(`${base}/print/invoice/${invoiceId}`, '_blank')
const printSaleReceipt = (saleId) => window.open(`${base}/print/sale/${saleId}`, '_blank')

const tabItems = [
  { key: 'historial', label: 'Historial', count: () => props.pastAppointments?.length || 0 },
  { key: 'compras', label: 'Compras', count: () => props.sales?.length || 0 },
  { key: 'saldo', label: 'Saldo', count: () => advances.value.length },
  { key: 'paquetes', label: 'Paquetes', count: () => clientPackages.value.length },
  { key: 'fotos', label: 'Fotos', count: () => totalPhotos.value },
  { key: 'garantias', label: 'Garantias', count: () => warranties.value.length },
  { key: 'futuras', label: 'Futuras', count: () => props.futureAppointments?.length || 0 },
]
</script>

<template>
  <Head :title="`${client.first_name} ${client.last_name}`" />

  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Link :href="`${base}/clientes`" class="text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </Link>
        <h1>{{ client.first_name }} {{ client.last_name }}</h1>
      </div>
      <div class="flex gap-2">
        <Link :href="`${base}/clientes/${client.id}/edit`"><Button variant="outline" size="sm">Editar</Button></Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- ===== LEFT PANEL ===== -->
      <div class="space-y-4">
        <!-- Profile card -->
        <Card>
          <CardContent class="pt-6">
            <!-- Avatar + Name -->
            <div class="text-center">
              <div class="relative w-20 h-20 mx-auto">
                <img v-if="client.photo_path" :src="`/storage/${client.photo_path}`" class="w-20 h-20 rounded-full object-cover" />
                <div v-else class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-bold text-white" style="background-color: var(--color-primary);">
                  {{ initials }}
                </div>
              </div>
              <h2 class="mt-3" style="font-size:20px; font-weight:600; color:#1A2420;">{{ client.first_name }} {{ client.last_name }}</h2>
              <a v-if="client.phone" :href="`https://wa.me/593${client.phone?.replace(/^0/, '')}`" target="_blank"
                class="t-action text-[15px] hover:underline">{{ client.phone }}</a>
              <div v-if="client.tags?.length" class="flex justify-center gap-1 mt-2">
                <span v-for="tag in client.tags" :key="tag" class="text-[10px] font-semibold px-2 py-0.5 rounded-full" style="background-color: var(--color-primary-10); color: var(--color-primary);">{{ tag }}</span>
              </div>
            </div>

            <!-- Quick actions -->
            <div class="flex gap-2 mt-4">
              <Button variant="outline" size="sm" class="flex-1 text-xs border-[var(--color-primary)] text-[var(--color-primary)]" @click="openWhatsapp">WhatsApp</Button>
              <Link :href="`${base}/agenda`" class="flex-1"><Button variant="outline" size="sm" class="w-full text-xs border-[var(--color-primary)] text-[var(--color-primary)]">Agendar</Button></Link>
              <Link :href="`${base}/ventas/nueva`" class="flex-1"><Button size="sm" class="w-full text-xs">Cobrar</Button></Link>
            </div>
          </CardContent>
        </Card>

        <!-- Metrics cards -->
        <div class="grid grid-cols-2 gap-3">
          <div class="kpi-card-primary rounded-xl p-3 text-center">
            <p class="t-kpi" style="color:#fff; font-size:22px;">{{ metrics.total_visits }}</p>
            <p class="kpi-label" style="color:rgba(255,255,255,0.75);">Visitas</p>
          </div>
          <div class="kpi-card-accent rounded-xl p-3 text-center">
            <p class="t-kpi" style="color:#fff; font-size:22px;">${{ Number(metrics.total_spent).toFixed(0) }}</p>
            <p class="kpi-label" style="color:rgba(255,255,255,0.75);">Gastado</p>
          </div>
          <div class="kpi-card-light rounded-xl p-3 text-center">
            <p class="t-kpi kpi-value-primary" style="font-size:22px;">${{ Number(metrics.avg_ticket).toFixed(0) }}</p>
            <p class="kpi-label">Ticket prom.</p>
          </div>
          <div class="kpi-card-light-accent rounded-xl p-3 text-center">
            <p class="t-name truncate">{{ metrics.favorite_service || '-' }}</p>
            <p class="kpi-label mt-1">Favorito</p>
          </div>
        </div>

        <!-- Balance -->
        <div v-if="clientBalance > 0" class="rounded-xl p-4" style="background-color: var(--color-primary-10); border: 1px solid var(--color-primary-15);">
          <p class="kpi-label" style="color: var(--color-primary);">Saldo a favor</p>
          <p class="t-kpi mt-1" style="color: var(--color-primary);">${{ clientBalance.toFixed(2) }}</p>
          <button @click="activeTab = 'saldo'" class="t-action text-xs mt-1 hover:underline">Ver movimientos</button>
        </div>

        <!-- Info card -->
        <Card>
          <CardContent class="pt-4 space-y-3">
            <div v-if="client.email" class="flex justify-between items-center">
              <span class="t-label">Email</span>
              <span class="t-name text-right truncate ml-2">{{ client.email }}</span>
            </div>
            <div v-if="client.cedula" class="flex justify-between items-center">
              <span class="t-label">Cedula</span>
              <span class="t-name">{{ client.cedula }}</span>
            </div>
            <div v-if="client.birthday" class="flex justify-between items-center">
              <span class="t-label">Cumpleanos</span>
              <span class="t-name">
                {{ formatDate(client.birthday) }}
                <span v-if="daysToBirthday() <= 30" class="t-action text-[11px] ml-1">(en {{ daysToBirthday() }}d)</span>
              </span>
            </div>
            <div v-if="client.preferred_stylist" class="flex justify-between items-center">
              <span class="t-label">Estilista fav.</span>
              <span class="t-name">{{ client.preferred_stylist.name }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="t-label">Fuente</span>
              <span class="t-name">{{ client.source }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="t-label">Puntos</span>
              <span class="t-name">{{ client.loyalty_points }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Allergies -->
        <div v-if="client.allergies" class="bg-red-50 border border-red-200 rounded-xl p-4">
          <p class="text-xs font-bold text-red-700 uppercase tracking-wider mb-1">Alergias</p>
          <p class="text-sm text-red-600">{{ client.allergies }}</p>
        </div>

        <!-- Notes -->
        <Card v-if="client.notes">
          <CardContent class="pt-4">
            <p class="kpi-label mb-2">Notas del equipo</p>
            <p class="text-sm text-gray-700 leading-relaxed">{{ client.notes }}</p>
          </CardContent>
        </Card>
      </div>

      <!-- ===== RIGHT PANEL ===== -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Tab nav with counters -->
        <div class="flex border-b overflow-x-auto">
          <button
            v-for="tab in tabItems"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5',
              activeTab === tab.key ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-gray-500 hover:text-gray-700']"
          >
            {{ tab.label }}
            <span v-if="tab.count()" :class="['text-[10px] font-bold px-1.5 py-0.5 rounded-full',
              activeTab === tab.key ? 'bg-[var(--color-primary-10)] text-[var(--color-primary)]' : 'bg-gray-100 text-gray-500']">{{ tab.count() }}</span>
          </button>
        </div>

        <!-- Tab: Historial (grouped by month, expandable) -->
        <div v-if="activeTab === 'historial'" class="space-y-4">
          <template v-if="groupedByMonth.length">
            <div v-for="group in groupedByMonth" :key="group.key">
              <!-- Month header -->
              <div class="flex items-center justify-between px-4 py-2 rounded-lg mb-2" style="background: #F4F9F7;">
                <span class="text-sm font-semibold text-gray-800 capitalize">{{ group.label }}</span>
                <span class="t-meta">{{ group.apts.length }} visita{{ group.apts.length > 1 ? 's' : '' }} · ${{ group.total.toFixed(2) }}</span>
              </div>

              <!-- Appointments -->
              <Card v-for="apt in group.apts" :key="apt.id" class="mb-2">
                <CardContent class="pt-3 pb-3">
                  <!-- Collapsed row -->
                  <div class="flex items-start gap-3 cursor-pointer" @click="toggleExpand(apt.id)">
                    <div class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0" :style="{ backgroundColor: apt.stylist?.color || '#94a3b8' }" />
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span style="font-size:14px; font-weight:600; color:#1A2420;">{{ apt.service?.name }}</span>
                        <span :class="['text-[10px] font-semibold px-2 py-0.5 rounded-full', statusColors[apt.status?.value || apt.status]]">{{ statusLabels[apt.status?.value || apt.status] }}</span>
                      </div>
                      <p class="t-meta mt-0.5">{{ formatDate(apt.starts_at) }} {{ formatTime(apt.starts_at) }} — {{ apt.stylist?.name }} · {{ apt.service?.duration_minutes || 30 }}min</p>
                      <div class="flex items-center gap-2 mt-1">
                        <span v-if="clientPhotos[apt.id]?.length" class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-blue-50 text-blue-600">📷 {{ clientPhotos[apt.id].length }} fotos</span>
                        <span v-if="apt.diagnosis" class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-green-50 text-green-600">📋 Con diagnostico</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                      <p class="t-money" style="font-size:15px;">${{ Number(apt.service?.base_price || 0).toFixed(2) }}</p>
                      <svg :class="['w-4 h-4 text-gray-400 transition-transform', expandedApt === apt.id && 'rotate-180']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                  </div>

                  <!-- Expanded detail -->
                  <div v-if="expandedApt === apt.id" class="mt-4 space-y-4 border-t pt-4">
                    <!-- Previous visit notes banner -->
                    <div v-if="getPrevVisitNotes(apt.id)" class="rounded-lg p-3" style="background: #E8F4F0;">
                      <p class="text-xs font-semibold text-gray-700 mb-1">📋 Nota de la visita anterior</p>
                      <p class="text-sm text-gray-600 italic">"{{ getPrevVisitNotes(apt.id) }}"</p>
                    </div>

                    <!-- Diagnosis section -->
                    <div class="rounded-lg p-4" style="background: #F4F9F7; border-left: 3px solid var(--color-primary);">
                      <p class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Diagnostico y notas</p>

                      <template v-if="diagState.diagnosis.value && !diagState.editing.value">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                          <div v-if="diagState.diagnosis.value.initial_condition"><span class="t-label block">Condicion inicial</span><span class="t-name">{{ diagState.diagnosis.value.initial_condition }}</span></div>
                          <div v-if="diagState.diagnosis.value.technique"><span class="t-label block">Tecnica aplicada</span><span class="t-name">{{ diagState.diagnosis.value.technique }}</span></div>
                          <div v-if="diagState.diagnosis.value.temperature"><span class="t-label block">Temperatura</span><span class="t-name">{{ diagState.diagnosis.value.temperature }}</span></div>
                          <div v-if="diagState.diagnosis.value.exposure_time"><span class="t-label block">Duracion procedimiento</span><span class="t-name">{{ diagState.diagnosis.value.exposure_time }}</span></div>
                        </div>
                        <p v-if="diagState.diagnosis.value.result" class="text-sm text-gray-700 mt-2">{{ diagState.diagnosis.value.result }}</p>
                        <p v-if="diagState.diagnosis.value.next_visit_notes" class="text-sm text-green-700 mt-2 italic">"{{ diagState.diagnosis.value.next_visit_notes }}"</p>
                        <button @click="diagState.editing.value = true; diagForm = { ...diagState.diagnosis.value }" class="t-action text-xs mt-2">Editar</button>
                      </template>

                      <template v-else-if="diagState.editing.value">
                        <div class="space-y-2">
                          <div class="grid grid-cols-2 gap-2">
                            <div><label class="t-label block mb-1">Condicion inicial</label><input v-model="diagForm.initial_condition" class="w-full text-sm border rounded-lg px-3 py-1.5" placeholder="Ej: cabello poroso, piel sensible, unas quebradizas..." /></div>
                            <div><label class="t-label block mb-1">Tecnica aplicada</label><input v-model="diagForm.technique" class="w-full text-sm border rounded-lg px-3 py-1.5" placeholder="Ej: balayage, presion profunda, esmaltado..." /></div>
                            <div><label class="t-label block mb-1">Temperatura</label><input v-model="diagForm.temperature" class="w-full text-sm border rounded-lg px-3 py-1.5" placeholder="Ej: 230°C, frio/caliente..." /></div>
                            <div><label class="t-label block mb-1">Duracion procedimiento</label><input v-model="diagForm.exposure_time" class="w-full text-sm border rounded-lg px-3 py-1.5" placeholder="Ej: 45 min, 1h 30min..." /></div>
                          </div>
                          <div><label class="t-label block mb-1">Resultado obtenido</label><textarea v-model="diagForm.result" rows="2" class="w-full text-sm border rounded-lg px-3 py-1.5" placeholder="Describe el resultado del servicio..." /></div>
                          <div><label class="t-label block mb-1">Nota para proxima visita</label><textarea v-model="diagForm.next_visit_notes" rows="2" class="w-full text-sm border rounded-lg px-3 py-1.5" placeholder="Recomendaciones para la proxima vez..." /></div>
                          <div><label class="t-label block mb-1">Notas internas</label><textarea v-model="diagForm.internal_notes" rows="1" class="w-full text-sm border rounded-lg px-3 py-1.5" placeholder="Solo visible para el equipo" /></div>
                          <div class="flex gap-2"><Button size="sm" @click="saveDiag(apt.id)">Guardar</Button><Button variant="outline" size="sm" @click="diagState.editing.value = false">Cancelar</Button></div>
                        </div>
                      </template>

                      <template v-else>
                        <p class="text-sm text-gray-400">Sin diagnostico registrado</p>
                        <button @click="diagState.editing.value = true" class="t-action text-xs mt-1">+ Agregar diagnostico</button>
                      </template>
                    </div>

                    <!-- Photos section -->
                    <div>
                      <p class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Fotos de la visita</p>
                      <div class="flex gap-2 flex-wrap items-start">
                        <div v-for="p in (clientPhotos[apt.id] || [])" :key="p.id" class="relative">
                          <img :src="`/storage/${p.thumbnail_path || p.photo_path}`" class="w-20 h-20 object-cover rounded-lg cursor-pointer hover:opacity-90" @click="lightboxPhoto = p" />
                          <span :class="[typeBadgeColor(p.type), 'absolute top-1 left-1 text-[8px] font-bold px-1 py-0.5 rounded']">{{ typePhotoLabel(p.type) }}</span>
                        </div>
                        <button @click="uploadHistPhoto(apt.id, 'before')" :disabled="uploadingHistPhoto" class="w-20 h-20 rounded-lg border-2 border-dashed border-amber-300 text-amber-600 flex flex-col items-center justify-center text-[10px] font-medium hover:bg-amber-50">📷<span>Antes</span></button>
                        <button @click="uploadHistPhoto(apt.id, 'after')" :disabled="uploadingHistPhoto" class="w-20 h-20 rounded-lg border-2 border-dashed border-green-300 text-green-600 flex flex-col items-center justify-center text-[10px] font-medium hover:bg-green-50">📷<span>Despues</span></button>
                      </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-2 border-t">
                      <Button v-if="apt.sale?.sri_invoice_id" variant="ghost" size="sm" class="text-xs" @click="printInvoice(apt.sale.sri_invoice_id)">Ver factura</Button>
                      <Button v-if="apt.sale" variant="ghost" size="sm" class="text-xs" @click="printSaleReceipt(apt.sale.id)">Reimprimir recibo</Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </template>
          <p v-else class="text-sm text-gray-400 text-center py-8">Sin historial de citas</p>
        </div>

        <!-- Tab: Compras -->
        <Card v-if="activeTab === 'compras'">
          <CardContent class="pt-4">
            <div v-if="sales?.length" class="space-y-0">
              <div v-for="sale in sales" :key="sale.id" class="py-3 border-b border-[#EEF2F0] last:border-0">
                <div class="flex justify-between items-center">
                  <span class="t-meta">{{ formatDate(sale.created_at) }}</span>
                  <span class="t-money" style="font-size:15px;">${{ Number(sale.total).toFixed(2) }}</span>
                </div>
                <div v-for="item in sale.items" :key="item.id" class="mt-1 ml-3 flex items-center gap-2">
                  <span class="w-1 h-1 rounded-full bg-gray-300" />
                  <span class="t-name" style="font-size:12px;">{{ item.name }}</span>
                  <span class="t-meta">x{{ item.quantity }} — ${{ Number(item.subtotal).toFixed(2) }}</span>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-8">Sin compras registradas</p>
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

        <!-- Tab: Fotos -->
        <Card v-if="activeTab === 'fotos'">
          <CardContent class="pt-4">
            <template v-if="totalPhotos > 0">
              <div v-for="(groupPhotos, aptId) in clientPhotos" :key="aptId" class="mb-6 last:mb-0">
                <!-- Appointment header -->
                <div class="flex items-center gap-2 mb-3 pb-2 border-b border-gray-100">
                  <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: groupPhotos[0]?.appointment?.stylist?.color || '#94a3b8' }" />
                  <span class="text-sm font-semibold text-gray-900">{{ groupPhotos[0]?.appointment?.service?.name || 'Cita' }}</span>
                  <span class="t-meta">{{ new Date(groupPhotos[0]?.created_at).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                  <span class="t-meta">· {{ groupPhotos[0]?.appointment?.stylist?.name }}</span>
                </div>

                <!-- Before/After side by side -->
                <div class="flex gap-3 items-start">
                  <div v-for="p in groupPhotos" :key="p.id" class="relative group">
                    <img :src="`/storage/${p.thumbnail_path || p.photo_path}`"
                      class="w-32 h-32 object-cover rounded-lg cursor-pointer hover:opacity-90 transition"
                      @click="lightboxPhoto = p" />
                    <span :class="[typeBadgeColor(p.type), 'absolute top-1.5 left-1.5 text-[9px] font-bold px-1.5 py-0.5 rounded']">{{ typePhotoLabel(p.type) }}</span>
                    <p v-if="p.caption" class="text-[11px] text-gray-500 italic mt-1 max-w-[128px] truncate">{{ p.caption }}</p>
                  </div>
                  <div v-if="groupPhotos.some(p => p.type === 'before') && groupPhotos.some(p => p.type === 'after')"
                    class="self-center text-gray-300 text-xl hidden sm:block" style="margin-left: -8px; margin-right: -8px;">→</div>
                </div>
              </div>
            </template>
            <div v-else class="text-center py-10">
              <p class="text-3xl mb-2">📷</p>
              <p class="text-sm text-gray-500 mb-1">Este cliente no tiene fotos aun.</p>
              <p class="text-xs text-gray-400">Las fotos se agregan desde cada cita en la agenda.</p>
              <Link :href="`${base}/agenda`" class="t-action text-xs mt-3 inline-block">Ir a la agenda</Link>
            </div>
          </CardContent>
        </Card>

        <!-- Lightbox -->
        <div v-if="lightboxPhoto" class="fixed inset-0 z-[70] bg-black/80 flex items-center justify-center" @click.self="lightboxPhoto = null">
          <button @click="lightboxPhoto = null" class="absolute top-4 right-4 text-white/70 hover:text-white">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
          <div class="max-w-2xl max-h-[80vh] mx-4">
            <img :src="`/storage/${lightboxPhoto.photo_path}`" class="max-w-full max-h-[75vh] rounded-lg" />
            <p v-if="lightboxPhoto.caption" class="text-white/70 text-sm text-center mt-2">{{ lightboxPhoto.caption }}</p>
            <p class="text-white/40 text-xs text-center mt-1">{{ typePhotoLabel(lightboxPhoto.type) }}</p>
          </div>
        </div>

        <!-- Tab: Garantias -->
        <Card v-if="activeTab === 'garantias'">
          <CardContent class="pt-4">
            <div v-if="warranties.length" class="space-y-3">
              <div v-for="w in warranties" :key="w.id"
                :class="['border rounded-xl p-4 space-y-2', w.status === 'active' ? 'border-green-200 bg-green-50/30' : 'border-gray-100']">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span :class="['w-2.5 h-2.5 rounded-full', w.status === 'active' ? 'bg-green-500' : w.status === 'used' ? 'bg-blue-500' : w.status === 'expired' ? 'bg-gray-400' : 'bg-red-400']" />
                    <span class="text-sm font-semibold text-gray-900">{{ w.service?.name }}</span>
                  </div>
                  <Badge :class="w.status === 'active' ? 'bg-green-100 text-green-700' : w.status === 'used' ? 'bg-blue-100 text-blue-700' : w.status === 'expired' ? 'bg-gray-100 text-gray-500' : 'bg-red-100 text-red-700'" class="text-[10px] font-semibold">
                    {{ { active: 'ACTIVA', used: 'USADA', expired: 'VENCIDA', void: 'ANULADA' }[w.status] }}
                  </Badge>
                </div>
                <div class="text-xs text-gray-500">
                  <span>Emitida: {{ new Date(w.issued_at).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                  <span v-if="w.appointment?.stylist"> · Por: {{ w.appointment.stylist.name }}</span>
                </div>
                <div v-if="w.status === 'active'" class="space-y-1">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500">Vence: {{ new Date(w.expires_at).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                    <span class="font-medium text-green-700">{{ Math.max(0, Math.ceil((new Date(w.expires_at) - new Date()) / 86400000)) }} dias restantes</span>
                  </div>
                  <div class="w-full h-1.5 rounded-full bg-green-100">
                    <div class="h-1.5 rounded-full bg-green-500 transition-all"
                      :style="{ width: `${Math.max(5, 100 - (Math.ceil((new Date(w.expires_at) - new Date()) / 86400000) / (w.service?.warranty_days || 30) * 100))}%` }" />
                  </div>
                </div>
                <p v-if="w.notes" class="text-xs text-gray-600">{{ w.notes }}</p>
                <div v-if="w.status === 'active'" class="flex gap-2 pt-1">
                  <Link :href="`${base}/agenda`"><Button size="sm" class="text-xs">Agendar visita de garantia</Button></Link>
                  <Button variant="ghost" size="sm" class="text-xs text-red-500" @click="voidWarranty(w.id)">Anular</Button>
                </div>
                <p v-if="w.status === 'used'" class="text-xs text-blue-600">Usada el {{ new Date(w.updated_at).toLocaleDateString('es-EC', { day: '2-digit', month: 'short' }) }}</p>
                <p v-if="w.status === 'void'" class="text-xs text-red-500">Motivo: {{ w.voided_reason }}</p>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-8">Sin garantias registradas</p>
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
