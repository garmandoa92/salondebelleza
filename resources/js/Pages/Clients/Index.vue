<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  clients: Object,
  stylists: Array,
  filters: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const search = ref(props.filters?.search || '')
let debounceTimer = null

watch(search, (val) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(`/salon/${tenantId}/clientes`, {
      search: val || undefined,
      ...Object.fromEntries(Object.entries(props.filters || {}).filter(([k]) => k !== 'search')),
    }, { preserveState: true, preserveScroll: true })
  }, 300)
})

const applyFilter = (key, value) => {
  router.get(`/salon/${tenantId}/clientes`, {
    ...props.filters,
    [key]: value || undefined,
  }, { preserveState: true, preserveScroll: true })
}

const deleteClient = (client) => {
  if (confirm(`Eliminar "${client.first_name} ${client.last_name}"?`)) {
    router.delete(`/salon/${tenantId}/clientes/${client.id}`)
  }
}

const initials = (c) => ((c.first_name?.[0] || '') + (c.last_name?.[0] || '')).toUpperCase()

const isInactive = (c) => {
  if (!c.last_visit_at) return true
  const daysSince = Math.floor((Date.now() - new Date(c.last_visit_at).getTime()) / 86400000)
  return daysSince > 60
}

const daysSince = (date) => {
  if (!date) return null
  return Math.floor((Date.now() - new Date(date).getTime()) / 86400000)
}
</script>

<template>
  <Head title="Clientes" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Clientes</h1>
      <Link :href="`/salon/${tenantId}/clientes/create`">
        <Button>+ Nuevo cliente</Button>
      </Link>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <Input v-model="search" placeholder="Buscar por nombre, telefono o cedula..." class="max-w-sm" />

      <select
        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
        :value="filters?.stylist_id || ''"
        @change="applyFilter('stylist_id', $event.target.value)"
      >
        <option value="">Todos los estilistas</option>
        <option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>

      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input
          type="checkbox"
          :checked="filters?.inactive"
          @change="applyFilter('inactive', $event.target.checked ? '1' : undefined)"
          class="rounded border-gray-300"
        />
        Solo inactivos (60+ dias)
      </label>
    </div>

    <!-- Table -->
    <Card>
      <CardContent class="pt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium">Cliente</th>
              <th class="pb-2 font-medium">Telefono</th>
              <th class="pb-2 font-medium text-center">Visitas</th>
              <th class="pb-2 font-medium text-right">Total gastado</th>
              <th class="pb-2 font-medium">Ultima visita</th>
              <th class="pb-2 font-medium text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="client in clients.data"
              :key="client.id"
              class="border-b last:border-0 hover:bg-gray-50"
              :class="{ 'opacity-50': isInactive(client) }"
            >
              <td class="py-3">
                <Link :href="`/salon/${tenantId}/clientes/${client.id}`" class="flex items-center gap-2 hover:text-primary">
                  <Avatar class="h-8 w-8">
                    <AvatarFallback class="text-xs">{{ initials(client) }}</AvatarFallback>
                  </Avatar>
                  <div>
                    <span class="font-medium">{{ client.first_name }} {{ client.last_name }}</span>
                    <div class="flex gap-1 mt-0.5">
                      <Badge v-for="tag in (client.tags || [])" :key="tag" variant="secondary" class="text-[10px] px-1 py-0">{{ tag }}</Badge>
                      <Badge v-if="client.allergies" variant="destructive" class="text-[10px] px-1 py-0">Alergias</Badge>
                    </div>
                  </div>
                </Link>
              </td>
              <td class="py-3 text-gray-600">{{ client.phone }}</td>
              <td class="py-3 text-center">{{ client.visit_count }}</td>
              <td class="py-3 text-right font-medium">${{ Number(client.total_spent).toFixed(2) }}</td>
              <td class="py-3 text-gray-600">
                <template v-if="client.last_visit_at">
                  {{ daysSince(client.last_visit_at) }}d atras
                </template>
                <template v-else>
                  <span class="text-gray-400">Nunca</span>
                </template>
              </td>
              <td class="py-3 text-right">
                <div class="flex justify-end gap-1">
                  <Link :href="`/salon/${tenantId}/clientes/${client.id}`">
                    <Button variant="ghost" size="sm">Ver</Button>
                  </Link>
                  <Link :href="`/salon/${tenantId}/clientes/${client.id}/edit`">
                    <Button variant="ghost" size="sm">Editar</Button>
                  </Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="!clients.data?.length" class="text-center py-8 text-gray-400">
          No se encontraron clientes
        </div>

        <!-- Pagination -->
        <div v-if="clients.last_page > 1" class="flex justify-center gap-1 pt-4">
          <Link
            v-for="link in clients.links"
            :key="link.label"
            :href="link.url || '#'"
            :class="['px-3 py-1 rounded text-sm',
              link.active ? 'bg-primary text-white' : link.url ? 'hover:bg-gray-100' : 'text-gray-300']"
            v-html="link.label"
            preserve-state
          />
        </div>
      </CardContent>
    </Card>
  </div>
</template>
