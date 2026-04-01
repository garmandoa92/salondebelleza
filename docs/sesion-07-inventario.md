# Sesión 7 — Inventario de Productos

**Duración estimada:** 2 días  
**Semana:** Semana 7  
**Dependencias:** Sesiones anteriores completadas  

---

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Modelos Product y StockMovement ya existen. El cobro ya descuenta automáticamente
productos de tipo "sale" cuando se venden. Falta la gestión completa de inventario.

## ProductController (resource completo)

### Pages/Products/Index.vue
- Tabla de productos con: nombre, SKU, tipo (uso/venta), stock actual, stock mínimo, estado
- Indicadores de stock:
  * Verde: stock > stock_mínimo * 1.5
  * Amarillo: stock entre stock_mínimo y stock_mínimo * 1.5
  * Rojo: stock <= stock_mínimo (alerta)
  * Gris: sin stock configurado
- Filtros: tipo (uso/venta), proveedor, con stock bajo
- Búsqueda por nombre o SKU
- Botón "Registrar compra" (atajos a crear movimiento de entrada)
- Botón "Ajustar stock" (para correcciones)
- Exportar inventario a Excel

### Pages/Products/Form.vue
- Campos del producto (todos los del modelo)
- Sección especial si type=use:
  * Stock inicial al crear
  * Definir unidad de medida claramente
  * Nota sobre cómo se consume (automático en servicios)
- Sección si type=sale:
  * Precio de venta
  * Foto del producto
  * Visible en checkout (toggle)

### StockMovementController
POST /stock/purchase   → Registrar compra/entrada (aumenta stock)
POST /stock/adjustment → Ajuste manual (puede aumentar o disminuir)
GET  /stock/movements  → Historial de movimientos

### Pages/Products/Movements.vue (historial de movimientos)
- Timeline de todos los movimientos del producto
- Filtros por tipo, rango de fechas
- Para consumos: muestra link a la cita/servicio
- Para compras: proveedor y costo

## AutoConsumption (lógica ya parcialmente en cobro, ampliar aquí)
Cuando se completa una cita:
1. Lee la recipe del servicio (array de {product_id, quantity})
2. Por cada producto en la recipe: crea StockMovement tipo consumption
3. Descontado del stock
4. Si el stock queda bajo del mínimo: crea notificación (DB + WhatsApp al dueño)

## AlertaStockJob (scheduled daily a las 8am)
1. Busca todos los productos con stock <= min_stock
2. Agrupa por tenant
3. Envía resumen por WhatsApp al propietario del salón:
   "⚠️ Stock bajo en tu salón:
   - Tinte Loreal Rubio 8.0: 2 unidades (mínimo: 5)
   - Keratina XYZ: 100ml (mínimo: 500ml)
   Ingresa al sistema para hacer tu pedido."