# Sesión 4 — Agenda Visual (FullCalendar) — SaaS 2026

**Duración estimada:** 4-5 días  
**Semana:** 3-4  
**Dependencias:** Sesiones 1, 2 y 3 completadas  

---

## Objetivo
Construir el módulo CENTRAL del producto: la agenda visual de citas con todos los features modernos de un SaaS 2026. Este módulo es el corazón del producto — el dueño del salón lo usa cada día. NO recortar ningún feature.

---

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Ya tenemos: modelos completos, ServiceController, StylistController.

Construyo el modulo CENTRAL del producto: la agenda visual de citas.
Este modulo es el corazon del SaaS — debe verse y sentirse como un producto premium 2026.
NO recortes ningun feature. Implementa absolutamente todo lo descrito en este prompt.

=============================================================
ENDPOINTS — AppointmentController
=============================================================

GET  /agenda                      → Vista principal Inertia
GET  /agenda/events               → JSON events para FullCalendar
                                    params: start, end, stylist_ids[]
                                    Formato de respuesta:
                                    [{
                                      id, resourceId (stylist uuid),
                                      title (nombre cliente),
                                      start, end (ISO 8601 con tz America/Guayaquil),
                                      backgroundColor (stylist.color),
                                      borderColor (color segun status),
                                      textColor: #ffffff,
                                      editable: status != completed && status != cancelled,
                                      extendedProps: {
                                        client_name, client_phone, client_avatar,
                                        service_name, service_duration, price,
                                        stylist_name, stylist_color,
                                        status, notes, internal_notes, started_at
                                      }
                                    }]

GET  /agenda/occupancy            → Ocupacion de la semana
                                    param: week_start (YYYY-MM-DD)
                                    respuesta: { mon:65, tue:90, wed:30, thu:75, fri:85, sat:40, sun:0 }

POST /agenda/appointments         → Crear cita
PUT  /agenda/appointments/{id}    → Actualizar (drag, resize, edicion)
DELETE /agenda/appointments/{id}  → Cancelar con motivo obligatorio
GET  /agenda/appointments/{id}    → Detalle completo
POST /agenda/appointments/{id}/confirm  → Confirmar
POST /agenda/appointments/{id}/start    → Iniciar (en progreso)
POST /agenda/appointments/{id}/complete → Completar → dispara cobro
POST /agenda/appointments/{id}/no-show  → No show
GET  /agenda/availability         → Slots libres
                                    params: stylist_id, service_id, date
                                    respuesta: [{ time: "09:00", datetime: ISO, available: bool }]

=============================================================
CONFIGURACION FULLCALENDAR — Pages/Agenda/Index.vue
=============================================================

Instalar exactamente:
npm install @fullcalendar/vue3 @fullcalendar/core
            @fullcalendar/resource-timegrid @fullcalendar/resource-daygrid
            @fullcalendar/timegrid @fullcalendar/daygrid
            @fullcalendar/interaction tippy.js

Configuracion del objeto calendarOptions (ref reactivo):
  schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source'
  initialView: 'resourceTimeGridDay'
  headerToolbar: false
  nowIndicator: true
  editable: true
  droppable: true
  eventResizableFromStart: false
  resourceEditable: true
  selectable: true
  selectMirror: true
  selectMinDistance: 5
  eventOverlap: false
  snapDuration: '00:15:00'
  slotDuration: '00:15:00'
  slotLabelInterval: '00:30:00'
  slotMinTime: '07:00:00'
  slotMaxTime: '22:00:00'
  allDaySlot: false
  locale: 'es'
  timeZone: 'America/Guayaquil'
  height: 'calc(100vh - 120px)'
  resources: computed(() => stylists.value.map(s => ({ id: s.id, title: s.name, color: s.color })))
  events: { url: '/agenda/events', method: 'GET', extraParams: () => activeFilters.value }
  eventDrop, eventResize, eventAllow, eventDragStart, eventContent,
  eventDidMount, select, eventClick, datesSet

=============================================================
FEATURE 1 — DRAG & DROP COMPLETO (3 dimensiones)
=============================================================

El drag funciona en tres dimensiones simultaneamente:
  a) Cambio de hora: arrastrar arriba/abajo en la misma columna
  b) Cambio de estilista: arrastrar a otra columna (otro recurso)
  c) Cambio de dia: en vista semana, arrastrar al slot de otro dia

