<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, usePage, Link } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  service: { type: Object, default: null },
  categories: Array,
  products: Array,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const isEditing = computed(() => !!props.service)

const form = useForm({
  service_category_id: props.service?.service_category_id || '',
  name: props.service?.name || '',
  description: props.service?.description || '',
  base_price: props.service?.base_price || '',
  duration_minutes: props.service?.duration_minutes || 30,
  preparation_minutes: props.service?.preparation_minutes || 0,
  is_visible: props.service?.is_visible ?? true,
  requires_consultation: props.service?.requires_consultation ?? false,
  iva_rate: props.service?.iva_rate,
  recipe: props.service?.recipe || [],
  image: null,
})

const globalIva = computed(() => page.props.tenantIva || 15)
const ivaMode = ref(props.service?.iva_rate !== null && props.service?.iva_rate !== undefined ? 'custom' : 'global')
const setIvaMode = (mode) => {
  ivaMode.value = mode
  form.iva_rate = mode === 'global' ? null : globalIva.value
}
const setCustomIva = (rate) => {
  ivaMode.value = 'custom'
  form.iva_rate = rate
}

const durationPreview = computed(() => {
  const m = parseInt(form.duration_minutes) || 0
  if (m < 60) return `${m}min`
  const h = Math.floor(m / 60)
  const mins = m % 60
  return mins ? `${h}h ${mins}min` : `${h}h`
})

const submit = () => {
  if (isEditing.value) {
    form.post(`/salon/${tenantId}/servicios/${props.service.id}`, {
      _method: 'put',
      forceFormData: true,
    })
  } else {
    form.post(`/salon/${tenantId}/servicios`, {
      forceFormData: true,
    })
  }
}

const addRecipeItem = () => {
  form.recipe.push({ product_id: '', quantity: 1, unit: 'ml' })
}

const removeRecipeItem = (index) => {
  form.recipe.splice(index, 1)
}
</script>

<template>
  <Head :title="isEditing ? 'Editar servicio' : 'Nuevo servicio'" />

  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ isEditing ? 'Editar servicio' : 'Nuevo servicio' }}</h1>
      <Link :href="`/salon/${tenantId}/servicios`">
        <Button variant="outline">Volver</Button>
      </Link>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <Card>
        <CardHeader><CardTitle class="text-base">Informacion del servicio</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="category">Categoria</Label>
            <select
              id="category"
              v-model="form.service_category_id"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
              required
            >
              <option value="">Seleccionar...</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
            <p v-if="form.errors.service_category_id" class="text-sm text-red-500">{{ form.errors.service_category_id }}</p>
          </div>

          <div class="space-y-2">
            <Label for="name">Nombre del servicio</Label>
            <Input id="name" v-model="form.name" required />
            <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
          </div>

          <div class="space-y-2">
            <Label for="description">Descripcion (opcional)</Label>
            <textarea
              id="description"
              v-model="form.description"
              class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
              rows="3"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="price">Precio base ($)</Label>
              <Input id="price" v-model="form.base_price" type="number" step="0.01" min="0.01" required />
              <p v-if="form.errors.base_price" class="text-sm text-red-500">{{ form.errors.base_price }}</p>
            </div>

            <div class="space-y-2">
              <Label for="duration">Duracion (minutos)</Label>
              <div class="flex items-center gap-2">
                <Input id="duration" v-model="form.duration_minutes" type="number" min="5" max="480" required />
                <span class="text-sm text-gray-500 whitespace-nowrap">{{ durationPreview }}</span>
              </div>
              <p v-if="form.errors.duration_minutes" class="text-sm text-red-500">{{ form.errors.duration_minutes }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="prep">Minutos de preparacion</Label>
            <Input id="prep" v-model="form.preparation_minutes" type="number" min="0" max="60" />
            <p class="text-xs text-gray-400">Tiempo buffer entre citas para preparar el puesto</p>
          </div>

          <div class="space-y-2">
            <Label>Foto del servicio</Label>
            <Input type="file" accept="image/*" @change="form.image = $event.target.files[0]" />
          </div>

          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.is_visible" class="rounded border-gray-300" />
              <span class="text-sm">Visible en booking publico</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.requires_consultation" class="rounded border-gray-300" />
              <span class="text-sm">Requiere consulta previa</span>
            </label>
          </div>
        </CardContent>
      </Card>

      <!-- IVA section -->
      <Card>
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
            Usa IVA 0% unicamente para servicios medico-esteticos con prescripcion medica. El uso incorrecto puede resultar en glosas del SRI. Consulta con tu contador.
          </div>
        </CardContent>
      </Card>

      <!-- Recipe section -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="text-base">Receta de productos</CardTitle>
            <Button type="button" variant="outline" size="sm" @click="addRecipeItem">+ Producto</Button>
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="form.recipe.length" class="space-y-2">
            <div v-for="(item, i) in form.recipe" :key="i" class="flex items-center gap-2">
              <select
                v-model="item.product_id"
                class="flex h-9 flex-1 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
              >
                <option value="">Seleccionar producto...</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <Input v-model="item.quantity" type="number" step="0.01" min="0.01" class="w-24" placeholder="Cant." />
              <Input v-model="item.unit" class="w-20" placeholder="ml" />
              <Button type="button" variant="ghost" size="sm" class="text-red-500" @click="removeRecipeItem(i)">X</Button>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">Sin productos en la receta. Los productos se descuentan automaticamente al completar el servicio.</p>
        </CardContent>
      </Card>

      <div class="flex justify-end gap-2">
        <Link :href="`/salon/${tenantId}/servicios`">
          <Button type="button" variant="outline">Cancelar</Button>
        </Link>
        <Button type="submit" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear servicio') }}
        </Button>
      </div>
    </form>
  </div>
</template>
