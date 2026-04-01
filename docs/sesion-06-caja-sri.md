# Sesión 6 — Caja, Ventas y Facturación SRI

**Duración estimada:** 4 días  
**Semana:** 6-7  
**Dependencias:** Sesiones 1-5 completadas  

## REGLA ABSOLUTA
TODO se construye desde cero en esta sesión.
NO copiar, NO adaptar, NO referenciar código de ningún otro proyecto (incluyendo FacturaSmart).

---

Contexto: SaaS salones de belleza Ecuador, stack en CLAUDE.md.
Ya tenemos: agenda completa, clientes, servicios. Modelos Sale, SaleItem, SriInvoice ya existen.

REGLA ABSOLUTA: TODO se construye desde cero en esta sesión.
NO copiar, NO adaptar, NO referenciar codigo de ningun otro proyecto.
Construye el motor SRI como si nunca hubieras visto facturación electronica antes.

=============================================================
MOTOR SRI ECUADOR — Construir completamente desde cero
=============================================================

## Clase 1: SriXmlGenerator.php
Ubicacion: app/Services/Sri/SriXmlGenerator.php
Responsabilidad: generar el XML de cada tipo de comprobante

Metodo principal: generate(SriInvoice $invoice): string

El XML de FACTURA debe cumplir el esquema SRI Ecuador v2.1.0:

<?xml version="1.0" encoding="UTF-8"?>
<factura id="comprobante" version="2.1.0">
  <infoTributaria>
    <ambiente>1|2</ambiente>               <!-- 1=pruebas, 2=produccion -->
    <tipoEmision>1</tipoEmision>           <!-- 1=normal -->
    <razonSocial>NOMBRE DEL SALON</razonSocial>
    <nombreComercial>NOMBRE COMERCIAL</nombreComercial>
    <ruc>RUC DEL SALON</ruc>
    <claveAcceso>49 DIGITOS</claveAcceso>
    <codDoc>01</codDoc>                    <!-- 01=factura, 03=nota venta, 04=nota credito -->
    <estab>001</estab>
    <ptoEmi>001</ptoEmi>
    <secuencial>000000001</secuencial>
    <dirMatriz>DIRECCION DEL SALON</dirMatriz>
    <regimenMicroempresa>CONTRIBUYENTE REGIMEN MICROEMPRESAS</regimenMicroempresa>
    <!-- o: <contribuyenteRimpe>CONTRIBUYENTE NEGOCIO POPULAR</contribuyenteRimpe> -->
    <!-- o: nada si es regimen general -->
  </infoTributaria>
  <infoFactura>
    <fechaEmision>DD/MM/YYYY</fechaEmision>
    <dirEstablecimiento>DIRECCION SUCURSAL</dirEstablecimiento>
    <obligadoContabilidad>NO</obligadoContabilidad>
    <tipoIdentificacionComprador>07|05|04|06</tipoIdentificacionComprador>
    <!-- 07=consumidor final, 05=cedula, 04=RUC, 06=pasaporte -->
    <razonSocialComprador>CONSUMIDOR FINAL|NOMBRE</razonSocialComprador>
    <identificacionComprador>9999999999999|cedula|ruc</identificacionComprador>
    <totalSinImpuestos>DECIMAL</totalSinImpuestos>
    <totalDescuento>DECIMAL</totalDescuento>
    <totalConImpuestos>
      <totalImpuesto>
        <codigo>2</codigo>                 <!-- 2=IVA -->
        <codigoPorcentaje>4</codigoPorcentaje>  <!-- 4=15% desde abril 2024 -->
        <baseImponible>DECIMAL</baseImponible>
        <valor>DECIMAL</valor>
      </totalImpuesto>
      <!-- Si hay items con IVA 0%: agregar otro bloque con codigoPorcentaje=0 -->
    </totalConImpuestos>
    <propina>0.00</propina>
    <importeTotal>DECIMAL</importeTotal>
    <moneda>DOLAR</moneda>
    <pagos>
      <pago>
        <formaPago>01|20|16|19</formaPago>
        <!-- 01=sin uso de sistema, 20=otros, 16=tarjeta debito, 19=tarjeta credito -->
        <total>DECIMAL</total>
        <plazo>0</plazo>
        <unidadTiempo>dias</unidadTiempo>
      </pago>
    </pagos>
  </infoFactura>
  <detalles>
    <detalle>
      <codigoPrincipal>SRV-001</codigoPrincipal>  <!-- codigo interno del servicio -->
      <descripcion>Corte de cabello</descripcion>
      <cantidad>1.000000</cantidad>
      <precioUnitario>15.000000</precioUnitario>
      <descuento>0.00</descuento>
      <precioTotalSinImpuesto>15.00</precioTotalSinImpuesto>
      <impuestos>
        <impuesto>
          <codigo>2</codigo>
          <codigoPorcentaje>4</codigoPorcentaje>   <!-- 4=15% -->
          <tarifa>15.00</tarifa>
          <baseImponible>15.00</baseImponible>
          <valor>2.25</valor>
        </impuesto>
      </impuestos>
    </detalle>
  </detalles>
  <infoAdicional>
    <campoAdicional nombre="Email">email@cliente.com</campoAdicional>
    <campoAdicional nombre="Telefono">0999999999</campoAdicional>
  </infoAdicional>
