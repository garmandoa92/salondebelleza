# Sesión 18 — Panel de Superadmin

**Duración estimada:** 3-4 días  
**Semana:** Post MVP  
**Dependencias:** Sesiones 1-13 completadas

---

## Objetivo
Tú como dueño del SaaS necesitas ver y gestionar todo desde un solo lugar. El superadmin es tu panel de control del negocio: cuánto estás ganando, quién está pagando, quién está a punto de cancelar, y acceso directo a cualquier tenant para dar soporte.

---

## Acceso

URL exclusiva en el dominio central:
```
http://miapp.ec/superadmin
```

Protegido por:
- Middleware `SuperAdminMiddleware` — solo usuarios con role `superadmin`
- IP whitelist opcional (tu IP fija)
- 2FA obligatorio

Crear usuario superadmin desde CLI:
```bash
php artisan superadmin:create {email} {password}
```

---

## SuperAdminController

```
GET /superadmin                    → Dashboard principal
GET /superadmin/tenants            → Lista de todos los tenants
GET /superadmin/tenants/{id}       → Detalle de un tenant
POST /superadmin/tenants/{id}/impersonate → Acceder como ese tenant
POST /superadmin/tenants/{id}/extend-trial → Extender trial
POST /superadmin/tenants/{id}/activate    → Activar tenant
POST /superadmin/tenants/{id}/suspend     → Suspender tenant
GET /superadmin/revenue            → Reportes de ingresos
GET /superadmin/metrics            → Métricas globales
GET /superadmin/demo-requests      → Solicitudes de demo
```

---

## Pages/Superadmin/Dashboard.vue

### KPIs principales (cards grandes)

```
MRR actual          Tenants activos     Trial activos      Churn del mes
$X,XXX/mes          XXX                 XXX                X%
vs mes anterior     +X este mes         X vencen esta sem  vs mes anterior
```

### Gráfico de MRR (últimos 12 meses)
Línea de ingresos mensuales con Chart.js.
Mostrar: MRR nuevo + expansión - cancelaciones = MRR neto.

### Actividad reciente
```
Timeline de los últimos eventos:
- "Salón Valeria se registró hace 2 horas"
- "Beauty Center pagó $29 hace 3 horas"  
- "Salón Glamour canceló su suscripción hace 1 día"
- "Demo solicitada por María González hace 2 días"
```

### Alertas urgentes
```
🔴 X tenants con pago fallido
🟡 X trials vencen esta semana (llamarlos)
🟡 X facturas SRI rechazadas sin resolver
```

---

## Pages/Superadmin/Tenants/Index.vue

### Tabla de todos los tenants

Columnas:
```
Salón | Plan | Estado | MRR | Citas/mes | Creado | Trial vence | Acciones
```

Filtros:
```
[Todos] [Trial] [Activos] [Suspendidos] [Cancelados]
[Plan: Básico / Pro / Cadena]
[Creado: esta semana / este mes / este año]
```

Búsqueda: por nombre del salón, email del dueño, slug.

Indicadores visuales:
- Verde: suscripción activa y pagando
- Amarillo: en trial (con días restantes)
- Naranja: pago fallido (en período de gracia)
- Rojo: suspendido

### Acciones rápidas por tenant (dropdown)
```
→ Ver detalle completo
→ Acceder como este salón (impersonar)
→ Extender trial X días
→ Cambiar plan
→ Suspender acceso
→ Reactivar acceso
→ Ver facturas de Stripe
→ Enviar email al dueño
```

---

## Pages/Superadmin/Tenants/Show.vue

### Detalle completo de un tenant

**Header:**
- Nombre del salón + logo
- Plan actual + estado
- Dueño: nombre + email + teléfono
- Fecha de registro
- Botón "Acceder como este salón" (impersonar)

**Métricas del tenant:**
```
Citas totales      Clientes            Facturas SRI       MRR
XXX                XXX                 XXX (X rechazadas)  $XX/mes
```

**Actividad reciente del tenant:**
- Últimas 10 acciones (citas creadas, ventas, facturas)
- Último login: hace X horas/días

