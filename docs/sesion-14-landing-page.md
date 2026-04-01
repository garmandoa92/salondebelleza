# Sesión 14 — Landing Page del SaaS

**Duración estimada:** 2-3 días  
**Semana:** Post MVP  
**Dependencias:** Sesiones 1-13 completadas

---

## Objetivo
Construir la página de ventas principal en el dominio central (miapp.ec) que convierte visitantes en clientes pagantes. Sin esta página no puedes vender online. Debe cargar en menos de 2 segundos y verse perfecta en móvil.

---

## Rutas (dominio central, NO dentro de tenant)

```
GET /          → Landing page principal
GET /precios   → Página de precios detallada
GET /demo      → Solicitar demo (formulario)
GET /contacto  → Contacto
```

---

## Pages/Landing/Index.vue — Secciones en orden

### SECCIÓN 1 — Hero (lo primero que ve el visitante)

Debe comunicar el valor en menos de 5 segundos:

**Headline principal:**
"Gestiona tu salón de belleza como los grandes"

**Subheadline:**
"Agenda inteligente, facturación SRI automática y fidelización de clientes — todo desde $15/mes"

**Elementos:**
- Botón primario: "Empieza gratis 30 días" → /register
- Botón secundario: "Ver demo en vivo" → abre video modal
- Video o GIF animado mostrando la agenda en acción (captura real del sistema)
- Badge de confianza: "Sin tarjeta de crédito · Cancela cuando quieras"
- Logos pequeños: "Facturación SRI oficial · Google Wallet · Apple Wallet"

**Fondo:**
Gradiente suave rosado/blanco. No imagen de stock — usar la captura real del sistema.

---

### SECCIÓN 2 — Problema que resuelves

3 columnas mostrando el dolor del dueño de salón:

```
❌ "Agenda por WhatsApp"        → caótico, sin recordatorios
❌ "Facturación manual en SRI"  → lento, errores, multas
❌ "Sin datos de tu negocio"    → no sabes qué servicios son más rentables
```

Flecha hacia abajo → "Existe una mejor manera"

---

### SECCIÓN 3 — Features principales (6 cards)

```
📅 Agenda visual
   Arrastra citas, recibe reservas online 24/7,
   recordatorios automáticos por WhatsApp

🧾 Facturación SRI
   Facturas electrónicas autorizadas en segundos,
   sin salir del sistema

👤 Clientes con historia
   Ficha completa, alergias, preferencias,
   historial de servicios y gasto total

📦 Inventario inteligente
   Stock de productos, alertas automáticas,
   consumo descontado al completar el servicio

💳 Fidelización digital
   Tarjeta de puntos en Google y Apple Wallet,
   como Starbucks pero para tu salón

📊 Reportes que importan
   Servicios más rentables, horas pico,
   clientes en riesgo de no volver
```

---

### SECCIÓN 4 — Demo visual / Screenshots

Carousel o tabs mostrando capturas reales del sistema:
- Tab "Agenda" → captura del calendario con citas
- Tab "Cobro" → captura del checkout
- Tab "Clientes" → captura de la ficha del cliente
- Tab "Reportes" → captura del dashboard

Botón debajo: "Pruébalo tú mismo → Empieza gratis"

---

### SECCIÓN 5 — Comparativa (vs. la competencia)

Tabla comparativa:

| Feature | Tu SaaS | Mangomint | Fresha | Excel + WhatsApp |
|---------|---------|-----------|--------|-----------------|
| Agenda online | ✅ | ✅ | ✅ | ❌ |
| Facturación SRI Ecuador | ✅ | ❌ | ❌ | ❌ |
| WhatsApp recordatorios | ✅ | ❌ | ❌ | ❌ |
| Google/Apple Wallet | ✅ | ❌ | ❌ | ❌ |
| Precio | $15/mes | $165/mes | Comisión | $0 pero caótico |
| En español Ecuador | ✅ | ❌ | ❌ | ✅ |

---

### SECCIÓN 6 — Precios

3 cards de planes (igual que la tabla del documento):

```
Básico $15/mes
  Hasta 2 estilistas
  Agenda + CRM + SRI + Inventario + WhatsApp
  [Empezar gratis]

★ Profesional $29/mes  ← destacado
  Hasta 8 estilistas
  Todo Básico + Comisiones + Reportes + FideliaCard
  [Empezar gratis]

Cadena $59/mes
  Estilistas ilimitados
  Todo Pro + Multi-sucursal
  [Empezar gratis]
```

Nota debajo: "30 días gratis en el plan Profesional · Sin tarjeta de crédito"

---

### SECCIÓN 7 — Testimonios

Cuando tengas clientes reales, aquí van sus testimonios con:
- Foto del dueño/a
- Nombre del salón
- Ciudad
- Quote de 2-3 líneas
- Resultado concreto: "Ahorro 2 horas al día en administración"

Por ahora usar placeholders con "Tu nombre aquí — Salón en Quito"

---

### SECCIÓN 8 — FAQ

```
¿Necesito instalar algo?
No, funciona 100% en el browser desde cualquier dispositivo.

¿Qué pasa si cancelo?
Puedes cancelar en cualquier momento. Sin penalizaciones.

¿Funciona con el SRI de Ecuador?
Sí, emite facturas electrónicas autorizadas por el SRI en tiempo real.

¿Puedo migrar mis clientes existentes?
Sí, importa tu lista de clientes desde Excel en minutos.

¿Tiene app móvil?
Funciona perfecto en el browser del celular. App nativa próximamente.

¿Qué pasa con mis datos si cancelo?
Puedes exportar todos tus datos en cualquier momento antes de cancelar.
```

---

### SECCIÓN 9 — CTA final

```
"¿Listo para transformar tu salón?"

[Empieza gratis por 30 días →]

Sin tarjeta de crédito · Configuración en 5 minutos · Soporte en español
```

---

## PagesLanding/Demo.vue — Formulario de demo

Para salones grandes que quieren ver el sistema antes de registrarse:

Campos:
- Nombre completo
- Nombre del salón
- Teléfono (WhatsApp)
- Ciudad
- Número de estilistas
- ¿Qué usas ahora? (Excel, WhatsApp, otro software)
- Mensaje opcional

Al enviar:
- Guarda en tabla `demo_requests` en DB central
- Envía email al dueño del SaaS con los datos
- Muestra página de confirmación: "Te contactamos en menos de 24 horas"
- Envía WhatsApp automático al interesado: "Hola [nombre], recibimos tu solicitud..."

---

## SEO básico

En cada página:
```html
<title>Software para Salones de Belleza en Ecuador | [Nombre del SaaS]</title>
<meta name="description" content="Gestiona tu salón con agenda online, 
facturación SRI automática y fidelización de clientes. Desde $15/mes.">
<meta property="og:image" content="captura del sistema">
```

---

## Performance requerida

- Lighthouse score > 90 en móvil
- Tiempo de carga < 2 segundos
- Imágenes optimizadas (WebP, lazy loading)
- Sin bloqueos de render

---

## Verificación al terminar esta sesión

- [ ] La landing carga en menos de 2 segundos
- [ ] Se ve perfecta en móvil (320px - 428px)
- [ ] El botón "Empieza gratis" lleva al registro
- [ ] La tabla de precios muestra los 3 planes correctamente
- [ ] El formulario de demo envía email al dueño del SaaS
- [ ] Las meta tags de SEO están presentes
- [ ] El FAQ despliega/colapsa correctamente