</factura>

Implementar tambien los generadores de:
  generateNotaVenta(): para consumidores finales sin datos (esquema diferente al de factura)
  generateNotaCredito(): referencia obligatoria a la factura que anula
  generateLiquidacionCompra(): para compras a personas sin RUC

## Clase 2: SriAccessKeyGenerator.php
Ubicacion: app/Services/Sri/SriAccessKeyGenerator.php
Responsabilidad: calcular la clave de acceso de 49 digitos

La clave de acceso se calcula concatenando exactamente:
  [0-7]   fecha: ddMMaaaa (8 digitos)
  [8-9]   tipo comprobante: 01=factura, 03=nota venta, 04=nota credito (2 digitos)
  [10-22] RUC del emisor (13 digitos)
  [23]    ambiente: 1=pruebas, 2=produccion (1 digito)
  [24-26] establecimiento (3 digitos, ej: 001)
  [27-29] punto de emision (3 digitos, ej: 001)
  [30-38] secuencial (9 digitos, ej: 000000001)
  [39]    tipo de emision: 1=normal (1 digito)
  [40-48] codigo numerico: 8 digitos aleatorios + 1 digito verificador modulo 11

Calculo del digito verificador (modulo 11):
  Tomar los primeros 48 caracteres de la clave
  Multiplicar cada digito por el peso: ciclo 2,3,4,5,6,7 (de derecha a izquierda)
  Sumar todos los productos
  residuo = suma % 11
  Si residuo == 0: digito = 0
  Si residuo == 1: digito = 1
  Sino: digito = 11 - residuo

Validar que la clave resultante tenga exactamente 49 digitos.
Validar que no exista ya en la DB antes de asignarla.

## Clase 3: SriSignatureService.php
Ubicacion: app/Services/Sri/SriSignatureService.php
Responsabilidad: firmar el XML con XAdES-BES usando el certificado .p12

Dependencias PHP: ext-openssl (nativo en PHP), DOMDocument, DOMXPath

Proceso de firma XAdES-BES:
1. Cargar el certificado .p12 del tenant (desencriptado desde la DB)
   openssl_pkcs12_read($p12Content, $certs, $password)
2. Extraer: clave privada ($certs['pkey']), certificado publico ($certs['cert'])
3. Calcular el hash SHA1 del certificado en base64
4. Generar el ID unico para cada nodo de firma (prefijo: Signature, SignedProperties, etc.)
5. Construir el nodo <ds:Signature> con:
   - <ds:SignedInfo> con referencias al contenido y a las propiedades firmadas
   - <ds:SignatureValue> con la firma RSA-SHA1 del SignedInfo canonicalizado (C14N)
   - <ds:KeyInfo> con el certificado X509 en base64
   - <xades:QualifyingProperties> con fecha y hora de firma
6. Insertar el nodo Signature dentro del XML del comprobante
7. Retornar el XML firmado como string

Nota: El SRI Ecuador acepta XAdES-BES con algoritmos:
  Canonicalizacion: http://www.w3.org/TR/2001/REC-xml-c14n-20010315
  Firma: http://www.w3.org/2000/09/xmldsig#rsa-sha1
  Digest: http://www.w3.org/2000/09/xmldsig#sha1

