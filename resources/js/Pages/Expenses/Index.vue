<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ExpenseModal from '@/Components/ExpenseModal.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  expenses: Object,
  categories: Array,
  pl: Object,
  filters: Object,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const activeTab = ref('gastos')
const showModal = ref(false)
const editingExpense = ref(null)
const deleting = ref(null)
const generatingRecurring = ref(false)
const localCategories = ref([...props.categories])

// Month/Year selector
const selectedMonth = ref(props.filters?.month || new Date().getMonth() + 1)
const selectedYear = ref(props.filters?.year || new Date().getFullYear())
const selectedCategory = ref(props.filters?.categoryId || '')

const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']

const applyFilters = () => {
  router.get(`${base}/gastos`, {
    month: selectedMonth.value,
    year: selectedYear.value,
    category_id: selectedCategory.value || undefined,
  }, { preserveState: true })
}

watch([selectedMonth, selectedYear, selectedCategory], () => applyFilters())

// Group expenses by date
const groupedExpenses = computed(() => {
  const groups = {}
  const items = props.expenses?.data || []
  items.forEach(exp => {
    const date = exp.expense_date?.split('T')[0] || exp.expense_date
    if (!groups[date]) groups[date] = []
    groups[date].push(exp)
  })
  return groups
})

const formatDate = (d) => {
  const date = new Date(d + 'T12:00:00')
  return date.toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long' })
}

