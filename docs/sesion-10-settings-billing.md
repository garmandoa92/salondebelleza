# Sesión 10 — Settings del Salón + Billing con Stripe

**Duración estimada:** 2-3 días  
**Semana:** 9-10  
**Dependencias:** Sesiones 1-9 completadas  

---

## Parte A — Settings del Salón

Construyo el módulo de configuración del salón (accesible solo para owner y admin).

## Pages/Settings/Index.vue (múltiples secciones en tabs)

TAB "Mi salón":
- Logo (upload con preview y crop)
- Nombre del salón, dirección, teléfono, email de contacto
- RUC y razón social (para facturación SRI)
- Certificado digital SRI: upload del .p12 + contraseña (guardado encriptado)
- Ambiente SRI: pruebas | producción
- Numeración de facturas: establecimiento (3 dígitos), punto de emisión (3 dígitos)

TAB "Horario del salón":
- Días de atención (toggles: Lun-Dom)
- Horario de apertura y cierre por día
- Días festivos (lista con fechas, bloquean automáticamente la agenda)

TAB "Reservas online":
- Toggle: activar/desactivar reservas online
- Anticipación mínima para reservar (ej: "al menos 2 horas antes")
- Anticipación máxima (ej: "hasta 30 días en el futuro")
- Mensaje de bienvenida en la página de reservas
- Color primario de la página de reservas (color picker)
- Foto de portada (upload)
- Política de cancelación (texto enriquecido)

TAB "WhatsApp":
- API Key 360dialog
- Número registrado
- Toggles por tipo de notificación (confirmaciones, recordatorios, facturas)
- Botón de prueba de conexión

TAB "Pagos":
- Métodos de pago habilitados en el checkout (toggles)
- Nombre personalizado para cada método (ej: "Transferencia Banco Pichincha")
- Datos de transferencia (para mostrar al cliente al generar link de pago)

TAB "Equipo":
- Lista de usuarios del sistema con roles
- Invitar nuevo usuario por email
- Cambiar rol de usuario existente
- Desactivar acceso

