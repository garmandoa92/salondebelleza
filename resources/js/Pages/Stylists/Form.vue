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
  stylist: { type: Object, default: null },
  categories: Array,
  branches: { type: Array, default: () => [] },
})

const page = usePage()
const tenantId = page.props.tenant?.id
const isEditing = computed(() => !!props.stylist)

const days = [
  { key: 'monday', label: 'Lunes' },
  { key: 'tuesday', label: 'Martes' },
  { key: 'wednesday', label: 'Miercoles' },
  { key: 'thursday', label: 'Jueves' },
  { key: 'friday', label: 'Viernes' },
  { key: 'saturday', label: 'Sabado' },
  { key: 'sunday', label: 'Domingo' },
]

const defaultSchedule = {}
days.forEach(d => {
  defaultSchedule[d.key] = d.key === 'sunday' ? [] : [{ start: '09:00', end: '18:00' }]
})

const form = useForm({
  name: props.stylist?.name || '',
  phone: props.stylist?.phone || '',
  email: props.stylist?.email || '',
  bio: props.stylist?.bio || '',
  color: props.stylist?.color || '#3B82F6',
  specialties: props.stylist?.specialties || [],
  schedule: props.stylist?.schedule || { ...defaultSchedule },
  commission_rules: props.stylist?.commission_rules || { default: 40, by_category: {} },
  branch_ids: props.stylist?.branches?.map(b => b.id) || [],
  photo: null,
})

const toggleBranch = (id) => {
  const idx = form.branch_ids.indexOf(id)
  if (idx >= 0) form.branch_ids.splice(idx, 1)
  else form.branch_ids.push(id)
}

const submit = () => {
  if (isEditing.value) {
    form.post(`/salon/${tenantId}/estilistas/${props.stylist.id}`, {
      _method: 'put',
      forceFormData: true,
    })
  } else {
    form.post(`/salon/${tenantId}/estilistas`, {
      forceFormData: true,
    })
  }
}

const toggleDay = (dayKey) => {
  if (form.schedule[dayKey]?.length) {
    form.schedule[dayKey] = []
  } else {
    form.schedule[dayKey] = [{ start: '09:00', end: '18:00' }]
  }
}

const addSlot = (dayKey) => {
  if (form.schedule[dayKey].length < 2) {
    form.schedule[dayKey].push({ start: '14:00', end: '18:00' })
  }
}

const removeSlot = (dayKey, index) => {
  form.schedule[dayKey].splice(index, 1)
}

const copyFromMonday = (dayKey) => {
  if (form.schedule.monday) {
    form.schedule[dayKey] = JSON.parse(JSON.stringify(form.schedule.monday))
  }
}

const toggleSpecialty = (catId) => {
  const idx = form.specialties.indexOf(catId)
  if (idx >= 0) {
    form.specialties.splice(idx, 1)
  } else {
    form.specialties.push(catId)
  }
}

const addCategoryException = (catId) => {
  if (!form.commission_rules.by_category) form.commission_rules.by_category = {}
  form.commission_rules.by_category[catId] = form.commission_rules.default
}

const removeCategoryException = (catId) => {
  delete form.commission_rules.by_category[catId]
}
</script>

