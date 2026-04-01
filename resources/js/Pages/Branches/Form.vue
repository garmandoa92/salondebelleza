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
  branch: { type: Object, default: null },
  users: Array,
  stylists: Array,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`
const isEditing = computed(() => !!props.branch)

const days = [
  { key: 'monday', label: 'Lunes' }, { key: 'tuesday', label: 'Martes' },
  { key: 'wednesday', label: 'Miercoles' }, { key: 'thursday', label: 'Jueves' },
  { key: 'friday', label: 'Viernes' }, { key: 'saturday', label: 'Sabado' },
  { key: 'sunday', label: 'Domingo' },
]

const defaultSchedule = {}
days.forEach(d => {
  defaultSchedule[d.key] = d.key === 'sunday' ? [] : [{ start: '09:00', end: '18:00' }]
})

const form = useForm({
  name: props.branch?.name || '',
  address: props.branch?.address || '',
  phone: props.branch?.phone || '',
  email: props.branch?.email || '',
  ruc: props.branch?.ruc || '',
  razon_social: props.branch?.razon_social || '',
  manager_user_id: props.branch?.manager_user_id || '',
  schedule: props.branch?.schedule || { ...defaultSchedule },
  sri_establishment: props.branch?.sri_establishment || '001',
  sri_emission_point: props.branch?.sri_emission_point || '001',
  is_active: props.branch?.is_active ?? true,
  stylist_ids: props.branch?.stylists?.map(s => s.id) || [],
})

const submit = () => {
  if (isEditing.value) {
    form.put(`${base}/sucursales/${props.branch.id}`)
  } else {
    form.post(`${base}/sucursales`)
  }
}

const toggleDay = (key) => {
  form.schedule[key] = form.schedule[key]?.length ? [] : [{ start: '09:00', end: '18:00' }]
}

const toggleStylist = (id) => {
  const idx = form.stylist_ids.indexOf(id)
  if (idx >= 0) form.stylist_ids.splice(idx, 1)
  else form.stylist_ids.push(id)
}
</script>

<template>
  <Head :title="isEditing ? 'Editar sucursal' : 'Nueva sucursal'" />

  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ isEditing ? 'Editar sucursal' : 'Nueva sucursal' }}</h1>
      <Link :href="`${base}/sucursales`"><Button variant="outline">Volver</Button></Link>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <Card>
        <CardHeader><CardTitle class="text-base">Informacion de la sucursal</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2"><Label>Nombre</Label><Input v-model="form.name" required /><p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p></div>
          <div class="space-y-2"><Label>Direccion</Label><Input v-model="form.address" /></div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2"><Label>Telefono</Label><Input v-model="form.phone" /></div>
            <div class="space-y-2"><Label>Email</Label><Input v-model="form.email" type="email" /></div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2"><Label>RUC (13 digitos)</Label><Input v-model="form.ruc" maxlength="13" placeholder="Si difiere del salon principal" /></div>
            <div class="space-y-2"><Label>Razon social</Label><Input v-model="form.razon_social" placeholder="Si difiere del salon principal" /></div>
          </div>
          <div class="space-y-2">
            <Label>Gerente</Label>
            <select v-model="form.manager_user_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
              <option value="">Sin gerente asignado</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2"><Label>Establecimiento SRI</Label><Input v-model="form.sri_establishment" maxlength="3" /></div>
            <div class="space-y-2"><Label>Punto de emision SRI</Label><Input v-model="form.sri_emission_point" maxlength="3" /></div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle class="text-base">Estilistas asignados</CardTitle></CardHeader>
        <CardContent>
          <div class="flex flex-wrap gap-2">
            <label v-for="s in stylists" :key="s.id"
              :class="['flex items-center gap-2 px-3 py-1.5 rounded-full border cursor-pointer text-sm transition-colors',
                form.stylist_ids.includes(s.id) ? 'bg-primary text-white border-primary' : 'border-gray-200 hover:bg-gray-50']"
            >
              <input type="checkbox" :checked="form.stylist_ids.includes(s.id)" @change="toggleStylist(s.id)" class="sr-only" />
              {{ s.name }}
            </label>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle class="text-base">Horario de la sucursal</CardTitle></CardHeader>
        <CardContent class="space-y-2">
          <div v-for="day in days" :key="day.key" class="flex items-center gap-3 py-1 border-b last:border-0">
            <div class="w-24">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" :checked="form.schedule[day.key]?.length > 0" @change="toggleDay(day.key)" class="rounded border-gray-300" />
                <span class="text-sm font-medium">{{ day.label }}</span>
              </label>
            </div>
            <div v-if="form.schedule[day.key]?.length" class="flex items-center gap-2">
              <Input v-model="form.schedule[day.key][0].start" type="time" class="w-28" />
              <span class="text-gray-400">-</span>
              <Input v-model="form.schedule[day.key][0].end" type="time" class="w-28" />
            </div>
            <span v-else class="text-sm text-gray-400">Cerrado</span>
          </div>
        </CardContent>
      </Card>

      <div class="flex justify-end gap-2">
        <Link :href="`${base}/sucursales`"><Button type="button" variant="outline">Cancelar</Button></Link>
        <Button type="submit" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear sucursal') }}
        </Button>
      </div>
    </form>
  </div>
</template>
