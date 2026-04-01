# Sesión 16 — Paquetes y Bonos

**Duración estimada:** 3-4 días  
**Semana:** Post MVP  
**Dependencias:** Sesiones 1-13 completadas

---

## Objetivo
Agregar un módulo de paquetes y bonos que permita dos casos de uso:
1. **Bono de sesiones** — el cliente compra X sesiones del mismo servicio (ej: 10 depilaciones) y se descuenta una cada vez que viene
2. **Paquete combo** — el cliente compra una combinación de servicios distintos (ej: Botox + Limpieza facial) que puede usar en días diferentes

---

## Modelos nuevos

### Package
**Tabla:** `packages`

- `id` uuid PK
- `name` string — ej: "Paquete 10 Depilaciones", "Renovación Facial"
- `description` text nullable
- `price` decimal(8,2) — precio total del paquete
- `type` enum(sessions, combo)
  - `sessions` = bono de X sesiones del mismo servicio
  - `combo` = combinación de servicios distintos
- `items` json — `[{service_id, service_name, quantity}]`
- `validity_days` integer — días para usarlo desde la compra (ej: 365)
- `image_path` string nullable
- `is_active` boolean default true
- `sort_order` integer default 0
- `created_at`, `updated_at`, `deleted_at`

---

### ClientPackage (paquete comprado por un cliente)
**Tabla:** `client_packages`

- `id` uuid PK
- `client_id` uuid FK clients
- `package_id` uuid FK packages
- `sale_id` uuid nullable FK sales — la venta donde se compró
- `package_name` string — snapshot del nombre al momento de compra
- `package_price` decimal(8,2) — snapshot del precio
- `purchased_at` timestamp
- `expires_at` timestamp nullable — purchased_at + validity_days
- `status` enum(active, completed, expired) default active
- `notes` text nullable
- `created_at`, `updated_at`

---

### ClientPackageItem (cada servicio/sesión dentro del paquete)
**Tabla:** `client_package_items`

- `id` uuid PK
- `client_package_id` uuid FK client_packages
- `service_id` uuid FK services
- `service_name` string — snapshot del nombre
- `total_quantity` integer default 1 — cuántas sesiones tiene
- `used_quantity` integer default 0 — cuántas ha usado
- `last_used_at` timestamp nullable
- `last_appointment_id` uuid nullable FK appointments
- `created_at`, `updated_at`

Campo calculado: `remaining = total_quantity - used_quantity`

---

## PackageController — CRUD de paquetes

```
GET    /packages          → Lista de paquetes activos e inactivos
POST   /packages          → Crear paquete
PUT    /packages/{id}     → Actualizar
DELETE /packages/{id}     → Desactivar (no eliminar si tiene ventas)
GET    /packages/{id}     → Detalle del paquete
```

---

## Pages/Packages/Index.vue

- Grid de cards de paquetes
- Cada card: nombre, tipo (badge Sessions/Combo), precio, servicios incluidos, estado
- Toggle activo/inactivo inline
- Botón "+ Nuevo paquete"
- Indicador de cuántos clientes tienen este paquete activo

---

## Pages/Packages/Form.vue

**Campos base:**
- Nombre del paquete
- Tipo: Sessions o Combo (radio con descripción de cada uno)
- Precio total ($)
- Días de validez (input numérico + presets: 30, 60, 90, 180, 365 días)
- Descripción (textarea opcional)
- Foto opcional

**Si tipo = Sessions:**
```
Seleccionar servicio (1 solo)
Cantidad de sesiones (input numérico, min: 2, max: 100)
Preview: "El cliente tendrá X sesiones de [servicio] por $Y"
```

**Si tipo = Combo:**
```
Tabla dinámica de servicios:
[Buscar servicio] [Cantidad] [Agregar]
| Servicio         | Cantidad | Acciones |
| Botox capilar    | 1        | [Eliminar]|
| Limpieza facial  | 1        | [Eliminar]|

Preview: "El cliente tendrá: 1x Botox + 1x Limpieza por $Y"
Nota: "Cada servicio puede usarse en una visita diferente"
```

---

## Venta de paquetes en el Checkout

En `Pages/Sales/Checkout.vue` agregar sección "Paquetes" junto a Servicios y Productos:

- Buscador de paquetes activos
- Al agregar un paquete a la venta:
  - Se muestra como ítem con el precio del paquete
  - Al completar la venta: crea automáticamente `ClientPackage` + `ClientPackageItems`
  - `expires_at` = `completed_at` + `validity_days`
  - Si el cliente no existe: crear cliente primero

---

## Uso del paquete al agendar una cita

### En AppointmentModal.vue (modal de nueva cita)

Cuando se selecciona un cliente y un servicio, verificar si el cliente tiene un `ClientPackage` activo con ese servicio:

```javascript
// Si tiene paquete activo con ese servicio
if (clientHasActivePackage) {
  // Mostrar banner verde debajo del selector de servicio:
  "✓ Este cliente tiene X sesiones disponibles del Paquete [nombre]
   Vence: DD/MM/YYYY"
  
  // Toggle: "Descontar del paquete" (default: true si tiene paquete)
  // Si toggle activo: al completar la cita se descuenta automáticamente
}
```

