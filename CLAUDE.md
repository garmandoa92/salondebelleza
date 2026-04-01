# CLAUDE.md — SaaS Gestión de Salones de Belleza (Ecuador)

## Cómo trabajamos en este proyecto
Cada sesión de desarrollo tiene su archivo en la carpeta `docs/sesion-XX-nombre.md`.
Antes de empezar cualquier sesión, lee el archivo correspondiente con todos los detalles.
El flujo es: leer el archivo de sesión → implementar todo lo que dice → probar → commit.

## Stack técnico
- Backend: Laravel 11
- Frontend: Vue 3 + Inertia.js + Vite
- UI: Tailwind CSS + shadcn-vue
- Base de datos: MySQL 8 / MariaDB 10.6
- Cache/Queue: Redis (Laravel Horizon para jobs)
- Multitenancy: stancl/tenancy v3 (base de datos SEPARADA por tenant)
- Autenticación: Laravel Breeze + Spatie Permissions
- Storage: S3-compatible (MinIO local, Hetzner Object Storage en producción)
- Email: Resend
- WhatsApp: 360dialog Business API
- Suscripciones: Stripe + Laravel Cashier
- Gráficos: Chart.js v4
- Calendario: @fullcalendar/vue3 v6 con scheduler

## Convenciones de naming
- Modelos en singular PascalCase: Appointment, Client, Stylist, Sale
- Tablas en plural snake_case: appointments, clients, stylists, sales
- Controllers: resourceful siempre: AppointmentController
- Jobs: verbos en pasado: SendAppointmentReminderJob, ProcessSriDocumentJob
- Eventos: AppointmentCreated, SaleCompleted, InvoiceAuthorized
- Enums: app/Enums/ con PHP Enums nativos de Laravel 11
- Services: app/Services/ con una clase por dominio

## Estructura de carpetas
```
app/
  Http/Controllers/        # Resourceful controllers — solo validan y llaman Services
  Models/                  # Eloquent models
  Services/                # Toda la lógica de negocio
  Services/Sri/            # Motor SRI: SriXmlGenerator, SriSignatureService, etc.
  Jobs/                    # Queue jobs async
  Events/ + Listeners/     # Event-driven
  Enums/                   # PHP Enums nativos
  DTOs/                    # Data Transfer Objects cuando aplica

resources/js/
  Pages/           # Inertia pages (páginas completas)
  Components/      # Componentes reutilizables
  Layouts/         # AppLayout, AuthLayout, PublicLayout
  Composables/     # useAppointment, useClient, useSale
```

## Reglas de arquitectura — NUNCA violar estas reglas
- NUNCA usar Livewire — solo Vue 3 + Inertia
- NUNCA poner lógica de negocio en Controllers — siempre en Services
- NUNCA hacer HTTP calls síncronas al SRI desde el request — siempre Queue
- NUNCA mezclar lógica de negocio en Blade/Vue — solo props de Inertia
- NUNCA usar $guarded = [] en modelos — siempre $fillable explícito
- NUNCA copiar código de otros proyectos (FacturaSmart, etc.) — todo desde cero
- Controllers solo hacen: validar request → llamar Service → return Inertia response
- Siempre usar Form Requests para validación
- Todos los IDs son UUID (HasUuids trait en todos los modelos)
- Soft deletes en todas las tablas principales (SoftDeletes trait)

## Multitenancy — CRÍTICO
- Usamos stancl/tenancy v3 con base de datos SEPARADA por tenant
- El tenant = el salón de belleza completo
- Cada tenant tiene su propio subdominio: {slug}.miapp.test (local) / {slug}.miapp.ec (prod)
- El dominio central maneja: registro, login de dueños, billing
- Los subdominios corren la app del salón con su propia DB aislada
- NUNCA hacer queries cross-tenant
- Los jobs en contexto tenant DEBEN usar WithTenantContext trait
- Modelos centrales (fuera de tenant): Tenant, Plan, TenantUser, Subscription
- Helper global tenant() retorna el Tenant actual en cualquier contexto
- NUNCA hardcodear tenant_id — siempre usar tenant()->id

