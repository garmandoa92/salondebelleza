# CLAUDE.md — SaaS Gestión de Salones de Belleza (Ecuador)

## Cómo trabajamos en este proyecto
Cada sesión de desarrollo tiene su archivo en la carpeta `docs/sesion-XX-nombre.md`.
Antes de empezar cualquier sesión, lee el archivo correspondiente con todos los detalles.
El flujo es: leer el archivo de sesión → implementar todo lo que dice → probar → commit.

## Stack técnico
- Backend: Laravel 13
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
- **Desarrollo**: identificación por path `/salon/{tenant}/...` en localhost:8000
- **Producción**: se puede cambiar a subdominio `{slug}.miapp.ec`
- El dominio central maneja: registro, login de dueños, billing
- Cada tenant tiene su propia DB aislada (tenant_{slug})
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

### Multitenancy
- No acceder a modelos tenant desde contexto central sin inicializar tenancy
- No usar config('database.default') directamente
- No mezclar lógica de tenant con lógica central

## Decisiones tecnicas importantes
- **Multitenancy por path** (no subdominios): URLs son `/salon/{tenant}/...` para funcionar en localhost sin configurar DNS. En produccion se puede cambiar a subdominios.
- **Tenant ID = slug** (no UUID): el ID del tenant es el slug (ej: "demo"), lo que genera DB names legibles como `tenant_demo`.
- **Timezone**: APP_TIMEZONE=America/Guayaquil. Las fechas se guardan en hora Ecuador en MySQL (no UTC).
- **FullCalendar events via axios**: no usar URL directa en events config (Inertia middleware interfiere). Usar funcion `fetchEvents` con axios.
- **Checkout modal resetea estado**: al cerrar y reabrir el modal de cobro, todos los campos se resetean via `watch(props.open)`.

## Estado del proyecto
**MVP + Fase 2-3 COMPLETO (Sesiones 1-11, 13)** — Todas las funcionalidades implementadas y verificadas. Sesion 12 (FideliaCard) saltada por decision del usuario.

## Índice de sesiones
| Sesión | Archivo | Estado | Descripción | Fase |
|--------|---------|--------|-------------|------|
| 0 | Este archivo (CLAUDE.md) | - | Contexto base del proyecto | - |
| 1 | docs/sesion-01-multitenant.md | DONE | Boilerplate multitenant + Auth | MVP |
| 2 | docs/sesion-02-modelos.md | DONE | Todos los modelos y migraciones | MVP |
| 3 | docs/sesion-03-servicios-estilistas.md | DONE | Catálogo servicios + Estilistas | MVP |
| 4 | docs/sesion-04-agenda.md | DONE | Agenda FullCalendar 2026 | MVP |
| 5 | docs/sesion-05-booking-crm.md | DONE | Booking público + CRM clientes | MVP |
| 6 | docs/sesion-06-caja-sri.md | DONE | Caja + Ventas + Facturación SRI | MVP |
| 7 | docs/sesion-07-inventario.md | DONE | Inventario de productos | MVP |
| 8 | docs/sesion-08-whatsapp.md | DONE | WhatsApp + Notificaciones | MVP |
| 9 | docs/sesion-09-comisiones-dashboard.md | DONE | Comisiones + Dashboard | MVP |
| 10 | docs/sesion-10-settings-billing.md | DONE | Settings + Billing Stripe | MVP |
| 11 | docs/sesion-11-reportes.md | DONE | Reportes y analítica | Fase 2 |
| 12 | docs/sesion-12-fideliacard.md | SKIPPED | Integración FideliaCard | Fase 2 |
| 13 | docs/sesion-13-multisucursal.md | DONE | Multi-sucursal | Fase 3 |
| 14 | docs/sesion-14-landing-page.md | PENDING | Landing page del SaaS | Post MVP |
| 15 | docs/sesion-15-onboarding.md | PENDING | Onboarding guiado | Post MVP |
| 16 | docs/sesion-16-paquetes.md | DONE | Paquetes y Bonos | Post MVP |
| 17a | docs/sesion-17a-impresion.md | DONE | Impresión térmica de tickets | Post MVP |
| 17b | docs/sesion-17b-anticipos.md | DONE | Anticipos y abonos | Post MVP |
| 17c | docs/sesion-17c-reportes-excel.md | DONE | Reportes Excel completos | Post MVP |
| 18 | docs/sesion-18-superadmin.md | PENDING | Panel de superadmin | Post MVP |
| 19  | docs/sesion-19-fotos.md       | DONE | Módulo de fotos antes/después | Post MVP |
| 19b | docs/sesion-19b-garantias.md  | Módulo de garantías           | Post MVP |
| 19c | docs/sesion-19c-historial-mejorado.md | DONE | Historial con diagnostico y fotos | Post MVP |

## Modulos implementados
- **Auth**: registro salon, login central -> redirect a tenant, login tenant con token cross-domain
- **Servicios**: CRUD categorias + servicios, toggle activo, receta de productos
- **Estilistas**: CRUD, horario semanal doble franja, comisiones por categoria, bloqueos
- **Agenda**: FullCalendar resourceTimeGridDay, drag&drop, tooltips tippy.js, drawer detalle, modal 4 pasos
- **Booking publico**: /reservar sin auth, 4 pasos, crea cliente automaticamente
- **CRM Clientes**: tabla con busqueda/filtros, ficha completa con historial/compras/citas
- **Caja/Ventas**: checkout modal con items, descuentos, IVA 15%, metodos de pago, vuelto
- **Facturacion SRI**: motor completo (XML, clave acceso mod11, firma stub, SOAP, RIDE HTML), historial con drawer
- **Inventario**: CRUD productos, compras, ajustes, movimientos, indicadores stock
- **WhatsApp**: 360dialog API, jobs confirmacion/recordatorio/factura, normalizacion telefono Ecuador
- **Notificaciones**: dropdown campana en topbar, polling 60s, mark read
- **Comisiones**: calculo automatico, resumen por periodo, detalle por estilista, pago batch
- **Dashboard**: KPIs hoy vs ayer, agenda del dia, metricas mes, alertas, accesos rapidos
- **Settings**: 6 tabs (salon, SRI, reservas, WhatsApp, equipo, suscripcion)
- **Reportes**: 8 secciones (KPIs, ingresos, servicios, heatmap demanda, estilistas, retencion, forecast, inventario)
- **Multi-sucursal**: branches CRUD, branch_stylist pivot, branch_id en appointments/sales, plan validation
- **Paquetes y Bonos**: Package (sessions/combo), ClientPackage, venta en checkout, descuento al completar cita
- **Impresion Termica**: PrintService genera HTML para 80mm/58mm, tickets de venta/cita/cierre de caja/comisiones, botones en checkout/drawer/ventas
- **Anticipos y Abonos**: ClientAdvance model, registro desde agenda/ficha cliente, aplicacion automatica en checkout, saldo a favor, panel dashboard
- **Reportes Excel**: maatwebsite/excel, 7 exports (ventas, P&L, citas, clientes, comisiones, inventario, flujo caja) con hojas multiples y estilos
- **Fotos Antes/Despues**: AppointmentPhoto model, subida con compresion, thumbnails GD, lightbox, tab Fotos en ficha cliente, foto perfil cliente

<!-- MEMORY:START -->
# salondebelleza

_Last updated: 2026-04-06 | 0 active memories, 0 total_

_For deeper context, use memory_search, memory_related, or memory_ask tools._
<!-- MEMORY:END -->