### En AppointmentDrawer.vue (detalle de cita)

Si la cita tiene un paquete asociado:
- Mostrar badge: "Sesión de paquete" en lugar del precio
- Al marcar como completada: incrementar `used_quantity` en 1
- Si `used_quantity` = `total_quantity`: marcar item como agotado
- Si todos los items agotados: marcar `ClientPackage` como `completed`

### En AppointmentController

Al crear/completar una cita con paquete:
```
POST /appointments/{id}/use-package
  Recibe: client_package_item_id
  Incrementa used_quantity en 1
  Actualiza last_used_at y last_appointment_id
  Verifica si el paquete completo está agotado
  Si agotado: status = completed + notificación al cliente
```

---

## Ficha del cliente — Tab "Paquetes"

En `Pages/Clients/Show.vue` agregar tab "Paquetes":

### Paquetes activos

Para cada `ClientPackage` activo:

**Si tipo = Sessions:**
```
┌─────────────────────────────────────────┐
│ Paquete 10 Depilaciones                 │
│ Comprado: 15 mar 2026                   │
│ Vence: 15 mar 2027                      │
│                                         │
│ ██████░░░░  6/10 sesiones usadas        │
│ Quedan: 4 sesiones                      │
│                                         │
│ [Ver historial de uso]                  │
└─────────────────────────────────────────┘
```

**Si tipo = Combo:**
```
┌─────────────────────────────────────────┐
│ Renovación Facial                       │
│ Comprado: 20 abr 2026                   │
│ Vence: 20 may 2026                      │
│                                         │
│ ✅ Botox capilar — usado 20 abr         │
│ ⏳ Limpieza facial — pendiente          │
│                                         │
│ [Agendar sesión pendiente]              │
└─────────────────────────────────────────┘
```

### Paquetes completados/vencidos

Lista colapsable con:
- Nombre del paquete
- Fecha de compra y vencimiento
- Estado: Completado ✅ o Vencido ⏰
- Botón "Renovar" → abre checkout con ese paquete pre-seleccionado

### Botón "Comprar paquete"

Abre modal con lista de paquetes activos para comprarle al cliente directamente desde su ficha.

---

## Alertas automáticas de paquetes

### Job: CheckExpiringPackagesJob (schedulado diario a las 9am)

Para cada `ClientPackage` activo:

**Si vence en 7 días Y le quedan sesiones:**
```
WhatsApp al cliente:
"Hola [nombre], tu paquete [nombre del paquete] vence el [fecha].
Te quedan [X] sesiones por usar. ¡No las pierdas!
Agenda tu cita: [link de booking]"
```

**Si vence en 1 día:**
```
WhatsApp urgente:
"⚠️ [nombre], tu paquete [nombre] vence MAÑANA.
Te quedan [X] sesiones. Llámanos hoy para agendarlas."
```

### Evento: ClientPackageCompleted

Se dispara cuando `used_quantity` = `total_quantity` en todos los items.

```
WhatsApp al cliente:
"¡Completaste tu paquete [nombre]! 🎉
Fue un placer atenderte [X] veces.
¿Quieres renovarlo? Tenemos una oferta especial para ti."
[Link de booking]
```

Notificación interna al recepcionista:
"El cliente [nombre] completó su paquete [nombre]. Oportunidad de renovación."

### Notificación al recepcionista cuando quedan 2 sesiones

Al descontar una sesión, si `remaining` = 2:
Notificación interna: "[Cliente] solo le quedan 2 sesiones en [paquete]. Ofrécele renovación."

---

## Reportes de paquetes

En `Pages/Reports/Index.vue` agregar tab "Paquetes":

```
KPIs:
- Paquetes vendidos este período
- Ingresos por paquetes ($)
- Paquetes activos actualmente
- Sesiones pendientes de uso (todas las sesiones no usadas)

Tabla de paquetes más vendidos:
| Paquete | Vendidos | Ingresos | Sesiones usadas | Sesiones pendientes |

Clientes con paquetes próximos a vencer (en 15 días):
| Cliente | Paquete | Vence | Sesiones restantes | [WhatsApp] |
```

---

## Actualizar el CLAUDE.md

Agregar al índice de sesiones:
```
| 16 | docs/sesion-16-paquetes.md | Paquetes y Bonos | Post MVP |
```

---

## Verificación al terminar esta sesión

- [ ] Se puede crear un paquete tipo Sessions con un servicio y cantidad
- [ ] Se puede crear un paquete tipo Combo con múltiples servicios
- [ ] Al vender un paquete en el checkout se crea el ClientPackage correctamente
- [ ] Al agendar una cita aparece el banner si el cliente tiene paquete activo
- [ ] Al completar la cita se descuenta una sesión automáticamente
- [ ] La ficha del cliente muestra los paquetes con barra de progreso
- [ ] El combo muestra checklist con servicios usados y pendientes
- [ ] El job de alertas funciona y envía WhatsApp cuando vence en 7 días
- [ ] Al completar el paquete se envía WhatsApp al cliente
- [ ] El reporte de paquetes muestra los KPIs correctamente
