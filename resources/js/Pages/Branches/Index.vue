<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  branches: Array,
  canCreate: Boolean,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const deleteBranch = (b) => {
  if (confirm(`Eliminar/desactivar "${b.name}"?`)) {
    router.delete(`${base}/sucursales/${b.id}`)
  }
}
</script>

<template>
  <Head title="Sucursales" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Sucursales</h1>
      <Link v-if="canCreate" :href="`${base}/sucursales/create`">
        <Button>+ Nueva sucursal</Button>
      </Link>
      <Button v-else variant="outline" disabled title="Plan Cadena requerido">
        + Nueva sucursal (Plan Cadena)
      </Button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card v-for="b in branches" :key="b.id" class="relative">
        <CardContent class="pt-6 space-y-3">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="font-semibold text-gray-900">{{ b.name }}</h3>
              <p v-if="b.address" class="text-sm text-gray-500">{{ b.address }}</p>
            </div>
            <div class="flex gap-1">
              <Badge v-if="b.is_main" variant="default" class="text-xs">Matriz</Badge>
              <Badge :class="b.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="text-xs">
                {{ b.is_active ? 'Activa' : 'Inactiva' }}
              </Badge>
            </div>
          </div>

          <div v-if="b.phone || b.email" class="text-sm text-gray-500">
            <p v-if="b.phone">{{ b.phone }}</p>
            <p v-if="b.email">{{ b.email }}</p>
          </div>

          <div v-if="b.manager" class="text-sm">
            <span class="text-gray-500">Gerente:</span> {{ b.manager.name }}
          </div>

          <div v-if="b.ruc" class="text-sm text-gray-500">
            RUC: {{ b.ruc }} <span v-if="b.razon_social">· {{ b.razon_social }}</span>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-2 border-t text-center text-sm">
            <div>
              <p class="font-semibold">{{ b.stylists_count || 0 }}</p>
              <p class="text-xs text-gray-500">Estilistas</p>
            </div>
            <div>
              <p class="font-semibold">{{ b.appointments_today || 0 }}</p>
              <p class="text-xs text-gray-500">Citas hoy</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-1.5 text-xs text-gray-400">
            <span>SRI: {{ b.sri_establishment }}-{{ b.sri_emission_point }}</span>
            <span>· {{ b.sri_ambiente === 'production' ? 'Produccion' : 'Pruebas' }}</span>
            <Badge v-if="b.sri_certificate_uploaded" class="bg-green-100 text-green-700 text-[10px] px-1.5 py-0">Cert</Badge>
          </div>

          <div class="flex gap-1 pt-2">
            <Link :href="`${base}/sucursales/${b.id}/edit`" class="flex-1">
              <Button variant="outline" size="sm" class="w-full">Editar</Button>
            </Link>
            <Button variant="ghost" size="sm" class="text-red-600" @click="deleteBranch(b)">
              {{ b.is_main ? '' : 'Eliminar' }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <div v-if="!branches?.length" class="text-center py-12 text-gray-500">
      <p>No hay sucursales configuradas.</p>
      <p class="text-sm mt-1">Al crear la primera, se marca automaticamente como matriz.</p>
    </div>
  </div>
</template>