eventDrop({ event, oldEvent, revert }) {
  // FullCalendar ya mueve el evento visualmente (optimistic update automatico)
  axios.put('/agenda/appointments/' + event.id, {
    starts_at: event.startStr,
    ends_at:   event.endStr,
    stylist_id: event.getResources()[0]?.id
  }).then(() => {
    showToast('Cita actualizada', 'success')
    invalidateAvailabilityCache(event.start)
  }).catch(err => {
    revert()  // FullCalendar revierte a posicion original
    showToast('No se pudo mover: ' + (err.response?.data?.message || 'Error'), 'error')
  })
}

=============================================================
FEATURE 2 — CONFLICTOS EN ROJO DURANTE EL DRAG
=============================================================

Los slots ocupados se muestran en ROJO mientras el usuario arrastra,
ANTES de soltar el evento. Implementar con eventAllow callback:

eventAllow(dropInfo, draggedEvent) {
  const api = calendarRef.value.getApi()
  const existingEvents = api.getEvents()
  const targetResourceId = dropInfo.resource?.id

  const hasConflict = existingEvents.some(e => {
    if (e.id === draggedEvent.id) return false
    if (targetResourceId && e.getResources()[0]?.id !== targetResourceId) return false
    return e.start < dropInfo.end && e.end > dropInfo.start
  })

  return !hasConflict
  // Cuando retorna false, FullCalendar aplica automaticamente:
  // - cursor: not-allowed
  // - clase .fc-event-dragging-not-allowed en el contenedor del slot
}

Agregar CSS global:
.fc-event-dragging-not-allowed .fc-timegrid-col-bg {
  background: rgba(239, 68, 68, 0.20) !important;
}
.fc-highlight {
  background: rgba(59, 130, 246, 0.15) !important;
}

=============================================================
FEATURE 3 — RESIZE DE DURACION
=============================================================

eventResize({ event, revert }) {
  const minDuration = event.extendedProps.service_duration  // minutos
  const actualDuration = (event.end - event.start) / 60000

  if (actualDuration < minDuration) {
    revert()
    showToast('Duracion minima: ' + minDuration + ' minutos', 'warning')
    return
  }

  axios.put('/agenda/appointments/' + event.id, {
    starts_at: event.startStr,
    ends_at: event.endStr
  }).catch(err => {
    revert()
    showToast('No se pudo actualizar la duracion', 'error')
  })
}

=============================================================
FEATURE 4 — CLICK EN SLOT VACIO PARA NUEVA CITA
=============================================================

select({ start, end, resource }) {
  openAppointmentModal({
    starts_at: start,
    ends_at: end,
    stylist_id: resource?.id
  })
  calendarRef.value.getApi().unselect()
}

=============================================================
FEATURE 5 — COLOR CODING DOBLE (estilista + estado)
=============================================================

eventDidMount({ event, el }) {
  const statusColors = {
    pending:     '#94a3b8',
    confirmed:   '#3b82f6',
    in_progress: '#22c55e',
    completed:   '#15803d',
    cancelled:   '#ef4444',
    no_show:     '#f97316'
  }
  const status = event.extendedProps.status
  el.style.borderLeft = '4px solid ' + statusColors[status]
  el.style.borderLeftWidth = '4px'
  if (status === 'cancelled') {
    el.style.opacity = '0.45'
    el.querySelector('.fc-event-title')?.style.setProperty('text-decoration', 'line-through')
  }
  // Inicializar tippy tooltip (ver Feature 7)
  initTooltip(event, el)
}

=============================================================
FEATURE 6 — CONTENIDO ENRIQUECIDO EN CADA EVENTO
=============================================================

eventContent({ event, timeText }) {
  const p = event.extendedProps
  const isInProgress = p.status === 'in_progress'
  const timerAttr = isInProgress && p.started_at
    ? 'data-started="' + p.started_at + '"'
    : ''

  return {
    html: '<div class="apt-card" style="padding:2px 6px;height:100%;overflow:hidden;position:relative">' +
      '<div style="font-weight:600;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + p.client_name + '</div>' +
      '<div style="font-size:11px;opacity:0.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + p.service_name + '</div>' +
      (isInProgress ? '<span class="event-timer" ' + timerAttr + ' style="position:absolute;top:2px;right:4px;font-size:10px;opacity:0.8;background:rgba(0,0,0,0.2);border-radius:3px;padding:1px 3px"></span>' : '') +
      (p.notes ? '<span style="position:absolute;bottom:2px;right:4px;font-size:10px">📝</span>' : '') +
      (p.status === 'pending' ? '<span style="position:absolute;bottom:2px;right:4px;font-size:10px">🕐</span>' : '') +
      '</div>'
  }
}

