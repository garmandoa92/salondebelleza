<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import axios from 'axios'

const props = defineProps({
  saleId: String,
  open: Boolean,
})
const emit = defineEmits(['close'])

const page = usePage()
const base = `/salon/${page.props.tenant?.id}`
const sale = ref(null)
const loading = ref(false)

watch(() => props.saleId, async (id) => {
  if (!id) return
  loading.value = true
  try {
    const { data } = await axios.get(`${base}/ventas/${id}`)
    sale.value = data
  } finally { loading.value = false }
})

const statusLabels = { draft: 'Borrador', completed: 'Completada', refunded: 'Reembolsada' }
const statusColors = { draft: 'bg-gray-100 text-gray-700', completed: 'bg-green-100 text-green-700', refunded: 'bg-red-100 text-red-700' }
const typeLabels = { service: 'Servicio', product: 'Producto', package: 'Paquete' }
const typeColors = { service: 'bg-blue-100 text-blue-700', product: 'bg-amber-100 text-amber-700', package: 'bg-purple-100 text-purple-700' }
const paymentLabels = { cash: 'Efectivo', transfer: 'Transferencia', card_debit: 'T. Debito', card_credit: 'T. Credito', other: 'Otro' }

const formatDate = (d) => d ? new Date(d).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'
const initials = (n) => n?.split(' ').map(w => w?.[0]).filter(Boolean).join('').toUpperCase().slice(0, 2) || '?'
const st = (s) => s?.value || s || 'draft'
const itemType = (t) => t?.value || t || 'service'
const whatsappUrl = (phone) => `https://wa.me/593${phone?.replace(/^0/, '').replace(/\D/g, '')}`
</script>

<template>
  <Transition name="drawer">
    <div v-if="open" class="fixed inset-0 z-50 flex justify-end">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <div class="relative w-full max-w-lg bg-white shadow-xl overflow-y-auto">
        <div v-if="loading" class="flex items-center justify-center h-64">
          <span class="text-gray-400">Cargando...</span>
        </div>

        <div v-else-if="sale" class="p-5 space-y-5">
          <!-- Header -->
          <div class="flex items-start justify-between">
            <div>
              <h2 class="font-semibold text-gray-900 text-lg">Venta</h2>
              <p class="text-xs text-gray-400 font-mono">{{ sale.id?.slice(0, 8) }}...</p>
              <p class="text-sm text-gray-500">{{ formatDate(sale.completed_at || sale.created_at) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <Badge :class="statusColors[st(sale.status)]" class="text-xs">{{ statusLabels[st(sale.status)] }}</Badge>
              <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
          </div>

          <!-- Client -->
          <div class="flex items-center gap-3 bg-gray-50 rounded-lg p-3">
            <Avatar class="h-10 w-10">
              <AvatarFallback class="text-sm">{{ sale.client ? initials(`${sale.client.first_name} ${sale.client.last_name}`) : '?' }}</AvatarFallback>
            </Avatar>
            <div class="flex-1">
              <p class="font-medium text-sm">{{ sale.client ? `${sale.client.first_name} ${sale.client.last_name}` : 'Sin cliente' }}</p>
              <a v-if="sale.client?.phone" :href="whatsappUrl(sale.client.phone)" target="_blank" class="text-xs text-green-600 hover:underline">
                {{ sale.client.phone }}
              </a>
            </div>
          </div>

          <!-- Items -->
          <div>
            <h3 class="text-sm font-medium text-gray-500 mb-2">Items</h3>
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b text-gray-400 text-xs">
                  <th class="text-left pb-1 font-medium">Descripcion</th>
                  <th class="w-12 pb-1 font-medium text-center">Cant</th>
                  <th class="w-16 pb-1 font-medium text-right">P.U.</th>
                  <th class="w-16 pb-1 font-medium text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in sale.items" :key="item.id" class="border-b last:border-0">
                  <td class="py-2">
                    <div class="flex items-center gap-1.5">
                      <Badge :class="typeColors[itemType(item.type)]" class="text-[10px] px-1.5 py-0">{{ typeLabels[itemType(item.type)] }}</Badge>
                      <span class="font-medium">{{ item.name }}</span>
                    </div>
                    <div v-if="item.stylist" class="text-xs text-gray-400 mt-0.5 flex items-center gap-1">
                      <span class="w-2 h-2 rounded-full inline-block" :style="{ backgroundColor: item.stylist.color }"></span>
                      {{ item.stylist.name }}
                      <span v-if="item.commission" class="text-gray-400 ml-1">({{ item.commission.rate }}% = ${{ Number(item.commission.amount).toFixed(2) }})</span>
                    </div>
                  </td>
                  <td class="py-2 text-center text-gray-600">{{ item.quantity }}</td>
                  <td class="py-2 text-right text-gray-600">${{ Number(item.unit_price).toFixed(2) }}</td>
                  <td class="py-2 text-right font-medium">${{ Number(item.subtotal).toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Financial summary -->
          <div class="bg-gray-50 rounded-lg p-4 space-y-1.5 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>${{ Number(sale.subtotal).toFixed(2) }}</span></div>
            <div v-if="Number(sale.discount_amount) > 0" class="flex justify-between text-red-500">
              <span>Descuento {{ sale.discount_type === 'percentage' ? `(${sale.discount_reason || ''})` : '' }}</span>
              <span>-${{ Number(sale.discount_amount).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between"><span class="text-gray-500">IVA {{ sale.iva_rate || 15 }}%</span><span>${{ Number(sale.iva_amount).toFixed(2) }}</span></div>
            <div class="flex justify-between text-lg font-bold border-t pt-2 mt-1"><span>Total</span><span>${{ Number(sale.total).toFixed(2) }}</span></div>
            <div v-if="Number(sale.tip) > 0" class="flex justify-between text-gray-500"><span>Propina</span><span>+${{ Number(sale.tip).toFixed(2) }}</span></div>
          </div>

          <!-- Payment methods -->
          <div>
            <h3 class="text-sm font-medium text-gray-500 mb-2">Metodos de pago</h3>
            <div class="flex flex-wrap gap-2">
              <div v-for="(p, i) in sale.payment_methods" :key="i" class="bg-gray-100 rounded-lg px-3 py-2 text-sm">
                <span class="font-medium">{{ paymentLabels[p.method] || p.method }}</span>
                <span class="text-gray-600 ml-1">${{ Number(p.amount).toFixed(2) }}</span>
              </div>
            </div>
          </div>

          <!-- Invoice -->
          <div>
            <h3 class="text-sm font-medium text-gray-500 mb-2">Factura electronica</h3>
            <div v-if="sale.sri_invoice" class="bg-green-50 rounded-lg p-3 text-sm space-y-1">
              <div class="flex items-center gap-2">
                <Badge class="bg-green-200 text-green-800 text-xs">{{ sale.sri_invoice.status }}</Badge>
                <span class="font-mono text-xs text-gray-500">{{ sale.sri_invoice.access_key?.slice(0, 20) }}...</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400">Sin comprobante generado</p>
          </div>

          <!-- Actions -->
          <div class="space-y-2 pt-2 border-t">
            <Button v-if="sale.client?.phone" variant="outline" class="w-full text-green-600 border-green-300" @click="window.open(whatsappUrl(sale.client.phone), '_blank')">
              Enviar por WhatsApp
            </Button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: all 0.25s ease-out; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from > div:last-child, .drawer-leave-to > div:last-child { transform: translateX(100%); }
</style>
