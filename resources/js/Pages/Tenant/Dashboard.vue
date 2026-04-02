<script setup>
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

const statusColors = {
  pending: 'bg-gray-300', confirmed: 'bg-blue-400', in_progress: 'bg-green-400', completed: 'bg-green-600',
}
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
              <CardTitle class="text-base">Agenda de hoy</CardTitle>
              <Link :href="`${base}/agenda`"><Button variant="ghost" size="sm" class="text-xs">Ver completa</Button></Link>
            </div>
          </CardHeader>
          <CardContent>
            <div v-if="today_agenda?.length" class="space-y-4">
              <div v-for="stylist in today_agenda" :key="stylist.id">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: stylist.color }" />
                  <span class="text-sm font-medium">{{ stylist.name }}</span>
                  <Badge variant="secondary" class="text-[10px]">{{ stylist.appointments?.length || 0 }}</Badge>
                </div>
                <div v-if="stylist.appointments?.length" class="space-y-1 ml-5">
                  <div
                    v-for="apt in stylist.appointments"
                    :key="apt.id"
                    class="flex items-center justify-between text-sm py-1.5 px-2 rounded hover:bg-gray-50"
                  >
                    <div class="flex items-center gap-2">
                      <span :class="['w-1.5 h-1.5 rounded-full', statusColors[apt.status?.value || apt.status]]" />
                      <span class="text-gray-500 font-mono text-xs">{{ formatTime(apt.starts_at) }}</span>
                      <span class="font-medium">{{ apt.client?.first_name }} {{ apt.client?.last_name }}</span>
                      <span class="text-gray-400 text-xs">{{ apt.service?.name }}</span>
                    </div>
                    <Button
                      v-if="(apt.status?.value || apt.status) === 'pending'"
                      variant="ghost" size="sm" class="text-xs text-green-600 h-6"
                      @click="confirmAppointment(apt.id)"
                    >Confirmar</Button>
                  </div>
                </div>
                <p v-else class="text-xs text-gray-400 ml-5">Sin citas</p>
              </div>
            </div>
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
