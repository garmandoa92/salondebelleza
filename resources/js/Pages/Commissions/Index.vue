<script setup>
import { ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  summary: Array,
  period: Object,
  totals: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const periodStart = ref(props.period?.start || '')
const periodEnd = ref(props.period?.end || '')

const applyPeriod = () => {
  router.get(`${base}/comisiones`, { period_start: periodStart.value, period_end: periodEnd.value }, { preserveState: true })
}

const payAll = async (stylistId) => {
  const ids = props.summary
    .filter(s => s.stylist_id === stylistId && s.status === 'pending')
    .map(s => s.commission_ids || [])
    .flat()

  // For now, pay via the stylist detail page which has individual commission IDs
  router.visit(`${base}/comisiones/estilista/${stylistId}?period_start=${periodStart.value}&period_end=${periodEnd.value}`)
}

const statusLabels = { pending: 'Pendiente', paid: 'Pagado', empty: 'Sin comisiones' }
const statusColors = { pending: 'bg-amber-100 text-amber-700', paid: 'bg-green-100 text-green-700', empty: 'bg-gray-100 text-gray-500' }

const initials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
</script>

<template>
  <Head title="Comisiones" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Comisiones</h1>
    </div>

    <!-- Period selector -->
    <Card>
      <CardContent class="pt-4">
        <div class="flex flex-wrap items-end gap-3">
          <div class="space-y-1">
            <Label class="text-xs">Inicio del periodo</Label>
            <Input v-model="periodStart" type="date" class="w-40" />
          </div>
          <div class="space-y-1">
            <Label class="text-xs">Fin del periodo</Label>
            <Input v-model="periodEnd" type="date" class="w-40" />
          </div>
          <Button @click="applyPeriod" size="sm">Aplicar</Button>

          <div class="ml-auto flex gap-4 text-sm">
            <div>
              <span class="text-gray-500">Total vendido:</span>
              <span class="font-bold ml-1">${{ Number(totals?.sold || 0).toFixed(2) }}</span>
            </div>
            <div>
              <span class="text-gray-500">Total comisiones:</span>
              <span class="font-bold ml-1 text-primary">${{ Number(totals?.commissions || 0).toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Summary table -->
    <Card>
      <CardContent class="pt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium">Estilista</th>
              <th class="pb-2 font-medium text-center">Servicios</th>
              <th class="pb-2 font-medium text-right">Total vendido</th>
              <th class="pb-2 font-medium text-center">% Promedio</th>
              <th class="pb-2 font-medium text-right">Comision</th>
              <th class="pb-2 font-medium text-center">Estado</th>
              <th class="pb-2 font-medium text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in summary" :key="s.stylist_id" class="border-b last:border-0 hover:bg-gray-50">
              <td class="py-3">
                <div class="flex items-center gap-2">
                  <Avatar class="h-8 w-8">
                    <AvatarFallback class="text-xs text-white" :style="{ backgroundColor: s.stylist_color }">{{ initials(s.stylist_name) }}</AvatarFallback>
                  </Avatar>
                  <span class="font-medium">{{ s.stylist_name }}</span>
                </div>
              </td>
              <td class="py-3 text-center">{{ s.services_count }}</td>
              <td class="py-3 text-right">${{ Number(s.total_sold).toFixed(2) }}</td>
              <td class="py-3 text-center">{{ s.avg_rate }}%</td>
              <td class="py-3 text-right font-bold text-primary">${{ Number(s.commission_amount).toFixed(2) }}</td>
              <td class="py-3 text-center">
                <Badge :class="statusColors[s.status]" class="text-xs">{{ statusLabels[s.status] }}</Badge>
              </td>
              <td class="py-3 text-right">
                <Link :href="`${base}/comisiones/estilista/${s.stylist_id}?period_start=${periodStart}&period_end=${periodEnd}`">
                  <Button variant="ghost" size="sm" class="text-xs">Detalle</Button>
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!summary?.length" class="text-center py-8 text-gray-400">Sin comisiones en este periodo</div>
      </CardContent>
    </Card>
  </div>
</template>
