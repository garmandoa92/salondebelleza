<script setup>
import { computed } from 'vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  kpis: Object,
  today_agenda: Array,
  month: Object,
  alerts: Object,
  pending_advances: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const pctChange = (current, previous) => {
  if (!previous) return current > 0 ? '+100' : '0'
  return ((current - previous) / previous * 100).toFixed(0)
}

const formatTime = (d) => new Date(d).toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' })

const confirmAppointment = async (id) => {
  await axios.post(`${base}/agenda/appointments/${id}/confirm`)
  router.reload()
}

const startAppointment = async (id) => {
  await axios.post(`${base}/agenda/appointments/${id}/start`)
  router.reload()
}

const completeAppointment = async (id) => {
  await axios.post(`${base}/agenda/appointments/${id}/complete`)
  router.reload()
}

const goCheckout = (aptId) => {
  router.visit(`${base}/ventas/nueva?appointment_id=${aptId}`)
}

const aptStatus = (apt) => apt.status?.value || apt.status || 'pending'

const statusBadge = (s) => ({
  pending: 'bg-[#FFF3E0] text-[#E65100]',
  confirmed: 'bg-[#E3F2FD] text-[#1565C0]',
  in_progress: 'bg-[#E8F5E9] text-[#2E7D32]',
  completed: 'bg-[#E8F4F0] text-[#0F6E56]',
}[s] || 'bg-gray-100 text-gray-600')

const statusLabel = (s) => ({
  pending: 'Pendiente', confirmed: 'Confirmada', in_progress: 'En curso', completed: 'Completada',
}[s] || s)

const isNow = (apt) => {
  const now = new Date()
  const start = new Date(apt.starts_at)
  const end = new Date(apt.ends_at)
  return now >= start && now <= end
}

const totalCitas = computed(() => (props.today_agenda || []).reduce((s, st) => s + (st.appointments?.length || 0), 0))
const maxServiceCount = computed(() => Math.max(1, ...(props.month?.top_services || []).map(s => s.count)))
const currentMonth = new Date().toLocaleDateString('es-EC', { month: 'long', year: 'numeric' })
</script>

