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
      <div class="flex gap-2">
        <Link :href="`${base}/agenda`"><Button variant="outline" size="sm">Ir a agenda</Button></Link>
        <Link :href="`${base}/ventas`"><Button variant="outline" size="sm">Ver caja</Button></Link>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <Card>
        <CardContent class="pt-4">
          <p class="text-xs text-gray-500 font-medium">Ingresos hoy</p>
          <p class="text-2xl font-bold mt-1">${{ Number(kpis?.revenue_today || 0).toFixed(2) }}</p>
          <p :class="['text-xs mt-1', Number(pctChange(kpis?.revenue_today, kpis?.revenue_yesterday)) >= 0 ? 'text-green-600' : 'text-red-600']">
            {{ pctChange(kpis?.revenue_today, kpis?.revenue_yesterday) }}% vs ayer
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-4">
          <p class="text-xs text-gray-500 font-medium">Citas hoy</p>
          <p class="text-2xl font-bold mt-1">{{ kpis?.appointments_total || 0 }}</p>
          <div class="flex gap-2 text-xs mt-1">
            <span class="text-green-600">{{ kpis?.appointments_completed || 0 }} ok</span>
            <span class="text-blue-600">{{ kpis?.appointments_pending || 0 }} pend</span>
            <span class="text-red-600">{{ kpis?.appointments_cancelled || 0 }} canc</span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-4">
          <p class="text-xs text-gray-500 font-medium">Clientes atendidos</p>
          <p class="text-2xl font-bold mt-1">{{ kpis?.clients_today || 0 }}</p>
          <p class="text-xs text-gray-400 mt-1">unicos hoy</p>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="pt-4">
          <p class="text-xs text-gray-500 font-medium">Ocupacion</p>
          <p class="text-2xl font-bold mt-1">{{ kpis?.occupancy || 0 }}%</p>
          <div class="w-full h-2 bg-gray-100 rounded-full mt-2">
            <div
              class="h-2 rounded-full transition-all"
              :style="{ width: `${kpis?.occupancy || 0}%`, backgroundColor: (kpis?.occupancy || 0) > 80 ? '#ef4444' : (kpis?.occupancy || 0) > 50 ? '#f59e0b' : '#22c55e' }"
            />
          </div>
        </CardContent>
      </Card>
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

        <!-- Alerts -->
        <Card>
          <CardHeader class="pb-2"><CardTitle class="text-base">Alertas</CardTitle></CardHeader>
          <CardContent class="space-y-2 text-sm">
            <div v-if="alerts?.low_stock?.length" class="flex items-start gap-2 text-amber-600">
              <span class="mt-0.5">⚠</span>
              <div>
                <p class="font-medium">Stock bajo ({{ alerts.low_stock.length }})</p>
                <p class="text-xs text-gray-500">{{ alerts.low_stock.map(p => p.name).slice(0, 3).join(', ') }}</p>
              </div>
            </div>

            <div v-if="alerts?.rejected_invoices > 0" class="flex items-start gap-2 text-red-600">
              <span class="mt-0.5">⚠</span>
              <p class="font-medium">{{ alerts.rejected_invoices }} facturas rechazadas</p>
            </div>

            <div v-if="alerts?.unconfirmed > 0" class="flex items-start gap-2 text-blue-600">
              <span class="mt-0.5">🕐</span>
              <p class="font-medium">{{ alerts.unconfirmed }} citas sin confirmar (1h+)</p>
            </div>

            <div v-if="alerts?.inactive_clients > 0" class="flex items-start gap-2 text-gray-500">
              <span class="mt-0.5">👤</span>
              <p>{{ alerts.inactive_clients }} clientes inactivos (60+ dias)</p>
            </div>

            <p v-if="!alerts?.low_stock?.length && !alerts?.rejected_invoices && !alerts?.unconfirmed" class="text-gray-400 text-center py-2">
              Sin alertas activas
            </p>
          </CardContent>
        </Card>

        <!-- Quick actions -->
        <Card>
          <CardContent class="pt-4 space-y-2">
            <Link :href="`${base}/agenda`" class="block"><Button variant="outline" size="sm" class="w-full justify-start">+ Nueva cita</Button></Link>
            <Link :href="`${base}/clientes/create`" class="block"><Button variant="outline" size="sm" class="w-full justify-start">+ Nuevo cliente</Button></Link>
            <Link :href="`${base}/comisiones`" class="block"><Button variant="outline" size="sm" class="w-full justify-start">Ver comisiones</Button></Link>
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
