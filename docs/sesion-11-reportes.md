# Sesión 11 — Reportes & Analítica

**Duración estimada:** 3-4 días  
**Semana:** Semana 13-15  
**Dependencias:** Sesiones anteriores completadas  

---

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Fase 1 completamente terminada: agenda, cobros, SRI, inventario, WhatsApp, comisiones.

Construyo el modulo completo de REPORTES Y ANALITICA.
Este modulo es el que hace que la duena del salon no pueda imaginar su negocio sin el sistema.

=============================================================
ReportService.php — Motor de reportes (app/Services/ReportService.php)
=============================================================

Construir desde cero. Todos los calculos ocurren aqui, nunca en controllers.
Cada metodo acepta un DateRange $period y retorna datos estructurados.

Metodos principales:

getRevenueReport(DateRange $period): array
  - Total de ingresos del periodo
  - Desglose por dia (para el grafico de linea)
  - Comparativo con el periodo anterior exacto (mismo numero de dias)
  - Porcentaje de crecimiento o caida vs periodo anterior
  - Ingresos por metodo de pago (efectivo, transferencia, tarjeta)
  - Ingresos por tipo (servicios vs productos)
  - Ticket promedio del periodo

getServicesReport(DateRange $period): array
  - Servicios mas vendidos (ranking por cantidad)
  - Servicios mas rentables (ranking por ingreso total)
  - Margen neto por servicio: (precio_servicio - costo_productos_consumidos) / precio_servicio * 100
  - Tiempo promedio real vs tiempo estimado por servicio
  - Servicios con mayor tasa de no-show

getStylistsReport(DateRange $period): array
  - Ranking de estilistas por: ingresos, servicios, ticket promedio
  - Horas trabajadas vs horas facturadas por estilista
  - Tasa de ocupacion individual: (citas completadas / slots disponibles) %
  - Crecimiento de cada estilista vs periodo anterior
  - Servicios mas realizados por cada estilista
  - Retención de clientes por estilista (clientes que vuelven a pedir el mismo)

getClientsReport(DateRange $period): array
  - Nuevos clientes del periodo
  - Clientes recurrentes (que ya habian venido antes del periodo)
  - Tasa de retención: clientes que vinieron en el periodo anterior y volvieron en este
  - Churn: clientes que vinieron hace 60+ dias y NO han vuelto
  - Valor de vida del cliente (LTV): gasto promedio * frecuencia de visita * meses activo
  - Top 10 clientes por gasto del periodo
  - De donde vienen los clientes nuevos (por campo source)

getDemandReport(DateRange $period): array
  - Horas pico: mapa de calor horas (7am-10pm) x dias (Lun-Dom) con intensidad de citas
  - Dias de mayor demanda del mes (ranking)
  - Tasa de ocupacion por dia de semana
  - Duracion promedio de las citas vs duracion estimada
  - Tasa de cancelacion y no-show por dia y por hora

getInventoryReport(DateRange $period): array
  - Productos mas consumidos en servicios
  - Productos mas vendidos al cliente
  - Rotacion de inventario: consumo_periodo / stock_promedio
  - Productos sin movimiento en el periodo
  - Costo de materiales como % de los ingresos

getForecast(): array
  - Citas confirmadas para los proximos 7 dias (ya agendadas)
  - Forecast de ingresos proximos 7 dias basado en citas confirmadas
  - Dias con baja ocupacion proyectada (donde recomendar promociones)
  - Proyeccion de cierre del mes: ingresos actuales + forecast hasta fin de mes
  Algoritmo de forecast:
    Para cada dia futuro sin citas: usar el promedio historico de ese dia de semana
    Para dias con citas: sumar el valor de citas confirmadas
    + estimacion de walk-ins basada en historico

=============================================================
ReportController — Endpoints
=============================================================

GET /reports                    → Vista principal de reportes (Inertia)
GET /reports/revenue            → Datos de ingresos (JSON para Charts)
GET /reports/services           → Datos de servicios
GET /reports/stylists           → Datos de estilistas
GET /reports/clients            → Datos de clientes
GET /reports/demand             → Mapa de calor y horas pico
GET /reports/inventory          → Reporte de inventario
GET /reports/forecast           → Forecast proximos 7 dias
GET /reports/export/{type}      → Exportar a Excel (revenue|services|stylists|clients)

Todos los endpoints aceptan query params:
  period: today|yesterday|last7|last30|this_month|last_month|custom
  date_from: YYYY-MM-DD (si period=custom)
  date_to: YYYY-MM-DD (si period=custom)

Cache en Redis: reports:{tenant_id}:{endpoint}:{period_hash} TTL: 10 minutos
Invalidar cache cuando se completa una venta o se modifica una cita.

=============================================================
Pages/Reports/Index.vue — Dashboard de reportes
=============================================================

Selector de periodo prominente en la parte superior:
  [Hoy] [Ayer] [Últimos 7 días] [Últimos 30 días] [Este mes] [Mes anterior] [Personalizado]
  Al cambiar: todos los graficos y numeros se actualizan simultaneamente
  Comparativo visible: "vs. periodo anterior: +12% ↑" o "-8% ↓" en rojo/verde

=============================================================
SECCION 1 — KPIs principales (4 cards grandes)
=============================================================

Card 1 — Ingresos del periodo:
  Numero grande: $XX,XXX.XX
  Comparativo: vs periodo anterior con flecha y porcentaje (verde si sube, rojo si baja)
  Sub-dato: ticket promedio del periodo