<template>
  <Head title="Dashboard" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-sm text-gray-500">{{ new Date().toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long' }) }}</p>
      </div>
      <div class="flex gap-2 flex-wrap justify-end">
        <Link :href="`${base}/comisiones`"><Button variant="outline" size="sm" class="border-[1.5px] border-[var(--color-primary)] text-[var(--color-primary)] hover:bg-[var(--color-primary-5)]">Ver comisiones</Button></Link>
        <Link :href="`${base}/clientes/create`"><Button variant="outline" size="sm" class="border-[1.5px] border-[var(--color-primary)] text-[var(--color-primary)] hover:bg-[var(--color-primary-5)]">+ Nuevo cliente</Button></Link>
        <Link :href="`${base}/agenda`"><Button variant="outline" size="sm" class="border-[1.5px] border-[var(--color-primary)] text-[var(--color-primary)] hover:bg-[var(--color-primary-5)]">Ir a agenda</Button></Link>
        <Link :href="`${base}/ventas`"><Button size="sm">Ver caja</Button></Link>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="kpi-card-primary rounded-xl p-4">
        <p class="text-xs font-medium opacity-80">Ingresos hoy</p>
        <p class="text-2xl font-bold mt-1">${{ Number(kpis?.revenue_today || 0).toFixed(2) }}</p>
        <p class="text-xs mt-1 opacity-75">{{ pctChange(kpis?.revenue_today, kpis?.revenue_yesterday) }}% vs ayer</p>
      </div>

      <div class="kpi-card-accent rounded-xl p-4">
        <p class="text-xs font-medium opacity-80">Citas hoy</p>
        <p class="text-2xl font-bold mt-1">{{ kpis?.appointments_total || 0 }}</p>
        <div class="flex gap-2 text-xs mt-1 opacity-80">
          <span>{{ kpis?.appointments_completed || 0 }} ok</span>
          <span>{{ kpis?.appointments_pending || 0 }} pend</span>
          <span>{{ kpis?.appointments_cancelled || 0 }} canc</span>
        </div>
      </div>

      <div class="kpi-card-light rounded-xl p-4">
        <p class="text-xs font-medium kpi-label">Clientes atendidos</p>
        <p class="text-2xl font-bold mt-1 kpi-value-primary">{{ kpis?.clients_today || 0 }}</p>
        <p class="text-xs mt-1 kpi-label">unicos hoy</p>
      </div>

      <div class="kpi-card-light-accent rounded-xl p-4">
        <p class="text-xs font-medium kpi-label">Ocupacion</p>
        <p class="text-2xl font-bold mt-1 kpi-value-accent">{{ kpis?.occupancy || 0 }}%</p>
        <div class="w-full h-2 rounded-full mt-2 kpi-bar-bg">
          <div class="h-2 rounded-full transition-all kpi-bar" :style="{ width: `${kpis?.occupancy || 0}%` }" />
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Today's agenda -->
      <div class="lg:col-span-2">
        <Card class="overflow-hidden">
          <!-- Fixed header -->
          <CardHeader class="pb-3 border-b">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <CardTitle class="text-base font-medium">Agenda de hoy</CardTitle>
                <span v-if="totalCitas" class="text-[11px] font-semibold px-2 py-0.5 rounded-full kpi-card-light kpi-value-primary">{{ totalCitas }} citas</span>
              </div>
              <Link :href="`${base}/agenda`" class="text-sm font-medium hover:underline" style="color: var(--color-primary);">Ver completa</Link>
            </div>
          </CardHeader>

          <!-- Scrollable list -->
          <div class="relative">
            <div class="max-h-[480px] overflow-y-auto scroll-smooth" style="scrollbar-width: thin;">
              <template v-if="today_agenda?.length">
                <template v-for="stylist in today_agenda" :key="stylist.id">
                  <!-- Stylist with appointments -->
                  <template v-if="stylist.appointments?.length">
                    <div class="sticky top-0 z-10 flex items-center gap-2 px-4 py-2 bg-gray-50 border-b border-gray-100">
                      <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: stylist.color }" />
                      <span class="text-xs font-semibold text-gray-700">{{ stylist.name }}</span>
                      <span class="text-[10px] text-gray-400">— {{ stylist.appointments.length }} cita{{ stylist.appointments.length > 1 ? 's' : '' }}</span>
                    </div>
                    <div
                      v-for="apt in stylist.appointments"
                      :key="apt.id"
                      :class="['flex items-center gap-3 px-4 h-10 border-b border-gray-50 text-sm hover:bg-gray-50/50 transition-colors',
                        aptStatus(apt) === 'in_progress' ? 'bg-[#F4F9F7] border-l-[3px]' : 'border-l-[3px] border-l-transparent']"
                      :style="aptStatus(apt) === 'in_progress' ? { borderLeftColor: 'var(--color-primary)' } : {}"
                    >
                      <!-- Time -->
                      <span class="w-14 shrink-0 text-xs font-bold" style="color: var(--color-primary);">
                        {{ formatTime(apt.starts_at) }}
                        <span v-if="isNow(apt)" class="ml-0.5 inline-block w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse" />
                      </span>
                      <!-- Client -->
                      <span class="w-28 shrink-0 text-sm font-medium text-gray-900 truncate">{{ apt.client?.first_name }} {{ apt.client?.last_name?.charAt(0) }}.</span>
                      <!-- Service -->
                      <span class="flex-1 text-xs text-gray-500 truncate">{{ apt.service?.name }}</span>
                      <!-- Status + Action -->
                      <div class="flex items-center gap-1 shrink-0">
                        <span :class="[statusBadge(aptStatus(apt)), 'text-[10px] font-semibold px-1.5 py-0.5 rounded-full whitespace-nowrap']">{{ statusLabel(aptStatus(apt)) }}</span>
                        <button v-if="aptStatus(apt) === 'pending' || aptStatus(apt) === 'confirmed'" @click="startAppointment(apt.id)"
                          class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-green-50 text-green-700 hover:bg-green-100 transition">Llego</button>
                        <button v-else-if="aptStatus(apt) === 'in_progress'" @click="completeAppointment(apt.id)"
                          class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-green-50 text-green-700 hover:bg-green-100 transition">Completar</button>
                        <button v-else-if="aptStatus(apt) === 'completed' && apt.payment_status !== 'paid'" @click="goCheckout(apt.id)"
                          class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-700 hover:bg-amber-100 transition">Cobrar</button>
                      </div>
                    </div>
                  </template>

                  <!-- Stylist without appointments -->
                  <div v-else class="flex items-center gap-2 px-4 h-8 text-gray-400 border-b border-gray-50">
                    <span class="w-2 h-2 rounded-full opacity-40" :style="{ backgroundColor: stylist.color }" />
                    <span class="text-xs">{{ stylist.name }} — Sin citas</span>
                  </div>
                </template>
              </template>
              <p v-else class="text-sm text-gray-400 text-center py-8">Sin citas para hoy</p>
            </div>
            <!-- Fade gradient at bottom -->
            <div v-if="totalCitas > 8" class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-white to-transparent pointer-events-none" />
          </div>
        </Card>
      </div>

      <!-- Right sidebar -->
      <div class="space-y-4">
        <!-- Month metrics -->
        <Card>
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-base font-medium">Este mes</CardTitle>
              <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 capitalize">{{ currentMonth }}</span>
            </div>
          </CardHeader>
          <CardContent class="space-y-4">
            <!-- Revenue comparison -->
            <div class="flex items-end justify-between">
              <div>
                <p class="text-xs text-gray-500 mb-1">Ingresos</p>
                <p class="text-2xl font-bold">${{ Number(month?.revenue || 0).toFixed(2) }}</p>
              </div>
              <div class="text-right">
                <p class="text-xs text-gray-400 mb-1">vs mes anterior</p>
                <p :class="['text-sm font-bold', Number(pctChange(month?.revenue, month?.last_month_revenue)) >= 0 ? 'text-green-600' : 'text-red-600']">
                  {{ Number(pctChange(month?.revenue, month?.last_month_revenue)) >= 0 ? '↑' : '↓' }} {{ pctChange(month?.revenue, month?.last_month_revenue) }}%
                </p>
              </div>
            </div>

            <!-- Top services with bars -->
            <div v-if="month?.top_services?.length" class="border-t pt-3">
              <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Servicios mas vendidos</p>
              <div class="space-y-3">
                <div v-for="svc in month.top_services" :key="svc.name">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-sm text-gray-800 truncate">{{ svc.name }}</span>
                    <span class="text-xs text-gray-500 shrink-0 ml-2">{{ svc.count }}x · ${{ Number(svc.total).toFixed(0) }}</span>
                  </div>
                  <div class="w-full h-2 rounded-full" style="background-color: var(--color-primary-10);">
                    <div class="h-2 rounded-full transition-all" style="background-color: var(--color-primary);"
                      :style="{ width: `${(svc.count / maxServiceCount) * 100}%` }" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer link -->
            <div class="border-t pt-3">
              <Link :href="`${base}/reportes`" class="text-sm font-medium hover:underline flex items-center gap-1" style="color: var(--color-primary);">
                ↗ Ver reporte completo del mes
              </Link>
            </div>
          </CardContent>
        </Card>

        <!-- Pending Advances -->
        <Card v-if="pending_advances?.count > 0">
          <CardHeader class="pb-2"><CardTitle class="text-base">Anticipos pendientes</CardTitle></CardHeader>
          <CardContent class="space-y-2">
            <p class="text-sm text-gray-500">{{ pending_advances.count }} anticipos · ${{ Number(pending_advances.total).toFixed(2) }} total</p>
            <div v-for="adv in pending_advances.items" :key="adv.id" class="flex items-center justify-between text-sm py-1 border-b last:border-0">
              <div>
                <p class="font-medium">{{ adv.client?.first_name }} {{ adv.client?.last_name }}</p>
                <p v-if="adv.appointment?.starts_at" class="text-xs text-gray-400">
                  {{ new Date(adv.appointment.starts_at).toLocaleDateString('es-EC', { day: '2-digit', month: 'short' }) }}
                </p>
              </div>
              <span class="font-bold text-green-600">${{ Number(adv.amount).toFixed(2) }}</span>
            </div>
          </CardContent>
        </Card>

        <!-- Alerts -->
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-base">Alertas</CardTitle></CardHeader>
          <CardContent class="space-y-2 text-sm">
            <div v-if="alerts?.rejected_invoices > 0" class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
              <span class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center text-red-600 text-xs font-bold">!</span>
              <p class="text-sm font-medium text-red-700">{{ alerts.rejected_invoices }} facturas rechazadas</p>
            </div>

            <div v-if="alerts?.low_stock?.length" class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
              <span class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 text-xs font-bold">!</span>
              <div>
                <p class="text-sm font-medium text-amber-700">Stock bajo ({{ alerts.low_stock.length }})</p>
                <p class="text-xs text-amber-600">{{ alerts.low_stock.map(p => p.name).slice(0, 3).join(', ') }}</p>
              </div>
            </div>

            <div v-if="alerts?.unconfirmed > 0" class="flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
              <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs">🕐</span>
              <p class="text-sm font-medium text-blue-700">{{ alerts.unconfirmed }} citas sin confirmar (1h+)</p>
            </div>

            <div v-if="alerts?.inactive_clients > 0" class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
              <span class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xs">👤</span>
              <p class="text-sm text-gray-600">{{ alerts.inactive_clients }} clientes inactivos (60+ dias)</p>
            </div>

            <p v-if="!alerts?.low_stock?.length && !alerts?.rejected_invoices && !alerts?.unconfirmed" class="text-gray-400 text-center py-2">
              Sin alertas activas
            </p>
          </CardContent>
        </Card>

      </div>
    </div>
  </div>
</template>
