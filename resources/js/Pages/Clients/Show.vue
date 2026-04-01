<script setup>
import { ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  client: Object,
  pastAppointments: Array,
  futureAppointments: Array,
  sales: Array,
  metrics: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const activeTab = ref('historial')

const initials = ((props.client.first_name?.[0] || '') + (props.client.last_name?.[0] || '')).toUpperCase()

const formatDate = (d) => new Date(d).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' })
const formatTime = (d) => new Date(d).toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' })

const statusLabels = {
  pending: 'Pendiente', confirmed: 'Confirmada', in_progress: 'En progreso',
  completed: 'Completada', cancelled: 'Cancelada', no_show: 'No show',
}
const statusColors = {
  completed: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700',
  no_show: 'bg-orange-100 text-orange-700', pending: 'bg-gray-100 text-gray-700',
  confirmed: 'bg-blue-100 text-blue-700', in_progress: 'bg-emerald-100 text-emerald-700',
}

const daysToBirthday = () => {
  if (!props.client.birthday) return null
  const today = new Date()
  const bday = new Date(props.client.birthday)
  bday.setFullYear(today.getFullYear())
  if (bday < today) bday.setFullYear(today.getFullYear() + 1)
  return Math.ceil((bday - today) / 86400000)
}
</script>

<template>
  <Head :title="`${client.first_name} ${client.last_name}`" />

  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">{{ client.first_name }} {{ client.last_name }}</h1>
      <div class="flex gap-2">
        <Link :href="`/salon/${tenantId}/clientes/${client.id}/edit`">
          <Button variant="outline">Editar</Button>
        </Link>
        <Link :href="`/salon/${tenantId}/clientes`">
          <Button variant="outline">Volver</Button>
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left panel - Client info -->
      <Card>
        <CardContent class="pt-6 space-y-4">
          <div class="text-center">
            <Avatar class="h-20 w-20 mx-auto">
              <AvatarFallback class="text-2xl">{{ initials }}</AvatarFallback>
            </Avatar>
            <h2 class="mt-3 font-semibold text-lg">{{ client.first_name }} {{ client.last_name }}</h2>
            <div class="flex justify-center gap-1 mt-1">
              <Badge v-for="tag in (client.tags || [])" :key="tag" variant="secondary" class="text-xs">{{ tag }}</Badge>
            </div>
          </div>

          <div class="space-y-3 text-sm border-t pt-4">
            <div class="flex justify-between">
              <span class="text-gray-500">Telefono</span>
              <a :href="`https://wa.me/593${client.phone?.replace(/^0/, '')}`" target="_blank" class="text-primary hover:underline">{{ client.phone }}</a>
            </div>
            <div v-if="client.email" class="flex justify-between">
              <span class="text-gray-500">Email</span>
              <span>{{ client.email }}</span>
            </div>
            <div v-if="client.cedula" class="flex justify-between">
              <span class="text-gray-500">Cedula</span>
              <span>{{ client.cedula }}</span>
            </div>
            <div v-if="client.birthday" class="flex justify-between">
              <span class="text-gray-500">Cumpleanos</span>
              <span>
                {{ formatDate(client.birthday) }}
                <span v-if="daysToBirthday() <= 30" class="text-primary text-xs ml-1">(en {{ daysToBirthday() }}d)</span>
              </span>
            </div>
            <div v-if="client.preferred_stylist" class="flex justify-between">
              <span class="text-gray-500">Estilista favorito</span>
              <span>{{ client.preferred_stylist.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Fuente</span>
              <span>{{ client.source }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Puntos fidelidad</span>
              <span class="font-medium">{{ client.loyalty_points }}</span>
            </div>
          </div>

          <!-- Allergies alert -->
          <div v-if="client.allergies" class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-xs font-medium text-red-700 flex items-center gap-1">⚠ Alergias</p>
            <p class="text-sm text-red-600 mt-1">{{ client.allergies }}</p>
          </div>

          <!-- Notes -->
          <div v-if="client.notes" class="text-sm">
            <p class="text-gray-500 text-xs mb-1">Notas del equipo</p>
            <p class="text-gray-700">{{ client.notes }}</p>
          </div>

          <!-- Metrics -->
          <div class="grid grid-cols-2 gap-3 border-t pt-4">
            <div class="text-center">
              <p class="text-2xl font-bold">{{ metrics.total_visits }}</p>
              <p class="text-xs text-gray-500">Visitas</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold">${{ Number(metrics.total_spent).toFixed(0) }}</p>
              <p class="text-xs text-gray-500">Total gastado</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold">${{ Number(metrics.avg_ticket).toFixed(0) }}</p>
              <p class="text-xs text-gray-500">Ticket promedio</p>
            </div>
            <div class="text-center">
              <p class="text-sm font-medium truncate">{{ metrics.favorite_service || '-' }}</p>
              <p class="text-xs text-gray-500">Servicio favorito</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Right panel - Tabs -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Tab nav -->
        <div class="flex border-b">
          <button
            v-for="tab in [{ key: 'historial', label: 'Historial' }, { key: 'compras', label: 'Compras' }, { key: 'futuras', label: 'Citas futuras' }]"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors',
              activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700']"
          >{{ tab.label }}</button>
        </div>

        <!-- Tab: Historial -->
        <Card v-if="activeTab === 'historial'">
          <CardContent class="pt-4">
            <div v-if="pastAppointments?.length" class="space-y-3">
              <div v-for="apt in pastAppointments" :key="apt.id" class="flex items-start gap-3 py-2 border-b last:border-0">
                <div class="w-2 h-2 rounded-full mt-2" :style="{ backgroundColor: apt.stylist?.color || '#94a3b8' }" />
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium">{{ apt.service?.name }}</p>
                    <span :class="['text-xs px-1.5 py-0.5 rounded-full', statusColors[apt.status?.value || apt.status]]">
                      {{ statusLabels[apt.status?.value || apt.status] }}
                    </span>
                  </div>
                  <p class="text-xs text-gray-500">
                    {{ formatDate(apt.starts_at) }} {{ formatTime(apt.starts_at) }} — {{ apt.stylist?.name }}
                  </p>
                  <p v-if="apt.notes" class="text-xs text-gray-400 mt-1">{{ apt.notes }}</p>
                </div>
                <p class="text-sm font-medium">${{ Number(apt.service?.base_price || 0).toFixed(2) }}</p>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-6">Sin historial de citas</p>
          </CardContent>
        </Card>

        <!-- Tab: Compras -->
        <Card v-if="activeTab === 'compras'">
          <CardContent class="pt-4">
            <div v-if="sales?.length" class="space-y-3">
              <div v-for="sale in sales" :key="sale.id" class="py-2 border-b last:border-0">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-500">{{ formatDate(sale.created_at) }}</span>
                  <span class="font-semibold">${{ Number(sale.total).toFixed(2) }}</span>
                </div>
                <div v-for="item in sale.items" :key="item.id" class="text-xs text-gray-500 ml-2">
                  {{ item.name }} x{{ item.quantity }} — ${{ Number(item.subtotal).toFixed(2) }}
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-6">Sin compras registradas</p>
          </CardContent>
        </Card>

        <!-- Tab: Futuras -->
        <Card v-if="activeTab === 'futuras'">
          <CardContent class="pt-4">
            <div v-if="futureAppointments?.length" class="space-y-3">
              <div v-for="apt in futureAppointments" :key="apt.id" class="flex items-center justify-between py-2 border-b last:border-0">
                <div>
                  <p class="text-sm font-medium">{{ apt.service?.name }}</p>
                  <p class="text-xs text-gray-500">{{ formatDate(apt.starts_at) }} {{ formatTime(apt.starts_at) }} — {{ apt.stylist?.name }}</p>
                </div>
                <Badge variant="secondary">{{ statusLabels[apt.status?.value || apt.status] }}</Badge>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400 text-center py-6">Sin citas futuras</p>
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
