# Sesión 5 — Booking Público + CRM Clientes

**Duración estimada:** 3-4 días  
**Semana:** 4-5  
**Dependencias:** Sesiones 1-4 completadas  

---

## Parte A — Booking Público

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Agenda interna ya está completa. Ahora construyo el BOOKING PÚBLICO.

El booking público es la página donde los clientes del salón reservan su cita sin login.
URL: {slug}.miapp.ec/reservar — accesible sin autenticación, usa PublicLayout.

## BookingController (público, sin auth)
GET  /reservar                → Vista de booking (SPA)
GET  /reservar/services       → Lista de servicios activos con foto, nombre, precio, duración
GET  /reservar/stylists       → Lista de estilistas activos con foto, nombre, bio
GET  /reservar/availability   → Slots disponibles (params: service_id, stylist_id, date)
POST /reservar/appointments   → Crear cita (sin auth del cliente)
GET  /reservar/confirm/{token} → Confirmar cita por link del email/WhatsApp
GET  /reservar/cancel/{token}  → Cancelar cita por link

## Pages/Public/Booking.vue
Flujo en 4 pasos con progress bar visual:

PASO 1 — Seleccionar servicio:
- Grid de cards por categoría
- Cada card: foto, nombre, duración, precio
- Click para seleccionar (highlight visual)

PASO 2 — Seleccionar estilista:
- "Sin preferencia" siempre disponible primero
- Cards de estilistas con foto, nombre, especialidades
- Solo muestra estilistas que pueden hacer el servicio seleccionado

PASO 3 — Seleccionar fecha y hora:
- Mini calendario (solo días futuros habilitados)
- Al seleccionar día: carga slots disponibles del estilista
- Slots como grid de botones (09:00, 09:30, 10:00...)
- Slots ocupados aparecen disabled

PASO 4 — Datos del cliente:
- Nombre completo, teléfono (campo principal), email (opcional)
- Notas opcionales para el estilista
- Checkbox: acepta políticas de cancelación
- Resumen de la reserva antes de confirmar
- Botón "Confirmar cita"

POST confirmación:
- Crea la cita con status=pending
- Si el teléfono ya existe en la DB: vincula al cliente existente
- Si no existe: crea cliente nuevo
- Envía confirmación por WhatsApp (job async)
- Redirige a página de confirmación con resumen y botón "Agregar a Google Calendar"

La página debe ser mobile-first, bella y rápida.
El salón puede personalizar: logo, color primario, foto de portada.

---

## Parte B — CRM Clientes

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.

Construyo el módulo de GESTIÓN DE CLIENTES (CRM interno).

## ClientController (resource completo)

### Pages/Clients/Index.vue
- Tabla con: foto/avatar, nombre, teléfono, último servicio, visitas, gasto total, estado
- Búsqueda en tiempo real: por nombre, teléfono o cédula
- Filtros: tag (VIP, inactivo, etc.), por estilista preferido, por fecha de última visita
- Ordenar por: nombre, total gastado, última visita, número de visitas
- Exportar a Excel (columnas básicas)
- Indicador visual de clientes "inactivos" (sin visita en 60+ días, color diferente)
- Botón "+ Nuevo cliente"

### Pages/Clients/Show.vue (ficha completa del cliente)
Panel izquierdo (1/3):
- Avatar/foto grande
- Nombre completo
- Badges de tags (editables inline)
- Teléfono (click para abrir WhatsApp)
- Email
- Cumpleaños (con cuántos días faltan si es próximo)
- Cédula
- Estilista favorito
- Fuente (cómo llegó al salón)
- Notas del equipo (textarea editable inline)
- Alergias e indicaciones (con ícono de advertencia si hay algo)
- Puntos de fidelidad actuales
- Botón "Editar datos"

Panel derecho (2/3) con tabs:
TAB "Historial":
- Timeline de todas las citas (completadas, canceladas, no-shows)
- Cada entrada: fecha, servicio, estilista, total cobrado, nota
- Click en entrada: despliega detalle + foto resultado si existe

TAB "Compras":
- Historial de ventas con productos comprados
- Total histórico gastado
- Ticket promedio

TAB "Fotos":
- Galería de fotos antes/después subidas por el personal
- Botón para agregar foto (con nota de qué servicio fue)

TAB "Citas futuras":
- Próximas citas agendadas
- Botón para agendar nueva cita (abre el modal de agenda pre-llenado con este cliente)

### Métricas en la ficha
- Total visitas / Visitas en los últimos 12 meses
- Total gastado / Promedio por visita
- Tasa de retención (viene vs. no viene al mes de una cita anterior)
- Servicio favorito (el más repetido)

### Importación de clientes
- Modal con upload de Excel/CSV
- Preview de primeras 5 filas con mapeo de columnas
- Importación en background (job) con reporte de resultados

---

## Verificación al terminar esta sesión

- [ ] El booking público carga en menos de 2 segundos en móvil
- [ ] Se puede completar una reserva en menos de 2 minutos
- [ ] La cita aparece en la agenda interna al instante
- [ ] La confirmación por WhatsApp llega al número de prueba
- [ ] La ficha del cliente muestra todo el historial correctamente
- [ ] La importación de CSV con 100 clientes funciona sin errores
