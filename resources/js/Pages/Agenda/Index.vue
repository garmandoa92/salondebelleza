<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import resourceTimeGridPlugin from '@fullcalendar/resource-timegrid'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import tippy from 'tippy.js'
import 'tippy.js/dist/tippy.css'
import 'tippy.js/themes/light-border.css'
import { Button } from '@/components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppointmentDrawer from './AppointmentDrawer.vue'
import AppointmentModal from './AppointmentModal.vue'
import Checkout from '../Sales/Checkout.vue'
import WeekOccupancyBar from './WeekOccupancyBar.vue'
import KeyboardHelpModal from './KeyboardHelpModal.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  stylists: Array,
  categories: Array,
})

const page = usePage()
const tenantId = page.props.tenant?.id
const base = `/salon/${tenantId}`

const calendarRef = ref(null)
const currentDate = ref(new Date())
const currentView = ref('resourceTimeGridDay')
const activeStylists = ref(props.stylists.map(s => s.id))
const showDrawer = ref(false)
const drawerAppointmentId = ref(null)
const showModal = ref(false)
const modalPrefill = ref({})
const showKeyboardHelp = ref(false)
const weekOccupancy = ref([])
const timerInterval = ref(null)

const dateLabel = computed(() => {
  const d = currentDate.value
  const opts = { timeZone: 'America/Guayaquil' }
  if (currentView.value.includes('Day') || currentView.value === 'resourceTimeGridDay') {
    return d.toLocaleDateString('es-EC', { ...opts, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  }
  if (currentView.value.includes('Week')) {
    const end = new Date(d); end.setDate(end.getDate() + 6)
    return `${d.getDate()} — ${end.toLocaleDateString('es-EC', { ...opts, day: 'numeric', month: 'long', year: 'numeric' })}`
  }
  return d.toLocaleDateString('es-EC', { ...opts, month: 'long', year: 'numeric' })
})

const isToday = computed(() => {
  const today = new Date()
  return currentDate.value.toDateString() === today.toDateString()
})

const filteredResources = computed(() =>
  props.stylists
    .filter(s => activeStylists.value.includes(s.id))
    .map(s => ({ id: s.id, title: s.name, color: s.color }))
)

const statusColors = {
  pending: '#94a3b8', confirmed: '#3b82f6', in_progress: '#22c55e',
  completed: '#15803d', cancelled: '#ef4444', no_show: '#f97316',
}
const statusLabels = {
  pending: 'Pendiente', confirmed: 'Confirmada', in_progress: 'En progreso',
  completed: 'Completada', cancelled: 'Cancelada', no_show: 'No show',
}

const fetchEvents = (info, successCallback, failureCallback) => {
  axios.get(`${base}/agenda/events`, {
    params: {
      start: info.startStr,
      end: info.endStr,
      stylist_ids: activeStylists.value,
    },
  }).then(res => successCallback(res.data)).catch(failureCallback)
}

const calendarOptions = computed(() => ({
  plugins: [resourceTimeGridPlugin, dayGridPlugin, timeGridPlugin, interactionPlugin],
  schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
  initialView: 'resourceTimeGridDay',
  headerToolbar: false,
  nowIndicator: true,
  editable: true,
  droppable: true,
  eventResizableFromStart: false,
  selectable: true,
  selectMirror: true,
  selectMinDistance: 5,
  eventOverlap: false,
  snapDuration: '00:15:00',
  slotDuration: '00:15:00',
  slotLabelInterval: '00:30:00',
  slotMinTime: '07:00:00',
  slotMaxTime: '22:00:00',
  allDaySlot: false,
  locale: 'es',
  timeZone: 'America/Guayaquil',
  height: 'calc(100vh - 180px)',
  resources: filteredResources.value,
  events: fetchEvents,
  eventDrop({ event, revert }) {
    axios.put(`${base}/agenda/appointments/${event.id}`, {
      starts_at: event.startStr,
      ends_at: event.endStr,
      stylist_id: event.getResources()[0]?.id,
    }, { headers: { Accept: 'application/json' } }).catch(() => revert())
  },
  eventResize({ event, revert }) {
    const minDuration = event.extendedProps.service_duration
    const actualDuration = (event.end - event.start) / 60000
    if (actualDuration < minDuration) {
      revert()
      return
    }
    axios.put(`${base}/agenda/appointments/${event.id}`, {
      starts_at: event.startStr,
      ends_at: event.endStr,
    }, { headers: { Accept: 'application/json' } }).catch(() => revert())
  },
  eventAllow(dropInfo, draggedEvent) {
    const api = calendarRef.value?.getApi()
    if (!api) return true
    const existingEvents = api.getEvents()
    const targetResourceId = dropInfo.resource?.id
    return !existingEvents.some(e => {
      if (e.id === draggedEvent.id) return false
      if (targetResourceId && e.getResources()[0]?.id !== targetResourceId) return false
      return e.start < dropInfo.end && e.end > dropInfo.start
    })
  },
  select({ start, end, resource }) {
    modalPrefill.value = { starts_at: start, ends_at: end, stylist_id: resource?.id }
    showModal.value = true
    calendarRef.value?.getApi()?.unselect()
  },
  eventClick({ event }) {
    drawerAppointmentId.value = event.id
    showDrawer.value = true
  },
  eventContent({ event }) {
    const p = event.extendedProps
    const isIP = p.status === 'in_progress'
    const timerAttr = isIP && p.started_at ? `data-started="${p.started_at}"` : ''
    return {
      html: `<div style="padding:2px 6px;height:100%;overflow:hidden;position:relative">
        <div style="font-weight:600;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${p.client_name}</div>
        <div style="font-size:11px;opacity:0.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${p.service_name}</div>
        ${isIP ? `<span class="event-timer" ${timerAttr} style="position:absolute;top:2px;right:4px;font-size:10px;opacity:0.8;background:rgba(0,0,0,0.2);border-radius:3px;padding:1px 3px"></span>` : ''}
        ${p.status === 'pending' ? '<span style="position:absolute;bottom:2px;right:4px;font-size:10px">🕐</span>' : ''}
      </div>`,
    }
  },
  eventDidMount({ event, el }) {
    const status = event.extendedProps.status
    el.style.borderLeft = `4px solid ${statusColors[status] || '#94a3b8'}`
    if (status === 'cancelled') {
      el.style.opacity = '0.45'
    }
    // Tooltip
    const p = event.extendedProps
    tippy(el, {
      content: `<div style="min-width:200px;padding:8px;font-size:13px">
        <div style="font-weight:600;margin-bottom:4px">${p.client_name}</div>
        <div style="color:#94a3b8;margin-bottom:8px;font-size:12px">${p.client_phone}</div>
        <table style="width:100%">
          <tr><td style="color:#94a3b8;padding:2px 0">Servicio</td><td style="text-align:right">${p.service_name}</td></tr>
          <tr><td style="color:#94a3b8;padding:2px 0">Duracion</td><td style="text-align:right">${p.service_duration}min</td></tr>
          <tr><td style="color:#94a3b8;padding:2px 0">Precio</td><td style="text-align:right">$${p.price}</td></tr>
          <tr><td style="color:#94a3b8;padding:2px 0">Estado</td><td style="text-align:right">${statusLabels[p.status]}</td></tr>
        </table>
        ${p.allergies ? `<div style="margin-top:8px;color:#ef4444;font-size:12px;border-top:1px solid #e5e7eb;padding-top:6px">⚠ ${p.allergies}</div>` : ''}
      </div>`,
      allowHTML: true,
      placement: 'right',
      theme: 'light-border',
      delay: [600, 100],
      maxWidth: 280,
    })
  },
  datesSet(info) {
    currentDate.value = info.start
    currentView.value = info.view.type
    loadOccupancy(info.start)
  },
}))

const loadOccupancy = async (date) => {
  const d = new Date(date)
  const monday = new Date(d)
  monday.setDate(d.getDate() - d.getDay() + 1)
  const weekStart = monday.toISOString().slice(0, 10)
  try {
    const { data } = await axios.get(`${base}/agenda/occupancy`, { params: { week_start: weekStart } })
    const dayNames = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom']
    const dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']
    weekOccupancy.value = dayKeys.map((k, i) => {
      const dayDate = new Date(monday)
      dayDate.setDate(monday.getDate() + i)
      return { shortName: dayNames[i], date: dayDate.toISOString().slice(0, 10), pct: data[k] || 0, label: dayNames[i], count: 0 }
    })
  } catch {}
}

const changeView = (view) => {
  calendarRef.value?.getApi()?.changeView(view)
}
const prev = () => calendarRef.value?.getApi()?.prev()
const next = () => calendarRef.value?.getApi()?.next()
const today = () => calendarRef.value?.getApi()?.today()
const navigateToDate = (date) => calendarRef.value?.getApi()?.gotoDate(date)

const toggleStylist = (id) => {
  const idx = activeStylists.value.indexOf(id)
  if (idx >= 0) activeStylists.value.splice(idx, 1)
  else activeStylists.value.push(id)
  calendarRef.value?.getApi()?.refetchResources()
  calendarRef.value?.getApi()?.refetchEvents()
}

const toggleAllStylists = () => {
  if (activeStylists.value.length === props.stylists.length) {
    activeStylists.value = []
  } else {
    activeStylists.value = props.stylists.map(s => s.id)
  }
  calendarRef.value?.getApi()?.refetchResources()
  calendarRef.value?.getApi()?.refetchEvents()
}

const onAppointmentUpdated = () => calendarRef.value?.getApi()?.refetchEvents()
const onAppointmentCreated = () => {
  showModal.value = false
  calendarRef.value?.getApi()?.refetchEvents()
}

// Checkout from drawer
const showCheckout = ref(false)
const checkoutData = ref({ appointmentId: null, clientId: null, clientName: null, preItems: [] })

const onCheckout = (data) => {
  checkoutData.value = data
  showCheckout.value = true
}

const onCheckoutCompleted = () => {
  showCheckout.value = false
  calendarRef.value?.getApi()?.refetchEvents()
}

const openNewAppointment = () => {
  modalPrefill.value = {}
  showModal.value = true
}

// Keyboard shortcuts
const onKey = (e) => {
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return
  const api = calendarRef.value?.getApi()
  const map = {
    'n': openNewAppointment, 'N': openNewAppointment,
    't': () => api?.today(), 'T': () => api?.today(),
    'ArrowLeft': () => api?.prev(), 'ArrowRight': () => api?.next(),
    'd': () => api?.changeView('resourceTimeGridDay'), 'D': () => api?.changeView('resourceTimeGridDay'),
    'w': () => api?.changeView('resourceTimeGridWeek'), 'W': () => api?.changeView('resourceTimeGridWeek'),
    'm': () => api?.changeView('dayGridMonth'), 'M': () => api?.changeView('dayGridMonth'),
    'Escape': () => { showDrawer.value = false; showModal.value = false; showKeyboardHelp.value = false },
    '?': () => showKeyboardHelp.value = true,
  }
  if (map[e.key]) { e.preventDefault(); map[e.key]() }
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
  timerInterval.value = setInterval(() => {
    document.querySelectorAll('.event-timer[data-started]').forEach(el => {
      const started = new Date(el.dataset.started)
      const mins = Math.floor((Date.now() - started.getTime()) / 60000)
      el.textContent = mins + 'min'
    })
  }, 60000)
})
onUnmounted(() => {
  window.removeEventListener('keydown', onKey)
  clearInterval(timerInterval.value)
})
</script>

<template>
  <Head title="Agenda" />

  <div class="flex flex-col h-full -m-4 sm:-m-6">
    <!-- Top bar -->
    <div class="flex items-center justify-between h-14 px-4 border-b bg-white shrink-0">
      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" @click="prev">&lt;</Button>
        <Button variant="outline" size="sm" :disabled="isToday" @click="today">Hoy</Button>
        <Button variant="outline" size="sm" @click="next">&gt;</Button>
        <span class="text-sm font-medium text-gray-700 ml-2 capitalize">{{ dateLabel }}</span>
      </div>

      <div class="flex items-center gap-1">
        <Button
          v-for="v in [{ key: 'resourceTimeGridDay', label: 'Dia' }, { key: 'resourceTimeGridWeek', label: 'Semana' }, { key: 'dayGridMonth', label: 'Mes' }]"
          :key="v.key"
          :variant="currentView === v.key ? 'default' : 'outline'"
          size="sm"
          @click="changeView(v.key)"
        >{{ v.label }}</Button>
      </div>

      <div class="flex items-center gap-2">
        <Button @click="openNewAppointment">+ Nueva cita</Button>
      </div>
    </div>

    <!-- Occupancy bar -->
    <WeekOccupancyBar :weekOccupancy="weekOccupancy" @navigate="navigateToDate" />

    <div class="flex flex-1 overflow-hidden">
      <!-- Sidebar: Stylists filter -->
      <div class="w-48 border-r bg-white p-3 hidden lg:block overflow-y-auto shrink-0">
        <div class="space-y-1">
          <label class="flex items-center gap-2 text-sm cursor-pointer py-1">
            <input
              type="checkbox"
              :checked="activeStylists.length === stylists.length"
              @change="toggleAllStylists"
              class="rounded border-gray-300"
            />
            <span class="font-medium">Todos</span>
          </label>
          <label
            v-for="s in stylists"
            :key="s.id"
            class="flex items-center gap-2 text-sm cursor-pointer py-1"
          >
            <input
              type="checkbox"
              :checked="activeStylists.includes(s.id)"
              @change="toggleStylist(s.id)"
              class="rounded"
              :style="{ accentColor: s.color }"
            />
            <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: s.color }" />
            <span class="truncate">{{ s.name }}</span>
          </label>
        </div>
      </div>

      <!-- Calendar -->
      <div class="flex-1 overflow-auto">
        <FullCalendar ref="calendarRef" :options="calendarOptions" />
      </div>
    </div>
  </div>

  <!-- Drawer -->
  <AppointmentDrawer
    :open="showDrawer"
    :appointmentId="drawerAppointmentId"
    @close="showDrawer = false"
    @updated="onAppointmentUpdated"
    @checkout="onCheckout"
  />

  <!-- Checkout from appointment -->
  <Checkout
    :open="showCheckout"
    :appointmentId="checkoutData.appointmentId"
    :clientId="checkoutData.clientId"
    :clientName="checkoutData.clientName"
    :preItems="checkoutData.preItems"
    @close="showCheckout = false"
    @completed="onCheckoutCompleted"
  />

  <!-- Modal -->
  <AppointmentModal
    :open="showModal"
    :prefill="modalPrefill"
    :stylists="stylists"
    :categories="categories"
    @close="showModal = false"
    @created="onAppointmentCreated"
  />

  <!-- Keyboard help -->
  <KeyboardHelpModal v-if="showKeyboardHelp" @close="showKeyboardHelp = false" />
</template>

<style>
.fc-event-dragging-not-allowed .fc-timegrid-col-bg {
  background: rgba(239, 68, 68, 0.20) !important;
}
.fc-highlight {
  background: rgba(59, 130, 246, 0.15) !important;
}
.fc .fc-timegrid-slot { height: 24px; }
.fc .fc-col-header-cell { padding: 8px 0; }
.fc-theme-standard td, .fc-theme-standard th { border-color: #f3f4f6; }
</style>
