# Sesión 15 — Onboarding Guiado

**Duración estimada:** 2-3 días  
**Semana:** Post MVP  
**Dependencias:** Sesiones 1-13 completadas

---

## Objetivo
El mayor problema de retención en SaaS es que el cliente se registra y no sabe qué hacer. El onboarding guiado lleva al dueño del salón desde el registro hasta tener su primer cita agendada en menos de 10 minutos. Un buen onboarding reduce el churn en los primeros 30 días.

---

## Flujo completo de onboarding

### Trigger
Cuando un tenant se registra por primera vez:
- `onboarding_completed_at` = null en la tabla tenants
- Redirige automáticamente al wizard de onboarding
- No puede acceder al dashboard hasta completar el paso mínimo (paso 2)

---

## Wizard de configuración (5 pasos)

### Pages/Onboarding/Index.vue

Barra de progreso visual en la parte superior:
```
[●]──[○]──[○]──[○]──[○]
 1    2    3    4    5
```

Puede saltar al dashboard después del paso 2 con:
"Configurar después → Ir al dashboard"

---

### Paso 1 — Datos del salón

```
Nombre del salón *
Teléfono de contacto *
Dirección *
Ciudad *
Logo (upload opcional — puede subirse después)
Color principal (color picker — afecta el booking público)
```

Al guardar: actualiza el tenant con estos datos.

---

### Paso 2 — Tu primer estilista

```
"¿Quién trabaja en tu salón?"

Nombre completo *
Teléfono (opcional)
Especialidades (checkboxes de categorías)
Horario de trabajo (horario semanal simplificado)
```

Nota: "Puedes agregar más estilistas después desde el menú Estilistas"

Al guardar: crea el Stylist y lo vincula al tenant.

---

### Paso 3 — Tus servicios principales

```
"¿Qué servicios ofreces?"

Tabla simple para agregar servicios rápido:
[Nombre] [Precio] [Duración] [+ Agregar]

Ejemplos pre-cargados (puede editarlos o borrarlos):
✓ Corte de dama        $15    60 min
✓ Coloración completa  $45    120 min
✓ Manicure             $12    45 min
```

Botón: "Agregar otro servicio"
Nota: "Puedes personalizar más detalles después"

---

### Paso 4 — Configura WhatsApp (opcional)

```
"¿Quieres enviar recordatorios automáticos a tus clientes?"

[Imagen explicativa de cómo llega el mensaje al cliente]

Para activarlo necesitas:
1. Registrarte en 360dialog.com (gratis para empezar)
2. Obtener tu API key
3. Pegar la API key aquí:

API Key: [___________________________]
         [Verificar conexión]

¿No tienes API key? → Saltar por ahora
```

---

### Paso 5 — ¡Listo! Crea tu primera cita

```
"Todo está configurado. ¡Crea tu primera cita!"

Resumen de lo configurado:
✅ Salón: [nombre]
✅ 1 estilista: [nombre]  
✅ X servicios configurados
✅ WhatsApp: activo / pendiente de configurar

[Ir a la agenda y crear mi primera cita →]
```

Al terminar:
- Marca `onboarding_completed_at` = now()
- Redirige a la agenda
- Muestra confetti animation por 2 segundos

---

## Checklist de progreso en el Dashboard

Una vez completado el wizard, mostrar en el dashboard
una card de "Completa tu configuración" mientras haya items pendientes:

```
┌─────────────────────────────────────────────┐
│  Configura tu salón  ████████░░  80%         │
│                                             │
│  ✅ Datos del salón                          │
│  ✅ Estilistas agregados                     │
│  ✅ Servicios configurados                   │
│  ✅ Primera cita creada                      │
│  ○  Configurar WhatsApp     [Configurar →]  │
│  ○  Subir logo del salón    [Subir →]       │
│  ○  Configurar SRI          [Configurar →]  │
└─────────────────────────────────────────────┘
```

Desaparece cuando todo está al 100%.

---

## Emails de onboarding (secuencia automática)

Usar Laravel Scheduler + jobs para enviar en el momento correcto.

### Email día 0 — Bienvenida (inmediato al registrarse)
```
Asunto: Bienvenida a [SaaS Name] — Tu salón está listo

Hola [nombre],

Tu salón [nombre del salón] ya está activo.
Tienes 30 días gratis para probar todo.

[Ir a mi panel →]

¿Necesitas ayuda para empezar? Responde este email.
```

### Email día 1 — Si no ha creado ninguna cita
```
Asunto: ¿Creaste tu primera cita? Te ayudamos

Hola [nombre],

Notamos que aún no has creado tu primera cita en [SaaS].
Toma menos de 2 minutos.

[Ver cómo crear una cita →] (link a video corto o GIF)
[Ir a mi agenda →]
```

### Email día 3 — Tips de uso
```
Asunto: 3 funciones que tus clientes van a amar

1. Link de reservas online — Compártelo en tu Instagram Bio
2. Recordatorios automáticos — Reduce los no-shows
3. Tarjeta de puntos digital — Fideliza como Starbucks

[Ver cómo activarlos →]
```

### Email día 7 — Si no ha configurado WhatsApp
```
Asunto: Activa los recordatorios automáticos hoy

El 30% de las citas canceladas se evitan con un 
recordatorio automático. Actívalo en 5 minutos.

[Configurar WhatsApp →]
```

### Email día 25 — Antes de que expire el trial
```
Asunto: Tu prueba gratis termina en 5 días

Hola [nombre],

Tu período de prueba vence el [fecha].
Para seguir usando [SaaS] elige tu plan:

Básico    $15/mes → [Elegir]
Pro       $29/mes → [Elegir] ← Recomendado
Cadena    $59/mes → [Elegir]

¿Tienes preguntas? Responde este email.
```

---

## OnboardingController

```
GET  /onboarding              → Vista del wizard
POST /onboarding/salon        → Guardar paso 1
POST /onboarding/stylist      → Guardar paso 2
POST /onboarding/services     → Guardar paso 3
POST /onboarding/whatsapp     → Guardar paso 4
POST /onboarding/complete     → Marcar como completado

GET  /onboarding/checklist    → Estado del checklist (JSON)
POST /onboarding/skip         → Saltar al dashboard
```

---

## Middleware OnboardingMiddleware

Si el tenant NO ha completado el onboarding:
- Redirige a /onboarding en lugar del dashboard
- Excepción: rutas de onboarding, logout, billing

---

## Verificación al terminar esta sesión

- [ ] El wizard de 5 pasos carga y navega correctamente
- [ ] Al registrar un salón nuevo va al onboarding automáticamente
- [ ] Puede saltar al dashboard después del paso 2
- [ ] El checklist del dashboard muestra el progreso real
- [ ] Los emails de onboarding se envían en el momento correcto
- [ ] Al completar el paso 5 muestra la animación de confetti
- [ ] El middleware redirige al onboarding si no está completado
