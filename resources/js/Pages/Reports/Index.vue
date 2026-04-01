<script setup>
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  revenue: Object,
  services: Object,
  stylists: Array,
  clients: Object,
  demand: Object,
  inventory: Object,
  forecast: Array,
  period: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const periods = [
  { key: 'today', label: 'Hoy' },
  { key: 'yesterday', label: 'Ayer' },
  { key: 'last7', label: '7 dias' },
  { key: 'last30', label: '30 dias' },
  { key: 'this_month', label: 'Este mes' },
  { key: 'last_month', label: 'Mes anterior' },
]

const changePeriod = (p) => {
  router.get(`${base}/reportes`, { period: p }, { preserveState: true })
}

const pctClass = (val) => Number(val) >= 0 ? 'text-green-600' : 'text-red-600'
const pctArrow = (val) => Number(val) >= 0 ? '↑' : '↓'

const heatColor = (val) => {
  if (val === 0) return 'bg-gray-50'
  if (val <= 2) return 'bg-green-100'
  if (val <= 4) return 'bg-green-300'
  if (val <= 6) return 'bg-amber-300'
  return 'bg-red-400 text-white'
}

const initials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
const stylistTab = ref('revenue')
</script>

<template>
  <Head title="Reportes" />

  <div class="space-y-6">
    <!-- Header + Period selector -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-900">Reportes</h1>
      <div class="flex gap-1">
        <Button
          v-for="p in periods" :key="p.key"
          :variant="period?.selected === p.key ? 'default' : 'outline'"
          size="sm"
          @click="changePeriod(p.key)"
        >{{ p.label }}</Button>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <Card>
        <CardContent class="pt-4">
          <p class="text-xs text-gray-500">Ingresos</p>
          <p class="text-2xl font-bold">${{ Number(revenue?.total || 0).toFixed(2) }}</p>
          <p :class="['text-xs mt-1', pctClass(revenue?.growth)]">{{ pctArrow(revenue?.growth) }} {{ Math.abs(revenue?.growth || 0) }}% vs anterior</p>
          <p class="text-xs text-gray-400 mt-1">Ticket promedio: ${{ Number(revenue?.avg_ticket || 0).toFixed(2) }}</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-4">
          <p class="text-xs text-gray-500">Citas</p>
          <p class="text-2xl font-bold">{{ revenue?.count || 0 }}</p>
          <p class="text-xs text-gray-400 mt-1">No-show: {{ services?.no_show_rate || 0 }}%</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-4">
          <p class="text-xs text-gray-500">Clientes</p>
          <p class="text-2xl font-bold">{{ clients?.active_clients || 0 }}</p>
          <p class="text-xs text-gray-400 mt-1">{{ clients?.new_clients || 0 }} nuevos · {{ clients?.recurring_clients || 0 }} recurrentes</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-4">
          <p class="text-xs text-gray-500">Costo materiales</p>
          <p class="text-2xl font-bold">{{ inventory?.material_cost_pct || 0 }}%</p>
          <p class="text-xs text-gray-400 mt-1">de los ingresos</p>
        </CardContent>
      </Card>
    </div>

    <!-- Revenue chart (simple bar representation) -->
    <Card>
      <CardHeader><CardTitle class="text-base">Ingresos por dia</CardTitle></CardHeader>
      <CardContent>
        <div v-if="revenue?.daily?.length" class="flex items-end gap-1 h-40">
          <div v-for="d in revenue.daily" :key="d.date" class="flex-1 flex flex-col items-center gap-1">
            <div
              class="w-full bg-primary/80 rounded-t transition-all hover:bg-primary"
              :style="{ height: `${Math.max(4, (d.total / Math.max(...revenue.daily.map(x => x.total || 1))) * 120)}px` }"
              :title="`${d.label}: $${d.total.toFixed(2)}`"
            />
            <span class="text-[9px] text-gray-400">{{ d.label.split(' ')[0] }}</span>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-8">Sin datos</p>

        <div v-if="revenue?.by_method" class="flex gap-4 mt-4 text-xs text-gray-500 border-t pt-3">
          <span>Efectivo: ${{ Number(revenue.by_method.cash || 0).toFixed(2) }}</span>
          <span>Tarjeta: ${{ Number((revenue.by_method.card_debit || 0) + (revenue.by_method.card_credit || 0)).toFixed(2) }}</span>
          <span>Transferencia: ${{ Number(revenue.by_method.transfer || 0).toFixed(2) }}</span>
        </div>
      </CardContent>
    </Card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Top services -->
      <Card>
        <CardHeader><CardTitle class="text-base">Servicios mas vendidos</CardTitle></CardHeader>
        <CardContent>
          <div v-if="services?.top_services?.length" class="space-y-3">
            <div v-for="(svc, i) in services.top_services" :key="svc.name" class="flex items-center gap-3">
              <span class="text-xs text-gray-400 w-5">{{ i + 1 }}</span>
              <div class="flex-1">
                <div class="flex justify-between text-sm">
                  <span class="font-medium">{{ svc.name }}</span>
                  <span class="text-gray-500">{{ svc.count }}x — ${{ Number(svc.revenue).toFixed(0) }}</span>
                </div>
                <div class="w-full h-1.5 bg-gray-100 rounded-full mt-1">
                  <div class="h-1.5 bg-primary rounded-full" :style="{ width: `${(svc.count / (services.top_services[0]?.count || 1)) * 100}%` }" />
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 text-center py-4">Sin datos</p>
        </CardContent>
      </Card>

      <!-- Demand heatmap -->
      <Card>
        <CardHeader><CardTitle class="text-base">Mapa de calor de demanda</CardTitle></CardHeader>
        <CardContent>
          <div v-if="demand?.heatmap?.length" class="overflow-x-auto">
            <table class="w-full text-[10px]">
              <thead>
                <tr>
                  <th class="text-left text-gray-400 pb-1">Hora</th>
                  <th v-for="d in demand.days" :key="d" class="text-center text-gray-400 pb-1">{{ d }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in demand.heatmap" :key="row.hour">
                  <td class="text-gray-500 pr-2 py-0.5">{{ row.hour }}</td>
                  <td v-for="d in demand.days" :key="d" class="text-center py-0.5">
                    <span
                      :class="['inline-block w-6 h-5 rounded text-[9px] leading-5', heatColor(row[d])]"
                      :title="`${d} ${row.hour}: ${row[d]} citas`"
                    >{{ row[d] || '' }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="demand.peak?.count" class="text-xs text-gray-500 mt-2">
              Hora pico: {{ demand.peak.day }} {{ demand.peak.hour }} ({{ demand.peak.count }} citas)
            </p>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Stylists ranking -->
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle class="text-base">Ranking de estilistas</CardTitle>
          <div class="flex gap-1">
            <Button v-for="t in [{ key: 'revenue', l: 'Ingresos' }, { key: 'services', l: 'Servicios' }, { key: 'completion', l: 'Completitud' }]"
              :key="t.key" :variant="stylistTab === t.key ? 'default' : 'outline'" size="sm" class="text-xs" @click="stylistTab = t.key"
            >{{ t.l }}</Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <table v-if="stylists?.length" class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium w-8">#</th>
              <th class="pb-2 font-medium">Estilista</th>
              <th class="pb-2 font-medium text-right">{{ stylistTab === 'revenue' ? 'Ingresos' : stylistTab === 'services' ? 'Servicios' : 'Completitud' }}</th>
              <th class="pb-2 font-medium text-right">Ticket promedio</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(s, i) in stylists" :key="s.id" class="border-b last:border-0">
              <td class="py-2 text-gray-400">{{ i + 1 }}</td>
              <td class="py-2">
                <div class="flex items-center gap-2">
                  <Avatar class="h-7 w-7"><AvatarFallback class="text-[10px] text-white" :style="{ backgroundColor: s.color }">{{ initials(s.name) }}</AvatarFallback></Avatar>
                  <span class="font-medium">{{ s.name }}</span>
                </div>
              </td>
              <td class="py-2 text-right font-medium">
                <template v-if="stylistTab === 'revenue'">${{ Number(s.revenue).toFixed(2) }}</template>
                <template v-else-if="stylistTab === 'services'">{{ s.services_count }}</template>
                <template v-else>{{ s.completion_rate }}%</template>
              </td>
              <td class="py-2 text-right text-gray-500">${{ Number(s.avg_ticket).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </CardContent>
    </Card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Client retention -->
      <Card>
        <CardHeader><CardTitle class="text-base">Retencion de clientes</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-3 gap-3 text-center">
            <div class="bg-green-50 rounded-lg p-3">
              <p class="text-xl font-bold text-green-700">{{ clients?.recurring_clients || 0 }}</p>
              <p class="text-xs text-gray-500">Recurrentes</p>
            </div>
            <div class="bg-amber-50 rounded-lg p-3">
              <p class="text-xl font-bold text-amber-700">{{ clients?.at_risk_clients?.length || 0 }}</p>
              <p class="text-xs text-gray-500">En riesgo</p>
            </div>
            <div class="bg-red-50 rounded-lg p-3">
              <p class="text-xl font-bold text-red-700">{{ clients?.churn_count || 0 }}</p>
              <p class="text-xs text-gray-500">Perdidos (60d+)</p>
            </div>
          </div>

          <div v-if="clients?.by_source && Object.keys(clients.by_source).length" class="border-t pt-3">
            <p class="text-xs text-gray-500 mb-2">Fuente de nuevos clientes</p>
            <div class="flex flex-wrap gap-2">
              <Badge v-for="(count, source) in clients.by_source" :key="source" variant="secondary" class="text-xs">
                {{ source }}: {{ count }}
              </Badge>
            </div>
          </div>

          <div v-if="clients?.at_risk_clients?.length" class="border-t pt-3">
            <p class="text-xs text-gray-500 mb-2">Clientes en riesgo (45-89 dias sin visita)</p>
            <div class="space-y-1 max-h-40 overflow-y-auto">
              <div v-for="c in clients.at_risk_clients.slice(0, 5)" :key="c.id" class="flex justify-between text-sm">
                <span>{{ c.name }}</span>
                <span class="text-amber-600 text-xs">{{ c.days_since }}d sin venir</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Forecast -->
      <Card>
        <CardHeader><CardTitle class="text-base">Forecast proximos 7 dias</CardTitle></CardHeader>
        <CardContent>
          <div v-if="forecast?.length" class="space-y-2">
            <div v-for="day in forecast" :key="day.date"
              :class="['flex items-center justify-between py-2 px-3 rounded-lg text-sm',
                day.is_today ? 'bg-blue-50' : day.occupancy > 70 ? 'bg-green-50' : day.occupancy < 30 ? 'bg-amber-50' : '']"
            >
              <div>
                <p class="font-medium" :class="{ 'text-blue-700': day.is_today }">{{ day.label }}</p>
                <p class="text-xs text-gray-500">{{ day.confirmed }} citas confirmadas</p>
              </div>
              <div class="text-right">
                <p class="font-medium">${{ Number(day.estimated_revenue).toFixed(0) }}</p>
                <div class="flex items-center gap-1">
                  <div class="w-16 h-1.5 bg-gray-100 rounded-full">
                    <div
                      class="h-1.5 rounded-full"
                      :class="day.occupancy > 70 ? 'bg-green-500' : day.occupancy < 30 ? 'bg-amber-500' : 'bg-blue-500'"
                      :style="{ width: `${day.occupancy}%` }"
                    />
                  </div>
                  <span class="text-[10px] text-gray-400">{{ day.occupancy }}%</span>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Inventory summary -->
    <Card>
      <CardHeader><CardTitle class="text-base">Inventario</CardTitle></CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <p class="text-xs text-gray-500 mb-2">Productos mas consumidos</p>
            <div v-if="inventory?.top_consumed?.length" class="space-y-1">
              <div v-for="p in inventory.top_consumed" :key="p.name" class="flex justify-between text-sm">
                <span>{{ p.name }}</span>
                <span class="text-gray-500">{{ p.consumed }} {{ p.unit }}</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400">Sin consumos</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-2">Productos mas vendidos</p>
            <div v-if="inventory?.top_sold?.length" class="space-y-1">
              <div v-for="p in inventory.top_sold" :key="p.name" class="flex justify-between text-sm">
                <span>{{ p.name }}</span>
                <span class="text-gray-500">${{ Number(p.revenue).toFixed(0) }}</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400">Sin ventas de productos</p>
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">{{ inventory?.no_movement_count || 0 }} productos sin movimiento en el periodo</p>
      </CardContent>
    </Card>
  </div>
</template>
