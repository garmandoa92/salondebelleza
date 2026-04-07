<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { useSriAutocomplete } from '@/composables/useSriAutocomplete'

const props = defineProps({
  open: Boolean,
  expense: { type: Object, default: null },
  categories: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const form = ref({
  description: '',
  expense_category_id: '',
  expense_date: new Date().toISOString().split('T')[0],
  amount: '',
  iva_amount: '',
  payment_method: 'cash',
  is_deductible: false,
  has_sri_invoice: false,
  sri_invoice_number: '',
  sri_authorization_number: '',
  supplier_name: '',
  supplier_ruc: '',
  has_retention: false,
  retention_percentage: '',
  is_recurring: false,
  recurrence_type: 'monthly',
  recurrence_day: 1,
  notes: '',
})

const receiptFile = ref(null)
const saving = ref(false)
const errors = ref({})

// New category inline form
const showNewCategory = ref(false)
const newCategoryName = ref('')
const newCategoryColor = ref('#6366F1')
const savingCategory = ref(false)

const totalCalculated = computed(() => {
  const amt = parseFloat(form.value.amount) || 0
  const iva = parseFloat(form.value.iva_amount) || 0
  return (amt + iva).toFixed(2)
})

const retentionCalculated = computed(() => {
  if (!form.value.has_retention || !form.value.retention_percentage) return '0.00'
  const amt = parseFloat(form.value.amount) || 0
  const pct = parseFloat(form.value.retention_percentage) || 0
  return (amt * pct / 100).toFixed(2)
})

const isEditing = computed(() => !!props.expense)

watch(() => props.open, (val) => {
  if (val) {
    errors.value = {}
    resetSri()
    receiptFile.value = null
    showNewCategory.value = false
    if (props.expense) {
      form.value = {
        description: props.expense.description || '',
        expense_category_id: props.expense.expense_category_id || '',
        expense_date: props.expense.expense_date?.split('T')[0] || '',
        amount: props.expense.amount || '',
        iva_amount: props.expense.iva_amount || '',
        payment_method: props.expense.payment_method || 'cash',
        is_deductible: !!props.expense.is_deductible,
        has_sri_invoice: !!props.expense.has_sri_invoice,
        sri_invoice_number: props.expense.sri_invoice_number || '',
        sri_authorization_number: props.expense.sri_authorization_number || '',
        supplier_name: props.expense.supplier_name || '',
        supplier_ruc: props.expense.supplier_ruc || '',
        has_retention: !!props.expense.has_retention,
        retention_percentage: props.expense.retention_percentage || '',
        is_recurring: !!props.expense.is_recurring,
        recurrence_type: props.expense.recurrence_type || 'monthly',
        recurrence_day: props.expense.recurrence_day || 1,
        notes: props.expense.notes || '',
      }
    } else {
      form.value = {
        description: '',
        expense_category_id: '',
        expense_date: new Date().toISOString().split('T')[0],
        amount: '',
        iva_amount: '',
        payment_method: 'cash',
        is_deductible: false,
        has_sri_invoice: false,
        sri_invoice_number: '',
        sri_authorization_number: '',
        supplier_name: '',
        supplier_ruc: '',
        has_retention: false,
        retention_percentage: '',
        is_recurring: false,
        recurrence_type: 'monthly',
        recurrence_day: 1,
        notes: '',
      }
    }
  }
})

// SRI Autocomplete
const { loading: sriLoading, status: sriStatus, message: sriMessage,
        items: sriItems, queryByAccessKey, reset: resetSri } = useSriAutocomplete()

function onClaveAccesoInput(val) {
  const clean = val.replace(/\D/g, '').slice(0, 49)
  form.value.sri_authorization_number = clean
  if (clean.length === 49) {
    queryByAccessKey(clean, form.value)
  } else {
    resetSri()
  }
}

// Auto-set IVA quick buttons
const setIva = (pct) => {
  const amt = parseFloat(form.value.amount) || 0
  form.value.iva_amount = (amt * pct / 100).toFixed(2)
}

// Auto-deductible when has_sri_invoice
watch(() => form.value.has_sri_invoice, (val) => {
  if (val) form.value.is_deductible = true
  else form.value.is_deductible = false
})

const onFileChange = (e) => {
  receiptFile.value = e.target.files[0] || null
}

const saveCategory = async () => {
  if (!newCategoryName.value.trim()) return
  savingCategory.value = true
  try {
    const { data } = await axios.post(`${base}/gastos/categorias`, {
      name: newCategoryName.value.trim(),
      color: newCategoryColor.value,
    })
    emit('saved') // refresh categories
    form.value.expense_category_id = data.id
    showNewCategory.value = false
    newCategoryName.value = ''
  } catch (e) {
    console.error(e)
  } finally {
    savingCategory.value = false
  }
}

const submit = async () => {
  saving.value = true
  errors.value = {}

  const fd = new FormData()
  Object.entries(form.value).forEach(([key, val]) => {
    if (val !== null && val !== undefined && val !== '') {
      fd.append(key, typeof val === 'boolean' ? (val ? '1' : '0') : val)
    }
  })
  if (receiptFile.value) {
    fd.append('receipt_file', receiptFile.value)
  }

  const url = isEditing.value ? `${base}/gastos/${props.expense.id}` : `${base}/gastos`
  if (isEditing.value) fd.append('_method', 'PUT')

  try {
    const response = await axios.post(url, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    emit('saved', response.data)
    emit('close')
  } catch (e) {
    console.error('Error guardando gasto:', e.response?.status, e.response?.data)
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
    }
  } finally {
    saving.value = false
  }
}

const paymentMethods = [
  { value: 'cash', label: 'Efectivo' },
  { value: 'transfer', label: 'Transferencia' },
  { value: 'card', label: 'Tarjeta' },
  { value: 'check', label: 'Cheque' },
]

const retentionOptions = [
  { value: 1, label: '1% — Bienes' },
  { value: 2, label: '2% — Servicios' },
  { value: 8, label: '8% — Honorarios' },
  { value: 10, label: '10% — Profesionales' },
  { value: 25, label: '25% — Sin factura' },
]

const recurrenceTypes = [
  { value: 'monthly', label: 'Mensual' },
  { value: 'bimonthly', label: 'Bimestral' },
  { value: 'quarterly', label: 'Trimestral' },
  { value: 'annual', label: 'Anual' },
]
</script>

<template>
  <!-- Backdrop -->
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center pt-8 px-4">
      <div class="fixed inset-0 bg-black/40" @click="$emit('close')" />

      <!-- Modal -->
      <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto z-10">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 rounded-t-xl flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-800">
            {{ isEditing ? 'Editar gasto' : 'Registrar gasto' }}
          </h2>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-5">
          <!-- Descripción -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción *</label>
            <input v-model="form.description" type="text"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] outline-none"
              placeholder="Ej: Arriendo local enero 2026" />
            <p v-if="errors.description" class="text-xs text-red-500 mt-1">{{ errors.description[0] }}</p>
            <p v-if="errors.expense_category_id" class="text-xs text-red-500 mt-1">{{ errors.expense_category_id[0] }}</p>
            <p v-if="errors.amount" class="text-xs text-red-500 mt-1">{{ errors.amount[0] }}</p>
            <p v-if="errors.expense_date" class="text-xs text-red-500 mt-1">{{ errors.expense_date[0] }}</p>
          </div>

          <!-- Categoría -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Categoría *</label>
            <div class="flex gap-2">
              <select v-model="form.expense_category_id"
                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[var(--color-primary)] outline-none">
                <option value="">Seleccionar...</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
              <button type="button" @click="showNewCategory = !showNewCategory"
                class="px-3 py-2 border border-gray-200 rounded-lg text-sm hover:bg-gray-50 font-medium">
                +
              </button>
            </div>
            <!-- Inline new category -->
            <div v-if="showNewCategory" class="mt-2 p-3 bg-gray-50 rounded-lg flex gap-2 items-end">
              <div class="flex-1">
                <label class="block text-xs text-gray-500 mb-1">Nombre</label>
                <input v-model="newCategoryName" type="text" class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" placeholder="Nueva categoría" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Color</label>
                <input v-model="newCategoryColor" type="color" class="w-10 h-8 border rounded cursor-pointer" />
              </div>
              <button type="button" @click="saveCategory" :disabled="savingCategory"
                class="px-3 py-1.5 bg-[var(--color-primary)] text-white rounded text-sm hover:opacity-90">
                {{ savingCategory ? '...' : 'Crear' }}
              </button>
            </div>
          </div>

          <!-- Fecha + Método de pago -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fecha *</label>
              <input v-model="form.expense_date" type="date"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[var(--color-primary)] outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago *</label>
              <select v-model="form.payment_method"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[var(--color-primary)] outline-none">
                <option v-for="pm in paymentMethods" :key="pm.value" :value="pm.value">{{ pm.label }}</option>
              </select>
            </div>
          </div>

          <!-- Monto + IVA + Total -->
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Monto base (sin IVA) *</label>
              <div class="relative">
                <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                <input v-model="form.amount" type="number" step="0.01" min="0.01"
                  class="w-full border border-gray-200 rounded-lg pl-7 pr-3 py-2 text-sm focus:ring-1 focus:ring-[var(--color-primary)] outline-none" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">IVA del gasto</label>
              <div class="relative">
                <span class="absolute left-3 top-2 text-gray-400 text-sm">$</span>
                <input v-model="form.iva_amount" type="number" step="0.01" min="0"
                  class="w-full border border-gray-200 rounded-lg pl-7 pr-3 py-2 text-sm focus:ring-1 focus:ring-[var(--color-primary)] outline-none" />
              </div>
              <div class="flex gap-1 mt-1">
                <button type="button" @click="setIva(15)" class="px-2 py-0.5 text-xs bg-gray-100 rounded hover:bg-gray-200">15%</button>
                <button type="button" @click="setIva(5)" class="px-2 py-0.5 text-xs bg-gray-100 rounded hover:bg-gray-200">5%</button>
                <button type="button" @click="setIva(0)" class="px-2 py-0.5 text-xs bg-gray-100 rounded hover:bg-gray-200">0%</button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Total</label>
              <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm font-semibold text-gray-800">
                ${{ totalCalculated }}
              </div>
            </div>
          </div>

          <!-- Toggle: Factura SRI -->
          <div class="border border-gray-100 rounded-lg p-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.has_sri_invoice" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]" />
              <div>
                <span class="text-sm font-medium text-gray-700">Factura electrónica SRI</span>
                <span class="text-xs text-gray-400 ml-2">(marca como deducible automáticamente)</span>
              </div>
            </label>

            <div v-if="form.has_sri_invoice" class="mt-4 space-y-3 pl-7">
              <!-- Clave de acceso SRI con autocomplete -->
              <div>
                <label class="block text-xs text-gray-500 mb-1">
                  Clave de acceso SRI (49 dígitos)
                  <span class="text-xs text-gray-400 font-normal ml-1">— autocompleta todos los campos</span>
                </label>
                <div class="relative">
                  <input
                    :value="form.sri_authorization_number"
                    @input="onClaveAccesoInput($event.target.value)"
                    type="text"
                    maxlength="49"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm font-mono tracking-widest pr-28"
                    placeholder="Ingresa los 49 dígitos..."
                  />
                  <!-- Badge de estado -->
                  <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-medium px-2 py-0.5 rounded-full"
                    :class="{
                      'bg-amber-100 text-amber-700': sriLoading,
                      'bg-emerald-100 text-emerald-700': sriStatus === 'authorized',
                      'bg-blue-100 text-blue-700': sriStatus === 'partial',
                      'bg-red-100 text-red-700': sriStatus === 'invalid' || sriStatus === 'error' || sriStatus === 'not_found' || sriStatus === 'not_authorized',
                      'bg-gray-100 text-gray-500': !sriLoading && !sriStatus,
                    }">
                    <span v-if="sriLoading">Consultando...</span>
                    <span v-else-if="sriStatus === 'authorized'">Autorizado</span>
                    <span v-else-if="sriStatus === 'partial'">Datos parciales</span>
                    <span v-else-if="sriStatus === 'invalid'">Invalido</span>
                    <span v-else-if="sriStatus === 'error' || sriStatus === 'not_found' || sriStatus === 'not_authorized'">Error SRI</span>
                    <span v-else>{{ form.sri_authorization_number?.length || 0 }}/49</span>
                  </span>
                </div>
                <!-- Banner de resultado -->
                <div v-if="sriStatus && sriMessage"
                  class="mt-2 flex items-start gap-2 p-2 rounded-lg text-xs"
                  :class="{
                    'bg-emerald-50 text-emerald-700 border border-emerald-200': sriStatus === 'authorized',
                    'bg-blue-50 text-blue-700 border border-blue-200': sriStatus === 'partial',
                    'bg-red-50 text-red-700 border border-red-200': sriStatus === 'invalid' || sriStatus === 'error' || sriStatus === 'not_found' || sriStatus === 'not_authorized',
                  }">
                  <span class="flex-shrink-0 mt-0.5">
                    {{ sriStatus === 'authorized' ? '✓' : sriStatus === 'partial' ? 'ℹ' : '✕' }}
                  </span>
                  <span>{{ sriMessage }}</span>
                </div>
              </div>

              <!-- Campos autocompletados -->
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 mb-1">Razon social proveedor</label>
                  <input v-model="form.supplier_name" type="text"
                    class="w-full border rounded px-2 py-1.5 text-sm"
                    :class="form.supplier_name && sriStatus === 'authorized' ? 'bg-emerald-50 border-emerald-300' : 'border-gray-200'" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">RUC Proveedor</label>
                  <input v-model="form.supplier_ruc" type="text" maxlength="13"
                    class="w-full border rounded px-2 py-1.5 text-sm"
                    :class="form.supplier_ruc && sriStatus === 'authorized' ? 'bg-emerald-50 border-emerald-300' : 'border-gray-200'" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 mb-1">N° Factura</label>
                  <input v-model="form.sri_invoice_number" type="text" maxlength="20" placeholder="001-001-000012345"
                    class="w-full border rounded px-2 py-1.5 text-sm"
                    :class="form.sri_invoice_number && (sriStatus === 'authorized' || sriStatus === 'partial') ? 'bg-emerald-50 border-emerald-300' : 'border-gray-200'" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">Fecha emision</label>
                  <input v-model="form.expense_date" type="date"
                    class="w-full border rounded px-2 py-1.5 text-sm"
                    :class="form.expense_date && (sriStatus === 'authorized' || sriStatus === 'partial') ? 'bg-emerald-50 border-emerald-300' : 'border-gray-200'" />
                </div>
              </div>

              <!-- Tabla de productos importados de la factura -->
              <div v-if="sriItems.length">
                <p class="text-xs text-gray-500 mb-2 font-medium">
                  Productos de la factura
                  <span class="text-xs font-normal text-gray-400 ml-1">({{ sriItems.length }} items importados)</span>
                </p>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                  <table class="w-full text-xs">
                    <thead>
                      <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Producto</th>
                        <th class="px-3 py-2 text-center font-medium text-gray-500 w-16">Cant.</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 w-24">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      <tr v-for="(item, idx) in sriItems" :key="idx" class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-gray-800">{{ item.descripcion }}</td>
                        <td class="px-3 py-2 text-center text-gray-500">{{ item.cantidad }}</td>
                        <td class="px-3 py-2 text-right font-medium text-gray-800">${{ parseFloat(item.subtotal).toFixed(2) }}</td>
                      </tr>
                    </tbody>
                  </table>
                  <div class="flex items-center justify-between px-3 py-2 bg-gray-50 border-t border-gray-200">
                    <span class="text-xs text-gray-500">Total factura</span>
                    <span class="text-sm font-semibold text-gray-800">${{ (parseFloat(form.amount || 0) + parseFloat(form.iva_amount || 0)).toFixed(2) }}</span>
                  </div>
                </div>
                <p class="text-[10px] text-gray-400 mt-1">Los productos son solo referencia — el monto base y el IVA ya se autocompletaron arriba.</p>
              </div>

              <!-- Upload comprobante -->
              <div>
                <label class="block text-xs text-gray-500 mb-1">Comprobante (foto/PDF, max 5MB)</label>
                <input @change="onFileChange" type="file" accept=".jpg,.jpeg,.png,.pdf"
                  class="w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
              </div>
            </div>
          </div>

          <!-- Toggle: Retención -->
          <div class="border border-gray-100 rounded-lg p-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.has_retention" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]" />
              <span class="text-sm font-medium text-gray-700">Retención en la fuente</span>
            </label>

            <div v-if="form.has_retention" class="mt-3 pl-7 grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-500 mb-1">Porcentaje</label>
                <select v-model="form.retention_percentage"
                  class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm">
                  <option value="">Seleccionar...</option>
                  <option v-for="opt in retentionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Valor retenido</label>
                <div class="bg-gray-50 border border-gray-200 rounded px-2 py-1.5 text-sm font-medium">
                  ${{ retentionCalculated }}
                </div>
              </div>
            </div>
          </div>

          <!-- Toggle: Recurrente -->
          <div class="border border-gray-100 rounded-lg p-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.is_recurring" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]" />
              <span class="text-sm font-medium text-gray-700">Gasto recurrente</span>
            </label>

            <div v-if="form.is_recurring" class="mt-3 pl-7 grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-500 mb-1">Frecuencia</label>
                <select v-model="form.recurrence_type"
                  class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm">
                  <option v-for="rt in recurrenceTypes" :key="rt.value" :value="rt.value">{{ rt.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Día del mes (1-28)</label>
                <input v-model.number="form.recurrence_day" type="number" min="1" max="28"
                  class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm" />
              </div>
            </div>
          </div>

          <!-- Notas -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea v-model="form.notes" rows="2"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[var(--color-primary)] outline-none"
              placeholder="Notas adicionales..." />
          </div>

          <!-- Submit -->
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="$emit('close')"
              class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
              Cancelar
            </button>
            <button type="button" @click="submit" :disabled="saving"
              class="px-6 py-2 bg-[var(--color-primary)] text-white rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-50">
              {{ saving ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Registrar gasto') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
