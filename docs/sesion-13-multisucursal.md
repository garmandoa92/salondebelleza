# Sesión 13 — Multi-sucursal

**Duración estimada:** 5-6 días  
**Semana:** Semana 21-24  
**Dependencias:** Sesiones anteriores completadas  

---

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Fases 1 y 2 completamente terminadas.

Construyo el modulo MULTI-SUCURSAL para el plan Cadena.
El tenant puede tener multiples sucursales (branches) dentro de la misma cuenta.
Los clientes, catálogo de servicios y reportes son compartidos entre sucursales por defecto.
Cada sucursal tiene su propia agenda, estilistas e inventario.

=============================================================
Modelo Branch (nueva tabla en la DB del tenant)
=============================================================

Tabla: branches
  id uuid PK
  name string (ej: "Sucursal Norte", "Matriz Kennedy")
  address text
  phone string
  email string nullable
  manager_user_id uuid nullable FK users (gerente de la sucursal)
  schedule json (horario de apertura de esta sucursal, puede diferir de otras)
  settings json (configuracion especifica: impresora, metodos de pago habilitados)
  sri_establishment string(3) (puede tener diferente establecimiento SRI: 001, 002, 003)
  sri_emission_point string(3)
  is_main boolean default false (la sucursal principal/matriz)
  is_active boolean default true
  sort_order integer default 0
  created_at, updated_at, deleted_at

Relaciones con modelos existentes:
  Stylist: agregar branch_id (un estilista puede estar en multiples sucursales via pivot)
  Appointment: agregar branch_id
  Sale: agregar branch_id
  StockMovement: agregar branch_id (el inventario puede ser por sucursal o centralizado)
  BlockedTime: agregar branch_id nullable

Tabla pivot: branch_stylist
  branch_id, stylist_id, schedule (horario especifico para esa sucursal), is_active

=============================================================
BranchController — CRUD de sucursales
=============================================================

Solo accesible por owner del tenant.

GET  /branches          → Lista de sucursales con stats resumidas
POST /branches          → Crear sucursal
PUT  /branches/{id}     → Actualizar
DELETE /branches/{id}   → Desactivar (no eliminar si tiene historial)
GET  /branches/{id}/stats → Stats de la sucursal

=============================================================
Cambios en la UI para multi-sucursal
=============================================================

SELECTOR DE SUCURSAL (topbar):
  Si el tenant tiene mas de una sucursal activa:
    Mostrar un selector dropdown en el topbar con el nombre de la sucursal actual
    "Ver todas las sucursales" → modo consolidado

  Cuando el usuario selecciona una sucursal:
    Guardar en session/localStorage: current_branch_id
    Toda la UI (agenda, inventario, caja, reportes) filtra por esa sucursal
    El selector persiste entre navegaciones

MODO CONSOLIDADO (ver todas):
  La agenda muestra un view simplificado de todas las sucursales
  Los reportes muestran datos consolidados con posibilidad de desglosar por sucursal
  La caja muestra totales de todas las sucursales + detalle por sucursal

=============================================================
Agenda con multi-sucursal
=============================================================

Cambios en AppointmentController:
  GET /agenda/events ahora acepta: branch_id (o 'all' para consolidado)
  POST /agenda/appointments requiere branch_id

En la agenda multi-sucursal (modo "ver todas"):
  Vista de dia: columnas agrupadas por sucursal, luego por estilista dentro de cada sucursal
  Un separador visual entre sucursales con el nombre de la sucursal como header
  Puede arrastrar citas entre estilistas de la misma sucursal (no entre sucursales)

Transferencia de cita entre sucursales:
  Boton "Transferir a otra sucursal" en el drawer de la cita
  Modal: seleccionar sucursal destino + estilista destino + confirmar
  Envia notificacion WhatsApp al cliente con la nueva ubicacion

=============================================================
Clientes compartidos entre sucursales
=============================================================

Los clientes son del TENANT, no de la sucursal.
Cuando un cliente va a cualquier sucursal, su historial es visible desde todas.

En la ficha del cliente:
  Nueva seccion "Historial por sucursal":
    Tab por cada sucursal mostrando las visitas especificas ahi
    Total consolidado al tope

Transferencia de cliente:
  No es necesaria — los clientes ya son del tenant completo
  Solo agregar en la ficha: "Sucursal favorita" (la que mas visita)

=============================================================
Catalogo de servicios — compartido o independiente
=============================================================

Agregar al modelo Service:
  scope enum(tenant, branch) default tenant
  branch_id uuid nullable (solo si scope=branch)