// Actualizar timers cada 60 segundos
onMounted(() => {
  timerInterval.value = setInterval(() => {
    document.querySelectorAll('.event-timer[data-started]').forEach(el => {
      const started = new Date(el.dataset.started)
      const mins = Math.floor((Date.now() - started.getTime()) / 60000)
      el.textContent = mins + 'min'
    })
  }, 60000)
})
onUnmounted(() => clearInterval(timerInterval.value))

=============================================================
FEATURE 7 — TOOLTIP ENRIQUECIDO CON TIPPY.JS
=============================================================

function initTooltip(event, el) {
  const p = event.extendedProps
  const statusLabels = {
    pending: 'Pendiente', confirmed: 'Confirmada',
    in_progress: 'En progreso', completed: 'Completada',
    cancelled: 'Cancelada', no_show: 'No se presentó'
  }
  tippy(el, {
    content: '<div style="min-width:220px;padding:10px;font-size:13px">' +
      '<div style="font-weight:600;font-size:14px;margin-bottom:6px">' + p.client_name + '</div>' +
      '<div style="color:#94a3b8;margin-bottom:10px;font-size:12px">' + p.client_phone + '</div>' +
      '<table style="width:100%;border-collapse:collapse">' +
      '<tr><td style="color:#94a3b8;padding:2px 0">Servicio</td><td style="text-align:right">' + p.service_name + '</td></tr>' +
      '<tr><td style="color:#94a3b8;padding:2px 0">Duración</td><td style="text-align:right">' + p.service_duration + ' min</td></tr>' +
      '<tr><td style="color:#94a3b8;padding:2px 0">Precio</td><td style="text-align:right">$' + p.price + '</td></tr>' +
      '<tr><td style="color:#94a3b8;padding:2px 0">Estilista</td><td style="text-align:right">' + p.stylist_name + '</td></tr>' +
      '<tr><td style="color:#94a3b8;padding:2px 0">Estado</td><td style="text-align:right">' + statusLabels[p.status] + '</td></tr>' +
      '</table>' +
      (p.notes ? '<div style="margin-top:8px;font-style:italic;color:#94a3b8;font-size:12px;border-top:1px solid #e5e7eb;padding-top:8px">' + p.notes + '</div>' : '') +
      '</div>',
    allowHTML: true,
    placement: 'right',
    theme: 'light-border',
    delay: [600, 100],
    maxWidth: 280
  })
}

=============================================================
FEATURE 8 — HEATMAP DE OCUPACION SEMANAL
=============================================================

Componente WeekOccupancyBar.vue, va entre la barra superior y el calendario.

<template>
  <div class="week-heatmap" style="display:flex;gap:4px;padding:6px 0;border-bottom:1px solid #e5e7eb">
    <div v-for="day in weekOccupancy" :key="day.date"
         @click="$emit('navigate', day.date)"
         :title="day.label + ': ' + day.pct + '% ocupado (' + day.count + ' citas)'"
         style="flex:1;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px">
      <div style="width:100%;height:32px;background:#f3f4f6;border-radius:3px;position:relative;overflow:hidden">
        <div :style="{
          position: 'absolute', bottom: 0, width: '100%',
          height: day.pct + '%',
          background: day.pct > 85 ? '#ef4444' : day.pct > 60 ? '#f59e0b' : '#22c55e',
          transition: 'height 0.3s ease'
        }"></div>
      </div>
      <span style="font-size:10px;color:#6b7280">{{ day.shortName }}</span>
      <span style="font-size:10px;font-weight:600">{{ day.pct }}%</span>
    </div>
  </div>
</template>

Cargar datos con: GET /agenda/occupancy?week_start=YYYY-MM-DD
Actualizar automaticamente cuando datesSet cambia de semana.

=============================================================
FEATURE 9 — ATAJOS DE TECLADO COMPLETOS
=============================================================

