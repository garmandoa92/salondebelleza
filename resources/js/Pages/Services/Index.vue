<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/Layouts/AppLayout.vue'
import CategoryModal from './CategoryModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  categories: Array,
  filters: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const search = ref(props.filters?.search || '')
const showCategoryModal = ref(false)
const editingCategory = ref(null)
let debounceTimer = null

watch(search, (val) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(`/salon/${tenantId}/servicios`, { search: val || undefined }, {
      preserveState: true,
      preserveScroll: true,
    })
  }, 300)
})

const toggleActive = (service) => {
  router.patch(`/salon/${tenantId}/servicios/${service.id}/toggle`, {}, {
    preserveScroll: true,
  })
}

const deleteService = (service) => {
  if (confirm(`Eliminar "${service.name}"?`)) {
    router.delete(`/salon/${tenantId}/servicios/${service.id}`, {
      preserveScroll: true,
    })
  }
}

const openCategoryModal = (cat = null) => {
  editingCategory.value = cat
  showCategoryModal.value = true
}

const formatDuration = (mins) => {
  if (mins < 60) return `${mins}min`
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m ? `${h}h ${m}min` : `${h}h`
}
</script>

<template>
  <Head title="Servicios" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Servicios</h1>
      <div class="flex gap-2">
        <Button variant="outline" @click="openCategoryModal()">
          + Categoria
        </Button>
        <Link :href="`/salon/${tenantId}/servicios/create`">
          <Button>+ Nuevo servicio</Button>
        </Link>
      </div>
    </div>

    <Input
      v-model="search"
      placeholder="Buscar servicios..."
      class="max-w-sm"
    />

    <div v-if="categories?.length" class="space-y-4">
      <Card v-for="cat in categories" :key="cat.id">
        <CardHeader class="pb-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span
                class="w-3 h-3 rounded-full"
                :style="{ backgroundColor: cat.color }"
              />
              <CardTitle class="text-base">{{ cat.name }}</CardTitle>
              <Badge variant="secondary" class="text-xs">{{ cat.services?.length || 0 }}</Badge>
            </div>
            <div class="flex gap-1">
              <Button variant="ghost" size="sm" @click="openCategoryModal(cat)">
                Editar
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent v-if="cat.services?.length" class="pt-0">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b text-left text-gray-500">
                  <th class="pb-2 font-medium">Servicio</th>
                  <th class="pb-2 font-medium">Duracion</th>
                  <th class="pb-2 font-medium text-right">Precio</th>
                  <th class="pb-2 font-medium text-center">IVA</th>
                  <th class="pb-2 font-medium text-center">Visible</th>
                  <th class="pb-2 font-medium text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="service in cat.services"
                  :key="service.id"
                  class="border-b last:border-0 hover:bg-gray-50"
                >
                  <td class="py-3">
                    <div class="font-medium text-gray-900">{{ service.name }}</div>
                    <div v-if="service.description" class="text-xs text-gray-500 truncate max-w-xs">{{ service.description }}</div>
                  </td>
                  <td class="py-3 text-gray-600">{{ formatDuration(service.duration_minutes) }}</td>
                  <td class="py-3 text-right font-medium">${{ Number(service.base_price).toFixed(2) }}</td>
                  <td class="py-3 text-center">
                    <span v-if="service.iva_rate === null || service.iva_rate === undefined" class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500">{{ $page.props.tenantIva || 15 }}% (global)</span>
                    <span v-else-if="Number(service.iva_rate) === 0" class="text-xs px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">0%</span>
                    <span v-else class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ service.iva_rate }}%</span>
                  </td>
                  <td class="py-3 text-center">
                    <button
                      @click="toggleActive(service)"
                      :class="[
                        'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
                        service.is_visible ? 'bg-primary' : 'bg-gray-300'
                      ]"
                    >
                      <span
                        :class="[
                          'inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform',
                          service.is_visible ? 'translate-x-4.5' : 'translate-x-1'
                        ]"
                      />
                    </button>
                  </td>
                  <td class="py-3 text-right">
                    <div class="flex justify-end gap-1">
                      <Link :href="`/salon/${tenantId}/servicios/${service.id}/edit`">
                        <Button variant="ghost" size="sm">Editar</Button>
                      </Link>
                      <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-700" @click="deleteService(service)">
                        Eliminar
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
        <CardContent v-else class="pt-0">
          <p class="text-sm text-gray-400">Sin servicios en esta categoria</p>
        </CardContent>
      </Card>
    </div>

    <div v-else class="text-center py-12 text-gray-500">
      No hay categorias de servicios. Crea una para empezar.
    </div>

    <CategoryModal
      v-if="showCategoryModal"
      :category="editingCategory"
      @close="showCategoryModal = false"
    />
  </div>
</template>
