# Sesión 12 — Integración FideliaCard

**Duración estimada:** 2-3 días  
**Semana:** Semana 16  
**Dependencias:** Sesiones anteriores completadas  

---

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Fase 1 y Fase 2 (reportes) terminadas.

Construyo la integracion con FideliaCard (fideliacard.com).
FideliaCard es un producto EXTERNO Y SEPARADO — no es parte de este proyecto.
La integracion se hace via API REST de FideliaCard.
Todo fallo de FideliaCard debe ser silencioso — nunca afectar el flujo del salón.

=============================================================
FideliaCardService.php (app/Services/FideliaCardService.php)
=============================================================

Construir desde cero. Wrapper alrededor de la API de FideliaCard.

Config necesaria por tenant (guardada en tenant settings):
  fideliacard_api_key:     string (la API key que el usuario obtiene en fideliacard.com)
  fideliacard_business_id: string (ID del negocio en FideliaCard)
  fideliacard_enabled:     boolean (toggle general)

class FideliaCardService
{
  private string $baseUrl = 'https://api.fideliacard.com/v1';

  private function isEnabled(): bool {
    return tenant()->settings['fideliacard_enabled'] ?? false
      && !empty(tenant()->settings['fideliacard_api_key']);
  }

  private function client(): PendingRequest {
    return Http::withToken(tenant()->settings['fideliacard_api_key'])
      ->baseUrl($this->baseUrl)
      ->timeout(5)  // maximo 5 segundos de espera
      ->retry(2, 1000);  // 2 reintentos con 1 segundo entre ellos
  }

  // Notificar una venta completada para que FideliaCard sume los puntos
  public function notifySale(Client $client, Sale $sale): void {
    if (!$this->isEnabled()) return;

    try {
      $this->client()->post('/transactions', [
        'business_id'     => tenant()->settings['fideliacard_business_id'],
        'customer_phone'  => $client->phone,
        'customer_name'   => $client->full_name,
        'customer_email'  => $client->email,
        'amount_spent'    => $sale->total,
        'reference'       => $sale->id,
        'source'          => 'salon_saas',
      ]);
    } catch (\Exception $e) {
      // SILENCIOSO: loguear en Laravel Log pero no lanzar excepcion
      Log::warning('FideliaCard notifySale failed', [
        'client_id' => $client->id,
        'sale_id'   => $sale->id,
        'error'     => $e->getMessage()
      ]);
    }
  }

  // Obtener puntos actuales de un cliente
  public function getClientPoints(Client $client): ?array {
    if (!$this->isEnabled()) return null;

    try {
      $response = $this->client()->get('/customers/by-phone/' . $client->phone);
      if ($response->successful()) {
        return $response->json(); // { points: 150, level: 'Plata', next_level_at: 200 }
      }
    } catch (\Exception $e) {
      Log::warning('FideliaCard getClientPoints failed', ['error' => $e->getMessage()]);
    }

    return null;
  }

  // Verificar que la API key es valida
  public function testConnection(): bool {
    if (!$this->isEnabled()) return false;

    try {
      $response = $this->client()->get('/ping');
      return $response->successful();
    } catch (\Exception $e) {
      return false;
    }
  }
}

=============================================================
NotifySaleToFideliaCardJob.php
=============================================================

class NotifySaleToFideliaCardJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  use WithTenantContext;  // CRITICO para multitenancy

  public int $tries = 3;
  public int $backoff = 60;  // 1 minuto entre reintentos

  public function __construct(
    private readonly string $clientId,
    private readonly string $saleId
  ) {}

  public function handle(FideliaCardService $fideliaCard): void {
    $client = Client::findOrFail($this->clientId);
    $sale   = Sale::findOrFail($this->saleId);
    $fideliaCard->notifySale($client, $sale);
  }

  // Si falla 3 veces: no hacer nada, solo loguear
  public function failed(\Throwable $e): void {
    Log::error('FideliaCard job failed after 3 tries', ['error' => $e->getMessage()]);
  }
}

=============================================================
Trigger — Cuando se completa una venta
=============================================================

En SaleController::complete() o en el evento SaleCompleted:

// Lanzar el job de forma async, con 5 segundos de delay para asegurar que la DB este commiteada
NotifySaleToFideliaCardJob::dispatch($client->id, $sale->id)->delay(5);

=============================================================
Settings — Configuracion de FideliaCard en el salon
=============================================================

Agregar a Pages/Settings/Index.vue una nueva TAB "Fidelidad":

Seccion: "Conectar con FideliaCard"

Card con:
  Logo de FideliaCard
  Descripcion: "Tus clientes acumulan puntos automaticamente con cada visita.
                Los puntos se muestran en Google Wallet y Apple Wallet."

Si NO conectado:
  Campo: "API Key de FideliaCard"
  Campo: "ID de tu negocio en FideliaCard"
  Boton: [Conectar] → llama a POST /settings/fideliacard/connect
    Si la conexion es exitosa: muestra estado "Conectado" en verde
    Si falla: muestra error "API Key invalida"
  Link: "Obtener mi API Key en fideliacard.com →" (abre en nueva pestaña)

Si YA conectado (estado verde "Conectado"):
  Nombre del negocio en FideliaCard (obtenido de la API)
  Cantidad de clientes con tarjeta activa
  Toggle: "Enviar puntos automaticamente al cobrar" (default ON)
  Boton: [Probar conexion] → muestra "Conexion exitosa" o el error
  Boton: [Desconectar] (con confirmacion)

=============================================================
Widget de puntos en la ficha del cliente
=============================================================

En Pages/Clients/Show.vue, agregar en el panel izquierdo:

Si el tenant tiene FideliaCard conectado Y el cliente tiene telefono:
  Card pequena (lazy loaded, no bloquea el render de la pagina):
    [Logo FideliaCard pequeno]
    "Puntos de fidelidad"
    Numero grande: 150 puntos
    Nivel: "Plata ⭐⭐" con barra de progreso al siguiente nivel
    "Le faltan 50 puntos para llegar a Oro"
    Link: "Ver en FideliaCard →"

Si el cliente aun no tiene tarjeta FideliaCard:
  "Este cliente aun no tiene tarjeta de fidelidad"
  Boton: [Enviar invitacion por WhatsApp]
    → Enviar mensaje: "Hola [nombre], acumula puntos en cada visita a [salon].
       Agrega tu tarjeta de puntos aqui: [link de FideliaCard]"

=============================================================
Widget en el Checkout — Puntos a ganar
=============================================================

En Pages/Sales/Checkout.vue, al final del resumen de la venta:

Si FideliaCard conectado:
  Card pequena con fondo verde claro:
    "El cliente ganara aproximadamente X puntos con esta compra"
    (estimacion basada en el total de la venta)
    Si el cliente tiene puntos canjeables:
      "Tiene 200 puntos canjeables (equivale a $5.00 de descuento)"
      Boton: [Aplicar descuento de puntos] → llama a FideliaCard API para canjear

=============================================================
Settings — FideliaCardController
=============================================================

POST /settings/fideliacard/connect   → Guardar API key y verificar conexion
DELETE /settings/fideliacard         → Desconectar (eliminar API key)
GET /settings/fideliacard/status     → Estado actual + stats (clientes con tarjeta, etc.)

---

## Verificación al terminar esta sesión

- [ ] Si FideliaCard está caído, el cobro funciona perfectamente
- [ ] Los puntos se notifican de forma async sin bloquear el checkout
- [ ] El widget de puntos en la ficha del cliente carga lazy
- [ ] La conexión/desconexión desde Settings funciona
- [ ] El botón "Probar conexión" retorna éxito o error claramente