mounted() { window.addEventListener('keydown', this.onKey) }
beforeUnmount() { window.removeEventListener('keydown', this.onKey) }

onKey(e) {
  const tag = e.target.tagName
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return

  const api = calendarRef.value.getApi()
  const keyMap = {
    'n': () => openAppointmentModal({ starts_at: new Date() }),
    'N': () => openAppointmentModal({ starts_at: new Date() }),
    't': () => api.today(),
    'T': () => api.today(),
    'ArrowLeft':  () => api.prev(),
    'ArrowRight': () => api.next(),
    'd': () => api.changeView('resourceTimeGridDay'),
    'D': () => api.changeView('resourceTimeGridDay'),
    'w': () => api.changeView('resourceTimeGridWeek'),
    'W': () => api.changeView('resourceTimeGridWeek'),
    'm': () => api.changeView('dayGridMonth'),
    'M': () => api.changeView('dayGridMonth'),
    'Escape': () => { closeDrawer(); closeModal() },
    '?': () => showKeyboardHelp.value = true
  }

  if (keyMap[e.key]) {
    e.preventDefault()
    keyMap[e.key]()
  }
}

Mostrar panel de ayuda de atajos cuando se presiona '?':
Componente KeyboardHelpModal.vue con tabla de todos los atajos.

=============================================================
FEATURE 10 — SOPORTE MOVIL COMPLETO
=============================================================

const isMobile = computed(() => window.innerWidth < 768)

En movil (< 768px):
- Vista por default: timeGridDay (sin recursos, un estilista a la vez)
- Agregar tabs de estilistas con scroll horizontal:
    <div class="stylist-tabs" style="overflow-x:auto;white-space:nowrap;padding:8px 0">
      <button v-for="s in stylists"
              :class="{ active: activeStylist === s.id }"
              @click="filterToStylist(s.id)"
              style="margin-right:8px;padding:6px 12px;border-radius:20px;display:inline-flex;align-items:center;gap:6px">
        <img :src="s.photo" style="width:24px;height:24px;border-radius:50%">
        {{ s.name }}
      </button>
    </div>

- Swipe horizontal para cambiar de dia:
    let touchStartX = 0
    @touchstart="e => { touchStartX = e.touches[0].clientX }"
    @touchend="e => {
      const delta = e.changedTouches[0].clientX - touchStartX
      if (Math.abs(delta) > 60) {
        delta < 0 ? api.next() : api.prev()
      }
    }"

- Long press 500ms en slot vacio para nueva cita:
    let longPressTimer
    @touchstart="e => { longPressTimer = setTimeout(() => openModalFromTouch(e), 500) }"
    @touchmove="() => clearTimeout(longPressTimer)"
    @touchend="() => clearTimeout(longPressTimer)"

=============================================================
AppointmentDrawer.vue — Detalle completo de la cita
=============================================================

Slide-in desde la derecha, 420px ancho, overlay negro semi-transparente.
Transicion: translateX(100%) → translateX(0), 250ms ease-out.
En movil: ocupa 100% del ancho con slide-up desde abajo.

Estructura:
HEADER:
  - Avatar del cliente (40px circular) + nombre completo (bold)
  - Badge de estado con color correspondiente
  - Boton X para cerrar

SUB-HEADER:
  - Fecha: "Martes 15 de abril, 2025"
  - Hora: "14:00 → 15:30 (90 min)"

FILA DE 3:
  - Servicio (con icono de la categoria)
  - Estilista (con foto 24px + nombre)
  - Precio ($XX.XX)

ALERTA DE ALERGIAS (si el cliente tiene):
  Fondo rojo, icono de advertencia, texto de las alergias.
  Siempre visible, no se puede colapsar.

TIMELINE DE ESTADOS:
  Puntos conectados con linea vertical mostrando cada cambio de estado:
  - "Creada el 15 abr 10:23 — por [usuario o Sistema]"
  - "Confirmada el 15 abr 11:00 — Sistema (WhatsApp)"
  - "Iniciada el 15 abr 14:02 — Valeria" (si aplica)
  - "Completada el 15 abr 15:10" (si aplica)

NOTAS DEL CLIENTE (textarea editable inline):
  Boton guardado que aparece al modificar, desaparece al guardar.

NOTAS INTERNAS (textarea, icono de candado, solo staff):
  Mismo comportamiento de edicion inline.