TAB "Suscripción" (billing):
- Plan actual con features incluidos
- Próxima fecha de facturación
- Botón "Cambiar plan" (abre modal con comparativa de planes)
- Historial de facturas de la suscripción (descargables)
- Botón "Cancelar suscripción"`, GRAY),

  ...spacer(1),
  

---

## Parte B — Billing con Stripe

Construyo el sistema de suscripciones SaaS usando Stripe + Laravel Cashier.

## BillingController (en el dominio central, no en subdominios)

### Flujo de suscripción:
1. Usuario se registra → 30 días de trial gratis (plan Profesional)
2. Al expirar trial → página de upgrade bloqueante
3. Al seleccionar plan → redirige a Stripe Checkout
4. Stripe confirma pago → webhook activa la suscripción
5. Fallo de pago → email automático + 7 días de gracia

### Endpoints:
POST /billing/checkout         → Crea sesión de Stripe Checkout
POST /billing/portal           → Abre Stripe Customer Portal (para cambios y cancelaciones)
POST /billing/webhook          → Webhook de Stripe (CSRF excluido)
GET  /billing/invoices         → Facturas de la suscripción

### Webhooks a manejar:
- checkout.session.completed → activar suscripción
- customer.subscription.updated → actualizar plan en DB
- customer.subscription.deleted → desactivar tenant
- invoice.payment_failed → email de aviso + activar gracia
- invoice.payment_succeeded → renovar período activo

### Pages/Upgrade.vue (página de upgrade al expirar trial)
- Comparativa de los 3 planes en cards
- El plan recomendado destacado
- Precio mensual claramente visible en USD
- Lista de features por plan
- Botón "Empezar con [Plan]" → flujo de Stripe Checkout
- Nota: "Cancela cuando quieras, sin penalizaciones"

### Middleware EnsureActiveSub
Si el tenant tiene trial activo o suscripción activa: pass.
Si no: redirige a /upgrade con mensaje amigable.
Excepciones: rutas de billing, rutas de cancelación, logout.`, GRAY),

  ...spacer(2),
  new Paragraph({ pageBreakBefore: true, children: [new TextRun('')] }),

  // ══════════════════════════════════════════════════
  // PROMPTS DE DEBUGGING
  // ══════════════════════════════════════════════════
  h1('Prompts de debugging frecuentes'),
  para('Cuando algo falla, usa estos prompts específicos para que CC identifique y corrija el problema.'),
  ...spacer(1),

  h3('Cuando el multitenancy falla'),
  promptBox(`Tengo un problema con multitenancy en stancl/tenancy.
El error es: [PEGA EL ERROR EXACTO AQUÍ]

Contexto:
- Usamos DB separada por tenant (not single DB)
- El tenant se detecta por subdominio
- El error ocurre cuando: [describe cuándo ocurre]
- El stack trace muestra: [pega el stack trace]

Revisa:
1. ¿El modelo está en la DB correcta (tenant vs central)?
2. ¿El job tiene WithTenantContext si debe correr en contexto tenant?
3. ¿Hay alguna query que cruza contextos de tenant?
4. ¿Los middleware están en el orden correcto?

Muéstrame el fix con explicación de por qué ocurrió.`, GRAY),

  ...spacer(1),
  h3('Cuando el SRI rechaza la factura'),
  promptBox(`El SRI está retornando error en la factura electrónica.
Sri_response guardado en DB: [PEGA EL JSON DE SRI AQUÍ]
El XML generado es: [PEGA LOS PRIMEROS 500 CHARS DEL XML]

Los errores más comunes en el SRI Ecuador que debes revisar:
- Clave de acceso duplicada o mal calculada (49 dígitos exactos)
- Fecha de emisión fuera del rango permitido
- RUC del emisor inválido o no activo
- Total con más de 2 decimales
- Secuencial no correlativo

Identifica el error específico en el response del SRI,
muéstrame qué campo del XML está mal y cómo corregirlo.`, GRAY),

  ...spacer(1),
  h3('Cuando FullCalendar no muestra datos'),
  promptBox(`FullCalendar no está mostrando las citas correctamente.
El problema específico es: [DESCRIBE EL PROBLEMA]

Comparte el código actual de:
1. La configuración del calendario (options object)
2. El endpoint que provee los eventos
3. Cómo se mapean los appointments a eventos de FC

Cosas a verificar:
- ¿El formato de las fechas es ISO 8601 con timezone?
- ¿resourceId está presente en cada evento?
- ¿Los resources tienen el formato correcto {id, title}?
- ¿El endpoint retorna Content-Type: application/json?
- ¿Hay error en la consola del browser?`, GRAY),

  ...spacer(2),
  new Paragraph({ pageBreakBefore: true, children: [new TextRun('')] }),

  // ══════════════════════════════════════════════════
  // CHECKLIST FINAL
  // ══════════════════════════════════════════════════
  h1('Checklist antes de mostrar a tu primer cliente'),

  new Table({
    width: { size: 9360, type: WidthType.DXA },
    columnWidths: [480, 6880, 2000],
    rows: [
      new TableRow({ children: [
        new TableCell({ borders, shading: { fill: DARK, type: ShadingType.CLEAR }, margins: { top: 80, bottom: 80, left: 120, right: 120 }, width: { size: 480, type: WidthType.DXA }, children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: '#', font: 'Arial', size: 18, bold: true, color: WHITE })] })] }),
        new TableCell({ borders, shading: { fill: DARK, type: ShadingType.CLEAR }, margins: { top: 80, bottom: 80, left: 120, right: 120 }, width: { size: 6880, type: WidthType.DXA }, children: [new Paragraph({ children: [new TextRun({ text: 'Item', font: 'Arial', size: 18, bold: true, color: WHITE })] })] }),
        new TableCell({ borders, shading: { fill: DARK, type: ShadingType.CLEAR }, margins: { top: 80, bottom: 80, left: 120, right: 120 }, width: { size: 2000, type: WidthType.DXA }, children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'Estado', font: 'Arial', size: 18, bold: true, color: WHITE })] })] }),
      ]}),
      ...[
        ['1', 'El flujo completo de registro de un nuevo salón funciona sin errores'],
        ['2', 'Se puede crear una cita desde la agenda y desde el booking público'],
        ['3', 'El cobro genera una venta correctamente con todos los métodos de pago'],
        ['4', 'La factura SRI se genera en ambiente de pruebas y el SRI la autoriza'],
        ['5', 'El RIDE llega por WhatsApp al número del cliente de prueba'],
        ['6', 'El recordatorio de cita se envía automáticamente 24h antes'],
        ['7', 'El stock de un producto se descuenta al completar una cita con recipe'],
        ['8', 'La página de booking público carga en < 2 segundos en móvil'],
        ['9', 'Las comisiones se calculan correctamente al cerrar una venta'],
        ['10', 'El dashboard carga en < 1 segundo con 50+ citas en la DB'],
        ['11', 'El billing con Stripe: trial expira → página upgrade → pago → activación'],
        ['12', 'Puedes crear un tenant nuevo en menos de 2 minutos desde el registro'],
        ['13', 'La app funciona bien en móvil (dueñas de salón usan el teléfono)'],
        ['14', 'No hay errores en la consola del browser ni en los logs de Laravel'],
        ['15', 'Tienes un salón demo listo con datos de prueba para mostrar'],
      ].map(([num, text]) => new TableRow({ children: [
        new TableCell({ borders, shading: { fill: GRAY_LIGHT, type: ShadingType.CLEAR }, margins: { top: 80, bottom: 80, left: 120, right: 120 }, width: { size: 480, type: WidthType.DXA }, children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: num, font: 'Arial', size: 18, color: GRAY })] })] }),
        new TableCell({ borders, shading: { fill: WHITE, type: ShadingType.CLEAR }, margins: { top: 80, bottom: 80, left: 120, right: 120 }, width: { size: 6880, type: WidthType.DXA }, children: [new Paragraph({ children: [new TextRun({ text, font: 'Arial', size: 18, color: DARK })] })] }),
        new TableCell({ borders, shading: { fill: WHITE, type: ShadingType.CLEAR }, margins: { top: 80, bottom: 80, left: 120, right: 120 }, width: { size: 2000, type: WidthType.DXA }, children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: '☐ Pendiente', font: 'Arial', size: 17, color: GRAY })] })] }),
      ]}))
    ]
  }),

  ...spacer(3),
  new Table({
    width: { size: 9360, type: WidthType.DXA },
    columnWidths: [9360],
    rows: [new TableRow({ children: [
      new TableCell({
        borders: { top: { style: BorderStyle.SINGLE, size: 4, color: PINK }, bottom: noBorder, left: noBorder, right: noBorder },
        shading: { fill: PINK_LIGHT, type: ShadingType.CLEAR },
        margins: { top: 200, bottom: 200, left: 240, right: 240 },
        width: { size: 9360, type: WidthType.DXA },
        children: [
          new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'Cuando este checklist esté completo', font: 'Arial', size: 22, bold: true, color: PINK })] }),
          new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 80 }, children: [new TextRun({ text: 'entra a 5 salones en Quito, muestra el demo en tu teléfono y consigue tu primer cliente pagando.', font: 'Arial', size: 20, color: GRAY })] }),
          new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 80 }, children: [new TextRun({ text: 'El producto ya es lo suficientemente bueno. La venta es el siguiente paso.', font: 'Arial', size: 20, italics: true, color: GRAY })] }),
        ]
      })
    ]})]
  }),

];

