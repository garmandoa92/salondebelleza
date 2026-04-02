<script setup>
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  invoices: Object,
  summary: Object,
  filters: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const activeTab = ref(props.filters?.status || '')
const search = ref(props.filters?.search || '')
const showDetail = ref(false)
const detailInvoice = ref(null)

const statusLabels = {
  draft: 'Borrador', signed: 'Firmada', sent: 'Enviada',
  authorized: 'Autorizada', rejected: 'Rechazada', cancelled: 'Anulada',
}
const statusColors = {
  draft: 'bg-gray-100 text-gray-700', signed: 'bg-blue-100 text-blue-700',
  sent: 'bg-yellow-100 text-yellow-700', authorized: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700', cancelled: 'bg-orange-100 text-orange-700',
}
const typeLabels = { invoice: 'Factura', sale_note: 'Nota Venta', credit_note: 'Nota Credito' }

const filterByStatus = (status) => {
  activeTab.value = status
  router.get(`${base}/facturacion`, { ...props.filters, status: status || undefined }, { preserveState: true })
}

const searchInvoices = () => {
  router.get(`${base}/facturacion`, { ...props.filters, search: search.value || undefined }, { preserveState: true })
}

const openDetail = async (inv) => {
  const { data } = await axios.get(`${base}/facturacion/${inv.id}`)
  detailInvoice.value = data
  showDetail.value = true
}

