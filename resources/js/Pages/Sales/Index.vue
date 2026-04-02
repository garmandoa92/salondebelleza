<script setup>
import { ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'
import SaleDrawer from './SaleDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  sales: Object,
  summary: Object,
  filters: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const showDrawer = ref(false)
const selectedSaleId = ref(null)

const openSale = (id) => {
  selectedSaleId.value = id
  showDrawer.value = true
}

const formatDate = (d) => new Date(d).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
const statusLabels = { draft: 'Borrador', completed: 'Completada', refunded: 'Reembolsada' }
const statusColors = { draft: 'bg-gray-100 text-gray-700', completed: 'bg-green-100 text-green-700', refunded: 'bg-red-100 text-red-700' }

const printClosing = () => window.open(`/salon/${tenantId}/print/closing/${new Date().toISOString().slice(0,10)}`, '_blank', 'width=400,height=600')
</script>

<template>
  <Head title="Ventas" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Ventas</h1>
      <div class="flex gap-2">
        <Button variant="outline" @click="printClosing">Cierre de caja</Button>
        <Link :href="`/salon/${tenantId}/ventas/nueva`"><Button>+ Nuevo cobro</Button></Link>
      </div>
    </div>

    <!-- Day summary -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
      <div class="kpi-card-primary rounded-xl p-4 text-center"><p class="t-kpi" style="color:#fff">${{ Number(summary?.total || 0).toFixed(2) }}</p><p class="kpi-label" style="color:rgba(255,255,255,0.75)">Total hoy</p></div>
      <div class="kpi-card-accent rounded-xl p-4 text-center"><p class="t-kpi" style="color:#fff">{{ summary?.count || 0 }}</p><p class="kpi-label" style="color:rgba(255,255,255,0.75)">Ventas hoy</p></div>
      <div class="kpi-card-light rounded-xl p-4 text-center"><p class="t-kpi kpi-value-primary">${{ Number(summary?.cash || 0).toFixed(2) }}</p><p class="kpi-label">Efectivo</p></div>
      <div class="kpi-card-light rounded-xl p-4 text-center"><p class="t-kpi kpi-value-primary">${{ Number(summary?.card || 0).toFixed(2) }}</p><p class="kpi-label">Tarjeta</p></div>
      <div class="kpi-card-light rounded-xl p-4 text-center"><p class="t-kpi kpi-value-primary">${{ Number(summary?.transfer || 0).toFixed(2) }}</p><p class="kpi-label">Transferencia</p></div>
    </div>

    <!-- Sales table -->
    <Card>
      <CardContent class="pt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left">
              <th class="pb-2 font-medium">Fecha</th>
              <th class="pb-2 font-medium">Cliente</th>
              <th class="pb-2 font-medium text-center">Items</th>
              <th class="pb-2 font-medium text-right">Total</th>
              <th class="pb-2 font-medium text-center">Estado</th>
              <th class="pb-2 font-medium text-center">Factura</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sale in sales.data" :key="sale.id" class="border-b last:border-0 cursor-pointer transition-colors" @mouseenter="$event.currentTarget.style.background='var(--color-primary-10)'" @mouseleave="$event.currentTarget.style.background=''" @click="openSale(sale.id)">
              <td class="py-3 text-gray-600">{{ formatDate(sale.completed_at || sale.created_at) }}</td>
              <td class="py-3 font-medium">{{ sale.client ? `${sale.client.first_name} ${sale.client.last_name}` : 'Sin cliente' }}</td>
              <td class="py-3 text-center">{{ sale.items?.length || 0 }}</td>
              <td class="py-3 text-right font-medium">${{ Number(sale.total).toFixed(2) }}</td>
              <td class="py-3 text-center">
                <span :class="['text-xs px-2 py-0.5 rounded-full', statusColors[sale.status?.value || sale.status]]">
                  {{ statusLabels[sale.status?.value || sale.status] }}
                </span>
              </td>
              <td class="py-3 text-center">
                <Badge v-if="sale.sri_invoice_id" variant="secondary" class="text-xs">Si</Badge>
                <span v-else class="text-xs text-gray-400">-</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!sales.data?.length" class="text-center py-8 text-gray-400">Sin ventas registradas</div>
      </CardContent>
    </Card>

    <SaleDrawer :open="showDrawer" :saleId="selectedSaleId" @close="showDrawer = false" />
  </div>
</template>
