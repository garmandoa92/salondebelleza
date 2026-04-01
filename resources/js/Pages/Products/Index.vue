<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  products: Object,
  suppliers: Array,
  filters: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`
const search = ref(props.filters?.search || '')
let debounceTimer = null

// Modals
const showPurchase = ref(false)
const showAdjustment = ref(false)
const purchaseForm = ref({ product_id: '', quantity: '', unit_cost: '', notes: '' })
const adjustmentForm = ref({ product_id: '', quantity: '', notes: '' })

watch(search, (val) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(`${base}/inventario`, { ...props.filters, search: val || undefined }, { preserveState: true, preserveScroll: true })
  }, 300)
})

const applyFilter = (key, value) => {
  router.get(`${base}/inventario`, { ...props.filters, [key]: value || undefined }, { preserveState: true })
}

const stockColor = (p) => {
  if (!p.min_stock || p.min_stock == 0) return 'text-gray-400'
  if (p.stock <= p.min_stock) return 'text-red-600 font-bold'
  if (p.stock <= p.min_stock * 1.5) return 'text-amber-600 font-semibold'
  return 'text-green-600'
}

const stockBg = (p) => {
  if (!p.min_stock || p.min_stock == 0) return ''
  if (p.stock <= p.min_stock) return 'bg-red-50'
  if (p.stock <= p.min_stock * 1.5) return 'bg-amber-50'
  return ''
}

const submitPurchase = async () => {
  await axios.post(`${base}/stock/purchase`, purchaseForm.value)
  showPurchase.value = false
  purchaseForm.value = { product_id: '', quantity: '', unit_cost: '', notes: '' }
  router.reload()
}

const submitAdjustment = async () => {
  await axios.post(`${base}/stock/adjustment`, adjustmentForm.value)
  showAdjustment.value = false
  adjustmentForm.value = { product_id: '', quantity: '', notes: '' }
  router.reload()
}

const deleteProduct = (p) => {
  if (confirm(`Eliminar "${p.name}"?`)) router.delete(`${base}/inventario/${p.id}`)
}
</script>

<template>
  <Head title="Inventario" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Inventario</h1>
      <div class="flex gap-2">
        <Button variant="outline" @click="showPurchase = true">Registrar compra</Button>
        <Button variant="outline" @click="showAdjustment = true">Ajustar stock</Button>
        <Link :href="`${base}/inventario/create`">
          <Button>+ Nuevo producto</Button>
        </Link>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <Input v-model="search" placeholder="Buscar por nombre o SKU..." class="max-w-sm" />
      <select class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" :value="filters?.type || ''" @change="applyFilter('type', $event.target.value)">
        <option value="">Todos los tipos</option>
        <option value="use">Uso interno</option>
        <option value="sale">Venta</option>
      </select>
      <select class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" :value="filters?.supplier || ''" @change="applyFilter('supplier', $event.target.value)">
        <option value="">Todos los proveedores</option>
        <option v-for="s in suppliers" :key="s" :value="s">{{ s }}</option>
      </select>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input type="checkbox" :checked="filters?.low_stock" @change="applyFilter('low_stock', $event.target.checked ? '1' : undefined)" class="rounded border-gray-300" />
        Solo stock bajo
      </label>
      <Link :href="`${base}/stock/movements`">
        <Button variant="outline" size="sm">Ver movimientos</Button>
      </Link>
    </div>

    <!-- Table -->
    <Card>
      <CardContent class="pt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium">Producto</th>
              <th class="pb-2 font-medium">SKU</th>
              <th class="pb-2 font-medium text-center">Tipo</th>
              <th class="pb-2 font-medium text-right">Costo</th>
              <th class="pb-2 font-medium text-right">Precio venta</th>
              <th class="pb-2 font-medium text-center">Stock</th>
              <th class="pb-2 font-medium text-center">Min</th>
              <th class="pb-2 font-medium text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in products.data" :key="p.id" :class="['border-b last:border-0 hover:bg-gray-50', stockBg(p)]">
              <td class="py-3">
                <div class="font-medium text-gray-900">{{ p.name }}</div>
                <div v-if="p.brand" class="text-xs text-gray-400">{{ p.brand }}</div>
              </td>
              <td class="py-3 text-gray-600 font-mono text-xs">{{ p.sku || '-' }}</td>
              <td class="py-3 text-center">
                <Badge :variant="p.type === 'sale' ? 'default' : 'secondary'" class="text-xs">
                  {{ p.type === 'sale' ? 'Venta' : 'Uso' }}
                </Badge>
              </td>
              <td class="py-3 text-right">${{ p.cost_price ? Number(p.cost_price).toFixed(2) : '-' }}</td>
              <td class="py-3 text-right">{{ p.sale_price ? '$' + Number(p.sale_price).toFixed(2) : '-' }}</td>
              <td :class="['py-3 text-center', stockColor(p)]">
                {{ Number(p.stock).toFixed(p.unit === 'unit' ? 0 : 1) }} {{ p.unit }}
              </td>
              <td class="py-3 text-center text-gray-400">{{ Number(p.min_stock).toFixed(0) }}</td>
              <td class="py-3 text-right">
                <div class="flex justify-end gap-1">
                  <Link :href="`${base}/inventario/${p.id}/edit`">
                    <Button variant="ghost" size="sm">Editar</Button>
                  </Link>
                  <Button variant="ghost" size="sm" class="text-red-600" @click="deleteProduct(p)">Eliminar</Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!products.data?.length" class="text-center py-8 text-gray-400">Sin productos</div>
      </CardContent>
    </Card>

    <!-- Purchase modal -->
    <div v-if="showPurchase" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPurchase = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold">Registrar compra</h2>
        <div class="space-y-3">
          <div class="space-y-1">
            <Label>Producto</Label>
            <select v-model="purchaseForm.product_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
              <option value="">Seleccionar...</option>
              <option v-for="p in products.data" :key="p.id" :value="p.id">{{ p.name }} ({{ Number(p.stock).toFixed(0) }} {{ p.unit }})</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <Label>Cantidad</Label>
              <Input v-model="purchaseForm.quantity" type="number" min="0.01" step="0.01" />
            </div>
            <div class="space-y-1">
              <Label>Costo unitario ($)</Label>
              <Input v-model="purchaseForm.unit_cost" type="number" min="0" step="0.01" />
            </div>
          </div>
          <div class="space-y-1">
            <Label>Notas</Label>
            <Input v-model="purchaseForm.notes" placeholder="Proveedor, # factura..." />
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="showPurchase = false">Cancelar</Button>
          <Button :disabled="!purchaseForm.product_id || !purchaseForm.quantity" @click="submitPurchase">Registrar</Button>
        </div>
      </div>
    </div>

    <!-- Adjustment modal -->
    <div v-if="showAdjustment" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showAdjustment = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold">Ajustar stock</h2>
        <div class="space-y-3">
          <div class="space-y-1">
            <Label>Producto</Label>
            <select v-model="adjustmentForm.product_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
              <option value="">Seleccionar...</option>
              <option v-for="p in products.data" :key="p.id" :value="p.id">{{ p.name }} ({{ Number(p.stock).toFixed(0) }} {{ p.unit }})</option>
            </select>
          </div>
          <div class="space-y-1">
            <Label>Cantidad (positivo = entrada, negativo = salida)</Label>
            <Input v-model="adjustmentForm.quantity" type="number" step="0.01" />
          </div>
          <div class="space-y-1">
            <Label>Motivo (obligatorio)</Label>
            <Input v-model="adjustmentForm.notes" placeholder="Motivo del ajuste..." />
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="showAdjustment = false">Cancelar</Button>
          <Button :disabled="!adjustmentForm.product_id || !adjustmentForm.quantity || !adjustmentForm.notes" @click="submitAdjustment">Ajustar</Button>
        </div>
      </div>
    </div>
  </div>
</template>
