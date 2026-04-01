<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  movements: Object,
  products: Array,
  filters: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const applyFilter = (key, value) => {
  router.get(`${base}/stock/movements`, { ...props.filters, [key]: value || undefined }, { preserveState: true })
}

const typeLabels = { purchase: 'Compra', consumption: 'Consumo', adjustment: 'Ajuste', sale: 'Venta', initial: 'Inicial' }
const typeColors = {
  purchase: 'bg-green-100 text-green-700', consumption: 'bg-red-100 text-red-700',
  adjustment: 'bg-blue-100 text-blue-700', sale: 'bg-purple-100 text-purple-700',
  initial: 'bg-gray-100 text-gray-700',
}

const formatDate = (d) => new Date(d).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <Head title="Movimientos de stock" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Movimientos de stock</h1>
      <Link :href="`${base}/inventario`"><Button variant="outline">Volver a inventario</Button></Link>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <select class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" :value="filters?.product_id || ''" @change="applyFilter('product_id', $event.target.value)">
        <option value="">Todos los productos</option>
        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" :value="filters?.type || ''" @change="applyFilter('type', $event.target.value)">
        <option value="">Todos los tipos</option>
        <option value="purchase">Compras</option>
        <option value="consumption">Consumos</option>
        <option value="adjustment">Ajustes</option>
        <option value="sale">Ventas</option>
        <option value="initial">Inicial</option>
      </select>
    </div>

    <Card>
      <CardContent class="pt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium">Fecha</th>
              <th class="pb-2 font-medium">Producto</th>
              <th class="pb-2 font-medium text-center">Tipo</th>
              <th class="pb-2 font-medium text-right">Cantidad</th>
              <th class="pb-2 font-medium text-right">Costo unit.</th>
              <th class="pb-2 font-medium">Notas</th>
              <th class="pb-2 font-medium">Por</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in movements.data" :key="m.id" class="border-b last:border-0 hover:bg-gray-50">
              <td class="py-3 text-gray-600 text-xs">{{ formatDate(m.created_at) }}</td>
              <td class="py-3 font-medium">
                {{ m.product?.name }}
                <span v-if="m.product?.sku" class="text-xs text-gray-400 ml-1">({{ m.product.sku }})</span>
              </td>
              <td class="py-3 text-center">
                <Badge :class="typeColors[m.type?.value || m.type]" class="text-xs">
                  {{ typeLabels[m.type?.value || m.type] }}
                </Badge>
              </td>
              <td :class="['py-3 text-right font-medium', Number(m.quantity) >= 0 ? 'text-green-600' : 'text-red-600']">
                {{ Number(m.quantity) >= 0 ? '+' : '' }}{{ Number(m.quantity).toFixed(1) }} {{ m.product?.unit }}
              </td>
              <td class="py-3 text-right">{{ m.unit_cost ? '$' + Number(m.unit_cost).toFixed(2) : '-' }}</td>
              <td class="py-3 text-gray-500 text-xs max-w-xs truncate">{{ m.notes || '-' }}</td>
              <td class="py-3 text-gray-500 text-xs">{{ m.creator?.name || '-' }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="!movements.data?.length" class="text-center py-8 text-gray-400">Sin movimientos</div>
      </CardContent>
    </Card>
  </div>
</template>
