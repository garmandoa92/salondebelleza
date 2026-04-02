<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, usePage, Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  product: { type: Object, default: null },
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`
const isEditing = computed(() => !!props.product)

const form = useForm({
  name: props.product?.name || '',
  sku: props.product?.sku || '',
  barcode: props.product?.barcode || '',
  type: props.product?.type?.value || props.product?.type || 'use',
  unit: props.product?.unit?.value || props.product?.unit || 'unit',
  cost_price: props.product?.cost_price || '',
  sale_price: props.product?.sale_price || '',
  stock: props.product?.stock || 0,
  min_stock: props.product?.min_stock || 0,
  supplier: props.product?.supplier || '',
  brand: props.product?.brand || '',
  iva_rate: props.product?.iva_rate,
  is_active: props.product?.is_active ?? true,
  image: null,
})

const globalIva = computed(() => page.props.tenantIva ?? 15)
const ivaMode = ref(props.product?.iva_rate !== null && props.product?.iva_rate !== undefined ? 'custom' : 'global')
const setIvaMode = (mode) => {
  ivaMode.value = mode
  form.iva_rate = mode === 'global' ? null : globalIva.value
}
const setCustomIva = (rate) => {
  ivaMode.value = 'custom'
  form.iva_rate = rate
}

const submit = () => {
  if (isEditing.value) {
    form.post(`${base}/inventario/${props.product.id}`, { _method: 'put', forceFormData: true })
  } else {
    form.post(`${base}/inventario`, { forceFormData: true })
  }
}

const units = [
  { value: 'unit', label: 'Unidad' },
  { value: 'ml', label: 'Mililitros (ml)' },
  { value: 'g', label: 'Gramos (g)' },
  { value: 'oz', label: 'Onzas (oz)' },
  { value: 'liter', label: 'Litros (L)' },
  { value: 'kg', label: 'Kilogramos (kg)' },
]
</script>

<template>
  <Head :title="isEditing ? 'Editar producto' : 'Nuevo producto'" />

  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ isEditing ? 'Editar producto' : 'Nuevo producto' }}</h1>
      <Link :href="`${base}/inventario`"><Button variant="outline">Volver</Button></Link>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <Card>
        <CardHeader><CardTitle class="text-base">Informacion del producto</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label>Nombre</Label>
            <Input v-model="form.name" required />
            <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label>SKU</Label>
              <Input v-model="form.sku" />
            </div>
            <div class="space-y-2">
              <Label>Codigo barras</Label>
              <Input v-model="form.barcode" />
            </div>
            <div class="space-y-2">
              <Label>Tipo</Label>
              <select v-model="form.type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                <option value="use">Uso interno</option>
                <option value="sale">Venta al cliente</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label>Unidad</Label>
              <select v-model="form.unit" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                <option v-for="u in units" :key="u.value" :value="u.value">{{ u.label }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <Label>Proveedor</Label>
              <Input v-model="form.supplier" />
            </div>
            <div class="space-y-2">
              <Label>Marca</Label>
              <Input v-model="form.brand" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Precio de costo ($)</Label>
              <Input v-model="form.cost_price" type="number" min="0" step="0.01" />
            </div>
            <div v-if="form.type === 'sale'" class="space-y-2">
              <Label>Precio de venta ($)</Label>
              <Input v-model="form.sale_price" type="number" min="0" step="0.01" />
            </div>
          </div>

          <div v-if="!isEditing" class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Stock inicial</Label>
              <Input v-model="form.stock" type="number" min="0" step="0.01" />
            </div>
            <div class="space-y-2">
              <Label>Stock minimo</Label>
              <Input v-model="form.min_stock" type="number" min="0" step="0.01" />
            </div>
          </div>
          <div v-else class="space-y-2">
            <Label>Stock minimo</Label>
            <Input v-model="form.min_stock" type="number" min="0" step="0.01" />
            <p class="text-xs text-gray-400">El stock se modifica con compras y ajustes, no editando directamente.</p>
          </div>

          <div class="space-y-2">
            <Label>Foto</Label>
            <Input type="file" accept="image/*" @change="form.image = $event.target.files[0]" />
          </div>

          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300" />
            <span class="text-sm">Producto activo</span>
          </label>

          <div v-if="form.type === 'use'" class="bg-blue-50 rounded-lg p-3 text-sm text-blue-700">
            Los productos de uso interno se descuentan automaticamente cuando se completa un servicio que los incluye en su receta.
          </div>
        </CardContent>
      </Card>

      <!-- IVA section (only for sale products) -->
      <Card v-if="form.type === 'sale'">
        <CardHeader><CardTitle class="text-base">Impuestos</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <div class="space-y-2">
            <Label>Tarifa de IVA</Label>
            <div class="space-y-2">
              <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors', ivaMode === 'global' ? 'border-primary bg-primary/5' : 'border-gray-200']">
                <input type="radio" name="iva_mode" :checked="ivaMode === 'global'" @change="setIvaMode('global')" class="text-primary" />
                <span class="text-sm">Usar IVA global del salon ({{ globalIva }}%)</span>
              </label>
              <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors', ivaMode === 'custom' ? 'border-primary bg-primary/5' : 'border-gray-200']">
                <input type="radio" name="iva_mode" :checked="ivaMode === 'custom'" @change="setIvaMode('custom')" class="text-primary" />
                <span class="text-sm">IVA personalizado</span>
              </label>
            </div>
            <div v-if="ivaMode === 'custom'" class="flex items-center gap-2 ml-6">
              <Button v-for="r in [0, 12, 15]" :key="r" type="button" size="sm"
                :variant="Number(form.iva_rate) === r ? 'default' : 'outline'" class="text-xs"
                @click="setCustomIva(r)">{{ r }}%</Button>
              <Input v-model="form.iva_rate" type="number" min="0" max="100" step="0.01" class="w-20 h-8 text-sm" />
              <span class="text-sm text-gray-500">%</span>
            </div>
          </div>
          <div v-if="ivaMode === 'custom' && Number(form.iva_rate) === 0" class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
            Usa IVA 0% unicamente para medicamentos y productos de salud. Verifica con tu contador que productos califican para tarifa 0%.
          </div>
        </CardContent>
      </Card>

      <div class="flex justify-end gap-2">
        <Link :href="`${base}/inventario`"><Button type="button" variant="outline">Cancelar</Button></Link>
        <Button type="submit" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear producto') }}
        </Button>
      </div>
    </form>
  </div>
</template>
