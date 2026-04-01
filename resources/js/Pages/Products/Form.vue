<script setup>
import { computed } from 'vue'
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
  is_active: props.product?.is_active ?? true,
  image: null,
})

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

      <div class="flex justify-end gap-2">
        <Link :href="`${base}/inventario`"><Button type="button" variant="outline">Cancelar</Button></Link>
        <Button type="submit" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear producto') }}
        </Button>
      </div>
    </form>
  </div>
</template>