const formatMoney = (v) => {
  const num = parseFloat(v) || 0
  return num.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const paymentLabel = (m) => ({
  cash: 'Efectivo', transfer: 'Transferencia', card: 'Tarjeta', check: 'Cheque',
}[m] || m)

const openCreate = () => {
  editingExpense.value = null
  showModal.value = true
}

const openEdit = (expense) => {
  editingExpense.value = expense
  showModal.value = true
}

const deleteExpense = async (expense) => {
  if (!confirm('¿Eliminar este gasto?')) return
  deleting.value = expense.id
  try {
    await axios.delete(`${base}/gastos/${expense.id}`)
    router.reload()
  } catch (e) {
    console.error(e)
  } finally {
    deleting.value = null
  }
}

const onSaved = (expense) => {
  // Navegar al mes del gasto guardado
  if (expense?.expense_date) {
    const d = new Date(expense.expense_date + 'T12:00:00')
    selectedMonth.value = d.getMonth() + 1
    selectedYear.value = d.getFullYear()
  }
  router.get(`${base}/gastos`, {
    month: selectedMonth.value,
    year: selectedYear.value,
  }, { preserveState: false })
  // Refresh categories
  axios.get(`${base}/gastos/categorias`).then(({ data }) => {
    localCategories.value = data
  })
}

const downloadReceipt = (expense) => {
  window.open(`${base}/gastos/${expense.id}/comprobante`, '_blank')
}

const exportExcel = () => {
  window.open(`${base}/gastos/exportar?month=${selectedMonth.value}&year=${selectedYear.value}`, '_blank')
}

// Recurring expenses
const recurringExpenses = computed(() => {
  const items = props.expenses?.data || []
  return items.filter(e => e.is_recurring && !e.parent_expense_id)
})

const generateRecurring = async () => {
  generatingRecurring.value = true
  try {
    // We'll call the generate endpoint via tinker-style; for now reload page
    router.reload()
  } finally {
    generatingRecurring.value = false
  }
}

// Category icon (simple SVG paths from Heroicons)
const categoryIcon = (icon) => {
  const icons = {
    'home': 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    'bolt': 'M13 10V3L4 14h7v7l9-11h-7z',
    'users': 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    'gift': 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7',
    'beaker': 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
    'megaphone': 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
    'wrench-screwdriver': 'M11.42 15.17l-4.655 4.655a2.12 2.12 0 01-3-3l4.655-4.655M21.68 6.83a2.12 2.12 0 00-3-3L15 7.5l.34 2.16 2.16.34 4.18-3.17zM3.75 21h.008v.008H3.75V21zm0 0L14.25 10.5',
    'cog': 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    'truck': 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
    'ellipsis-horizontal': 'M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'tag': 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
  }
  return icons[icon] || icons['tag']
}
</script>

<template>
  <Head title="Gastos" />

  <div class="max-w-7xl mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Gastos</h1>
        <p class="text-sm text-gray-500 mt-1">Gestión de gastos y estado de resultados</p>
      </div>
      <div class="flex items-center gap-3">
        <!-- Month/Year selector -->
        <select v-model="selectedMonth" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
          <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">{{ name }}</option>
        </select>
        <select v-model="selectedYear" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
          <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="rounded-xl p-4 bg-[var(--color-primary)] text-white">
        <p class="text-xs opacity-80">Ingresos Netos</p>
        <p class="text-2xl font-bold mt-1">${{ formatMoney(pl.ingresos_netos_base) }}</p>
        <p class="text-xs opacity-60 mt-1">{{ pl.total_ventas }} ventas del mes</p>
      </div>
      <div class="rounded-xl p-4 bg-[var(--color-accent)] text-white">
        <p class="text-xs opacity-80">Total Gastos</p>
        <p class="text-2xl font-bold mt-1">${{ formatMoney(pl.total_gastos) }}</p>
      </div>
      <div class="rounded-xl p-4" :class="pl.utilidad_operacional >= 0 ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'">
        <p class="text-xs opacity-80">Utilidad Operacional</p>
        <p class="text-2xl font-bold mt-1">${{ formatMoney(pl.utilidad_operacional) }}</p>
      </div>
      <div class="rounded-xl p-4 bg-blue-600 text-white">
        <p class="text-xs opacity-80">IVA Neto SRI</p>
        <p class="text-2xl font-bold mt-1">${{ formatMoney(pl.iva_neto_sri) }}</p>
        <p class="text-xs opacity-60 mt-1">A declarar al SRI</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="flex gap-6">
        <button v-for="tab in ['gastos', 'pl', 'recurrentes']" :key="tab"
          @click="activeTab = tab"
          class="pb-3 text-sm font-medium border-b-2 transition-colors"
          :class="activeTab === tab
            ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
            : 'border-transparent text-gray-500 hover:text-gray-700'">
          {{ { gastos: 'Gastos', pl: 'P&L', recurrentes: 'Recurrentes' }[tab] }}
        </button>
      </nav>
    </div>

    <!-- Tab: Gastos -->
    <div v-if="activeTab === 'gastos'">
      <!-- Toolbar -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <button @click="openCreate"
            class="px-4 py-2 bg-[var(--color-primary)] text-white rounded-lg text-sm font-medium hover:opacity-90">
            + Agregar gasto
          </button>
          <select v-model="selectedCategory" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="">Todas las categorías</option>
            <option v-for="cat in localCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>
        <button @click="exportExcel"
          class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
          Exportar Excel
        </button>
      </div>

      <!-- Grouped expenses list -->
      <div v-if="Object.keys(groupedExpenses).length === 0" class="text-center py-12 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm">No hay gastos registrados este mes</p>
      </div>

      <div v-for="(items, date) in groupedExpenses" :key="date" class="mb-6">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">{{ formatDate(date) }}</h3>
        <div class="space-y-2">
          <div v-for="expense in items" :key="expense.id"
            class="bg-white border border-gray-100 rounded-xl p-4 flex items-center gap-4 hover:shadow-sm transition-shadow">
            <!-- Category icon -->
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
              :style="{ backgroundColor: expense.category?.color + '20' }">
              <svg class="w-5 h-5" :style="{ color: expense.category?.color }" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" :d="categoryIcon(expense.category?.icon)" />
              </svg>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ expense.description }}</p>
              <p class="text-xs text-gray-400">
                {{ expense.category?.name }}
                <span v-if="expense.supplier_name"> &middot; {{ expense.supplier_name }}</span>
              </p>
              <!-- Badges -->
              <div class="flex gap-1.5 mt-1.5">
                <span v-if="expense.is_deductible" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-emerald-50 text-emerald-700">Deducible</span>
                <span v-if="expense.is_recurring" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-blue-50 text-blue-700">Recurrente</span>
                <span v-if="expense.receipt_file_path" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-gray-100 text-gray-600 cursor-pointer" @click="downloadReceipt(expense)">Adjunto</span>
                <span v-if="expense.has_retention" class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-orange-50 text-orange-700">Ret. {{ expense.retention_percentage }}%</span>
              </div>
            </div>

            <!-- Amount -->
            <div class="text-right flex-shrink-0">
              <p class="text-sm font-semibold text-gray-800">${{ formatMoney(expense.total_amount) }}</p>
              <p v-if="parseFloat(expense.iva_amount) > 0" class="text-[10px] text-gray-400">
                Base ${{ formatMoney(expense.amount) }} + IVA ${{ formatMoney(expense.iva_amount) }}
              </p>
              <p class="text-[10px] text-gray-400">{{ paymentLabel(expense.payment_method) }}</p>
            </div>

            <!-- Actions -->
            <div class="flex gap-1 flex-shrink-0">
              <button @click="openEdit(expense)" class="p-1.5 text-gray-400 hover:text-[var(--color-primary)] rounded-lg hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
              </button>
              <button @click="deleteExpense(expense)" :disabled="deleting === expense.id" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="expenses?.last_page > 1" class="flex justify-center gap-2 mt-6">
        <button v-for="p in expenses.last_page" :key="p"
          @click="router.get(`${base}/gastos`, { ...filters, page: p }, { preserveState: true })"
          class="w-8 h-8 rounded-lg text-sm"
          :class="p === expenses.current_page ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
          {{ p }}
        </button>
      </div>
    </div>

    <!-- Tab: P&L -->
    <div v-if="activeTab === 'pl'">
      <div class="grid lg:grid-cols-3 gap-6">
        <!-- P&L Table -->
        <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Estado de Resultados — {{ monthNames[selectedMonth - 1] }} {{ selectedYear }}</h3>
          </div>
          <div class="divide-y divide-gray-50">
            <!-- INGRESOS -->
            <div class="px-5 py-2 bg-[#E8F4F0]">
              <span class="text-sm font-semibold text-gray-700">INGRESOS</span>
            </div>
            <div class="px-5 py-2 flex justify-between">
              <span class="text-sm text-gray-600">Total cobrado a clientes (con IVA)</span>
              <span class="text-sm font-medium text-gray-800">${{ formatMoney(pl.ingresos_con_iva) }}</span>
            </div>
            <div class="px-5 py-2 flex justify-between">
              <span class="text-sm text-gray-500 pl-4">(-) IVA cobrado — obligación tributaria SRI</span>
              <span class="text-sm text-red-600">-${{ formatMoney(pl.iva_cobrado_clientes) }}</span>
            </div>
            <div class="px-5 py-2 flex justify-between bg-gray-50">
              <span class="text-sm font-semibold text-[var(--color-primary)]">INGRESOS NETOS (base imponible)</span>
              <span class="text-sm font-bold text-[var(--color-primary)]">${{ formatMoney(pl.ingresos_netos_base) }}</span>
            </div>

            <!-- COSTOS Y GASTOS -->
            <div class="px-5 py-2 bg-[#E8F4F0] mt-2">
              <span class="text-sm font-semibold text-gray-700">COSTOS Y GASTOS</span>
            </div>
            <div class="px-5 py-2 flex justify-between">
              <span class="text-sm text-gray-500 pl-4">Costo de productos e insumos</span>
              <span class="text-sm text-red-600">-${{ formatMoney(pl.costo_productos) }}</span>
            </div>
            <div class="px-5 py-2 flex justify-between bg-gray-50">
              <span class="text-sm font-semibold text-[var(--color-primary)]">GANANCIA BRUTA</span>
              <span class="text-sm font-bold text-[var(--color-primary)]">${{ formatMoney(pl.ganancia_bruta) }}</span>
            </div>
            <div class="px-5 py-2 flex justify-between">
              <span class="text-sm text-gray-500 pl-4">Gastos operativos</span>
              <span class="text-sm text-red-600">-${{ formatMoney(pl.gastos_operativos) }}</span>
            </div>
            <div class="px-5 py-2 flex justify-between">
              <span class="text-sm text-gray-500 pl-4">Comisiones a estilistas</span>
              <span class="text-sm text-red-600">-${{ formatMoney(pl.comisiones) }}</span>
            </div>

            <!-- UTILIDAD -->
            <div class="px-5 py-3 flex justify-between" :class="pl.utilidad_operacional >= 0 ? 'bg-emerald-50' : 'bg-red-50'">
              <span class="text-base font-bold" :class="pl.utilidad_operacional >= 0 ? 'text-emerald-700' : 'text-red-700'">UTILIDAD OPERACIONAL</span>
              <span class="text-lg font-bold" :class="pl.utilidad_operacional >= 0 ? 'text-emerald-700' : 'text-red-700'">${{ formatMoney(pl.utilidad_operacional) }}</span>
            </div>

            <!-- INFO TRIBUTARIA -->
            <div class="px-5 py-2 bg-gray-100 mt-2">
              <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Información Tributaria (referencial)</span>
            </div>
            <div class="px-5 py-2 flex justify-between">
              <span class="text-sm text-gray-500 pl-4">IVA cobrado a clientes</span>
              <span class="text-sm text-gray-600">${{ formatMoney(pl.iva_cobrado_clientes) }}</span>
            </div>
            <div class="px-5 py-2 flex justify-between">
              <span class="text-sm text-gray-500 pl-4">IVA pagado en compras (crédito tributario)</span>
              <span class="text-sm text-gray-600">-${{ formatMoney(pl.iva_pagado_compras) }}</span>
            </div>
            <div class="px-5 py-2 flex justify-between bg-blue-50">
              <span class="text-sm font-semibold text-blue-700">IVA neto a declarar al SRI</span>
              <span class="text-sm font-bold text-blue-700">${{ formatMoney(pl.iva_neto_sri) }}</span>
            </div>
            <div class="px-5 py-2 flex justify-between">
              <span class="text-sm text-gray-500 pl-4">Retenciones en la fuente emitidas</span>
              <span class="text-sm text-gray-600">${{ formatMoney(pl.retenciones_emitidas) }}</span>
            </div>
          </div>
        </div>

        <!-- Donut chart - Gastos por categoría -->
        <div class="bg-white border border-gray-100 rounded-xl p-5">
          <h3 class="font-semibold text-gray-800 mb-4">Gastos por categoría</h3>
          <div v-if="pl.gastos_por_categoria?.length" class="space-y-3">
            <div v-for="cat in pl.gastos_por_categoria" :key="cat.category" class="flex items-center gap-3">
              <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: cat.color }" />
              <div class="flex-1 min-w-0">
                <div class="flex justify-between items-baseline">
                  <span class="text-sm text-gray-700 truncate">{{ cat.category }}</span>
                  <span class="text-sm font-medium text-gray-800 ml-2">${{ formatMoney(cat.total) }}</span>
                </div>
                <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :style="{
                    width: (pl.total_gastos > 0 ? (cat.total / pl.total_gastos * 100) : 0) + '%',
                    backgroundColor: cat.color
                  }" />
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-400 text-sm">
            Sin gastos este mes
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Recurrentes -->
    <div v-if="activeTab === 'recurrentes'">
      <div class="bg-white border border-gray-100 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">Gastos recurrentes configurados</h3>
        </div>
        <div v-if="recurringExpenses.length === 0" class="p-8 text-center text-gray-400 text-sm">
          No hay gastos recurrentes. Crea un gasto y marca la opción "Gasto recurrente".
        </div>
        <div v-else class="divide-y divide-gray-50">
          <div v-for="expense in recurringExpenses" :key="expense.id"
            class="px-5 py-3 flex items-center gap-4">
            <div class="w-8 h-8 rounded-full flex items-center justify-center"
              :style="{ backgroundColor: expense.category?.color + '20' }">
              <svg class="w-4 h-4" :style="{ color: expense.category?.color }" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" :d="categoryIcon(expense.category?.icon)" />
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-800">{{ expense.description }}</p>
              <p class="text-xs text-gray-400">
                {{ { monthly: 'Mensual', bimonthly: 'Bimestral', quarterly: 'Trimestral', annual: 'Anual' }[expense.recurrence_type] || '-' }}
                &middot; Día {{ expense.recurrence_day }}
              </p>
            </div>
            <p class="text-sm font-semibold text-gray-800">${{ formatMoney(expense.total_amount) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Expense Modal -->
  <ExpenseModal
    :open="showModal"
    :expense="editingExpense"
    :categories="localCategories"
    @close="showModal = false"
    @saved="onSaved"
  />
</template>
