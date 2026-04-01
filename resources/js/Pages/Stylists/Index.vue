<script setup>
import { ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  stylists: Array,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const viewMode = ref(localStorage.getItem('stylists_view') || 'grid')

const setView = (mode) => {
  viewMode.value = mode
  localStorage.setItem('stylists_view', mode)
}

const toggleActive = (stylist) => {
  router.patch(`/salon/${tenantId}/estilistas/${stylist.id}/toggle`, {}, {
    preserveScroll: true,
  })
}

const deleteStylist = (stylist) => {
  if (confirm(`Eliminar "${stylist.name}"?`)) {
    router.delete(`/salon/${tenantId}/estilistas/${stylist.id}`)
  }
}

const initials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
</script>

<template>
  <Head title="Estilistas" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Estilistas</h1>
      <div class="flex gap-2">
        <div class="flex border rounded-md">
          <Button
            variant="ghost" size="sm"
            :class="viewMode === 'grid' ? 'bg-gray-100' : ''"
            @click="setView('grid')"
          >Grid</Button>
          <Button
            variant="ghost" size="sm"
            :class="viewMode === 'table' ? 'bg-gray-100' : ''"
            @click="setView('table')"
          >Tabla</Button>
        </div>
        <Link :href="`/salon/${tenantId}/estilistas/create`">
          <Button>+ Nuevo estilista</Button>
        </Link>
      </div>
    </div>

    <!-- Grid view -->
    <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card v-for="stylist in stylists" :key="stylist.id" class="relative">
        <CardContent class="pt-6">
          <div class="flex flex-col items-center text-center space-y-3">
            <Avatar class="h-16 w-16">
              <AvatarFallback
                class="text-lg text-white"
                :style="{ backgroundColor: stylist.color }"
              >{{ initials(stylist.name) }}</AvatarFallback>
            </Avatar>

            <div>
              <h3 class="font-semibold text-gray-900">{{ stylist.name }}</h3>
              <p v-if="stylist.phone" class="text-sm text-gray-500">{{ stylist.phone }}</p>
            </div>

            <div class="flex items-center gap-2">
              <Badge :variant="stylist.is_active ? 'default' : 'secondary'">
                {{ stylist.is_active ? 'Activo' : 'Inactivo' }}
              </Badge>
            </div>

            <div class="grid grid-cols-2 gap-4 w-full pt-2 border-t text-sm">
              <div>
                <p class="text-gray-500">Citas este mes</p>
                <p class="font-semibold">{{ stylist.appointments_this_month || 0 }}</p>
              </div>
              <div>
                <p class="text-gray-500">Comision</p>
                <p class="font-semibold">{{ stylist.commission_rules?.default || 0 }}%</p>
              </div>
            </div>

            <div class="flex gap-1 w-full pt-2">
              <Link :href="`/salon/${tenantId}/estilistas/${stylist.id}/edit`" class="flex-1">
                <Button variant="outline" size="sm" class="w-full">Editar</Button>
              </Link>
              <Link :href="`/salon/${tenantId}/estilistas/${stylist.id}/horario`" class="flex-1">
                <Button variant="outline" size="sm" class="w-full">Horario</Button>
              </Link>
              <Button
                variant="ghost" size="sm"
                @click="toggleActive(stylist)"
                :class="stylist.is_active ? 'text-yellow-600' : 'text-green-600'"
              >
                {{ stylist.is_active ? 'Desactivar' : 'Activar' }}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Table view -->
    <Card v-else>
      <CardContent class="pt-6">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium">Estilista</th>
              <th class="pb-2 font-medium">Telefono</th>
              <th class="pb-2 font-medium text-center">Citas/mes</th>
              <th class="pb-2 font-medium text-center">Comision</th>
              <th class="pb-2 font-medium text-center">Estado</th>
              <th class="pb-2 font-medium text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="stylist in stylists" :key="stylist.id" class="border-b last:border-0 hover:bg-gray-50">
              <td class="py-3">
                <div class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: stylist.color }" />
                  <span class="font-medium">{{ stylist.name }}</span>
                </div>
              </td>
              <td class="py-3 text-gray-600">{{ stylist.phone || '-' }}</td>
              <td class="py-3 text-center">{{ stylist.appointments_this_month || 0 }}</td>
              <td class="py-3 text-center">{{ stylist.commission_rules?.default || 0 }}%</td>
              <td class="py-3 text-center">
                <button
                  @click="toggleActive(stylist)"
                  :class="[
                    'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
                    stylist.is_active ? 'bg-primary' : 'bg-gray-300'
                  ]"
                >
                  <span
                    :class="[
                      'inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform',
                      stylist.is_active ? 'translate-x-4.5' : 'translate-x-1'
                    ]"
                  />
                </button>
              </td>
              <td class="py-3 text-right">
                <div class="flex justify-end gap-1">
                  <Link :href="`/salon/${tenantId}/estilistas/${stylist.id}/edit`">
                    <Button variant="ghost" size="sm">Editar</Button>
                  </Link>
                  <Link :href="`/salon/${tenantId}/estilistas/${stylist.id}/horario`">
                    <Button variant="ghost" size="sm">Horario</Button>
                  </Link>
                  <Button variant="ghost" size="sm" class="text-red-600" @click="deleteStylist(stylist)">Eliminar</Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </CardContent>
    </Card>

    <div v-if="!stylists?.length" class="text-center py-12 text-gray-500">
      No hay estilistas. Crea uno para empezar.
    </div>
  </div>
</template>