## Clase 4: SriWebService.php
Ubicacion: app/Services/Sri/SriWebService.php
Responsabilidad: comunicacion con los webservices SOAP del SRI

URLs del SRI:
  Pruebas — recepcion:    https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl
  Pruebas — autorizacion: https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl
  Produccion — recepcion:    https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl
  Produccion — autorizacion: https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl

Metodo enviarComprobante(string $xmlFirmado, string $ambiente): array
  1. Usar SoapClient con el WSDL correcto segun ambiente
  2. Llamar al metodo: validarComprobante(['xml' => base64_encode($xmlFirmado)])
  3. La respuesta tiene: estado (RECIBIDA|DEVUELTA), comprobantes[0].mensajes
  4. Si DEVUELTA: el XML tiene errores → parsear y retornar los mensajes de error
  5. Si RECIBIDA: proceder a consultar autorizacion

Metodo consultarAutorizacion(string $claveAcceso, string $ambiente): array
  1. Llamar: autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso])
  2. La respuesta tiene: autorizaciones[0]:
     - estado: AUTORIZADO|NO AUTORIZADO
     - numeroAutorizacion: string 49 digitos
     - fechaAutorizacion: datetime
     - mensajes: array de errores si NO AUTORIZADO
  3. Retornar estado + numeroAutorizacion + fechaAutorizacion + mensajes

## Clase 5: SriRideGenerator.php
Ubicacion: app/Services/Sri/SriRideGenerator.php
Responsabilidad: generar el PDF del RIDE (Representacion Impresa del Documento Electronico)

Usar la libreria dompdf/dompdf para generar el PDF desde HTML.

El RIDE debe contener:
  CABECERA:
    Logo del salon (si tiene) | Nombre y razon social | RUC | Direccion
    Tipo de comprobante (FACTURA ELECTRONICA) | Numero (001-001-000000001)
    Fecha de emision | Numero de autorizacion | Fecha de autorizacion
    Ambiente (PRODUCCION o PRUEBAS) | Emision: NORMAL
    Clave de acceso con codigo de barras (CODE128 o QR)

  DATOS DEL COMPRADOR:
    Razon social/nombre | Identificacion | Direccion | Email | Telefono

  TABLA DE DETALLE:
    Cod | Descripcion | Cantidad | Precio Unit | Descuento | Precio Total

  TOTALES:
    Subtotal 0% | Subtotal 15% | Descuento total | IVA 15% | TOTAL

  FORMAS DE PAGO:
    Tabla: Forma de pago | Valor

  INFO ADICIONAL:
    Campos adicionales (email, telefono del cliente)

Guardar el PDF en S3: invoices/{tenant_id}/{year}/{month}/{access_key}.pdf

=============================================================
FLUJO COMPLETO DEL COBRO
=============================================================

## SaleController

POST /sales              → Crear venta desde completar cita
GET  /sales/{id}         → Detalle
PUT  /sales/{id}         → Actualizar (solo si status=draft)
POST /sales/{id}/complete → Completar venta
POST /sales/{id}/invoice  → Generar factura electronica (lanza job async)
POST /sales/{id}/resend   → Reenviar RIDE por WhatsApp + email
GET  /sales              → Historial con filtros y paginacion
GET  /sales/summary      → Resumen del dia para dashboard

## Pages/Sales/Checkout.vue — Modal de cobro

Se abre al completar una cita. 6 secciones:

SECCION 1 — Items de la venta:
  Servicio de la cita agregado automaticamente (stylist pre-asignado)
  Buscador para agregar mas servicios
  Buscador para agregar productos de venta (con stock disponible)
  Tabla editable: descripcion | cantidad | precio | subtotal | [eliminar]

SECCION 2 — Descuento (collapsible):
  Toggle activar descuento
  Tipo: porcentaje (%) o monto fijo ($)
  Motivo obligatorio si hay descuento
  Preview del descuento en tiempo real

SECCION 3 — Propina:
  Input numerico
  Select de estilista destinatario

