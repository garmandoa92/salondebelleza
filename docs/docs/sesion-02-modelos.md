# Sesión 2 — Modelos y Migraciones Completas

**Duración estimada:** 2 días  
**Semana:** 1-2  
**Dependencias:** Sesión 1 completada y funcionando

---

## Objetivo
Crear TODOS los modelos y migraciones de la base de datos del tenant de una vez. Los modelos bien definidos desde el inicio evitan semanas de refactoring. Construir la estructura de datos completa antes de cualquier UI.

---

## Reglas para todos los modelos
- Todos usan `HasUuids` trait
- Todos usan `SoftDeletes` trait (excepto tablas pivot)
- Todos tienen `$fillable` explícito (NUNCA `$guarded = []`)
- Todos tienen relaciones definidas
- Todos tienen casts correctos para fechas, enums y json
- Todos tienen Factories con datos realistas en español

---

## Modelos a crear (en este orden exacto)

### 1. ServiceCategory
**Tabla:** `service_categories`
- `id` uuid PK
- `name` string
- `color` string — hex color para UI (#E91E8C)
- `sort_order` integer default 0
- `is_active` boolean default true
- `created_at`, `updated_at`, `deleted_at`

**Relaciones:** hasMany(Service)

**Seeder:** Crear categorías por defecto:
Corte y Estilo (#3B82F6), Coloración (#F59E0B), Tratamientos (#10B981),
Manicure y Pedicure (#EC4899), Cejas y Pestañas (#8B5CF6),
Maquillaje (#EF4444), Spa y Masajes (#14B8A6)

---

### 2. Service
**Tabla:** `services`
- `id` uuid PK
- `service_category_id` uuid FK
- `name` string
- `description` text nullable
- `base_price` decimal(8,2)
- `duration_minutes` integer
- `preparation_minutes` integer default 0 — tiempo buffer entre citas
- `recipe` json default [] — [{product_id, quantity, unit}]
- `image_path` string nullable
- `is_visible` boolean default true — visible en booking público
- `requires_consultation` boolean default false
- `sort_order` integer default 0
- `created_at`, `updated_at`, `deleted_at`

**Relaciones:** belongsTo(ServiceCategory), belongsToMany(Stylist), hasMany(SaleItem)

---

### 3. Stylist
**Tabla:** `stylists`
- `id` uuid PK
- `user_id` uuid nullable FK users — si tiene acceso al sistema
- `name` string
- `phone` string nullable
- `email` string nullable
- `photo_path` string nullable
- `bio` text nullable
- `specialties` json default [] — array de service_category ids
- `commission_rules` json default {} — {default: 40, by_category: {uuid: 35}}
- `schedule` json default {} — {monday: [{start:"09:00", end:"18:00"}], ...}
- `color` string — color en el calendario hex
- `is_active` boolean default true
- `sort_order` integer default 0
- `created_at`, `updated_at`, `deleted_at`

**Relaciones:** belongsTo(User), hasMany(Appointment), hasMany(Commission), belongsToMany(Service)

---

### 4. BlockedTime
**Tabla:** `blocked_times`
- `id` uuid PK
- `stylist_id` uuid nullable FK — null = bloquea todo el salón
- `starts_at` timestamp
- `ends_at` timestamp
- `reason` string nullable
- `is_recurring` boolean default false
- `recurrence_rule` string nullable — iCal RRULE
- `created_by` uuid FK users
- `created_at`, `updated_at`

**Índices:** (stylist_id, starts_at, ends_at)

---

### 5. Client
**Tabla:** `clients`
- `id` uuid PK
- `first_name` string
- `last_name` string
- `phone` string — índice, campo principal de búsqueda
- `email` string nullable
- `cedula` string nullable — validar 10 dígitos módulo 10
- `birthday` date nullable
- `notes` text nullable — notas privadas del personal
- `allergies` text nullable — SIEMPRE mostrar en rojo si tiene
- `tags` json default [] — ["VIP", "frecuente"]
- `preferred_stylist_id` uuid nullable FK stylists
- `loyalty_points` integer default 0
- `total_spent` decimal(10,2) default 0
- `visit_count` integer default 0
- `last_visit_at` timestamp nullable
- `source` enum(walk_in, referral, instagram, whatsapp, website, other) default walk_in
- `is_active` boolean default true
- `created_at`, `updated_at`, `deleted_at`

**Índices:** phone (unique), email

**Relaciones:** hasMany(Appointment), hasMany(Sale), belongsTo(Stylist, 'preferred_stylist_id')

---

### 6. Appointment
**Tabla:** `appointments`
- `id` uuid PK
- `client_id` uuid FK clients
- `stylist_id` uuid FK stylists
- `service_id` uuid FK services
- `starts_at` timestamp — índice
- `ends_at` timestamp
- `status` enum(pending, confirmed, in_progress, completed, cancelled, no_show) default pending
- `source` enum(manual, online_booking, whatsapp, phone) default manual
- `notes` text nullable — visibles para el cliente
- `internal_notes` text nullable — solo staff
- `confirmed_at` timestamp nullable
- `reminder_sent_at` timestamp nullable
- `cancellation_reason` text nullable
- `cancelled_by` enum(client, staff) nullable
- `cancelled_at` timestamp nullable
- `created_by` uuid FK users
- `created_at`, `updated_at`, `deleted_at`

**Índices:** (stylist_id, starts_at), (client_id, starts_at), status

**Relaciones:** belongsTo(Client), belongsTo(Stylist), belongsTo(Service), hasOne(Sale)

---

### 7. Product
**Tabla:** `products`
- `id` uuid PK
- `name` string
- `sku` string nullable unique
- `barcode` string nullable
- `type` enum(use, sale) — uso interno vs venta al cliente
- `unit` enum(ml, g, oz, unit, liter, kg) default unit
- `cost_price` decimal(8,2) nullable
- `sale_price` decimal(8,2) nullable — solo si type=sale
- `stock` decimal(10,3) default 0
- `min_stock` decimal(10,3) default 0
- `supplier` string nullable
- `brand` string nullable
- `image_path` string nullable
- `is_active` boolean default true
- `created_at`, `updated_at`, `deleted_at`

**Relaciones:** hasMany(StockMovement), hasMany(SaleItem)

---

### 8. StockMovement
**Tabla:** `stock_movements`
- `id` uuid PK
- `product_id` uuid FK products
- `type` enum(purchase, consumption, adjustment, sale, initial)
- `quantity` decimal(10,3) — positivo=entrada, negativo=salida
- `unit_cost` decimal(8,2) nullable
- `reference_type` string nullable — morph: Appointment, Sale
- `reference_id` uuid nullable
- `notes` text nullable
- `created_by` uuid FK users
- `created_at`, `updated_at`

**Relaciones:** belongsTo(Product), morphTo(reference)

---

### 9. Sale
**Tabla:** `sales`
- `id` uuid PK
- `appointment_id` uuid nullable FK appointments
- `client_id` uuid nullable FK clients
- `subtotal` decimal(10,2)
- `discount_amount` decimal(10,2) default 0
- `discount_type` enum(percentage, fixed) nullable
- `discount_reason` string nullable
- `iva_rate` decimal(5,2) default 15 — IVA Ecuador 15%
- `iva_amount` decimal(10,2) default 0
- `total` decimal(10,2)
- `tip` decimal(10,2) default 0
- `tip_stylist_id` uuid nullable FK stylists
- `payment_methods` json — [{method: cash|transfer|card_debit|card_credit|other, amount: decimal}]
- `status` enum(draft, completed, refunded) default draft
- `sri_invoice_id` uuid nullable FK sri_invoices
- `notes` text nullable
- `completed_at` timestamp nullable
- `completed_by` uuid FK users
- `created_at`, `updated_at`, `deleted_at`

**Relaciones:** belongsTo(Appointment), belongsTo(Client), hasMany(SaleItem), belongsTo(SriInvoice), hasMany(Commission)

---

### 10. SaleItem
**Tabla:** `sale_items`
- `id` uuid PK
- `sale_id` uuid FK sales
- `type` enum(service, product)
- `reference_id` uuid — service_id o product_id
- `name` string — snapshot del nombre al momento de la venta
- `quantity` decimal(10,3) default 1
- `unit_price` decimal(10,2)
- `discount_amount` decimal(10,2) default 0
- `subtotal` decimal(10,2)
- `iva_rate` decimal(5,2) default 15
- `iva_amount` decimal(10,2) default 0
- `stylist_id` uuid nullable FK stylists
- `created_at`, `updated_at`

**Relaciones:** belongsTo(Sale), belongsTo(Stylist)

---

### 11. SriInvoice
**Tabla:** `sri_invoices`
- `id` uuid PK
- `sale_id` uuid FK sales
- `invoice_type` enum(invoice, credit_note, debit_note, sale_note, purchase_liquidation) default invoice
- `establishment` string(3) default '001'
- `emission_point` string(3) default '001'
- `sequential` string(9) — con ceros a la izquierda: 000000001
- `access_key` string(49) unique — índice, exactamente 49 dígitos
- `issue_date` date
- `environment` enum(test, production) default test
- `buyer_identification_type` enum(RUC, cedula, passport, final_consumer) default final_consumer
- `buyer_identification` string nullable
- `buyer_name` string nullable
- `buyer_email` string nullable
- `subtotal_0` decimal(10,2) default 0 — base imponible 0%
- `subtotal_iva` decimal(10,2) default 0 — base imponible con IVA
- `iva_rate` decimal(5,2) default 15
- `iva_amount` decimal(10,2) default 0
- `total` decimal(10,2)
- `xml_unsigned` longtext nullable
- `xml_signed` longtext nullable
- `ride_path` string nullable
- `sri_status` enum(draft, signed, sent, authorized, rejected, cancelled) default draft
- `sri_authorization_number` string(49) nullable
- `sri_authorization_date` timestamp nullable
- `sri_response` json nullable
- `error_message` text nullable
- `retry_count` integer default 0
- `next_retry_at` timestamp nullable
- `created_at`, `updated_at`

**Índices:** access_key (unique), sri_status, (establishment, emission_point, sequential)

---

### 12. Commission
**Tabla:** `commissions`
- `id` uuid PK
- `stylist_id` uuid FK stylists
- `sale_item_id` uuid FK sale_items
- `amount` decimal(10,2)
- `rate` decimal(5,2) — porcentaje aplicado
- `status` enum(pending, paid) default pending
- `period_start` date
- `period_end` date
- `paid_at` timestamp nullable
- `paid_by` uuid nullable FK users
- `notes` text nullable
- `created_at`, `updated_at`

---

### 13. Notification (para notificaciones internas del sistema)
**Tabla:** usar la tabla de Laravel Notifications (`notifications`) que ya viene incluida

Tipos de notificaciones a registrar:
- `new_online_booking` — nueva reserva desde el booking público
- `low_stock` — producto por debajo del stock mínimo
- `sri_invoice_rejected` — factura rechazada por el SRI
- `appointment_no_show` — cita marcada como no-show
- `pending_appointment` — cita pending desde hace 1h+

---

## Factories y Seeders

Crear Factory para cada modelo con datos en español y realistas:
- Nombres de clientes ecuatorianos
- Teléfonos con formato 09XXXXXXXX
- Servicios típicos de salón de belleza
- Precios en rango real ($5-$150)
- Fechas coherentes (appointments en los últimos 30 días)

**DatabaseSeeder.php** debe:
1. Crear las 7 categorías de servicio por defecto
2. Crear 8 servicios de ejemplo (2 por categoría principal)
3. Dejar el resto para el TenantSeeder (Sesión 1)

---

## Verificación al terminar esta sesión

- [ ] `php artisan migrate --path=database/migrations/tenant` corre sin errores
- [ ] `php artisan tenants:migrate` aplica todas las migraciones a la DB del tenant demo
- [ ] Todos los modelos tienen HasUuids, SoftDeletes, $fillable
- [ ] Las relaciones funcionan: `Client::first()->appointments` retorna colección
- [ ] Las factories generan datos válidos: `Client::factory(10)->create()`
- [ ] No hay errores de foreign key al hacer seed
