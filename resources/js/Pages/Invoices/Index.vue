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
      <div class="relative w-full max-w-md bg-white shadow-xl overflow-y-auto p-5 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="font-semibold">{{ detailInvoice.establishment }}-{{ detailInvoice.emission_point }}-{{ detailInvoice.sequential }}</h2>
            <span :class="['text-xs px-2 py-0.5 rounded-full', statusColors[detailInvoice.sri_status?.value || detailInvoice.sri_status]]">
              {{ statusLabels[detailInvoice.sri_status?.value || detailInvoice.sri_status] }}
            </span>
          </div>
          <button @click="showDetail = false" class="text-gray-400 text-xl">&times;</button>
        </div>

        <div class="text-sm space-y-2">
          <div class="flex justify-between"><span class="text-gray-500">Fecha emision</span><span>{{ formatDate(detailInvoice.issue_date) }}</span></div>
          <div v-if="detailInvoice.sri_authorization_number" class="text-xs">
            <p class="text-gray-500">N° Autorizacion</p>
            <p class="font-mono break-all">{{ detailInvoice.sri_authorization_number }}</p>
          </div>
          <div class="text-xs">
            <p class="text-gray-500">Clave de acceso</p>
            <p class="font-mono break-all">{{ detailInvoice.access_key }}</p>
          </div>
        </div>

        <div class="border-t pt-3 text-sm space-y-1">
          <p><span class="text-gray-500">Comprador:</span> {{ detailInvoice.buyer_name || 'CONSUMIDOR FINAL' }}</p>
          <p><span class="text-gray-500">Identificacion:</span> {{ detailInvoice.buyer_identification || '9999999999999' }}</p>
        </div>

        <div class="border-t pt-3 text-sm">
          <div class="flex justify-between"><span class="text-gray-500">Subtotal IVA 15%</span><span>${{ Number(detailInvoice.subtotal_iva).toFixed(2) }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">IVA 15%</span><span>${{ Number(detailInvoice.iva_amount).toFixed(2) }}</span></div>
          <div class="flex justify-between font-bold text-base border-t pt-1 mt-1"><span>Total</span><span>${{ Number(detailInvoice.total).toFixed(2) }}</span></div>
        </div>

        <div v-if="detailInvoice.error_message" class="bg-red-50 rounded p-3 text-sm text-red-700">
          <p class="font-medium">Error SRI</p>
          <p>{{ detailInvoice.error_message }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
