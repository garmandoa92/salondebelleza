<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, usePage, Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  package: { type: Object, default: null },
  services: Array,
})

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`
const isEditing = computed(() => !!props.package)

const form = useForm({
  name: props.package?.name || '',
  description: props.package?.description || '',
  price: props.package?.price || '',
  type: props.package?.type || 'sessions',
  items: props.package?.items || [],
  validity_days: props.package?.validity_days || 365,
  is_active: props.package?.is_active ?? true,
})

// For sessions type: single service + quantity
const sessionServiceId = ref(form.items[0]?.service_id || '')
const sessionQuantity = ref(form.items[0]?.quantity || 10)

// For combo type: add service
const comboServiceId = ref('')

const validityPresets = [30, 60, 90, 180, 365]

const selectedService = computed(() => props.services?.find(s => s.id === sessionServiceId.value))

const buildItems = () => {
  if (form.type === 'sessions') {
    const svc = props.services?.find(s => s.id === sessionServiceId.value)
    if (svc) {
      form.items = [{ service_id: svc.id, service_name: svc.name, quantity: sessionQuantity.value }]
    }
  }
}

const addComboService = () => {
  const svc = props.services?.find(s => s.id === comboServiceId.value)
  if (!svc || form.items.some(i => i.service_id === svc.id)) return
  form.items.push({ service_id: svc.id, service_name: svc.name, quantity: 1 })
  comboServiceId.value = ''
}

const removeItem = (idx) => form.items.splice(idx, 1)

const submit = () => {
  if (form.type === 'sessions') buildItems()
  if (isEditing.value) {
    form.put(`${base}/paquetes/${props.package.id}`)
  } else {
    form.post(`${base}/paquetes`)
  }
}
</script>

<template>
  <Head :title="isEditing ? 'Editar paquete' : 'Nuevo paquete'" />

  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ isEditing ? 'Editar paquete' : 'Nuevo paquete' }}</h1>
      <Link :href="`${base}/paquetes`"><Button variant="outline">Volver</Button></Link>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <Card>
        <CardHeader><CardTitle class="text-base">Informacion del paquete</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2"><Label>Nombre</Label><Input v-model="form.name" required placeholder="Ej: 10 Depilaciones, Renovacion Facial" /></div>
          <div class="space-y-2"><Label>Descripcion (opcional)</Label><textarea v-model="form.description" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" rows="2" /></div>

          <div class="space-y-2">
            <Label>Tipo de paquete</Label>
            <div class="grid grid-cols-2 gap-3">
              <label :class="['flex flex-col gap-1 p-3 rounded-lg border cursor-pointer transition-colors',
                form.type === 'sessions' ? 'border-primary bg-primary/5' : 'border-gray-200']">
                <input type="radio" v-model="form.type" value="sessions" class="sr-only" />
                <span class="font-medium text-sm">Bono de sesiones</span>
                <span class="text-xs text-gray-500">X sesiones del mismo servicio</span>
              </label>
              <label :class="['flex flex-col gap-1 p-3 rounded-lg border cursor-pointer transition-colors',
                form.type === 'combo' ? 'border-primary bg-primary/5' : 'border-gray-200']">
                <input type="radio" v-model="form.type" value="combo" class="sr-only" />
                <span class="font-medium text-sm">Combo</span>
                <span class="text-xs text-gray-500">Combinacion de servicios distintos</span>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2"><Label>Precio total ($)</Label><Input v-model="form.price" type="number" step="0.01" min="0" required /></div>
            <div class="space-y-2">
              <Label>Validez (dias)</Label>
              <Input v-model="form.validity_days" type="number" min="1" required />
              <div class="flex gap-1 mt-1">
                <Button v-for="d in validityPresets" :key="d" type="button" variant="outline" size="sm" class="text-xs px-2 h-6"
                  @click="form.validity_days = d">{{ d }}d</Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Sessions: single service -->
      <Card v-if="form.type === 'sessions'">
        <CardHeader><CardTitle class="text-base">Servicio y sesiones</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label>Servicio</Label>
            <select v-model="sessionServiceId" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm" required>
              <option value="">Seleccionar servicio</option>
              <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }} (${{ Number(s.base_price).toFixed(2) }})</option>
            </select>
          </div>
          <div class="space-y-2">
            <Label>Cantidad de sesiones</Label>
            <Input v-model="sessionQuantity" type="number" min="2" max="100" required />
          </div>
          <div v-if="selectedService" class="bg-blue-50 rounded-lg p-3 text-sm text-blue-700">
            El cliente tendra <strong>{{ sessionQuantity }}</strong> sesiones de <strong>{{ selectedService.name }}</strong> por <strong>${{ Number(form.price || 0).toFixed(2) }}</strong>
          </div>
        </CardContent>
      </Card>

      <!-- Combo: multiple services -->
      <Card v-if="form.type === 'combo'">
        <CardHeader><CardTitle class="text-base">Servicios del combo</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="flex gap-2">
            <select v-model="comboServiceId" class="flex h-9 flex-1 rounded-md border border-input bg-transparent px-3 py-1 text-sm">
              <option value="">Seleccionar servicio</option>
              <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <Button type="button" variant="outline" @click="addComboService" :disabled="!comboServiceId">Agregar</Button>
          </div>

          <table v-if="form.items.length" class="w-full text-sm">
            <thead><tr class="border-b text-left text-gray-500"><th class="pb-2">Servicio</th><th class="pb-2 w-24">Cantidad</th><th class="pb-2 w-16"></th></tr></thead>
            <tbody>
              <tr v-for="(item, i) in form.items" :key="item.service_id" class="border-b last:border-0">
                <td class="py-2">{{ item.service_name }}</td>
                <td class="py-2"><Input v-model="item.quantity" type="number" min="1" class="w-20 h-7 text-sm" /></td>
                <td class="py-2 text-right"><Button type="button" variant="ghost" size="sm" class="text-red-500 h-7 text-xs" @click="removeItem(i)">Quitar</Button></td>
              </tr>
            </tbody>
          </table>

          <div v-if="form.items.length" class="bg-purple-50 rounded-lg p-3 text-sm text-purple-700">
            El cliente tendra: {{ form.items.map(i => `${i.quantity}x ${i.service_name}`).join(' + ') }} por <strong>${{ Number(form.price || 0).toFixed(2) }}</strong>
          </div>
        </CardContent>
      </Card>

      <div class="flex justify-end gap-2">
        <Link :href="`${base}/paquetes`"><Button type="button" variant="outline">Cancelar</Button></Link>
        <Button type="submit" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear paquete') }}
        </Button>
      </div>
    </form>
  </div>
</template>
