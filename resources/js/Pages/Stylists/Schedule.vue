<script setup>
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  stylist: Object,
  blockedTimes: Array,
})

const page = usePage()
const tenantId = page.props.tenant?.id

const days = [
  { key: 'monday', label: 'Lun' },
  { key: 'tuesday', label: 'Mar' },
  { key: 'wednesday', label: 'Mie' },
  { key: 'thursday', label: 'Jue' },
  { key: 'friday', label: 'Vie' },
  { key: 'saturday', label: 'Sab' },
  { key: 'sunday', label: 'Dom' },
]

const form = useForm({
  stylist_id: props.stylist.id,
  starts_at: '',
  ends_at: '',
  reason: '',
  is_salon_wide: false,
})

const submitBlock = () => {
  form.post(`/salon/${tenantId}/bloqueos`, {
    onSuccess: () => form.reset('starts_at', 'ends_at', 'reason', 'is_salon_wide'),
    preserveScroll: true,
  })
}

const deleteBlock = (id) => {
  if (confirm('Eliminar este bloqueo?')) {
    router.delete(`/salon/${tenantId}/bloqueos/${id}`, { preserveScroll: true })
  }
}

const formatDate = (d) => {
  return new Date(d).toLocaleString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <Head :title="`Horario - ${stylist.name}`" />

  <div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">{{ stylist.name }}</h1>
        <p class="text-sm text-gray-500">Horario y bloqueos</p>
      </div>
      <Link :href="`/salon/${tenantId}/estilistas`">
        <Button variant="outline">Volver</Button>
      </Link>
    </div>

    <!-- Horario regular -->
    <Card>
      <CardHeader><CardTitle class="text-base">Horario regular</CardTitle></CardHeader>
      <CardContent>
        <div class="grid grid-cols-7 gap-2">
          <div v-for="day in days" :key="day.key" class="text-center">
            <p class="text-xs font-medium text-gray-500 mb-2">{{ day.label }}</p>
            <div v-if="stylist.schedule?.[day.key]?.length">
              <div
                v-for="(slot, i) in stylist.schedule[day.key]"
                :key="i"
                class="text-xs bg-primary/10 text-primary rounded px-1 py-1 mb-1"
              >
                {{ slot.start }}<br/>{{ slot.end }}
              </div>
            </div>
            <div v-else class="text-xs text-gray-300">Libre</div>
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">Para modificar el horario, edita el perfil del estilista.</p>
      </CardContent>
    </Card>

    <!-- Crear bloqueo -->
    <Card>
      <CardHeader><CardTitle class="text-base">Nuevo bloqueo</CardTitle></CardHeader>
      <CardContent>
        <form @submit.prevent="submitBlock" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Inicio</Label>
              <Input v-model="form.starts_at" type="datetime-local" required />
              <p v-if="form.errors.starts_at" class="text-sm text-red-500">{{ form.errors.starts_at }}</p>
            </div>
            <div class="space-y-2">
              <Label>Fin</Label>
              <Input v-model="form.ends_at" type="datetime-local" required />
              <p v-if="form.errors.ends_at" class="text-sm text-red-500">{{ form.errors.ends_at }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label>Motivo</Label>
            <Input v-model="form.reason" placeholder="Vacaciones, cita medica, etc." />
          </div>

          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_salon_wide" class="rounded border-gray-300" />
            <span class="text-sm">Bloquear todo el salon (no solo este estilista)</span>
          </label>

          <Button type="submit" :disabled="form.processing">Crear bloqueo</Button>
        </form>
      </CardContent>
    </Card>

    <!-- Lista de bloqueos -->
    <Card>
      <CardHeader><CardTitle class="text-base">Bloqueos activos</CardTitle></CardHeader>
      <CardContent>
        <div v-if="blockedTimes?.length" class="space-y-2">
          <div
            v-for="bt in blockedTimes"
            :key="bt.id"
            class="flex items-center justify-between py-2 border-b last:border-0"
          >
            <div>
              <p class="text-sm font-medium">
                {{ formatDate(bt.starts_at) }} — {{ formatDate(bt.ends_at) }}
              </p>
              <p v-if="bt.reason" class="text-xs text-gray-500">{{ bt.reason }}</p>
              <div class="flex gap-2 mt-1">
                <Badge v-if="!bt.stylist_id" variant="destructive" class="text-xs">Todo el salon</Badge>
                <span v-if="bt.creator" class="text-xs text-gray-400">por {{ bt.creator.name }}</span>
              </div>
            </div>
            <Button variant="ghost" size="sm" class="text-red-500" @click="deleteBlock(bt.id)">
              Eliminar
            </Button>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400">Sin bloqueos activos</p>
      </CardContent>
    </Card>
  </div>
</template>
