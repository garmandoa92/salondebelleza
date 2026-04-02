# Sesión 16 — Paquetes, Bonos y Flujo de Cobro Perfecto

**Duración estimada:** 5-6 días
**Dependencias:** Sesiones 1-13 completadas y funcionando

---

## Objetivo
Implementar el sistema completo de paquetes y bonos, y corregir el flujo de citas para separar "Completar" de "Cobrar" en dos acciones independientes — una cita puede completarse sin que el pago sea inmediato.

---

## CAMBIO CRÍTICO — Separar los botones "Completar" y "Cobrar"

### El problema actual
El botón "Completar y cobrar" hace las dos cosas al mismo tiempo. Esto es incorrecto porque:
- Una clienta puede recibir el servicio hoy pero pagar mañana
- Una cita puede completarse aunque el pago falle
- Si usa un paquete: la cita se completa pero no hay cobro monetario
- El recepcionista puede querer completar primero y cobrar al final del día

### Los dos botones separados

En AppointmentDrawer.vue cuando la cita está in_progress:

```
[✓ Completar servicio]     [💰 Cobrar]
```

[✓ Completar servicio]:
- Marca la cita como completed
- Si tiene paquete vinculado: descuenta sesiones automáticamente
- Envía WhatsApp de servicio completado
- NO abre el checkout
- payment_status queda como pending (o package si usa paquete)

[💰 Cobrar]:
- Abre el modal de checkout con la cita pre-cargada
- Puede usarse ANTES, DURANTE o DESPUÉS de completar el servicio
- También disponible desde /ventas en "Pendientes de cobro"
- Al completar el cobro: payment_status = paid

### Nuevo campo en appointments

Agregar migración:
- payment_status enum(pending, paid, package, free) default pending
  - pending = completada pero sin cobro registrado
  - paid = cobrada con dinero
  - package = cubierta por un paquete
  - free = servicio gratuito (cortesía)
- client_package_item_id uuid nullable FK client_package_items
- sessions_used integer nullable

### Indicadores visuales en el calendario

Cita completed + payment_status=pending: borde naranja + icono moneda
Cita completed + payment_status=paid: verde oscuro solido
Cita completed + payment_status=package: verde con icono 📦

### Panel "Pendientes de cobro" en el sidebar del calendario

Muestra citas del día con status=completed y payment_status=pending:

```
💰 Pendientes de cobro (3)

  María Morales · Corte dama · 14:30
  [💰 Cobrar $15 →]

  Juan Pérez · Masaje · 15:00
  [💰 Cobrar $35 →]
```

---

## PARTE 1 — Modelos y migraciones

### Package
Tabla: packages

- id uuid PK
- name string
- description text nullable
- price decimal(8,2)
- type enum(sessions, combo)
- items json — [{service_id, service_name, quantity}]
- validity_days integer nullable (null = sin vencimiento, default 365)
- image_path string nullable
- is_active boolean default true
- sort_order integer default 0
- created_at, updated_at, deleted_at

### ClientPackage
Tabla: client_packages

- id uuid PK
- receipt_number string unique — formato PKG-YYYYMM-XXXX ej: PKG-202604-0001
- client_id uuid FK clients
- package_id uuid FK packages
- sale_id uuid nullable FK sales
- package_name string — snapshot nombre al comprar
- package_price decimal(8,2) — snapshot precio al comprar
- purchased_at timestamp
- expires_at timestamp nullable
- status enum(active, completed, expired) default active
- notes text nullable
- created_at, updated_at

### ClientPackageItem
Tabla: client_package_items

- id uuid PK
- client_package_id uuid FK client_packages
- service_id uuid FK services
- service_name string — snapshot
- total_quantity integer default 1
- used_quantity integer default 0
- last_used_at timestamp nullable
- last_appointment_id uuid nullable FK appointments
- created_at, updated_at

Propiedad calculada: remaining = total_quantity - used_quantity

### PackageUsageLog
Tabla: package_usage_logs

Historial detallado de cada uso de sesión:

- id uuid PK
- client_package_id uuid FK client_packages
- client_package_item_id uuid FK client_package_items
- appointment_id uuid nullable FK appointments
- service_id uuid FK services
- sessions_used integer default 1
- sessions_before integer
- sessions_after integer
- used_by uuid FK users
- notes text nullable
- created_at

---

## PARTE 2 — PackageService (app/Services/PackageService.php)

Toda la lógica va aquí, nunca en controllers.

getActivePackagesForService(clientId, serviceId): array
  Retorna todos los ClientPackages activos del cliente que incluyen ese servicio
  Con sus ClientPackageItems y remaining calculado
  Ordena por expires_at ASC (los que vencen antes, primero)

getAllActivePackages(clientId): array
  Retorna todos los paquetes activos del cliente sin filtrar por servicio