Card 2 — Citas del periodo:
  Numero grande: XXX citas
  Desglose pequeño: X completadas | X canceladas | X no-show
  Tasa de completitud: XX%

Card 3 — Clientes atendidos:
  Numero grande: XXX clientes unicos
  Nuevos vs recurrentes: XX nuevos | XX recurrentes
  Tasa de retención: XX%

Card 4 — Tasa de ocupacion:
  Numero grande: XX%
  Descripcion: horas facturadas / horas disponibles totales
  Comparativo vs periodo anterior

=============================================================
SECCION 2 — Grafico de ingresos (linea temporal)
=============================================================

Grafico de linea con Chart.js:
  Eje X: dias del periodo seleccionado
  Eje Y: ingresos en USD
  Dos lineas: periodo actual (color primario) vs periodo anterior (gris punteado)
  Hover tooltip: fecha + ingresos del dia + comparativo
  Debajo del grafico: desglose por metodo de pago con barras apiladas pequeñas

=============================================================
SECCION 3 — Servicios mas vendidos y rentables
=============================================================

Dos columnas:

Columna izquierda — Mas vendidos (barras horizontales):
  Top 8 servicios por cantidad de veces realizados
  Barra de progreso con la cantidad y el porcentaje del total
  Click en servicio: despliega detalle (ingresos, tiempo promedio, estilista que mas lo hace)

Columna derecha — Mas rentables (tabla):
  Columnas: Servicio | Ingresos | Costo materiales | Margen %
  Ordenado por margen descendente
  Margen < 50%: fila en amarillo (advertencia)
  Margen < 30%: fila en rojo (problematico)
  Tooltip explicativo: "El margen incluye solo el costo de productos consumidos segun la receta"

=============================================================
SECCION 4 — Mapa de calor de demanda
=============================================================

Tabla de 7 columnas (dias) x 15 filas (horas 7am-10pm):
  Cada celda: color segun intensidad de citas (blanco → verde claro → verde oscuro → naranja → rojo)
  Hover en celda: tooltip "Martes 14:00 — promedio de X citas en este horario"
  Fila adicional con totales por dia de semana
  Columna adicional con totales por hora

Debajo del mapa de calor:
  "Tus horas mas demandadas: Viernes 10:00-12:00 y Sabado 09:00-11:00"
  "Horas con baja ocupacion donde puedes ofrecer promociones: Martes y Miercoles por la tarde"

=============================================================
SECCION 5 — Ranking de estilistas
=============================================================

Tabla con tabs: [Por ingresos] [Por servicios] [Por ocupacion] [Por retencion]

Columnas comunes: foto | nombre | posicion (con flecha vs periodo anterior)
Columnas variables segun tab:
  Por ingresos:   ingresos | ticket promedio | vs periodo anterior
  Por servicios:  servicios realizados | servicios cancelados | tasa completitud
  Por ocupacion:  % ocupacion | horas facturadas | horas disponibles
  Por retencion:  clientes recurrentes | clientes nuevos | tasa retencion propia

Click en estilista: abre drawer con reporte individual completo del estilista

=============================================================
SECCION 6 — Retención y churn de clientes
=============================================================

Grafico de donut: Nuevos | Recurrentes | En riesgo (60+ dias sin venir) | Perdidos (90+ dias)

Tabla de clientes en riesgo de churn (los que llevan 45-89 dias sin visita):
  Nombre | Telefono | Ultima visita | Dias sin venir | Servicio favorito | [Enviar WhatsApp]
  Boton "Enviar recordatorio a todos" → lanza campana de WhatsApp masivo a este segmento

KPIs de retencion:
  LTV promedio: $XXX (valor de vida promedio de un cliente)
  Frecuencia promedio: cada X dias vuelve un cliente recurrente
  Mes de mayor retención del año historico

=============================================================
SECCION 7 — Forecast de la semana proxima
=============================================================

Card visual con los proximos 7 dias:
  Cada dia muestra:
    - Citas ya confirmadas (numero y barra de ocupacion)
    - Ingreso estimado basado en citas confirmadas + proyeccion historica
    - Indicador: dia lleno (rojo) / normal (verde) / baja ocupacion (amarillo → oportunidad)

Recomendacion automatica:
  "El martes tiene baja ocupacion proyectada (40%). Considera enviar una promocion
  de descuento del 15% en coloracion para ese dia."
  Boton: [Crear promocion para ese dia] → abre modal de campana WhatsApp pre-llenado

=============================================================
SECCION 8 — Exportaciones
=============================================================

Botones de exportacion por seccion:
  [📊 Exportar resumen ejecutivo PDF] → genera PDF con todos los KPIs del periodo
  [📋 Exportar detalle Excel] → hoja por cada seccion (ingresos, servicios, clientes, etc.)

El PDF ejecutivo debe incluir: logo del salon, periodo, KPIs principales, graficos (como imagenes)
Usar spatie/browsershot o dompdf con Chart.js pre-renderizado en servidor

---

## Verificación al terminar esta sesión

- [ ] ReportService retorna datos correctos para todos los períodos
- [ ] El mapa de calor muestra las horas pico correctamente
- [ ] La tabla de retención/churn identifica clientes en riesgo
- [ ] El forecast de 7 días muestra citas confirmadas + proyección
- [ ] La exportación Excel tiene una hoja por sección
- [ ] Los reportes cargan en menos de 2 segundos (Redis cache)
