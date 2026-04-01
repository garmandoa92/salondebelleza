<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ packages: Array })

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`

const typeLabel = (t) => t === 'sessions' ? 'Bono sesiones' : 'Combo'
const typeColor = (t) => t === 'sessions' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'

const toggleActive = (pkg) => {
  router.put(`${base}/paquetes/${pkg.id}`, { ...pkg, is_active: !pkg.is_active }, { preserveScroll: true })
}

const deletePackage = (pkg) => {
  if (confirm(`Eliminar "${pkg.name}"?`)) router.delete(`${base}/paquetes/${pkg.id}`)
}
</script>

<template>
  <Head title="Paquetes" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Paquetes y Bonos</h1>
      <Link :href="`${base}/paquetes/create`"><Button>+ Nuevo paquete</Button></Link>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card v-for="pkg in packages" :key="pkg.id" :class="{ 'opacity-50': !pkg.is_active }">
        <CardContent class="pt-6 space-y-3">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="font-semibold text-gray-900">{{ pkg.name }}</h3>
              <p class="text-2xl font-bold mt-1">${{ Number(pkg.price).toFixed(2) }}</p>
            </div>
            <Badge :class="typeColor(pkg.type)" class="text-xs">{{ typeLabel(pkg.type) }}</Badge>
          </div>

          <p v-if="pkg.description" class="text-sm text-gray-500 line-clamp-2">{{ pkg.description }}</p>

          <div class="text-sm text-gray-600 space-y-0.5">
            <div v-for="item in pkg.items" :key="item.service_id">
              {{ item.quantity }}x {{ item.service_name }}
            </div>
          </div>

          <div class="flex items-center justify-between text-xs text-gray-400 pt-2 border-t">
            <span>Validez: {{ pkg.validity_days }} dias</span>
            <span>{{ pkg.active_clients || 0 }} clientes activos</span>
          </div>

          <div class="flex gap-1 pt-1">
            <Link :href="`${base}/paquetes/${pkg.id}/edit`" class="flex-1">
              <Button variant="outline" size="sm" class="w-full">Editar</Button>
            </Link>
            <Button variant="ghost" size="sm" :class="pkg.is_active ? 'text-amber-600' : 'text-green-600'" @click="toggleActive(pkg)">
              {{ pkg.is_active ? 'Desactivar' : 'Activar' }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <div v-if="!packages?.length" class="text-center py-12 text-gray-500">
      <p>No hay paquetes creados.</p>
      <p class="text-sm mt-1">Crea tu primer paquete de sesiones o combo de servicios.</p>
    </div>
  </div>
</template>