const retryInvoice = async (id) => {
  await axios.post(`${base}/facturacion/${id}/retry`)
  router.reload()
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'

const printRide = () => window.open(`${base}/print/invoice/${detailInvoice.value.id}`, '_blank', 'width=400,height=600')
const openRideHtml = () => window.open(`${base}/facturacion/${detailInvoice.value.id}/ride`, '_blank')
const paymentLabels = { cash: 'Efectivo', transfer: 'Transferencia', card_debit: 'T. Debito', card_credit: 'T. Credito', other: 'Otro' }
</script>

<template>
  <Head title="Facturacion" />

  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Facturacion</h1>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <Card>
        <CardContent class="pt-4 text-center">
          <p class="text-2xl font-bold">${{ Number(summary?.total_amount || 0).toFixed(2) }}</p>
          <p class="text-xs text-gray-500">Total facturado</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-4 text-center">
          <p class="text-2xl font-bold">{{ summary?.total_count || 0 }}</p>
          <p class="text-xs text-gray-500">Comprobantes</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-4 text-center">
          <p class="text-2xl font-bold">${{ Number(summary?.iva_generated || 0).toFixed(2) }}</p>
          <p class="text-xs text-gray-500">IVA generado</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-4 text-center">
          <p class="text-2xl font-bold">{{ summary?.pending_count || 0 }}</p>
          <p class="text-xs text-gray-500">Pendientes</p>
        </CardContent>
      </Card>
    </div>

    <!-- Tabs + Search -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex gap-1">
        <Button v-for="tab in [{ key: '', label: 'Todas' }, { key: 'authorized', label: 'Autorizadas' }, { key: 'sent', label: 'Pendientes' }, { key: 'rejected', label: 'Rechazadas' }, { key: 'cancelled', label: 'Anuladas' }]"
          :key="tab.key"
          :variant="activeTab === tab.key ? 'default' : 'outline'"
          size="sm"
          @click="filterByStatus(tab.key)"
        >{{ tab.label }}</Button>
      </div>
      <form @submit.prevent="searchInvoices" class="flex gap-2">
        <Input v-model="search" placeholder="Buscar numero, RUC, nombre..." class="w-60" />
        <Button type="submit" variant="outline" size="sm">Buscar</Button>
      </form>
    </div>

    <!-- Table -->
    <Card>
      <CardContent class="pt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium">Numero</th>
              <th class="pb-2 font-medium">Tipo</th>
              <th class="pb-2 font-medium">Fecha</th>
              <th class="pb-2 font-medium">Comprador</th>
              <th class="pb-2 font-medium text-right">Total</th>
              <th class="pb-2 font-medium text-center">Estado</th>
              <th class="pb-2 font-medium text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in invoices.data" :key="inv.id" class="border-b last:border-0 hover:bg-gray-50 cursor-pointer" @click="openDetail(inv)">
              <td class="py-3 font-mono text-xs">{{ inv.establishment }}-{{ inv.emission_point }}-{{ inv.sequential }}</td>
              <td class="py-3"><Badge variant="secondary" class="text-xs">{{ typeLabels[inv.invoice_type?.value || inv.invoice_type] || inv.invoice_type }}</Badge></td>
              <td class="py-3 text-gray-600">{{ formatDate(inv.issue_date) }}</td>
              <td class="py-3">
                <span class="font-medium">{{ inv.buyer_name || 'Consumidor final' }}</span>
                <span class="text-xs text-gray-400 ml-1">{{ inv.buyer_identification }}</span>
              </td>
              <td class="py-3 text-right font-medium">${{ Number(inv.total).toFixed(2) }}</td>
              <td class="py-3 text-center">
                <span :class="['text-xs px-2 py-0.5 rounded-full', statusColors[inv.sri_status?.value || inv.sri_status]]">
                  {{ statusLabels[inv.sri_status?.value || inv.sri_status] }}
                </span>
              </td>
              <td class="py-3 text-right" @click.stop>
                <div class="flex justify-end gap-1">
                  <a :href="`${base}/facturacion/${inv.id}/ride`" target="_blank">
                    <Button variant="ghost" size="sm" class="text-xs">RIDE</Button>
                  </a>
                  <a :href="`${base}/facturacion/${inv.id}/xml`">
                    <Button variant="ghost" size="sm" class="text-xs">XML</Button>
                  </a>
                  <Button v-if="['rejected','draft'].includes(inv.sri_status?.value || inv.sri_status)" variant="ghost" size="sm" class="text-xs text-blue-600" @click="retryInvoice(inv.id)">
                    Reintentar
                  </Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!invoices.data?.length" class="text-center py-8 text-gray-400">Sin facturas</div>
      </CardContent>
    </Card>

    <!-- Detail drawer -->
    <div v-if="showDetail && detailInvoice" class="fixed inset-0 z-50 flex justify-end">
      <div class="absolute inset-0 bg-black/30" @click="showDetail = false" />
      <div class="relative w-full max-w-lg bg-white shadow-xl overflow-y-auto p-5 space-y-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
          <div>
            <h2 class="font-semibold text-lg">{{ detailInvoice.establishment }}-{{ detailInvoice.emission_point }}-{{ detailInvoice.sequential }}</h2>
            <Badge :class="statusColors[detailInvoice.sri_status?.value || detailInvoice.sri_status]" class="text-xs mt-1">
              {{ statusLabels[detailInvoice.sri_status?.value || detailInvoice.sri_status] }}
            </Badge>
          </div>
          <button @click="showDetail = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>

        <!-- SRI info -->
        <div class="text-sm space-y-2">
          <div class="flex justify-between"><span class="text-gray-500">Fecha emision</span><span>{{ formatDate(detailInvoice.issue_date) }}</span></div>
          <div v-if="detailInvoice.sri_authorization_number">
            <p class="text-xs text-gray-500">N° Autorizacion</p>
            <p class="text-xs font-mono break-all">{{ detailInvoice.sri_authorization_number }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Clave de acceso</p>
            <p class="text-xs font-mono break-all">{{ detailInvoice.access_key }}</p>
          </div>
        </div>

        <!-- Buyer -->
        <div class="border-t pt-3 text-sm space-y-1">
          <p><span class="text-gray-500">Comprador:</span> {{ detailInvoice.buyer_name || 'CONSUMIDOR FINAL' }}</p>
          <p><span class="text-gray-500">Identificacion:</span> {{ detailInvoice.buyer_identification || '9999999999999' }}</p>
          <p v-if="detailInvoice.buyer_email"><span class="text-gray-500">Email:</span> {{ detailInvoice.buyer_email }}</p>
        </div>

        <!-- Sale details -->
        <div v-if="detailInvoice.sale" class="border-t pt-3">
          <h3 class="text-sm font-medium text-gray-500 mb-2">Detalle de venta</h3>

          <!-- Client -->
          <div v-if="detailInvoice.sale.client" class="flex items-center gap-2 bg-gray-50 rounded-lg p-2 mb-3 text-sm">
            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-medium">
              {{ (detailInvoice.sale.client.first_name?.[0] || '') + (detailInvoice.sale.client.last_name?.[0] || '') }}
            </div>
            <div>
              <p class="font-medium">{{ detailInvoice.sale.client.first_name }} {{ detailInvoice.sale.client.last_name }}</p>
              <p v-if="detailInvoice.sale.client.phone" class="text-xs text-gray-500">{{ detailInvoice.sale.client.phone }}</p>
            </div>
          </div>

          <!-- Appointment info -->
          <div v-if="detailInvoice.sale.appointment" class="bg-blue-50 rounded-lg p-2 mb-3 text-sm">
            <p class="text-blue-700 font-medium">Cita: {{ detailInvoice.sale.appointment.service?.name }}</p>
            <p class="text-xs text-blue-600">Estilista: {{ detailInvoice.sale.appointment.stylist?.name }}</p>
          </div>

          <!-- Items -->
          <table class="w-full text-sm">
            <thead><tr class="border-b text-xs text-gray-400"><th class="text-left pb-1">Item</th><th class="w-10 pb-1 text-center">Cant</th><th class="w-16 pb-1 text-right">Subtotal</th></tr></thead>
            <tbody>
              <tr v-for="item in detailInvoice.sale.items" :key="item.id" class="border-b last:border-0">
                <td class="py-1.5">
                  <p class="font-medium">{{ item.name }}</p>
                  <p v-if="item.stylist" class="text-xs text-gray-400">{{ item.stylist.name }}</p>
                </td>
                <td class="py-1.5 text-center text-gray-600">{{ item.quantity }}</td>
                <td class="py-1.5 text-right font-medium">${{ Number(item.subtotal).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Payment methods -->
          <div v-if="detailInvoice.sale.payment_methods?.length" class="mt-3">
            <p class="text-xs text-gray-500 mb-1">Forma de pago</p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="(p, i) in detailInvoice.sale.payment_methods" :key="i" class="text-xs bg-gray-100 rounded px-2 py-1">
                {{ paymentLabels[p.method] || p.method }} ${{ Number(p.amount).toFixed(2) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Totals -->
        <div class="border-t pt-3 text-sm">
          <div v-if="Number(detailInvoice.subtotal_0) > 0" class="flex justify-between"><span class="text-gray-500">Subtotal IVA 0%</span><span>${{ Number(detailInvoice.subtotal_0).toFixed(2) }}</span></div>
          <div v-if="Number(detailInvoice.subtotal_iva) > 0" class="flex justify-between"><span class="text-gray-500">Subtotal IVA {{ detailInvoice.iva_rate ?? 15 }}%</span><span>${{ Number(detailInvoice.subtotal_iva).toFixed(2) }}</span></div>
          <div v-if="Number(detailInvoice.iva_amount) > 0" class="flex justify-between"><span class="text-gray-500">IVA {{ detailInvoice.iva_rate ?? 15 }}%</span><span>${{ Number(detailInvoice.iva_amount).toFixed(2) }}</span></div>
          <div class="flex justify-between font-bold text-base border-t pt-1 mt-1"><span>Total</span><span>${{ Number(detailInvoice.total).toFixed(2) }}</span></div>
        </div>

        <!-- Error -->
        <div v-if="detailInvoice.error_message" class="bg-red-50 rounded-lg p-3 text-sm text-red-700">
          <p class="font-medium">Error SRI</p>
          <p>{{ detailInvoice.error_message }}</p>
        </div>

        <!-- Actions -->
        <div class="border-t pt-3 space-y-2">
          <Button variant="outline" class="w-full" @click="printRide">Imprimir RIDE termico</Button>
          <Button variant="outline" class="w-full" @click="openRideHtml">Ver RIDE completo</Button>
          <Button v-if="(detailInvoice.sri_status?.value || detailInvoice.sri_status) === 'rejected'" class="w-full bg-amber-600 hover:bg-amber-700 text-white" @click="retryInvoice(detailInvoice.id); showDetail = false">
            Reintentar envio al SRI
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