generateReceiptNumber(): string
  Formato: PKG-YYYYMM-XXXX
  Ejemplo: PKG-202604-0001
  Busca el último receipt_number del mes actual y suma 1
  USA DB::transaction con lockForUpdate() para evitar duplicados en concurrencia
  Si es el primero del mes: PKG-202604-0001

createClientPackage(Client client, Package package, Sale sale): ClientPackage
  Genera el receipt_number
  Crea ClientPackage con expires_at = now() + validity_days
  Crea ClientPackageItems según package.items
  Dispara evento ClientPackagePurchased

useSessions(ClientPackageItem item, int sessions, Appointment appointment, User usedBy): PackageUsageLog
  Valida que remaining >= sessions (lanza excepción si no)
  Crea PackageUsageLog con sessions_before y sessions_after
  Actualiza used_quantity y last_used_at en el item
  Llama a checkCompletion
  Retorna el PackageUsageLog

checkCompletion(ClientPackage clientPackage): bool
  Si todos los items tienen used_quantity = total_quantity:
    Actualiza status = completed
    Dispara evento ClientPackageCompleted
    Retorna true
  Retorna false

getRemainingTotal(ClientPackage clientPackage): int
  Suma remaining de todos los items

---

## PARTE 3 — PackageController

GET    /packages
POST   /packages
GET    /packages/{id}
PUT    /packages/{id}
DELETE /packages/{id}

GET    /packages/available-for-client
  param: client_id (opcional)
  retorna paquetes activos del salón disponibles para comprar

GET    /packages/check-client
  params: client_id, service_id
  retorna:
    {
      has_packages: bool,
      packages: [{
        client_package_id,
        client_package_item_id,
        package_name,
        receipt_number,
        purchased_at,
        expires_at,
        total_quantity,
        used_quantity,
        remaining,
        service_name
      }]
    }

GET    /packages/client/{clientId}
  param: status = active|completed|expired|all
  retorna todos los paquetes del cliente con su historial de uso

POST   /packages/use-session
  body: { client_package_item_id, appointment_id, sessions_used }
  retorna:
    {
      success: true,
      receipt_number,
      package_name,
      sessions_before,
      sessions_after,
      package_completed: bool
    }

---

## PARTE 4 — Pages/Packages/

### Pages/Packages/Index.vue

Grid de cards. Cada card:
- Nombre + badge tipo (azul Sessions / morado Combo)
- Precio grande
- Servicios incluidos como chips
- Validez
- N clientes activos con este paquete
- Toggle activo/inactivo
- Botones: [Editar] [Ver clientes]

### Pages/Packages/Form.vue

Campos base:
- Nombre
- Tipo: radio Sessions o Combo
- Precio total
- Validez: input + presets [30d] [60d] [90d] [180d] [365d] [Sin vencimiento]
- Descripcion opcional

Si tipo = Sessions:
  Seleccionar 1 servicio
  Cantidad de sesiones (min:2 max:100)
  Preview azul:
  "El cliente tendrá 10 sesiones de [servicio] por $120.00
  Precio por sesión: $12.00 (vs $20.00 — 40% de ahorro)"

Si tipo = Combo:
  Tabla dinámica para agregar servicios con cantidad
  Precio normal total calculado automáticamente
  El admin define el precio del paquete
  Preview morado con el ahorro calculado

### Pages/Packages/Clients.vue

Tabla de clientes con ese paquete:
- Cliente | Recibo | Sesiones usadas/total | Comprado | Vence | Estado
- Filtros: activo / completado / vencido

---

## PARTE 5 — Modal de nueva cita (AppointmentModal.vue)

### PASO 2 — Dos tabs

[  Servicio  ]    [  Paquete  ]

Tab Servicio: comportamiento actual exactamente igual, sin ningún cambio.

Tab Paquete:

SECCIÓN A — Paquetes activos del cliente

Si el cliente tiene ClientPackages activos, mostrar cada uno:

Tipo Sessions:
```
📦 Paquete 10 Depilaciones Láser
Recibo: PKG-202604-0001
Comprado: 01 abr 2026 · Vence: 01 abr 2027

████████░░  8 de 10 sesiones restantes

Servicio: Depilación láser
Sesiones a descontar hoy: [−] 1 [+]  (máx: 8)

[● Usar sesión de este paquete]
```

Tipo Combo:
```
📦 Renovación Facial (Combo)
Recibo: PKG-202604-0002
Comprado: 01 abr 2026 · Vence: 01 may 2026

✅ Botox capilar — usado 01 abr (Valeria)
⏳ Limpieza facial — PENDIENTE

[● Usar Limpieza facial hoy]
```

Si el cliente no tiene paquetes activos:
"Este cliente no tiene paquetes activos."

SECCIÓN B — Comprar paquete nuevo (siempre visible)