BOTONES DE ACCION SEGUN STATUS:

  pending:
    [✓ Confirmar cita] verde
    [Llegó → Iniciar] azul
    [No se presentó] naranja
    [Cancelar] rojo outline

  confirmed:
    [Llegó → Iniciar servicio] verde, prominente
    [No se presentó] naranja outline
    [Cancelar] rojo outline

  in_progress:
    [Completar y cobrar →] verde, prominente, flecha
    [Cancelar] rojo outline con confirmacion extra

  completed:
    [Ver cobro] outline
    [Ver factura] outline (disabled si no hay factura)
    [Reagendar] azul

  cancelled / no_show:
    [Reagendar con datos pre-llenados] azul prominente
    Info: motivo de cancelacion + quien cancelo

ICONOS DE ACCIONES SECUNDARIAS (siempre visibles):
  [✏ Editar] [📋 Copiar] [💬 WhatsApp] [👤 Ver cliente]

=============================================================
AppointmentModal.vue — Nueva / Edicion de cita
=============================================================

Modal centrado, 600px ancho en desktop, fullscreen en movil.
4 pasos con progress bar visual en la parte superior.

PASO 1 — Cliente:
  Input de busqueda con debounce 300ms: nombre, telefono, cedula
  Dropdown de resultados:
    Avatar + nombre + telefono + ultimo servicio + hace cuanto fue
  Al seleccionar: mini-card aparece debajo con:
    ultima visita | total de visitas | servicio favorito
    ALERTA ROJA si tiene alergias (siempre visible)
  Boton "Nuevo cliente" abre sub-modal inline:
    Campos: nombre completo, telefono (obligatorio), email, cedula,
            como llego al salon (walk_in, referral, instagram, etc)
    Al crear: selecciona automaticamente el cliente nuevo

PASO 2 — Servicio:
  Grid de cards por categoria (tabs o accordion por categoria)
  Cada card: foto o icono, nombre, duracion, precio base
  Al seleccionar: calcular automaticamente hora_fin en el paso 3

PASO 3 — Horario:
  Selector de estilista: dropdown con foto + color + nombre
    Si vino de click en columna del calendario: pre-seleccionado
  Date picker: solo fechas futuras, dias que el estilista no trabaja = disabled
  Time picker: grid de botones de slots disponibles cada 15 min
    Slot libre: clickeable, color normal
    Slot ocupado: disabled + tooltip "Ocupado con [nombre del cliente]"
    Slot en el pasado (si es hoy): disabled con color mas tenue
    "Hora fin estimada: HH:MM" actualizado automaticamente al cambiar slot
  Indicador: "Hay X horas libres disponibles hoy para este estilista"

PASO 4 — Confirmacion:
  Textarea: notas para el estilista
  Toggle: "Enviar confirmacion por WhatsApp" (default true si hay telefono)
  Card de resumen con toda la info:
    Avatar cliente | Nombre
    Servicio — duracion — precio
    Estilista con foto
    Fecha y hora completas con icono
  Boton [Crear cita] prominente

=============================================================
SIDEBAR IZQUIERDO (260px, collapsible con boton hamburger)
=============================================================

Panel "Estilistas":
  Toggle individual por estilista (checkbox coloreado + foto + nombre)
  Toggle "Todos" que activa/desactiva todos
  Cuando se oculta un estilista: su columna desaparece del calendario

Panel "Fecha":
  Mini calendario mensual navegable
  Dias con citas: punto de color debajo del numero del dia
  Dia de hoy: resaltado con color primario
  Click en dia: navega el calendario principal a ese dia

Panel "Hoy — proximas 2 horas":
  Lista compacta de citas que empiezan en los proximos 120 min:
    Hora | Avatar | Nombre | Servicio | [Confirmar]
  Boton rapido "Confirmar" que hace PATCH /confirm sin abrir el drawer

Panel "Pendientes de confirmar" (si hay):
  Citas del dia con status=pending
  Boton "Confirmar todos" que lanza PATCH en batch

=============================================================
BARRA SUPERIOR PERSONALIZADA (no usar la de FullCalendar)
=============================================================

Altura 56px, border-bottom sutil.

IZQUIERDA:
  Boton < (prev)
  Boton "Hoy" (resaltado/disabled si ya estamos hoy)
  Boton > (next)
  Label de fecha dinamico:
    Vista dia:    "Martes 15 de abril, 2025"
    Vista semana: "14 — 20 de abril, 2025"
    Vista mes:    "Abril 2025"