<template>
  <Head :title="isEditing ? 'Editar estilista' : 'Nuevo estilista'" />

  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ isEditing ? 'Editar estilista' : 'Nuevo estilista' }}</h1>
      <Link :href="`/salon/${tenantId}/estilistas`">
        <Button variant="outline">Volver</Button>
      </Link>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Info personal -->
      <Card>
        <CardHeader><CardTitle class="text-base">Informacion personal</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Nombre completo</Label>
              <Input v-model="form.name" required />
              <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Color del calendario</Label>
              <div class="flex items-center gap-2">
                <input type="color" v-model="form.color" class="h-9 w-14 rounded border cursor-pointer" />
                <span class="text-sm text-gray-500">{{ form.color }}</span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Telefono</Label>
              <Input v-model="form.phone" type="tel" />
            </div>
            <div class="space-y-2">
              <Label>Email</Label>
              <Input v-model="form.email" type="email" />
            </div>
          </div>

          <div class="space-y-2">
            <Label>Bio</Label>
            <textarea
              v-model="form.bio"
              class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
              rows="2"
            />
          </div>

          <div class="space-y-2">
            <Label>Foto</Label>
            <Input type="file" accept="image/*" @change="form.photo = $event.target.files[0]" />
          </div>
        </CardContent>
      </Card>

      <!-- Especialidades -->
      <Card>
        <CardHeader><CardTitle class="text-base">Especialidades</CardTitle></CardHeader>
        <CardContent>
          <div class="flex flex-wrap gap-2">
            <label
              v-for="cat in categories"
              :key="cat.id"
              class="flex items-center gap-2 px-3 py-1.5 rounded-full border cursor-pointer transition-colors"
              :class="form.specialties.includes(cat.id)
                ? 'border-transparent text-white'
                : 'border-gray-200 text-gray-700 hover:bg-gray-50'"
              :style="form.specialties.includes(cat.id) ? { backgroundColor: cat.color } : {}"
            >
              <input
                type="checkbox"
                :checked="form.specialties.includes(cat.id)"
                @change="toggleSpecialty(cat.id)"
                class="sr-only"
              />
              <span class="text-sm">{{ cat.name }}</span>
            </label>
          </div>
        </CardContent>
      </Card>

      <!-- Horario semanal -->
      <Card>
        <CardHeader><CardTitle class="text-base">Horario semanal</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <div v-for="day in days" :key="day.key" class="flex items-start gap-3 py-2 border-b last:border-0">
            <div class="w-24 pt-1">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  :checked="form.schedule[day.key]?.length > 0"
                  @change="toggleDay(day.key)"
                  class="rounded border-gray-300"
                />
                <span class="text-sm font-medium">{{ day.label }}</span>
              </label>
            </div>

            <div v-if="form.schedule[day.key]?.length" class="flex-1 space-y-2">
              <div v-for="(slot, si) in form.schedule[day.key]" :key="si" class="flex items-center gap-2">
                <Input v-model="slot.start" type="time" class="w-28" />
                <span class="text-gray-400">-</span>
                <Input v-model="slot.end" type="time" class="w-28" />
                <Button
                  v-if="si > 0"
                  type="button" variant="ghost" size="sm" class="text-red-500"
                  @click="removeSlot(day.key, si)"
                >X</Button>
              </div>
              <div class="flex gap-2">
                <Button
                  v-if="form.schedule[day.key].length < 2"
                  type="button" variant="ghost" size="sm" class="text-xs"
                  @click="addSlot(day.key)"
                >+ Franja</Button>
                <Button
                  v-if="day.key !== 'monday'"
                  type="button" variant="ghost" size="sm" class="text-xs"
                  @click="copyFromMonday(day.key)"
                >Igual que Lunes</Button>
              </div>
            </div>
            <div v-else class="flex-1 pt-1">
              <span class="text-sm text-gray-400">Dia libre</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Comisiones -->
      <Card>
        <CardHeader><CardTitle class="text-base">Comisiones</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label>Porcentaje base</Label>
            <div class="flex items-center gap-2">
              <Input v-model="form.commission_rules.default" type="number" min="0" max="100" step="1" class="w-24" />
              <span class="text-sm text-gray-500">%</span>
            </div>
          </div>

          <div>
            <Label class="mb-2 block">Excepciones por categoria</Label>
            <p class="text-xs text-gray-400 mb-3">Si no hay excepcion, se aplica el porcentaje base</p>

            <div v-if="form.commission_rules.by_category && Object.keys(form.commission_rules.by_category).length" class="space-y-2 mb-3">
              <div v-for="(rate, catId) in form.commission_rules.by_category" :key="catId" class="flex items-center gap-2">
                <span class="flex-1 text-sm">{{ categories.find(c => c.id === catId)?.name || catId }}</span>
                <Input v-model="form.commission_rules.by_category[catId]" type="number" min="0" max="100" class="w-20" />
                <span class="text-sm text-gray-500">%</span>
                <Button type="button" variant="ghost" size="sm" class="text-red-500" @click="removeCategoryException(catId)">X</Button>
              </div>
            </div>

            <select
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
              @change="addCategoryException($event.target.value); $event.target.value = ''"
            >
              <option value="">+ Agregar excepcion...</option>
              <option
                v-for="cat in categories.filter(c => !form.commission_rules.by_category?.[c.id])"
                :key="cat.id"
                :value="cat.id"
              >{{ cat.name }}</option>
            </select>
          </div>
        </CardContent>
      </Card>

      <!-- Sucursales asignadas -->
      <Card v-if="branches.length">
        <CardHeader><CardTitle class="text-base">Sucursales asignadas</CardTitle></CardHeader>
        <CardContent>
          <div class="space-y-2">
            <label
              v-for="b in branches"
              :key="b.id"
              :class="['flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors',
                form.branch_ids.includes(b.id) ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-gray-50']"
            >
              <input type="checkbox" :checked="form.branch_ids.includes(b.id)" @change="toggleBranch(b.id)" class="rounded border-gray-300" />
              <span class="text-sm font-medium">{{ b.name }}</span>
            </label>
          </div>
          <p class="text-xs text-gray-400 mt-2">Selecciona en que sucursales trabaja este estilista.</p>
        </CardContent>
      </Card>

      <div class="flex justify-end gap-2">
        <Link :href="`/salon/${tenantId}/estilistas`">
          <Button type="button" variant="outline">Cancelar</Button>
        </Link>
        <Button type="submit" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear estilista') }}
        </Button>
      </div>
    </form>
  </div>
</template>