SECCION 4 — Resumen financiero (sticky en el lateral en desktop):
  Subtotal base:          $XX.XX
  Descuento:             -$XX.XX (si aplica, en rojo)
  Base imponible IVA 0%:  $XX.XX (si aplica)
  Base imponible IVA 15%: $XX.XX
  IVA 15%:                $XX.XX
  Total:                  $XX.XX (grande, bold)
  Propina:               +$XX.XX (si aplica)

SECCION 5 — Metodo de pago:
  Puede dividir en multiples metodos:
  [+ Agregar metodo de pago]
  Para cada metodo:
    Select: Efectivo | Transferencia | Tarjeta débito | Tarjeta crédito | Otro
    Monto: input numerico
    Para Efectivo: mostrar campo "Recibido" + calculo automatico de "Vuelto: $X.XX"
  Validacion: suma de metodos debe igualar el total exacto (con tolerancia 0.01)
  Indicador visual: "Falta: $X.XX" o "Correcto" segun el estado

SECCION 6 — Factura electronica (opcional):
  Toggle "¿Requiere comprobante electronico?"
  Si NO: genera nota de venta (schema mas simple)
  Si SI:
    Select tipo identificacion:
      Consumidor final (sin datos, por defecto)
      Cedula (10 digitos, validar algoritmo modulo 10)
      RUC (13 digitos, validar algoritmo modulo 11)
      Pasaporte (alfanumerico)
    Campo identificacion (validacion segun tipo)
    Campo nombre/razon social (auto-completado si existe en DB)
    Campo email (para enviar el RIDE)
  Si el cliente ya tiene datos en su ficha: pre-completar automaticamente

Botones:
  [Cancelar]   [Completar cobro →]

Al completar:
  1. Guarda la Sale con status=completed
  2. Guarda todos los SaleItems
  3. Cierra la Appointment con status=completed
  4. Calcula y guarda comisiones automaticamente
  5. Descuenta stock de productos vendidos
  6. Si se solicito comprobante: lanza ProcessSriDocumentJob (async, no bloquea)
  7. Muestra pantalla de confirmacion con: total cobrado + cambio + opcion de imprimir

## ProcessSriDocumentJob (Queue, construir desde cero)

1. Determinar tipo de comprobante:
   - Si identificacion = consumidor final: generar NOTA DE VENTA (codDoc=03)
   - Si identificacion = cedula|RUC|pasaporte: generar FACTURA (codDoc=01)

2. Obtener proximo secuencial para el tipo:
   SELECT MAX(CAST(sequential AS UNSIGNED)) + 1 FROM sri_invoices
   WHERE tenant_id = X AND invoice_type = Y AND establishment = Z AND emission_point = W
   Usar lock pesimista para evitar secuenciales duplicados en concurrencia:
   DB::transaction(function() { ... }, isolation: 'SERIALIZABLE')

3. Generar clave de acceso con SriAccessKeyGenerator

4. Generar XML con SriXmlGenerator

5. Validar el XML antes de firmar:
   - Totales cuadran con tolerancia 0.01
   - Clave de acceso tiene 49 digitos exactos
   - RUC del emisor presente y tiene 13 digitos
   - Secuencial tiene formato correcto (9 digitos con ceros a la izquierda)
   Si falla validacion: marcar sri_invoice como rejected con mensaje claro

6. Firmar con SriSignatureService

7. Guardar XML firmado en DB (campo xml_signed)

8. Enviar al SRI con SriWebService::enviarComprobante()
   Si DEVUELTA: guardar errores, marcar como rejected, no hacer retry automatico

9. Si RECIBIDA: consultar autorizacion (puede demorar hasta 30 segundos en el SRI)
   Reintentar la consulta hasta 3 veces con delay de 10 segundos entre intentos

10. Si AUTORIZADO:
    - Guardar: numero_autorizacion, fecha_autorizacion, estado=authorized
    - Generar RIDE PDF con SriRideGenerator
    - Guardar URL del RIDE en ride_path
    - Lanzar SendInvoiceNotificationJob (envia RIDE por WhatsApp + email)

11. Si NO AUTORIZADO:
    - Guardar mensajes de error del SRI en sri_response
    - Marcar como rejected
    - Crear notificacion interna para el dueno del salon
    - Si retry_count < 3: programar retry en 5 minutos

## SriInvoiceController — Historial completo de facturas

