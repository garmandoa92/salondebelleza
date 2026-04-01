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
  client: { type: Object, default: null },
  stylists: Array,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const isEditing = computed(() => !!props.client)

const form = useForm({
  first_name: props.client?.first_name || '',
  last_name: props.client?.last_name || '',
  phone: props.client?.phone || '',
  email: props.client?.email || '',
  cedula: props.client?.cedula || '',
  birthday: props.client?.birthday || '',
  notes: props.client?.notes || '',
  allergies: props.client?.allergies || '',
  tags: props.client?.tags || [],
  preferred_stylist_id: props.client?.preferred_stylist_id || '',
  source: props.client?.source || 'walk_in',
})

const submit = () => {
  if (isEditing.value) {
    form.put(`/salon/${tenantId}/clientes/${props.client.id}`)
  } else {
    form.post(`/salon/${tenantId}/clientes`)
  }
}

const sources = [
  { value: 'walk_in', label: 'Visita directa' },
  { value: 'referral', label: 'Referido' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'website', label: 'Sitio web' },
  { value: 'other', label: 'Otro' },
]

const tagInput = computed({
  get: () => (form.tags || []).join(', '),
  set: (val) => { form.tags = val.split(',').map(t => t.trim()).filter(Boolean) },
})
</script>

<template>
  <Head :title="isEditing ? 'Editar cliente' : 'Nuevo cliente'" />

  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ isEditing ? 'Editar cliente' : 'Nuevo cliente' }}</h1>
      <Link :href="`/salon/${tenantId}/clientes`">
        <Button variant="outline">Volver</Button>
      </Link>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <Card>
        <CardHeader><CardTitle class="text-base">Datos personales</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Nombre</Label>
              <Input v-model="form.first_name" required />
              <p v-if="form.errors.first_name" class="text-sm text-red-500">{{ form.errors.first_name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Apellido</Label>
              <Input v-model="form.last_name" required />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Telefono</Label>
              <Input v-model="form.phone" type="tel" required />
              <p v-if="form.errors.phone" class="text-sm text-red-500">{{ form.errors.phone }}</p>
            </div>
            <div class="space-y-2">
              <Label>Email</Label>
              <Input v-model="form.email" type="email" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Cedula</Label>
              <Input v-model="form.cedula" maxlength="10" />
            </div>
            <div class="space-y-2">
              <Label>Cumpleanos</Label>
              <Input v-model="form.birthday" type="date" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Estilista preferido</Label>
              <select v-model="form.preferred_stylist_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                <option value="">Sin preferencia</option>
                <option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <Label>Como llego al salon</Label>
              <select v-model="form.source" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>

          <div class="space-y-2">
            <Label>Tags (separados por coma)</Label>
            <Input v-model="tagInput" placeholder="VIP, frecuente, nueva" />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle class="text-base">Notas e indicaciones</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label>Alergias / indicaciones medicas</Label>
            <textarea v-model="form.allergies" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" rows="2" placeholder="Alergia al latex, sensibilidad al amoniaco..." />
          </div>
          <div class="space-y-2">
            <Label>Notas del equipo</Label>
            <textarea v-model="form.notes" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" rows="2" placeholder="Notas internas..." />
          </div>
        </CardContent>
      </Card>

      <div class="flex justify-end gap-2">
        <Link :href="`/salon/${tenantId}/clientes`">
          <Button type="button" variant="outline">Cancelar</Button>
        </Link>
        <Button type="submit" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear cliente') }}
        </Button>
      </div>
    </form>
  </div>
</template>
