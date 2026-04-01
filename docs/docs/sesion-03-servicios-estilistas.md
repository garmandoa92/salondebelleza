# Sesión 3 — Catálogo de Servicios + Estilistas

**Duración estimada:** 2 días  
**Semana:** 2  
**Dependencias:** Sesiones 1 y 2 completadas

---

## Objetivo
Construir el módulo de gestión de servicios y estilistas. Es el módulo más simple — úsalo para establecer los patrones de UI (Controller → Service → Inertia → Vue) que se repetirán en todos los demás módulos.

---

## Módulo 1: Catálogo de Servicios

### ServiceController (resource completo)
Rutas bajo middleware: auth, tenant, verified

### Pages/Services/Index.vue
- Tabla de servicios agrupada por categoría con acordeón por categoría
- Columnas: nombre, duración, precio, estado (toggle activo/inactivo inline), acciones
- Búsqueda en tiempo real (debounce 300ms, sin recargar página con Inertia)
- Drag & drop para reordenar dentro de categoría (SortableJS)
- Badge de color por categoría (usar el color del modelo ServiceCategory)
- Botón "Nueva categoría" (modal) y "Nuevo servicio" (página separada)

### Pages/Services/Form.vue (crear y editar)
Campos:
- Categoría (select con colores)
- Nombre del servicio
- Descripción (textarea opcional)
- Precio base (input decimal en $)
- Duración (input en minutos con preview: "1h 30min")
- Minutos de preparación (tooltip explicando que es el buffer entre citas)
- Foto del servicio (upload con preview circular)
- Visible en booking público (toggle)
- Requiere consulta previa (toggle)

Sección "Receta de productos" (collapsible):
- Buscador de productos tipo=use
- Tabla dinámica: producto | cantidad | unidad | [eliminar]
- Nota: "Los productos se descuentan automáticamente al completar el servicio"

### ServiceCategoryController
- CRUD simple via modal en la página Index
- Campos: nombre, color (color picker nativo HTML), orden
- No tiene página separada

### ServiceCategoryForm (modal)
- Input nombre
- Input type="color" para el color
- Previsualización del badge con el color elegido

---

## Módulo 2: Gestión de Estilistas

### StylistController (resource completo)

### Pages/Stylists/Index.vue
- Grid de cards de estilistas (foto circular, nombre, especialidades como chips, estado)
- Toggle activo/inactivo inline en cada card
- Métricas rápidas en la card: citas este mes, ticket promedio
- Botón "+ Nuevo estilista"
- Alternar entre vista grid y vista tabla (guardar preferencia en localStorage)

### Pages/Stylists/Form.vue
Secciones:

**Info personal:**
- Foto (upload con preview y crop circular)
- Nombre completo
- Teléfono, email
- Bio (textarea)
- Color del calendario (color picker — será el color de sus citas en la agenda)

**Especialidades:**
- Checkboxes de categorías de servicios (con el color de cada categoría)

**Horario semanal:**
- Para cada día Lun-Dom: toggle habilitado/deshabilitado
- Si habilitado: puede tener hasta 2 franjas horarias (para descanso al medio)
- Ejemplo: 09:00-13:00 y 14:00-18:00
- Copiar horario de otro día (botón "igual que lunes")

**Comisiones:**
- Porcentaje base (ej: 40%)
- Tabla dinámica de excepciones por categoría:
  | Categoría | % personalizado | [eliminar] |
- Nota: "Si no hay excepción para una categoría, se aplica el porcentaje base"

### Pages/Stylists/Schedule.vue
- Vista del horario + bloqueos del estilista
- Listado de bloqueos activos (fecha, motivo, quién lo creó)
- Formulario para agregar bloqueo:
  - Fecha inicio (datetime)
  - Fecha fin (datetime)
  - Motivo (texto libre)
  - ¿Bloqueo de todo el salón? (checkbox — stylist_id = null)

---

## Form Requests

Crear con validación completa:
- `StoreServiceRequest`
- `UpdateServiceRequest`
- `StoreStylistRequest`
- `UpdateStylistRequest`

Validaciones importantes:
- `duration_minutes`: min:5, max:480
- `base_price`: min:0.01, max:9999.99
- `color`: regex hexadecimal #RRGGBB
- `commission_rules.default`: between:0,100

---

## Verificación al terminar esta sesión

- [ ] Se puede crear, editar y eliminar un servicio con foto
- [ ] El toggle activo/inactivo funciona sin recargar la página
- [ ] El drag & drop reordena los servicios
- [ ] Se puede crear, editar y eliminar un estilista con foto
- [ ] El horario semanal con doble franja funciona
- [ ] Las comisiones con excepciones por categoría se guardan correctamente
- [ ] Los bloqueos de horario se crean y listan correctamente