GET  /invoices                  → Lista de todas las facturas
GET  /invoices/{id}             → Detalle de una factura
GET  /invoices/{id}/ride        → Descargar PDF del RIDE
GET  /invoices/{id}/xml         → Descargar XML firmado
POST /invoices/{id}/retry       → Reintentar envio al SRI
POST /invoices/{id}/void        → Anular factura (genera nota de credito)
GET  /invoices/ats/{year}/{month} → Exportar XML del ATS mensual

=============================================================
Pages/Invoices/Index.vue — SECCION DE FACTURAS COMPLETA
=============================================================

Esta es una pagina COMPLETA en el menu principal, NO solo un historial secundario.
Accesible desde el menu lateral: "Facturación" con icono de documento.

TABS principales:
  [Todas] [Autorizadas] [Pendientes] [Rechazadas] [Anuladas]

FILTROS (barra de filtros expandible):
  Rango de fechas (date range picker)
  Tipo: Factura | Nota de venta | Nota de crédito
  Estado: Autorizada | Pendiente | Rechazada | Anulada
  Buscar: por numero, por RUC/cedula del comprador, por nombre del comprador
  Monto: desde/hasta

TABLA PRINCIPAL (paginada, 25 por pagina):
  Columnas:
    Numero (001-001-000000001)
    Tipo (badge: Factura / Nota Venta / Nota Crédito)
    Fecha emisión
    Comprador (nombre + identificacion)
    Total ($XX.XX)
    Estado (badge con colores: verde=Autorizada, gris=Pendiente, rojo=Rechazada, naranja=Anulada)
    N° Autorizacion (truncado con tooltip del completo)
    Acciones: [📥 RIDE] [📄 XML] [↩ Reenviar] [... mas]

Click en fila → abre Drawer lateral con detalle completo de la factura.

DRAWER DE DETALLE DE FACTURA:
  Numero completo + tipo + estado badge
  Fecha de emision y fecha de autorizacion
  Numero de autorizacion completo (49 digitos) con boton copiar
  Clave de acceso con boton copiar

  DATOS DEL EMISOR:
    Nombre del salon | RUC | Ambiente (Produccion / Pruebas)

  DATOS DEL COMPRADOR:
    Tipo identificacion + numero | Nombre | Email | Telefono

  DETALLE DE ITEMS:
    Tabla: Descripcion | Qty | Precio Unit | Descuento | Subtotal

  TOTALES:
    Subtotal 0% | Subtotal 15% | IVA 15% | Total

  FORMAS DE PAGO

  TIMELINE SRI:
    Generada XX:XX | Enviada XX:XX | Respuesta XX:XX | Autorizada XX:XX
    Si rechazada: mensajes de error del SRI en rojo con codigo de error

  ACCIONES segun estado:
    Autorizada: [Descargar RIDE] [Descargar XML] [Reenviar por WhatsApp] [Anular]
    Pendiente:  [Reintentar envio al SRI] [Descargar XML para revision]
    Rechazada:  [Ver errores del SRI] [Corregir y reintentar] [Descargar XML]
    Anulada:    [Ver nota de credito]

RESUMEN FINANCIERO (cards encima de la tabla):
  Total facturado (periodo): $XX,XXX.XX
  Cantidad de comprobantes: XXX
  IVA generado: $X,XXX.XX
  Pendientes de autorizacion: X

EXPORTACIONES:
  [Exportar Excel] → todas las facturas del filtro activo
  [Descargar ATS] → abre modal para seleccionar mes/año y descarga el XML del ATS

=============================================================
ATS — Anexo Transaccional Simplificado
=============================================================

GET /invoices/ats/{year}/{month} → genera y descarga el XML del ATS

El ATS es un reporte mensual que se presenta al SRI con todas las ventas.
Formato: XML segun esquema DIMM ATS del SRI Ecuador.

