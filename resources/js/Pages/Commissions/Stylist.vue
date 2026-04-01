<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  stylist: Object,
  detail: Array,
  period: Object,
  total: Number,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const initials = props.stylist.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)

const payAll = async () => {
  const pendingIds = props.detail.filter(d => d.status === 'pending').map(d => d.id)
  if (!pendingIds.length) return
  if (!confirm(`Marcar ${pendingIds.length} comisiones como pagadas?`)) return

  await axios.post(`${base}/comisiones/pay`, { commission_ids: pendingIds })
  router.reload()
}

const pendingTotal = props.detail.filter(d => d.status === 'pending').reduce((sum, d) => sum + Number(d.amount), 0)
</script>

<template>
  <Head :title="`Comisiones - ${stylist.name}`" />

  <div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Avatar class="h-12 w-12">
          <AvatarFallback class="text-white" :style="{ backgroundColor: stylist.color }">{{ initials }}</AvatarFallback>
        </Avatar>
        <div>
          <h1 class="text-xl font-bold">{{ stylist.name }}</h1>
          <p class="text-sm text-gray-500">{{ period.start }} — {{ period.end }}</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-right">
          <p class="text-2xl font-bold text-primary">${{ Number(total).toFixed(2) }}</p>
          <p class="text-xs text-gray-500">Total a pagar</p>
        </div>
        <Link :href="`${base}/comisiones`"><Button variant="outline">Volver</Button></Link>
      </div>
    </div>

    <Card>
      <CardHeader class="pb-2">
        <div class="flex items-center justify-between">
          <CardTitle class="text-base">Detalle de servicios</CardTitle>
          <Button v-if="pendingTotal > 0" size="sm" @click="payAll">
            Marcar todo pagado (${{ pendingTotal.toFixed(2) }})
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium">Fecha</th>
              <th class="pb-2 font-medium">Cliente</th>
              <th class="pb-2 font-medium">Servicio</th>
              <th class="pb-2 font-medium text-right">Precio</th>
              <th class="pb-2 font-medium text-center">%</th>
              <th class="pb-2 font-medium text-right">Comision</th>
              <th class="pb-2 font-medium text-center">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in detail" :key="d.id" class="border-b last:border-0 hover:bg-gray-50">
              <td class="py-2 text-gray-600 text-xs">{{ d.date }}</td>
              <td class="py-2">{{ d.client }}</td>
              <td class="py-2">{{ d.service }}</td>
              <td class="py-2 text-right">${{ Number(d.price).toFixed(2) }}</td>
              <td class="py-2 text-center">{{ d.rate }}%</td>
              <td class="py-2 text-right font-medium">${{ Number(d.amount).toFixed(2) }}</td>
              <td class="py-2 text-center">
                <Badge :class="d.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'" class="text-xs">
                  {{ d.status === 'paid' ? 'Pagado' : 'Pendiente' }}
                </Badge>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t font-bold">
              <td colspan="5" class="py-3 text-right">Total:</td>
              <td class="py-3 text-right text-primary">${{ Number(total).toFixed(2) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        <div v-if="!detail?.length" class="text-center py-8 text-gray-400">Sin comisiones en este periodo</div>
      </CardContent>
    </Card>
  </div>
</template>
