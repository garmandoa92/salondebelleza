<script setup>
import { ref } from 'vue'
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
})

// SRI form
const sriForm = useForm({
  ambiente_sri: props.settings?.ambiente_sri || 'test',
  establecimiento: props.settings?.establecimiento || '001',
  punto_emision: props.settings?.punto_emision || '001',
  regimen_tributario: props.settings?.regimen_tributario || 'general',
  obligado_contabilidad: props.settings?.obligado_contabilidad || 'NO',
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
          <Button type="submit" :disabled="salonForm.processing">Guardar</Button>
        </form>
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
            <Button type="submit" :disabled="sriForm.processing">Guardar</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle class="text-base">Certificado digital (.p12)</CardTitle></CardHeader>
        <CardContent>
          <div v-if="hasCertificate" class="flex items-center gap-2 mb-4">
            <Badge class="bg-green-100 text-green-700">Certificado cargado</Badge>
          </div>
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
            <Button type="submit" :disabled="certForm.processing">Subir certificado</Button>
          </form>
        </CardContent>
      </Card>
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