Estructura minima del ATS:
<ats>
  <TipoIDInformante>RUC</TipoIDInformante>
  <IdInformante>RUC_DEL_SALON</IdInformante>
  <razonSocial>NOMBRE DEL SALON</razonSocial>
  <Anio>2025</Anio>
  <Mes>04</Mes>
  <numEstabRuc>1</numEstabRuc>
  <totalVentas>DECIMAL</totalVentas>
  <codigoOperativo>IVA</codigoOperativo>
  <compras>
    <detalleCompras>
      <!-- Una entrada por cada compra con factura -->
      <codSustento>01</codSustento>
      <tpIdProv>04</tpIdProv>
      <idProv>RUC_PROVEEDOR</idProv>
      <tipoComp>01</tipoComp>
      <parteRel>NO</parteRel>
      <fechaRegistro>DD/MM/YYYY</fechaRegistro>
      <establecimiento>001</establecimiento>
      <puntoEmision>001</puntoEmision>
      <secuencial>000000001</secuencial>
      <fechaEmision>DD/MM/YYYY</fechaEmision>
      <autorizacion>NUMERO_AUTORIZACION</autorizacion>
      <baseNoGraIva>0.00</baseNoGraIva>
      <baseImponible>0.00</baseImponible>
      <baseImpGrav>DECIMAL</baseImpGrav>
      <montoIce>0.00</montoIce>
      <montoIva>DECIMAL</montoIva>
      <valRetBien10>0.00</valRetBien10>
      <valRetServ20>0.00</valRetServ20>
      <valorRetBienes>0.00</valorRetBienes>
      <valorRetServicios>0.00</valorRetServicios>
      <valRetServ100>0.00</valRetServ100>
      <totbasesImpReemb>0.00</totbasesImpReemb>
    </detalleCompras>
  </compras>
  <ventas>
    <detalleVentas>
      <!-- Una entrada por cada venta del mes -->
      <tpIdCliente>07</tpIdCliente>
      <idCliente>9999999999999</idCliente>
      <parteRelVtas>NO</parteRelVtas>
      <tipoComprobante>18</tipoComprobante>  <!-- 18=liquidacion servicios -->
      <tipoEm>F</tipoEm>
      <numeroComprobantes>CANTIDAD</numeroComprobantes>
      <baseNoGraIva>0.00</baseNoGraIva>
      <baseImponible>0.00</baseImponible>
      <baseImpGrav>DECIMAL</baseImpGrav>
      <montoIva>DECIMAL</montoIva>
      <montoIce>0.00</montoIce>
      <valorRetIva>0.00</valorRetIva>
      <valorRetRenta>0.00</valorRetRenta>
    </detalleVentas>
  </ventas>
</ats>

=============================================================
CONFIGURACION SRI POR TENANT (Settings)
=============================================================

Guardar en la tabla tenants (DB central) o en una tabla tenant_sri_configs:
  ruc                    → RUC del salon (13 digitos)
  razon_social           → Nombre legal del salon
  nombre_comercial       → Nombre comercial
  direccion_matriz       → Direccion principal
  ambiente_sri           → 1=pruebas | 2=produccion (default: 1)
  establecimiento        → 001 (3 digitos)
  punto_emision          → 001 (3 digitos)
  regimen_tributario     → general | rimpe_emprendedor | rimpe_negocio_popular
  obligado_contabilidad  → SI | NO
  certificado_p12        → blob encriptado con AES-256 (clave = APP_KEY + tenant_id)
  certificado_password   → encriptado con Crypt::encrypt()
  certificado_expires_at → fecha de vencimiento del certificado (alerta cuando quede 1 mes)

VALIDACIONES EN SETTINGS:
  RUC: exactamente 13 digitos, algoritmo modulo 11
  Cedula: exactamente 10 digitos, algoritmo modulo 10
  Establecimiento y punto_emision: exactamente 3 digitos numericos
  Certificado .p12: validar que se puede abrir con la password dada antes de guardar

ALERTA DE CERTIFICADO PROXIMO A VENCER:
  Job diario que verifica certificados con vencimiento en <= 30 dias
  Notificacion interna + WhatsApp al dueno del salon

---

## Verificación al terminar esta sesión

- [ ] Se puede completar un cobro con múltiples métodos de pago
- [ ] El vuelto se calcula automáticamente para efectivo
- [ ] La factura SRI se genera en ambiente de pruebas y el SRI la autoriza
- [ ] El RIDE PDF se genera correctamente con todos los campos
- [ ] Las notas de venta (consumidor final) se generan sin error
- [ ] La sección de facturas muestra todos los estados correctamente
- [ ] El ATS mensual se puede exportar en formato XML
- [ ] Los reintentos automáticos funcionan para facturas rechazadas