**Historial de pagos:**
- Tabla con todas las facturas de Stripe
- Estado: pagada / fallida / reembolsada

**Notas internas (solo superadmin):**
- Textarea para agregar notas sobre el tenant
- "Llamé al dueño el 15/04, está interesado en plan Cadena"
- "Tiene problemas con el certificado SRI"

---

## Impersonación de tenants

Cuando el superadmin hace click en "Acceder como este salón":

1. Guarda en session: `impersonating_tenant_id` + `original_user_id`
2. Inicializa el contexto del tenant
3. Redirige al dashboard del salón
4. Muestra banner amarillo en la parte superior:
   ```
   ⚠️ Estás viendo el panel de "Salón Valeria" como superadmin
   [Volver a superadmin]
   ```
5. Puede hacer cualquier acción como si fuera el dueño
6. Al click "Volver": destruye la impersonación y regresa al superadmin

---

## Pages/Superadmin/Revenue.vue

### Reporte de ingresos

**Gráfico principal:**
MRR por mes en los últimos 12 meses con desglose:
- MRR Básico (azul)
- MRR Profesional (rosa)
- MRR Cadena (morado)

**Métricas de crecimiento:**
```
MRR actual:         $X,XXX
MRR hace 3 meses:   $X,XXX
Crecimiento 3m:     +XX%

ARR proyectado:     $XX,XXX
LTV promedio:       $XXX (MRR / churn rate)
CAC estimado:       $XX (si tienes datos de ads)
```

**Tabla de ingresos por plan:**
```
Plan       | Tenants | MRR      | % del total
Básico     | XX      | $XXX     | XX%
Profesional| XX      | $X,XXX   | XX%
Cadena     | XX      | $XXX     | XX%
Total      | XXX     | $X,XXX   | 100%
```

**Churn:**
```
Tenants que cancelaron este mes: X
MRR perdido: $XXX
Razones (si las capturaste en cancelación):
- "Muy caro": X
- "No lo usaba": X  
- "Prefiero otro sistema": X
```

---

## Pages/Superadmin/DemoRequests.vue

Lista de solicitudes de demo enviadas desde la landing:

```
Nombre | Salón | Teléfono | Ciudad | Estilistas | Recibido | Estado | Acciones
```

Estados:
- Nuevo (rojo) — no contactado
- Contactado (amarillo) — en proceso
- Convertido (verde) — se registró
- No interesado (gris) — descartado

Acciones:
- Marcar como contactado
- Marcar como convertido (buscar el tenant)
- Agregar nota
- Abrir WhatsApp directo

---

## Métricas globales de uso

```
GET /superadmin/metrics → JSON con:
  - Total de citas creadas hoy / esta semana / este mes
  - Total de facturas SRI generadas
  - Total de mensajes WhatsApp enviados
  - Storage usado en S3
  - Tenants más activos (por citas)
  - Tenants menos activos (en riesgo de churn)
  - Features más usados
  - Features menos usados (candidatos a eliminar)
```

---

## Alertas automáticas al superadmin

Jobs schedulados que envían WhatsApp/email al superadmin:

**Diario a las 8am:**
```
"Resumen del día:
- X nuevos registros ayer
- MRR actual: $X,XXX
- X trials vencen esta semana
- X pagos fallidos pendientes"
```

**Inmediato cuando:**
- Un tenant se registra (nuevo lead caliente)
- Un tenant cancela su suscripción
- Un pago falla por segunda vez
- Un tenant lleva 7 días sin crear ninguna cita (en riesgo)

---

## Verificación al terminar esta sesión

- [ ] Solo el superadmin puede acceder a /superadmin
- [ ] El dashboard muestra MRR real desde Stripe
- [ ] La lista de tenants tiene todos los filtros funcionando
- [ ] La impersonación funciona y muestra el banner amarillo
- [ ] Al salir de impersonación vuelve al superadmin correctamente
- [ ] El reporte de ingresos muestra datos reales de Stripe
- [ ] Las solicitudes de demo aparecen y se pueden gestionar
- [ ] Las alertas automáticas llegan por WhatsApp/email
- [ ] El comando php artisan superadmin:create funciona