CENTRO:
  Tabs: [Día] [Semana] [Mes]

DERECHA:
  Buscador global (icono lupa → abre modal de busqueda de citas)
  Filtro de estilistas (dropdown multi-select rapido)
  Boton "+ Nueva cita" color primario

Modal de busqueda global:
  Input libre: busca por nombre de cliente, servicio, estilista
  Resultados en tiempo real: muestra citas pasadas y futuras
  Cada resultado: fecha + hora + cliente + servicio + estilista + estado
  Click en resultado: navega al dia correspondiente y abre el drawer de esa cita

=============================================================
AvailabilityService.php — CONSTRUIR DESDE CERO
=============================================================

IMPORTANTE: Este servicio se construye completamente desde cero.
No copiar ni adaptar codigo de ningun otro proyecto.

public function getAvailableSlots(
  string $stylist_id,
  string $service_id,
  string $date
): array {

  // 1. Cargar servicio
  $service = Service::findOrFail($service_id)
  $blockMinutes = $service->duration_minutes + $service->preparation_minutes

  // 2. Verificar que el estilista trabaja ese dia
  $stylist = Stylist::findOrFail($stylist_id)
  $dayOfWeek = strtolower(Carbon::parse($date)->format('l')) // monday, tuesday...
  $schedule = $stylist->schedule[$dayOfWeek] ?? []
  if (empty($schedule)) return []

  // 3. Cargar citas existentes del estilista ese dia (status != cancelled)
  $existing = Appointment::where('stylist_id', $stylist_id)
    ->whereDate('starts_at', $date)
    ->whereNotIn('status', ['cancelled'])
    ->get(['starts_at', 'ends_at'])

  // 4. Cargar bloqueos de horario que aplican a ese dia
  $blocks = BlockedTime::where(function($q) use ($stylist_id, $date) {
    $q->where('stylist_id', $stylist_id)
      ->orWhereNull('stylist_id')
  })
  ->whereDate('starts_at', '<=', $date)
  ->whereDate('ends_at', '>=', $date)
  ->get(['starts_at', 'ends_at'])

  // 5. Generar slots candidatos
  $slots = []
  foreach ($schedule as $shift) {  // puede haber manana + tarde
    $current = Carbon::parse($date . ' ' . $shift['start'])
    $shiftEnd = Carbon::parse($date . ' ' . $shift['end'])

    while ($current->copy()->addMinutes($blockMinutes)->lte($shiftEnd)) {
      $slotEnd = $current->copy()->addMinutes($blockMinutes)

      // 6. Verificar que no colisiona con ninguna cita
      $hasConflict = $existing->some(fn($apt) =>
        $current->lt($apt->ends_at) && $slotEnd->gt($apt->starts_at)
      )

      // 7. Verificar que no esta en un bloqueo
      if (!$hasConflict) {
        $hasConflict = $blocks->some(fn($b) =>
          $current->lt($b->ends_at) && $slotEnd->gt($b->starts_at)
        )
      }

      // 8. Si es hoy, no mostrar slots pasados o en menos de 30 min
      if (!$hasConflict && $date === now()->toDateString()) {
        $hasConflict = $current->lt(now()->addMinutes(30))
      }

      if (!$hasConflict) {
        $slots[] = [
          'time' => $current->format('H:i'),
          'datetime' => $current->toIso8601String(),
          'available' => true
        ]
      }

      $current->addMinutes(15)
    }
  }

  return $slots
}

Cache en Redis:
  Key: availability:{tenant()->id}:{stylist_id}:{service_id}:{date}
  TTL: 5 minutos
  Invalidar cuando se crea/modifica/cancela una cita o se modifica el horario

Tests PHPUnit requeridos para AvailabilityService:
  test('estilista que no trabaja ese dia retorna array vacio')
  test('dia completamente lleno retorna array vacio')
  test('dia con cita al medio retorna slots libres de manana y tarde')
  test('bloqueo de horario parcial elimina esos slots')
  test('preparation_minutes se suma al calculo de colision con la siguiente cita')
  test('slots en el pasado no aparecen cuando la fecha es hoy')
  test('turno partido manana y tarde genera slots correctamente en ambos turnos')