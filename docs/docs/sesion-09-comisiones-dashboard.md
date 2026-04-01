# Sesión 9 — Comisiones + Dashboard

**Duración estimada:** 2-3 días  
**Semana:** Semana 8-9  
**Dependencias:** Sesiones anteriores completadas  

---

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Los modelos Commission, Sale, SaleItem, Stylist ya existen.
Las comisiones se crean automáticamente al completar una venta. Falta la gestión y liquidación.

## CommissionService (app/Services/CommissionService.php)
calculateForSale(Sale $sale): void
  - Por cada SaleItem de la venta con stylist_id:
  - Obtiene la regla de comisión del estilista:
    1. Busca en commission_rules['by_category'][category_id]
    2. Si no hay, usa commission_rules['default']
    3. Si no hay default, usa 0
  - Crea Commission record con el porcentaje aplicado y el monto calculado
  - El monto = saleItem.subtotal * rate / 100

## CommissionController

GET /commissions                → Dashboard de comisiones
GET /commissions/summary        → Resumen del período para todos los estilistas
POST /commissions/close-period  → Cerrar período y marcar comisiones como listas para pago
POST /commissions/pay           → Marcar un batch de comisiones como pagadas
GET /commissions/stylist/{id}   → Detalle de comisiones de un estilista

### Pages/Commissions/Index.vue
- Selector de período: quincenal (1-15, 16-fin de mes) o mensual
- Tabla resumen por estilista:
  Nombre | Servicios | Total vendido | % promedio | Comisión a pagar | Estado
- Estado del período: abierto | cerrado | pagado
- Al cerrar período: genera un resumen en PDF descargable por estilista
- Al marcar como pagado: registra la fecha de pago

### Pages/Commissions/Stylist.vue (detalle de un estilista)
- Cabecera: nombre, período, total a pagar
- Tabla de servicios:
  Fecha | Cliente | Servicio | Precio | % Comisión | Monto comisión
- Desglose por categoría de servicio
- Botón "Descargar liquidación" (PDF con firma del estilista placeholder)`, PURPLE),

  ...spacer(1),
  

---

## Parte B — Dashboard Principal

Construyo el DASHBOARD principal del salón (Pages/Dashboard/Index.vue).
Esta es la primera pantalla que ve el dueño al entrar al sistema.

## DashboardController
GET /dashboard → Retorna todos los datos del dashboard en una sola query optimizada

Datos necesarios (todos para "hoy" por default, con comparativo vs. ayer):

### KPIs principales (cards grandes arriba)
- Ingresos del día: total de ventas completadas hoy vs. ayer (% diferencia)
- Citas del día: total / completadas / pendientes / canceladas
- Clientes atendidos hoy: únicos
- Tasa de ocupación: (horas con citas / horas laborales totales de todos los estilistas) %

### Agenda de hoy (panel central)
- Timeline compacto de las citas de hoy agrupadas por estilista
- Indicador visual de la hora actual
- Quick actions: confirmar cita pendiente con un click

### Métricas del mes (panel derecho)
- Ingresos del mes vs. mes anterior (mini gráfico de línea, Chart.js)
- Servicios más vendidos este mes (top 5, gráfico de barras horizontal)
- Estilista con más ventas este mes

### Alertas activas (panel inferior)
- Stock bajo (lista de productos)
- Facturas rechazadas por SRI pendientes de retry
- Citas de hace 1h+ sin confirmar (posible no-show)
- Clientes sin visita en 60+ días (si hay campaña activa)

### Accesos rápidos
- + Nueva cita
- + Nuevo cliente
- Ir a agenda de hoy
- Ver caja del día

El dashboard debe cargar en < 1 segundo. Usa eager loading agresivo y
considera cachear los datos del dashboard por 5 minutos en Redis.

---

## Verificación

- [ ] Las comisiones se calculan correctamente al completar una venta
- [ ] El cierre de período genera el PDF de liquidación por estilista
- [ ] El dashboard carga en menos de 1 segundo con 50+ citas
- [ ] Los KPIs muestran comparativo vs período anterior
