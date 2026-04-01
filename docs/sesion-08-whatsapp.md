# Sesión 8 — WhatsApp + Notificaciones

**Duración estimada:** 2 días  
**Semana:** Semana 7-8  
**Dependencias:** Sesiones anteriores completadas  

---

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Usamos 360dialog como proveedor de WhatsApp Business API.
Cada tenant tiene sus propias credenciales de 360dialog (guardadas en tenant settings o DB central).

## WhatsAppService (app/Services/WhatsAppService.php)
Clase singleton que maneja toda la comunicación con 360dialog API.

Métodos principales:
- sendTemplate(string $phone, string $templateName, array $components): bool
- sendText(string $phone, string $message): bool
- getTemplates(): array
- validatePhone(string $phone): string (normaliza a formato internacional +593...)

Para Ecuador: si el número empieza con 09, convertir a +5939...
Si empieza con 9 (sin cero), agregar +593.

## Templates de WhatsApp necesarios
(Estos se aprueban en Meta Business Manager, generar el código para registrarlos)

1. appointment_confirmation
   "¡Hola {{1}}! Tu cita en {{2}} está confirmada.
   📅 {{3}} a las {{4}}
   💇 Servicio: {{5}} con {{6}}
   ¿Necesitas cancelar? Escríbenos con 24h de anticipación."

2. appointment_reminder_24h
   "Hola {{1}}, te recordamos que mañana tienes cita en {{2}}.
   📅 {{3}} a las {{4}}
   💇 {{5}} con {{6}}
   ¡Te esperamos! 🌟"

3. appointment_reminder_2h
   "¡Ya casi es tu hora! {{1}}, tu cita es hoy a las {{2}}.
   📍 {{3}}"

4. invoice_ride
   "Hola {1}, aqui tienes tu factura del servicio en {2}.
   Numero: {3}
   Total: $ {4}
   {5}" (el RIDE se envia como documento adjunto)

5. stock_alert
   "⚠️ Alerta de stock bajo en {{1}}:
   {{2}}
   Accede al sistema para gestionar tu inventario."

6. welcome_new_client
   "¡Bienvenida/o {{1}} a {{2}}! 🌟
   Tu primera cita está agendada para {{3}} a las {{4}}.
   Si tienes dudas, responde este mensaje."

## Jobs de notificación

SendAppointmentConfirmationJob:
- Trigger: AppointmentCreated event
- Delay: inmediato
- Usa template appointment_confirmation

SendAppointmentReminderJob:
- Schedulado por Laravel Scheduler
- Corre cada 15 minutos
- Busca citas con starts_at en las próximas 24h y reminder_sent_at IS NULL
- Envía template appointment_reminder_24h
- Marca reminder_sent_at
- Repite para citas en próximas 2h (template _2h)

SendInvoiceJob:
- Trigger: SriInvoice → authorized
- Envía el RIDE como PDF adjunto por WhatsApp
- También lo envía por email si hay email disponible

## NotificationController (para el salón, no para clientes)
El salón recibe notificaciones internas del sistema en la UI:
- Nueva reserva online
- Stock bajo
- Factura rechazada por SRI
- Cita de hace 30 min que sigue en "pending" (se olvidaron de actualizar)

GET  /notifications       → Lista de notificaciones del tenant
POST /notifications/{id}/read → Marcar como leída
POST /notifications/read-all  → Marcar todas como leídas

Notificaciones en la UI: badge en el ícono de campana en el topbar,
dropdown con las últimas 10 notificaciones.
Usar Laravel Notifications con canal database.

## Configuración de WhatsApp por tenant
En Settings del salón: sección "WhatsApp"
- API Key de 360dialog
- Número de WhatsApp Business registrado
- Toggle: activar/desactivar cada tipo de notificación
- Botón "Probar conexión" (envía mensaje de prueba al número del dueño)