Lista de paquetes del salón disponibles para comprar.
Cada paquete con: nombre, precio, servicios, validez.
Botón: [Comprar y usar primera sesión hoy →]

Al comprar:
1. Se crea el ClientPackage al guardar la cita
2. Primera sesión ya incluida en esta cita
3. PASO 4 muestra el costo del paquete

### PASO 4 — Confirmación según el caso

CASO 1 — Servicio normal:
```
María Morales · Depilación láser · Valeria · Hoy 15:00
TOTAL A COBRAR: $20.00
```

CASO 2 — Usa sesión de paquete:
```
María Morales · Depilación láser · Valeria · Hoy 15:00

✓ Sesión del paquete
  Recibo: PKG-202604-0001
  Paquete 10 Depilaciones Láser
  Quedarán 7 de 10 sesiones
  Vence: 01 abr 2027

TOTAL A COBRAR HOY: $0.00
(incluido en el paquete)
```

CASO 3 — Compra paquete nuevo + usa primera sesión:
```
María Morales · Depilación láser · Valeria · Hoy 15:00

📦 PAQUETE NUEVO
  Paquete 10 Depilaciones Láser
  Primera sesión incluida hoy
  Quedarán 9 de 10 sesiones

TOTAL A COBRAR HOY: $120.00
(precio del paquete completo)
```

CASO 4 — Último servicio de un combo:
```
María Morales · Limpieza facial · Valeria · Hoy 15:00

✓ Último servicio del paquete
  Recibo: PKG-202604-0002
  Renovación Facial (Combo)
  El paquete quedará COMPLETADO ✓

TOTAL A COBRAR HOY: $0.00
(incluido en el paquete)
```

---

## PARTE 6 — AppointmentController: lógica al completar

En AppointmentController::complete():

```php
$appointment->update(['status' => 'completed']);

if ($appointment->client_package_item_id) {
    $item = ClientPackageItem::find($appointment->client_package_item_id);
    $sessionsToUse = $appointment->sessions_used ?? 1;

    $usageLog = $packageService->useSessions(
        $item, $sessionsToUse, $appointment, auth()->user()
    );

    $appointment->update(['payment_status' => 'package']);
    $remaining = $usageLog->sessions_after;
    $completed = $packageService->checkCompletion($item->clientPackage);
    $receiptNumber = $item->clientPackage->receipt_number;
    $packageName = $item->clientPackage->package_name;

    if ($completed) {
        // WhatsApp: paquete completado con numero de recibo
        WhatsApp al cliente:
        "¡Completaste tu {packageName}! 🎉
        Recibo: {receiptNumber}
        Fue un placer atenderte todas las sesiones, {client.first_name}.
        Como cliente frecuente tienes 10% de descuento en tu próximo paquete.
        ¿Renovamos? [link booking]"

        // Notificación interna
        "{client.full_name} completó el paquete {packageName}.
        Recibo: {receiptNumber}. Oportunidad de renovación."

    } elseif ($remaining <= 2) {
        // WhatsApp: pocas sesiones restantes
        WhatsApp al cliente:
        "Hola {client.first_name}, usaste {sessionsToUse} sesión(es)
        de tu {packageName}.
        Recibo: {receiptNumber}
        ⚠️ Solo te quedan {remaining} sesiones.
        ¡No olvides renovar tu paquete! [link booking]"

        // Notificación interna urgente
        "{client.full_name} solo le quedan {remaining} sesiones
        en {packageName}. Recibo: {receiptNumber}. Ofrécele renovación."
    } else {
        // WhatsApp: uso normal
        WhatsApp al cliente:
        "Hola {client.first_name}, usaste {sessionsToUse} sesión(es)
        de tu {packageName}.
        Recibo: {receiptNumber}
        Te quedan {remaining} sesiones disponibles.
        ¡Hasta la próxima! 💆‍♀️"
    }
} else {
    $appointment->update(['payment_status' => 'pending']);
}
```

---

## PARTE 7 — Ficha del cliente: Tab "Paquetes"

En Pages/Clients/Show.vue agregar tab "Paquetes".

Paquetes activos — Tipo Sessions:
```
📦 Paquete 10 Depilaciones Láser
Recibo: PKG-202604-0001 · Estado: Activo 🟢
Comprado: 01 abr 2026 · Vence: 01 abr 2027

██████░░░░  4 de 10 sesiones usadas — Quedan 6

[Ver historial ▼]
  15 abr · Valeria · 1 sesión · Quedan 6/10
  08 abr · Valeria · 1 sesión · Quedan 7/10
  01 abr · Valeria · 1 sesión · Quedan 9/10 ← primera

[Agendar próxima sesión →]    [Renovar paquete]
```