## Facturación SRI Ecuador — CRÍTICO
- TODO el motor SRI se construye desde cero en este proyecto
- NO copiar, NO adaptar, NO referenciar ningún otro proyecto
- IVA vigente en Ecuador: 15% (desde abril 2024, código 4 en catálogo SRI)
- Tipos de comprobantes: factura (01), nota de venta (03), nota de crédito (04)
- Clave de acceso: exactamente 49 dígitos con dígito verificador módulo 11
- Transmisión en tiempo real al SRI (obligatorio desde enero 2026)
- Ambiente de pruebas: celcer.sri.gob.ec
- Ambiente de producción: cel.sri.gob.ec
- Almacenar XML firmado y RIDE por mínimo 7 años en S3
- Soportar regímenes: RIMPE Negocio Popular, RIMPE Emprendedor, Régimen General

## Lo que NO hacer — nunca
- No usar array_push → usar collect()
- No queries N+1 → siempre eager loading con with()
- No lógica en vistas Vue → en composables o Services
- No hardcodear strings de configuración → usar config() o tenant()->settings
- No hacer llamadas API síncronas que puedan fallar → siempre Queue con retry
### Laravel
- No usar $guarded = [] → siempre $fillable explícito en modelos
- No queries N+1 → siempre eager loading con with()
- No lógica en Controllers → siempre en Services
- No hardcodear config → usar config() o tenant()->settings
- No llamadas síncronas que puedan fallar → siempre Queue con retry
- No asumir que el tenant existe → verificar con middleware
- No usar array_push → usar collect()
- No paginar con skip/take manual → usar paginate(25)
- No queries dentro de loops → preparar datos antes del loop
- No usar DB::statement() en migraciones → usar Schema builder
- No usar firstOrCreate() en concurrencia → DB::transaction()
- No olvidar WithTenantContext en jobs de tenant
- No cachear sin TTL → siempre definir expiración
- No cachear cross-tenant → incluir tenant()->id en la cache key
- No loguear datos sensibles → nunca Log::info() con passwords o cédulas
- No almacenar el .p12 sin encriptar → Crypt::encrypt() siempre

### Vue / Frontend
- No lógica de negocio en componentes → en composables
- No axios calls directamente en componentes → en composables
- No usar v-html con datos del usuario → riesgo XSS
- No mutar props directamente → emit o estado propio
- No olvidar loading + error en cada llamada async → siempre los dos estados

### SRI Ecuador
- No redondear con round() → usar bcmath: bcadd(), bcmul(), bcdiv()
- No generar clave de acceso sin verificar que no existe en DB
- No enviar XML sin validar estructura primero
- No asumir respuesta del SRI en menos de 30 segundos → timeout explícito
- No perder el XML firmado → guardar en DB antes de enviar al SRI

## Índice de sesiones
| Sesión | Archivo | Descripción | Fase |
|--------|---------|-------------|------|
| 0 | Este archivo (CLAUDE.md) | Contexto base del proyecto | - |
| 1 | docs/sesion-01-multitenant.md | Boilerplate multitenant + Auth | MVP |
| 2 | docs/sesion-02-modelos.md | Todos los modelos y migraciones | MVP |
| 3 | docs/sesion-03-servicios-estilistas.md | Catálogo servicios + Estilistas | MVP |
| 4 | docs/sesion-04-agenda.md | Agenda FullCalendar 2026 | MVP |
| 5 | docs/sesion-05-booking-crm.md | Booking público + CRM clientes | MVP |
| 6 | docs/sesion-06-caja-sri.md | Caja + Ventas + Facturación SRI | MVP |
| 7 | docs/sesion-07-inventario.md | Inventario de productos | MVP |
| 8 | docs/sesion-08-whatsapp.md | WhatsApp + Notificaciones | MVP |
| 9 | docs/sesion-09-comisiones-dashboard.md | Comisiones + Dashboard | MVP |
| 10 | docs/sesion-10-settings-billing.md | Settings + Billing Stripe | MVP |
| 11 | docs/sesion-11-reportes.md | Reportes y analítica | Fase 2 |
| 12 | docs/sesion-12-fideliacard.md | Integración FideliaCard | Fase 2 |
| 13 | docs/sesion-13-multisucursal.md | Multi-sucursal | Fase 3 |

<!-- MEMORY:START -->
# salondebelleza

_Last updated: 2026-04-01 | 0 active memories, 0 total_

_For deeper context, use memory_search, memory_related, or memory_ask tools._
<!-- MEMORY:END -->