En Pages/Services/Index.vue:
  Seccion "Servicios globales" (scope=tenant, visibles en todas las sucursales)
  Seccion "Servicios exclusivos de esta sucursal" (scope=branch, solo en esta)
  Toggle al crear/editar servicio: "Disponible en todas las sucursales" vs "Solo en [sucursal actual]"

=============================================================
Inventario — por sucursal o centralizado
=============================================================

Agregar configuracion en tenant settings:
  inventory_mode: centralized | per_branch

Si centralized:
  Un solo inventario, los movimientos registran la sucursal donde ocurrieron
  Las alertas de stock son del inventario global
  El manager puede hacer "transferencia entre sucursales" de productos

Si per_branch:
  Cada sucursal tiene su propio stock completamente independiente
  Las alertas de stock son por sucursal
  Reporte consolidado muestra stock de todas las sucursales

Tabla stock_transfers (solo si inventory_mode=centralized):
  id, from_branch_id, to_branch_id, product_id, quantity, notes, user_id, created_at

=============================================================
Reportes consolidados multi-sucursal
=============================================================

Cambios en ReportService:
  Todos los metodos aceptan ahora: branch_id (string|null)
    Si branch_id = null: datos consolidados de todas las sucursales
    Si branch_id = uuid: datos solo de esa sucursal

En Pages/Reports/Index.vue:
  Agregar filtro de sucursal junto al selector de periodo
  Vista consolidada: graficos con linea por sucursal para comparar
  Nuevo tab "Comparativa de sucursales":
    Tabla comparativa: ingresos | citas | ticket promedio | ocupacion
    Una fila por sucursal + fila de totales
    Grafico de barras agrupadas para comparar visualmente

=============================================================
Gestion de estilistas multi-sucursal
=============================================================

Un estilista puede trabajar en multiples sucursales:
  Tabla branch_stylist con horario especifico por sucursal
  Ejemplo: Valeria trabaja en Sucursal Norte Lun-Mier y en Matriz Jue-Sab

En Pages/Stylists/Form.vue:
  Nueva seccion "Sucursales asignadas":
    Lista de sucursales con toggle y campo de horario especifico para cada una
    Si tiene horario en multiple sucursales: la disponibilidad se calcula
    sin solapar los horarios de las distintas sucursales

En la agenda:
  En el selector de estilistas (sidebar izquierdo):
    Si modo consolidado: agrupar por sucursal
    El estilista aparece solo en los dias/horarios que trabaja en cada sucursal

=============================================================
Permisos por sucursal
=============================================================

Agregar roles especificos de sucursal (via Spatie Permissions):
  branch_manager: puede ver y gestionar su sucursal asignada, no otras
  branch_receptionist: puede agendar y cobrar en su sucursal asignada
  stylist: puede ver solo su propia agenda

Middleware BranchAccessMiddleware:
  Verifica que el usuario tiene acceso a la branch_id del request
  El owner y admin tienen acceso a todas las sucursales
  El branch_manager solo a la suya

=============================================================
Pages/Branches/Index.vue — Gestion de sucursales
=============================================================

Grid de cards de sucursales:
  Cada card: nombre, direccion, gerente asignado, estilistas activos, estado
  Metricas resumidas: ingresos del mes, citas hoy, ocupacion actual (%)
  Acciones: [Editar] [Ver agenda] [Ver reportes] [Activar/Desactivar]

Botón "+ Nueva sucursal":
  Form con: nombre, direccion, telefono, horario, manager, establecimiento SRI
  Al crear: genera automaticamente los permisos de acceso

=============================================================
Plan Cadena — Validaciones
=============================================================

En EnsureActiveSub middleware:
  Si el tenant intenta crear la segunda sucursal y su plan es Basico o Profesional:
    Redirigir a pagina de upgrade con mensaje:
    "Para gestionar multiples sucursales necesitas el plan Cadena ($59/mes).
    Actualiza tu plan para continuar."

En la UI: el boton "+ Nueva sucursal" aparece pero al clickear
  muestra el modal de upgrade si el plan no lo permite

---

## Verificación al terminar esta sesión

- [ ] El selector de sucursal en el topbar filtra toda la UI
- [ ] Un estilista puede estar en múltiples sucursales con horarios distintos
- [ ] Los reportes consolidados muestran datos de todas las sucursales
- [ ] El plan Básico/Pro no puede crear una segunda sucursal (upgrade requerido)
- [ ] Los permisos branch_manager solo dan acceso a la sucursal asignada