Paquetes activos — Tipo Combo:
```
📦 Renovación Facial
Recibo: PKG-202604-0002 · Estado: Activo 🟢
Comprado: 01 abr 2026 · Vence: 01 may 2026

✅ Botox capilar — usado 01 abr (Valeria)
⏳ Limpieza facial — PENDIENTE

[Agendar Limpieza facial →]
```

Paquetes anteriores (colapsable):
```
▶ Paquetes anteriores (2)

  ✅ Paquete 5 Depilaciones — Completado 15 mar 2026
     Recibo: PKG-202603-0005

  ⏰ Renovación Facial — Vencido 28 feb 2026
     Recibo: PKG-202602-0003 · 1 sesión sin usar al vencer
```

---

## PARTE 8 — Checkout: venta directa de paquetes

En Pages/Sales/Checkout.vue agregar sección Paquetes junto a Servicios.

Al agregar un paquete a la venta y completar el cobro:
1. Genera receipt_number: PKG-YYYYMM-XXXX
2. Crea ClientPackage vinculado a la Sale
3. Crea los ClientPackageItems
4. Pantalla de confirmación muestra:
   "✅ Venta completada · $120.00
    📦 Paquete activado: PKG-202604-0001
    María Morales · 10 sesiones disponibles
    Vence: 01 abr 2027
    WhatsApp enviado ✓"
5. WhatsApp automático:
   "Hola María, tu Paquete 10 Depilaciones Láser está activo 🎉
   Recibo: PKG-202604-0001
   Tienes 10 sesiones disponibles.
   Vence: 01 abr 2027
   Agenda tu primera sesión: [link booking]"

---

## PARTE 9 — Reportes de paquetes

En Pages/Reports/Index.vue agregar tab "Paquetes":

KPIs:
- Paquetes vendidos este período
- Ingresos por paquetes
- Paquetes activos actualmente
- Sesiones pendientes de uso en total

Tabla paquetes más vendidos:
| Paquete | Vendidos | Ingresos | Sesiones vendidas | Usadas | Pendientes |

Clientes con paquetes próximos a vencer (15 días):
| Cliente | Paquete | Recibo | Vence en | Sesiones restantes | [WhatsApp] |

Paquetes completados este período:
| Cliente | Paquete | Recibo | Comprado | Completado | [Renovar] |

---

## PARTE 10 — Jobs automáticos

### CheckExpiringPackagesJob (schedulado diario a las 9am)

Para cada ClientPackage activo verificar:

Si vence en exactamente 15 días:
  WhatsApp al cliente con el recibo y sesiones restantes

Si vence en exactamente 7 días:
  WhatsApp urgente al cliente
  Notificación interna al recepcionista

Si vence en exactamente 1 día:
  WhatsApp muy urgente al cliente
  WhatsApp al dueño del salón con la alerta

Comando para probar manualmente:
  php artisan packages:check-expiring

### CheckExpiredPackagesJob (schedulado diario a las 00:01am)

Busca ClientPackages con expires_at < now() y status=active:
- Cambia a expired
- Notificación interna con sesiones perdidas

---

## PARTE 11 — Actualizar CLAUDE.md

Agregar al índice:
| 16 | docs/sesion-16-paquetes.md | Paquetes, Bonos y Flujo de Cobro | Post MVP |

---

## Verificación al terminar

Flujo 1 — Servicio normal sin paquete:
- [ ] Tab Servicio funciona igual que antes sin cambios
- [ ] Aparecen dos botones separados: Completar y Cobrar
- [ ] Completar sin cobrar: cita queda completed con payment_status=pending
- [ ] El sidebar muestra la cita en "Pendientes de cobro"
- [ ] Se puede cobrar desde el sidebar o desde /ventas

Flujo 2 — Usar sesión de paquete existente:
- [ ] Tab Paquete muestra paquetes activos del cliente con recibo
- [ ] El selector de sesiones funciona
- [ ] PASO 4 muestra: recibo PKG-XXXXXX, quedarán X sesiones, total $0.00
- [ ] Al completar la cita: sesión descontada automáticamente
- [ ] WhatsApp llega con el recibo y sesiones restantes

Flujo 3 — Comprar paquete desde el modal:
- [ ] Sección B muestra paquetes disponibles para comprar
- [ ] Al confirmar: ClientPackage creado con receipt_number
- [ ] Primera sesión descontada
- [ ] WhatsApp de activación con el recibo

Flujo 4 — Vender paquete desde el checkout:
- [ ] Se puede agregar paquete a la venta
- [ ] Confirmación muestra el recibo generado
- [ ] WhatsApp de activación llega al cliente

Ficha del cliente:
- [ ] Tab Paquetes muestra activos con barra de progreso y recibo
- [ ] Historial de uso con detalle de cada sesión
- [ ] Paquetes anteriores en sección colapsable
- [ ] Botón Renovar funciona

Jobs:
- [ ] php artisan packages:check-expiring funciona y envía WhatsApp