const doc = new Document({
  styles: {
    default: { document: { run: { font: 'Arial', size: 22 } } },
    paragraphStyles: [
      { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 36, bold: true, font: 'Arial' }, paragraph: { spacing: { before: 400, after: 160 }, outlineLevel: 0 } },
      { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 28, bold: true, font: 'Arial' }, paragraph: { spacing: { before: 320, after: 120 }, outlineLevel: 1 } },
      { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { size: 24, bold: true, font: 'Arial' }, paragraph: { spacing: { before: 240, after: 80 }, outlineLevel: 2 } },
    ]
  },
  sections: [{
    properties: {
      page: {
        size: { width: 12240, height: 15840 },
        margin: { top: 1440, right: 1080, bottom: 1440, left: 1080 }
      }
    },
    children
  }]
});

Packer.toBuffer(doc).then(buffer => {
  fs.writeFileSync('/mnt/user-data/outputs/ClaudeCode_Prompts_Salones.docx', buffer);
  console.log('✅ Documento generado exitosamente');
}).catch(err => {
  console.error('Error:', err);
  process.exit(1);
});

---

## Verificación al terminar esta sesión

- [ ] El certificado SRI se puede subir y guarda encriptado
- [ ] El ambiente SRI (pruebas/producción) se puede cambiar con toggle
- [ ] El trial de 30 días expira y muestra la página de upgrade
- [ ] El pago con Stripe activa la suscripción inmediatamente
- [ ] El webhook de Stripe maneja: pago exitoso, fallido, cancelación
- [ ] Se puede invitar a un usuario con rol específico por email
- [ ] Los campos de WhatsApp se pueden configurar y probar desde settings
