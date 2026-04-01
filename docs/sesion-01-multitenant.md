# Sesión 1 — Boilerplate Multitenant + Auth

**Duración estimada:** 3-4 días  
**Semana:** 1  
**Dependencias:** Ninguna — es la base de todo

---

## Objetivo de esta sesión
Construir el boilerplate completo con multitenancy funcional, sistema de autenticación en dos niveles (dominio central + subdominios de tenant), y estructura de planes de suscripción.

Esta es la sesión más crítica del proyecto. Una arquitectura multitenant correcta desde el inicio evita una reescritura total más adelante.

---

## 1. Instalación y configuración inicial

```bash
composer create-project laravel/laravel salon-saas
cd salon-saas
composer require stancl/tenancy
composer require spatie/laravel-permission
composer require laravel/cashier
composer require laravel/breeze
php artisan breeze:install
php artisan tenancy:install
```

---

## 2. Modelo Tenant (DB central — no en tenant)

**Tabla:** `tenants`

Campos:
- `id` uuid PK
- `name` string — nombre del salón
- `slug` string unique — para subdominio: salon1.miapp.test
- `ruc` string nullable — RUC SRI Ecuador (13 dígitos)
- `razon_social` string nullable
- `phone` string
- `address` text nullable
- `logo_path` string nullable
- `plan_id` uuid FK a plans
- `trial_ends_at` timestamp nullable
- `settings` json — timezone, moneda, config general, fideliacard_enabled, etc.
- `is_active` boolean default true
- `created_at`, `updated_at`, `deleted_at` (SoftDeletes)

El modelo Tenant debe implementar `HasDatabase` y `HasDomains` de stancl/tenancy.

---

## 3. Modelo Plan (DB central)

**Tabla:** `plans`

Campos:
- `id` uuid PK
- `name` string — Básico, Profesional, Cadena
- `slug` string unique — basico, profesional, cadena
- `price` decimal(8,2)
- `billing_cycle` enum(monthly, yearly)
- `max_stylists` integer — -1 = ilimitado
- `max_branches` integer — -1 = ilimitado
- `features` json — lista de features habilitados por plan
- `is_active` boolean default true
- `stripe_price_id` string nullable

**Seeder de planes:**
```
Básico:       $15/mes, 2 estilistas, 1 sucursal
Profesional:  $29/mes, 8 estilistas, 1 sucursal  ← plan por defecto en trial
Cadena:       $59/mes, -1 estilistas, -1 sucursales
```

---

## 4. Auth del dueño del salón (DB central)

**Tabla:** `tenant_users` (en DB central)

Campos:
- `id` uuid PK
- `tenant_id` uuid FK
- `name` string
- `email` string unique
- `password` string hashed
- `role` enum(owner, admin)
- `remember_token` string nullable
- `email_verified_at` timestamp nullable
- `last_login_at` timestamp nullable
- `created_at`, `updated_at`

**Flujo de registro:**
1. Usuario llena: nombre del salón, slug, email, password, teléfono
2. Sistema valida que el slug no existe
3. Crea el Tenant en DB central
4. Crea el TenantUser con role=owner
5. Crea la base de datos del tenant automáticamente (stancl/tenancy)
6. Corre las migraciones del tenant
7. Activa trial de 30 días en plan Profesional
8. Redirige al subdominio: http://{slug}.miapp.test/dashboard

**Flujo de login (dominio central):**
1. Email + password
2. Busca en tenant_users
3. Si válido → redirige a http://{tenant.slug}.miapp.test/dashboard

---

## 5. Auth dentro del tenant (DB del tenant)

**Tabla:** `users` (en cada DB de tenant)

Campos:
- `id` uuid PK
- `name` string
- `email` string unique
- `password` string hashed
- `role` — gestionado por Spatie Permissions
- `stylist_id` uuid nullable FK (si el user también es estilista)
- `is_active` boolean default true
- `last_login_at` timestamp nullable
- `created_at`, `updated_at`, `deleted_at`

**Roles de Spatie dentro del tenant:**
- `owner` — acceso total
- `admin` — acceso total excepto billing
- `receptionist` — agenda, clientes, cobros (sin reportes financieros)
- `stylist` — solo su propia agenda y clientes

---

## 6. Layouts Inertia (resources/js/Layouts/)

**AppLayout.vue** — layout principal del salón
- Sidebar izquierdo colapsible (260px)
- Topbar con: nombre del salón, usuario actual, notificaciones, logout
- Navegación: Dashboard, Agenda, Clientes, Servicios, Inventario, Reportes, Configuración
- Badge de notificaciones en el ícono de campana
- Responsive: en móvil el sidebar se convierte en bottom navigation

**AuthLayout.vue** — para login y registro
- Centrado vertical y horizontal
- Sin sidebar
- Logo del producto

**PublicLayout.vue** — para el booking público del salón
- Sin autenticación requerida
- Logo y colores personalizables del salón
- Mobile-first

---

## 7. Middleware necesario

**TenantMiddleware:**
- Detecta el tenant por subdominio
- Inicializa el contexto de stancl/tenancy
- Si el subdominio no existe: 404

**EnsureTenantIsActive:**
- Verifica que el tenant tiene trial activo O suscripción activa en Stripe
- Si no: redirige a /upgrade

**RedirectIfTrialExpired:**
- Si el trial expiró y no hay suscripción activa: página de upgrade bloqueante
- Excepciones: rutas de billing, logout, upgrade

**BranchAccessMiddleware (para Fase 3):**
- Verifica que el usuario tiene acceso a la sucursal del request
- Preparar la estructura ahora aunque la lógica completa va en Sesión 13

---

## 8. Comandos Artisan de utilidad

```bash
# Crear tenant desde CLI (para desarrollo)
php artisan tenant:create {name} {slug} {email} {plan?}

# Listar todos los tenants
php artisan tenant:list

# Correr migraciones en todos los tenants
php artisan tenants:migrate

# Correr seeders en un tenant específico
php artisan tenant:seed {slug}
```

---

## 9. TenantSeeder — datos de prueba

Crear un tenant de prueba llamado "Salón Demo" con slug "demo" que incluya:
- 3 estilistas con horarios configurados
- 10 servicios en 4 categorías
- 20 clientes con historial
- 30 citas de los últimos 30 días (completadas, canceladas, no-shows)
- 5 citas futuras confirmadas

---

## 10. Configuración de subdominios locales

En `config/tenancy.php`:
```php
'central_domains' => [
    'miapp.test',
],
'tenant_route_prefix' => null,
```

En `.env`:
```
APP_URL=http://miapp.test
TENANCY_CENTRAL_DOMAIN=miapp.test
```

---

## Tests requeridos

```php
// tests/Feature/TenantRegistrationTest.php
test('puede registrar un nuevo salón y crear su base de datos')
test('el slug debe ser único')
test('el trial de 30 días se activa automáticamente al registrarse')
test('después del registro redirige al subdominio correcto')
test('el login en el dominio central redirige al subdominio del tenant')
test('no puede acceder al subdominio de otro tenant')
```

---

## Verificación al terminar esta sesión

Antes de pasar a la Sesión 2, verificar que:
- [ ] `http://miapp.test/register` muestra el formulario de registro
- [ ] Al registrar un salón se crea la DB `tenant_{slug}` automáticamente
- [ ] `http://demo.miapp.test/dashboard` carga con el layout correcto
- [ ] El login en `http://miapp.test/login` redirige al subdominio
- [ ] Los roles de Spatie están creados dentro del tenant
- [ ] `php artisan tenant:create` funciona desde CLI
- [ ] Todos los tests pasan
