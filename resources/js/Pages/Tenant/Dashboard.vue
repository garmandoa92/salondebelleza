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
        <Card>
          <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <CardTitle class="text-base font-medium">Agenda de hoy</CardTitle>
                <span v-if="totalCitas" class="text-[11px] font-semibold px-2 py-0.5 rounded-full kpi-card-light kpi-value-primary">{{ totalCitas }} citas</span>
              </div>
              <Link :href="`${base}/agenda`" class="text-sm font-medium hover:underline" style="color: var(--color-primary);">Ver completa</Link>
            </div>
          </CardHeader>
          <CardContent class="space-y-2">
            <template v-if="today_agenda?.length">
              <template v-for="stylist in today_agenda" :key="stylist.id">
                <!-- Stylist with appointments -->
                <div v-if="stylist.appointments?.length" class="bg-gray-50/80 rounded-xl overflow-hidden">
                  <!-- Stylist header -->
                  <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                      <span class="w-3 h-3 rounded-full ring-2 ring-white" :style="{ backgroundColor: stylist.color }" />
                      <span class="text-sm font-semibold text-gray-900">{{ stylist.name }}</span>
                    </div>
                    <span class="text-[11px] text-gray-500">{{ stylist.appointments.length }} cita{{ stylist.appointments.length > 1 ? 's' : '' }}</span>
                  </div>
                  <!-- Appointments -->
                  <div class="divide-y divide-gray-100">
                    <div
                      v-for="apt in stylist.appointments"
                      :key="apt.id"
                      :class="['px-4 py-3 transition-colors', aptStatus(apt) === 'in_progress' ? 'bg-[#F4F9F7] border-l-[3px]' : 'border-l-[3px] border-l-transparent']"
                      :style="aptStatus(apt) === 'in_progress' ? { borderLeftColor: 'var(--color-primary)' } : {}"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-sm font-bold" style="color: var(--color-primary);">{{ formatTime(apt.starts_at) }}</span>
                            <span v-if="isNow(apt)" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-green-500 text-white uppercase">Ahora</span>
                          </div>
                          <p class="text-sm font-semibold text-gray-900">{{ apt.client?.first_name }} {{ apt.client?.last_name }}</p>
                          <p class="text-xs text-gray-500">{{ apt.service?.name }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                          <span :class="[statusBadge(aptStatus(apt)), 'text-[10px] font-semibold px-2 py-0.5 rounded-full']">{{ statusLabel(aptStatus(apt)) }}</span>
                          <Button v-if="aptStatus(apt) === 'pending'" variant="ghost" size="sm" class="text-[11px] h-6 px-2 text-blue-600" @click="confirmAppointment(apt.id)">Confirmar</Button>
                          <Button v-if="aptStatus(apt) === 'confirmed'" variant="ghost" size="sm" class="text-[11px] h-6 px-2 text-green-600" @click="startAppointment(apt.id)">Llego</Button>
                          <Button v-if="aptStatus(apt) === 'in_progress'" variant="ghost" size="sm" class="text-[11px] h-6 px-2 text-green-700" @click="completeAppointment(apt.id)">Completar</Button>
                          <Button v-if="aptStatus(apt) === 'in_progress' || (aptStatus(apt) === 'completed' && apt.payment_status !== 'paid')" variant="ghost" size="sm" class="text-[11px] h-6 px-2 text-amber-600" @click="goCheckout(apt.id)">Cobrar</Button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Stylist without appointments (compact) -->
                <div v-else class="flex items-center gap-2 px-4 py-2 text-gray-400">
                  <span class="w-2.5 h-2.5 rounded-full opacity-40" :style="{ backgroundColor: stylist.color }" />
                  <span class="text-sm">{{ stylist.name }}</span>
                  <span class="text-xs">— Sin citas hoy</span>
                </div>
              </template>
            </template>
            <p v-else class="text-sm text-gray-400 text-center py-6">Sin citas para hoy</p>
          </CardContent>
        </Card>
      </div>

      <!-- Right sidebar -->
      <div class="space-y-4">
        <!-- Month metrics -->
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-base">Este mes</CardTitle></CardHeader>
          <CardContent class="space-y-3">
            <div>
              <p class="text-xs text-gray-500">Ingresos</p>
              <p class="text-xl font-bold">${{ Number(month?.revenue || 0).toFixed(2) }}</p>
              <p :class="['text-xs', Number(pctChange(month?.revenue, month?.last_month_revenue)) >= 0 ? 'text-green-600' : 'text-red-600']">
                {{ pctChange(month?.revenue, month?.last_month_revenue) }}% vs mes anterior
              </p>
            </div>

            <div v-if="month?.top_services?.length" class="border-t pt-3">
              <p class="text-xs text-gray-500 mb-2">Servicios mas vendidos</p>
              <div v-for="svc in month.top_services" :key="svc.name" class="flex items-center justify-between text-sm py-1">
                <span class="truncate">{{ svc.name }}</span>
                <div class="flex items-center gap-2">
                  <Badge variant="secondary" class="text-[10px]">{{ svc.count }}x</Badge>
                  <span class="text-xs text-gray-500">${{ Number(svc.total).toFixed(0) }}</span>
                </div>
              </div>
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
