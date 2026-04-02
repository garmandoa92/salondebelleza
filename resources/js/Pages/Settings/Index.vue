<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  tenant: Object,
  settings: Object,
  users: Array,
  roles: Array,
  sequentials: { type: Array, default: () => [] },
  hasCertificate: Boolean,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`
const activeTab = ref('salon')

// Salon form
const salonForm = useForm({
  name: props.tenant?.name || '',
  phone: props.tenant?.phone || '',
  address: props.tenant?.address || '',
  ruc: props.tenant?.ruc || '',
  razon_social: props.tenant?.razon_social || '',
  inventory_mode: props.settings?.inventory_mode || 'centralized',
})

// Appearance
const palettes = [
  { name: 'Verde Salvia', primary: '#4A7C6F', accent: '#C9A96E', bg: '#F7F5F2', text: '#2D3330' },
  { name: 'Rosa Polvos', primary: '#C4829A', accent: '#E8D5C4', bg: '#FDF8F5', text: '#3D2B33' },
  { name: 'Azul Ceniza', primary: '#5B7FA6', accent: '#A8C4D4', bg: '#F5F7FA', text: '#2A3440' },
  { name: 'Negro Dorado', primary: '#1A1A1A', accent: '#C9A96E', bg: '#F9F8F6', text: '#1A1A1A' },
  { name: 'Lavanda', primary: '#7B6FA0', accent: '#C8B8E8', bg: '#F8F6FC', text: '#2D2840' },
]
const appearanceForm = useForm({
  primary_color: props.settings?.primary_color || '#4A7C6F',
  accent_color: props.settings?.accent_color || '#C9A96E',
  bg_color: props.settings?.bg_color || '#F7F5F2',
  text_color: props.settings?.text_color || '#2D3330',
})
const applyPalette = (p) => {
  appearanceForm.primary_color = p.primary
  appearanceForm.accent_color = p.accent
  appearanceForm.bg_color = p.bg
  appearanceForm.text_color = p.text
  previewColors()
}
const previewColors = () => {
  const root = document.documentElement
  root.style.setProperty('--color-primary', appearanceForm.primary_color)
  root.style.setProperty('--color-accent', appearanceForm.accent_color)
  root.style.setProperty('--color-bg', appearanceForm.bg_color)
  root.style.setProperty('--color-text', appearanceForm.text_color)
  // Update shadcn primary
  const hex = appearanceForm.primary_color
  let r = parseInt(hex.slice(1, 3), 16) / 255, g = parseInt(hex.slice(3, 5), 16) / 255, b = parseInt(hex.slice(5, 7), 16) / 255
  const max = Math.max(r, g, b), min = Math.min(r, g, b)
  let h = 0, s = 0, l = (max + min) / 2
  if (max !== min) { const d = max - min; s = l > 0.5 ? d / (2 - max - min) : d / (max + min); if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6; else if (max === g) h = ((b - r) / d + 2) / 6; else h = ((r - g) / d + 4) / 6 }
  root.style.setProperty('--primary', `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`)
}
const submitAppearance = () => appearanceForm.put(`${base}/settings/appearance`)

// Sequential correction
const showSeqModal = ref(false)
const seqForm = useForm({ type: '', next_sequential: '' })
const seqLabel = ref('')
const openSeqModal = (seq) => {
  seqForm.type = seq.key
  seqForm.next_sequential = seq.next_sequential
  seqLabel.value = seq.label
  showSeqModal.value = true
}
const submitSeq = () => seqForm.put(`${base}/settings/sequential`, {
  onSuccess: () => { showSeqModal.value = false },
})

// Certificate info
const showCertForm = ref(false)
const certInfo = computed(() => props.settings?.certificate_info)
const certExpired = computed(() => certInfo.value?.is_valid === false)
const certExpiringSoon = computed(() => certInfo.value?.days_until_expiry != null && certInfo.value.days_until_expiry <= 30 && certInfo.value.days_until_expiry >= 0)

// SRI form
const sriForm = useForm({
  ambiente_sri: props.settings?.ambiente_sri || 'test',
  establecimiento: props.settings?.establecimiento || '001',
  punto_emision: props.settings?.punto_emision || '001',
  regimen_tributario: props.settings?.regimen_tributario || 'general',
  obligado_contabilidad: props.settings?.obligado_contabilidad || 'NO',
  iva_rate: props.settings?.iva_rate ?? 15,
})

// Certificate
const certForm = useForm({ certificate: null, certificate_password: '' })

// Booking form
const bookingForm = useForm({
  booking_enabled: props.settings?.booking_enabled ?? true,
  booking_min_advance_hours: props.settings?.booking_min_advance_hours || 2,
  booking_max_advance_days: props.settings?.booking_max_advance_days || 30,
  booking_welcome_message: props.settings?.booking_welcome_message || '',
  booking_primary_color: props.settings?.booking_primary_color || '#3B82F6',
  booking_cancellation_policy: props.settings?.booking_cancellation_policy || '',
})

// WhatsApp form
const waForm = useForm({
  whatsapp_api_key: props.settings?.whatsapp_api_key || '',
  whatsapp_phone: props.settings?.whatsapp_phone || '',
  whatsapp_confirmations: props.settings?.whatsapp_confirmations ?? true,
  whatsapp_reminders: props.settings?.whatsapp_reminders ?? true,
  whatsapp_invoices: props.settings?.whatsapp_invoices ?? true,
})

// Invite user
const inviteForm = useForm({ name: '', email: '', role: 'receptionist' })

const submitSalon = () => salonForm.put(`${base}/settings/salon`)
const submitSri = () => sriForm.put(`${base}/settings/sri`)
const submitCert = () => certForm.post(`${base}/settings/certificate`, { forceFormData: true })
const submitBooking = () => bookingForm.put(`${base}/settings/booking`)
const submitWhatsapp = () => waForm.put(`${base}/settings/whatsapp`)
const submitInvite = () => inviteForm.post(`${base}/settings/invite`, {
  onSuccess: () => inviteForm.reset(),
})
const toggleUser = (id) => router.patch(`${base}/settings/users/${id}/toggle`)

const tabs = [
  { key: 'salon', label: 'Mi salon' },
  { key: 'sri', label: 'SRI / Facturacion' },
  { key: 'booking', label: 'Reservas online' },
  { key: 'whatsapp', label: 'WhatsApp' },
  { key: 'team', label: 'Equipo' },
  { key: 'billing', label: 'Suscripcion' },
]
</script>

<template>
  <Head title="Configuracion" />

  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Configuracion</h1>

    <!-- Tabs -->
    <div class="flex border-b overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
          activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700']"
      >{{ tab.label }}</button>
    </div>

    <!-- Tab: Mi salon -->
    <Card v-if="activeTab === 'salon'">
      <CardHeader><CardTitle class="text-base">Datos del salon</CardTitle></CardHeader>
      <CardContent>
        <form @submit.prevent="submitSalon" class="space-y-4 max-w-lg">
          <div class="space-y-2"><Label>Nombre del salon</Label><Input v-model="salonForm.name" required /></div>
          <div class="space-y-2"><Label>Telefono</Label><Input v-model="salonForm.phone" /></div>
          <div class="space-y-2"><Label>Direccion</Label><Input v-model="salonForm.address" /></div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2"><Label>RUC (13 digitos)</Label><Input v-model="salonForm.ruc" maxlength="13" /></div>
            <div class="space-y-2"><Label>Razon social</Label><Input v-model="salonForm.razon_social" /></div>
          </div>
          <div class="space-y-2 pt-4 border-t">
            <Label>Modo de inventario (multi-sucursal)</Label>
            <select v-model="salonForm.inventory_mode" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
              <option value="centralized">Centralizado (un solo inventario, compartido entre sucursales)</option>
              <option value="per_branch">Por sucursal (cada sucursal con inventario independiente)</option>
            </select>
            <p class="text-xs text-gray-400">Define si el stock de productos es compartido o independiente por sucursal.</p>
          </div>
          <Button type="submit" :disabled="salonForm.processing">Guardar</Button>
        </form>
      </CardContent>
    </Card>

    <!-- Appearance (inside salon tab) -->
    <Card v-if="activeTab === 'salon'">
      <CardHeader><CardTitle class="text-base">Apariencia</CardTitle></CardHeader>
      <CardContent class="space-y-4">
        <p class="text-sm text-gray-500">Elige una paleta o personaliza los colores de tu salon.</p>

        <div class="flex flex-wrap gap-2">
          <button v-for="p in palettes" :key="p.name" @click="applyPalette(p)"
            :class="['flex items-center gap-2 px-3 py-2 rounded-lg border text-sm transition-colors',
              appearanceForm.primary_color === p.primary ? 'border-2 border-gray-900' : 'border-gray-200 hover:bg-gray-50']">
            <span class="w-4 h-4 rounded-full" :style="{ backgroundColor: p.primary }" />
            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: p.accent }" />
            <span class="text-xs">{{ p.name }}</span>
          </button>
        </div>

        <div class="grid grid-cols-2 gap-4 max-w-md">
          <div class="space-y-1">
            <Label class="text-xs">Color primario</Label>
            <div class="flex items-center gap-2">
              <input type="color" v-model="appearanceForm.primary_color" @input="previewColors" class="h-9 w-12 rounded border cursor-pointer" />
              <Input v-model="appearanceForm.primary_color" class="flex-1 text-xs font-mono" @input="previewColors" />
            </div>
          </div>
          <div class="space-y-1">
            <Label class="text-xs">Color acento</Label>
            <div class="flex items-center gap-2">
              <input type="color" v-model="appearanceForm.accent_color" @input="previewColors" class="h-9 w-12 rounded border cursor-pointer" />
              <Input v-model="appearanceForm.accent_color" class="flex-1 text-xs font-mono" @input="previewColors" />
            </div>
          </div>
        </div>

        <div class="border rounded-lg p-4 space-y-2" :style="{ backgroundColor: appearanceForm.bg_color }">
          <p class="text-xs text-gray-400">Vista previa</p>
          <div class="flex gap-2">
            <button class="px-4 py-2 rounded-md text-white text-sm font-medium" :style="{ backgroundColor: appearanceForm.primary_color }">Boton primario</button>
            <button class="px-4 py-2 rounded-md text-white text-sm font-medium" :style="{ backgroundColor: appearanceForm.accent_color }">Acento</button>
          </div>
          <div class="flex gap-3 text-sm">
            <span class="font-medium" :style="{ color: appearanceForm.text_color }">Texto normal</span>
            <span class="font-medium" :style="{ color: appearanceForm.primary_color }">Link primario</span>
            <span class="font-medium" :style="{ color: appearanceForm.accent_color }">Acento</span>
          </div>
        </div>

        <Button @click="submitAppearance" :disabled="appearanceForm.processing">Guardar apariencia</Button>
      </CardContent>
    </Card>

    <!-- Tab: SRI -->
    <div v-if="activeTab === 'sri'" class="space-y-4">
      <Card>
        <CardHeader><CardTitle class="text-base">Configuracion SRI</CardTitle></CardHeader>
        <CardContent>
          <form @submit.prevent="submitSri" class="space-y-4 max-w-lg">
            <div class="space-y-2">
              <Label>Ambiente</Label>
              <select v-model="sriForm.ambiente_sri" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                <option value="test">Pruebas</option>
                <option value="production">Produccion</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2"><Label>Establecimiento</Label><Input v-model="sriForm.establecimiento" maxlength="3" /></div>
              <div class="space-y-2"><Label>Punto de emision</Label><Input v-model="sriForm.punto_emision" maxlength="3" /></div>
            </div>
            <div class="space-y-2">
              <Label>Regimen tributario</Label>
              <select v-model="sriForm.regimen_tributario" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                <option value="general">Regimen General</option>
                <option value="rimpe_emprendedor">RIMPE Emprendedor</option>
                <option value="rimpe_negocio_popular">RIMPE Negocio Popular</option>
              </select>
            </div>
            <div class="space-y-2">
              <Label>Obligado a llevar contabilidad</Label>
              <select v-model="sriForm.obligado_contabilidad" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                <option value="NO">NO</option>
                <option value="SI">SI</option>
              </select>
            </div>
            <div class="space-y-2 pt-4 border-t">
              <Label>Tarifa IVA general (%)</Label>
              <div class="flex items-center gap-2">
                <div class="flex gap-1">
                  <Button v-for="r in [0, 12, 15]" :key="r" type="button" size="sm"
                    :variant="Number(sriForm.iva_rate) === r ? 'default' : 'outline'" class="text-xs"
                    @click="sriForm.iva_rate = r">{{ r }}%</Button>
                </div>
                <Input v-model="sriForm.iva_rate" type="number" min="0" max="100" step="0.01" class="w-20" />
                <span class="text-sm text-gray-500">%</span>
              </div>
              <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 mt-2">
                <p class="font-medium">Aviso importante</p>
                <p class="mt-1">La configuracion incorrecta del IVA puede resultar en sanciones del SRI. Usa 15% si no estas seguro. Consulta con tu contador antes de cambiar este valor.</p>
              </div>
            </div>
            <Button type="submit" :disabled="sriForm.processing">Guardar</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle class="text-base">Certificado digital (.p12)</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <!-- Certificate info card -->
          <div v-if="hasCertificate && settings?.certificate_info" class="border rounded-lg p-4 space-y-2"
            :class="certExpired ? 'border-red-300 bg-red-50' : certExpiringSoon ? 'border-amber-300 bg-amber-50' : 'border-green-300 bg-green-50'">
            <div class="flex items-center gap-2">
              <span v-if="certExpired" class="text-red-600 font-medium text-sm">Certificado vencido</span>
              <span v-else-if="certExpiringSoon" class="text-amber-700 font-medium text-sm">Certificado por vencer</span>
              <span v-else class="text-green-700 font-medium text-sm">Certificado digital activo</span>
            </div>
            <table class="text-sm w-full">
              <tr v-if="settings.certificate_info.titular"><td class="text-gray-500 pr-3 py-0.5">Titular</td><td class="font-medium">{{ settings.certificate_info.titular }}</td></tr>
              <tr v-if="settings.certificate_info.ruc"><td class="text-gray-500 pr-3 py-0.5">RUC</td><td class="font-medium font-mono">{{ settings.certificate_info.ruc }}</td></tr>
              <tr v-if="settings.certificate_info.issuer"><td class="text-gray-500 pr-3 py-0.5">Emitido por</td><td>{{ settings.certificate_info.issuer }}</td></tr>
              <tr v-if="settings.certificate_info.valid_from"><td class="text-gray-500 pr-3 py-0.5">Valido desde</td><td>{{ settings.certificate_info.valid_from }}</td></tr>
              <tr v-if="settings.certificate_info.valid_until">
                <td class="text-gray-500 pr-3 py-0.5">Valido hasta</td>
                <td :class="certExpired ? 'text-red-600 font-bold' : certExpiringSoon ? 'text-amber-600 font-bold' : ''">
                  {{ settings.certificate_info.valid_until }}
                  <span v-if="certExpiringSoon && !certExpired" class="text-xs"> ({{ settings.certificate_info.days_until_expiry }} dias)</span>
                </td>
              </tr>
            </table>
          </div>

          <div v-else-if="hasCertificate" class="flex items-center gap-2">
            <Badge class="bg-green-100 text-green-700">Certificado cargado</Badge>
            <span class="text-xs text-gray-400">Sube de nuevo para ver los datos del certificado</span>
          </div>

          <!-- Upload / replace form -->
          <div v-if="showCertForm || !hasCertificate">
            <form @submit.prevent="submitCert" class="space-y-4 max-w-lg">
              <div class="space-y-2">
                <Label>Archivo .p12</Label>
                <Input type="file" accept=".p12,.pfx" @change="certForm.certificate = $event.target.files[0]" />
                <p v-if="certForm.errors.certificate" class="text-sm text-red-500">{{ certForm.errors.certificate }}</p>
              </div>
              <div class="space-y-2">
                <Label>Contrasena del certificado</Label>
                <Input v-model="certForm.certificate_password" type="password" />
              </div>
              <div class="flex gap-2">
                <Button type="submit" :disabled="certForm.processing">Subir certificado</Button>
                <Button v-if="hasCertificate" type="button" variant="outline" @click="showCertForm = false">Cancelar</Button>
              </div>
            </form>
          </div>
          <Button v-else variant="outline" size="sm" @click="showCertForm = true">Reemplazar certificado</Button>
        </CardContent>
      </Card>

      <!-- Sequentials -->
      <Card>
        <CardHeader><CardTitle class="text-base">Numeracion de comprobantes</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-2 gap-4 text-sm mb-2">
            <div><span class="text-gray-500">Establecimiento:</span> <span class="font-mono font-medium">{{ settings?.establecimiento || '001' }}</span></div>
            <div><span class="text-gray-500">Punto de emision:</span> <span class="font-mono font-medium">{{ settings?.punto_emision || '001' }}</span></div>
          </div>

          <div v-for="seq in sequentials" :key="seq.key" class="border rounded-lg p-3 space-y-1">
            <div class="flex items-center justify-between">
              <h4 class="text-sm font-medium">{{ seq.label }}</h4>
              <Button variant="ghost" size="sm" class="text-xs h-7" @click="openSeqModal(seq)">Corregir secuencial</Button>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm">
              <div><span class="text-gray-500">Ultimo emitido:</span> <span class="font-mono">{{ seq.last_sequential }}</span></div>
              <div><span class="text-gray-500">Proximo:</span> <span class="font-mono font-medium">{{ seq.next_sequential }}</span>
                <Badge v-if="seq.has_override" class="ml-1 bg-amber-100 text-amber-700 text-[10px] px-1 py-0">manual</Badge>
              </div>
            </div>
            <div class="text-xs text-gray-400">
              {{ seq.month_count }} emitidas este mes
              <span v-if="seq.last_invoice"> · Ultimo: {{ seq.last_invoice.sequential }} ({{ seq.last_invoice.date }}) ${{ Number(seq.last_invoice.total).toFixed(2) }}</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Sequential correction modal -->
    <div v-if="showSeqModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showSeqModal = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h3 class="font-semibold">Correccion de secuencial</h3>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
          Solo usa esto si hay una diferencia entre el sistema y los registros del SRI.
        </div>
        <div class="text-sm"><span class="text-gray-500">Tipo:</span> <span class="font-medium">{{ seqLabel }}</span></div>
        <div class="space-y-2">
          <Label>Proximo numero a emitir</Label>
          <Input v-model="seqForm.next_sequential" maxlength="9" class="font-mono" placeholder="000000046" />
          <p v-if="seqForm.errors.next_sequential" class="text-sm text-red-500">{{ seqForm.errors.next_sequential }}</p>
          <p class="text-xs text-gray-400">9 digitos con ceros a la izquierda</p>
        </div>
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="showSeqModal = false">Cancelar</Button>
          <Button :disabled="seqForm.processing" @click="submitSeq">Guardar correccion</Button>
        </div>
      </div>
    </div>

    <!-- Tab: Booking -->
    <Card v-if="activeTab === 'booking'">
      <CardHeader><CardTitle class="text-base">Reservas online</CardTitle></CardHeader>
      <CardContent>
        <form @submit.prevent="submitBooking" class="space-y-4 max-w-lg">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="bookingForm.booking_enabled" class="rounded" />
            <span class="text-sm font-medium">Activar reservas online</span>
          </label>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2"><Label>Anticipacion minima (horas)</Label><Input v-model="bookingForm.booking_min_advance_hours" type="number" min="0" /></div>
            <div class="space-y-2"><Label>Anticipacion maxima (dias)</Label><Input v-model="bookingForm.booking_max_advance_days" type="number" min="1" /></div>
          </div>
          <div class="space-y-2">
            <Label>Mensaje de bienvenida</Label>
            <textarea v-model="bookingForm.booking_welcome_message" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" rows="2" />
          </div>
          <div class="space-y-2">
            <Label>Color primario</Label>
            <input type="color" v-model="bookingForm.booking_primary_color" class="h-9 w-14 rounded border cursor-pointer" />
          </div>
          <div class="space-y-2">
            <Label>Politica de cancelacion</Label>
            <textarea v-model="bookingForm.booking_cancellation_policy" class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" rows="3" />
          </div>
          <Button type="submit" :disabled="bookingForm.processing">Guardar</Button>
        </form>
      </CardContent>
    </Card>

    <!-- Tab: WhatsApp -->
    <Card v-if="activeTab === 'whatsapp'">
      <CardHeader><CardTitle class="text-base">WhatsApp (360dialog)</CardTitle></CardHeader>
      <CardContent>
        <form @submit.prevent="submitWhatsapp" class="space-y-4 max-w-lg">
          <div class="space-y-2"><Label>API Key</Label><Input v-model="waForm.whatsapp_api_key" type="password" /></div>
          <div class="space-y-2"><Label>Numero registrado</Label><Input v-model="waForm.whatsapp_phone" placeholder="+593..." /></div>
          <div class="space-y-2">
            <Label>Notificaciones activas</Label>
            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" v-model="waForm.whatsapp_confirmations" class="rounded" /><span class="text-sm">Confirmacion de citas</span></label>
            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" v-model="waForm.whatsapp_reminders" class="rounded" /><span class="text-sm">Recordatorios (24h y 2h)</span></label>
            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" v-model="waForm.whatsapp_invoices" class="rounded" /><span class="text-sm">Envio de facturas</span></label>
          </div>
          <Button type="submit" :disabled="waForm.processing">Guardar</Button>
        </form>
      </CardContent>
    </Card>

    <!-- Tab: Team -->
    <Card v-if="activeTab === 'team'">
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle class="text-base">Equipo</CardTitle>
        </div>
      </CardHeader>
      <CardContent class="space-y-6">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-left text-gray-500">
              <th class="pb-2 font-medium">Nombre</th>
              <th class="pb-2 font-medium">Email</th>
              <th class="pb-2 font-medium">Rol</th>
              <th class="pb-2 font-medium text-center">Estado</th>
              <th class="pb-2 font-medium text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id" class="border-b last:border-0">
              <td class="py-2 font-medium">{{ u.name }}</td>
              <td class="py-2 text-gray-600">{{ u.email }}</td>
              <td class="py-2"><Badge variant="secondary" class="text-xs">{{ u.roles?.[0]?.name || '-' }}</Badge></td>
              <td class="py-2 text-center">
                <Badge :class="u.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="text-xs">
                  {{ u.is_active ? 'Activo' : 'Inactivo' }}
                </Badge>
              </td>
              <td class="py-2 text-right">
                <Button variant="ghost" size="sm" class="text-xs" @click="toggleUser(u.id)">
                  {{ u.is_active ? 'Desactivar' : 'Activar' }}
                </Button>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="border-t pt-4">
          <h3 class="text-sm font-medium mb-3">Invitar nuevo usuario</h3>
          <form @submit.prevent="submitInvite" class="flex flex-wrap items-end gap-3">
            <div class="space-y-1"><Label class="text-xs">Nombre</Label><Input v-model="inviteForm.name" class="w-40" required /></div>
            <div class="space-y-1"><Label class="text-xs">Email</Label><Input v-model="inviteForm.email" type="email" class="w-48" required /></div>
            <div class="space-y-1">
              <Label class="text-xs">Rol</Label>
              <select v-model="inviteForm.role" class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                <option v-for="r in roles" :key="r.name" :value="r.name">{{ r.name }}</option>
              </select>
            </div>
            <Button type="submit" size="sm" :disabled="inviteForm.processing">Invitar</Button>
          </form>
          <p v-if="inviteForm.errors.email" class="text-sm text-red-500 mt-1">{{ inviteForm.errors.email }}</p>
        </div>
      </CardContent>
    </Card>

    <!-- Tab: Billing -->
    <Card v-if="activeTab === 'billing'">
      <CardHeader><CardTitle class="text-base">Suscripcion</CardTitle></CardHeader>
      <CardContent class="space-y-4">
        <div class="bg-blue-50 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-semibold text-blue-900">Plan {{ settings?.subscription_plan || 'Profesional' }}</p>
              <p class="text-sm text-blue-700">
                {{ tenant?.trial_ends_at ? `Trial hasta ${new Date(tenant.trial_ends_at).toLocaleDateString('es-EC')}` : 'Suscripcion activa' }}
              </p>
            </div>
            <Badge class="bg-blue-200 text-blue-800">
              {{ tenant?.trial_ends_at ? 'Trial' : 'Activo' }}
            </Badge>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div v-for="plan in [
            { name: 'Basico', price: 15, stylists: 2, branches: 1 },
            { name: 'Profesional', price: 29, stylists: 8, branches: 1 },
            { name: 'Cadena', price: 59, stylists: 'Ilimitados', branches: 'Ilimitadas' },
          ]" :key="plan.name"
            :class="['border rounded-lg p-4', plan.name === 'Profesional' ? 'border-primary border-2' : '']"
          >
            <h3 class="font-semibold">{{ plan.name }}</h3>
            <p class="text-2xl font-bold mt-1">${{ plan.price }}<span class="text-sm font-normal text-gray-500">/mes</span></p>
            <ul class="text-sm text-gray-600 mt-3 space-y-1">
              <li>{{ plan.stylists }} estilistas</li>
              <li>{{ plan.branches }} sucursal{{ plan.branches !== 1 ? 'es' : '' }}</li>
            </ul>
          </div>
        </div>

        <p class="text-xs text-gray-400">La integracion completa con Stripe se activa configurando STRIPE_KEY en el .env</p>
      </CardContent>
    </Card>
  </div>
</template>
