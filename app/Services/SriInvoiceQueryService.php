<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SriInvoiceQueryService
{
    const WSDL_PRODUCCION = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
    const WSDL_PRUEBAS = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

    /**
     * Decodificar clave de acceso sin llamar al SRI.
     * Extrae RUC, fecha, número de factura del string de 49 dígitos.
     */
    public function decodeAccessKey(string $claveAcceso): array
    {
        if (strlen($claveAcceso) !== 49 || !ctype_digit($claveAcceso)) {
            throw new \InvalidArgumentException('La clave de acceso debe tener exactamente 49 dígitos numéricos');
        }

        if (!$this->verifyCheckDigit($claveAcceso)) {
            throw new \InvalidArgumentException('Dígito verificador inválido — clave de acceso incorrecta');
        }

        // Estructura clave de acceso SRI (49 dígitos):
        // 1-8: fecha ddmmaaaa (8), 9-10: tipo comprobante (2), 11-23: RUC (13),
        // 24: ambiente (1), 25-27: establecimiento (3), 28-30: punto emisión (3),
        // 31-39: secuencial (9), 40-47: código numérico (8), 48: tipo emisión (1),
        // 49: dígito verificador (1)
        $fecha = substr($claveAcceso, 0, 8);
        $tipo = substr($claveAcceso, 8, 2);
        $ruc = substr($claveAcceso, 10, 13);
        $ambiente = substr($claveAcceso, 23, 1);
        $establecimiento = substr($claveAcceso, 24, 3);
        $puntoEmision = substr($claveAcceso, 27, 3);
        $secuencial = substr($claveAcceso, 30, 9);

        $dia = substr($fecha, 0, 2);
        $mes = substr($fecha, 2, 2);
        $anio = substr($fecha, 4, 4);

        return [
            'ruc' => $ruc,
            'fecha_emision' => $anio . '-' . $mes . '-' . $dia,
            'invoice_number' => $establecimiento . '-' . $puntoEmision . '-' . $secuencial,
            'tipo_comprobante' => $tipo,
            'ambiente' => $ambiente === '2' ? 'produccion' : 'pruebas',
            'serie' => $establecimiento . $puntoEmision,
        ];
    }

    /**
     * Verificar dígito verificador (módulo 11).
     */
    private function verifyCheckDigit(string $clave): bool
    {
        $digits = str_split(substr($clave, 0, 48));
        $coefs = [2, 3, 4, 5, 6, 7];
        $suma = 0;
        $coefIndex = 0;

        foreach (array_reverse($digits) as $digit) {
            $suma += (int) $digit * $coefs[$coefIndex % 6];
            $coefIndex++;
        }

        $residuo = $suma % 11;
        $verificador = $residuo === 0 ? 0 : ($residuo === 1 ? 1 : 11 - $residuo);

        return (int) substr($clave, 48, 1) === $verificador;
    }

    /**
     * Consultar comprobante completo al SRI.
     * Retorna razón social, productos, montos.
     */
    public function queryInvoice(string $claveAcceso): array
    {
        $cacheKey = 'sri_invoice_' . $claveAcceso;

        return Cache::store('file')->remember($cacheKey, 86400, function () use ($claveAcceso) {
            $decoded = $this->decodeAccessKey($claveAcceso);

            $wsdl = $decoded['ambiente'] === 'produccion'
                ? self::WSDL_PRODUCCION
                : self::WSDL_PRUEBAS;

            try {
                $client = new \SoapClient($wsdl, [
                    'exceptions' => true,
                    'connection_timeout' => 15,
                    'cache_wsdl' => WSDL_CACHE_BOTH,
                ]);

                $response = $client->autorizacionComprobante([
                    'claveAccesoComprobante' => $claveAcceso,
                ]);

                return $this->parseResponse($response, $decoded, $claveAcceso);

            } catch (\SoapFault $e) {
                Log::warning('SRI SOAP fault al consultar factura: ' . $e->getMessage());

                return [
                    'status' => 'partial',
                    'message' => 'Datos básicos decodificados. No se pudo conectar al SRI.',
                    'ruc' => $decoded['ruc'],
                    'fecha_emision' => $decoded['fecha_emision'],
                    'invoice_number' => $decoded['invoice_number'],
                    'supplier_name' => null,
                    'subtotal' => null,
                    'iva' => null,
                    'total' => null,
                    'items' => [],
                ];
            }
        });
    }

    /**
     * Parsear respuesta XML del SRI.
     */
    private function parseResponse($response, array $decoded, string $claveAcceso): array
    {
        $autorizacion = $response->RespuestaAutorizacionComprobante
            ->autorizaciones
            ->autorizacion ?? null;

        if (!$autorizacion) {
            return [
                'status' => 'not_found',
                'message' => 'Comprobante no encontrado en el SRI',
                'ruc' => $decoded['ruc'],
                'fecha_emision' => $decoded['fecha_emision'],
                'invoice_number' => $decoded['invoice_number'],
                'supplier_name' => null,
                'subtotal' => null,
                'iva' => null,
                'total' => null,
                'items' => [],
            ];
        }

        $estado = $autorizacion->estado ?? '';

        if ($estado !== 'AUTORIZADO') {
            return [
                'status' => 'not_authorized',
                'message' => 'Comprobante no autorizado — estado: ' . $estado,
                'ruc' => $decoded['ruc'],
                'fecha_emision' => $decoded['fecha_emision'],
                'invoice_number' => $decoded['invoice_number'],
                'supplier_name' => null,
                'subtotal' => null,
                'iva' => null,
                'total' => null,
                'items' => [],
            ];
        }

        $xmlComprobante = $autorizacion->comprobante ?? '';
        $xml = simplexml_load_string($xmlComprobante);

        if (!$xml) {
            return [
                'status' => 'parse_error',
                'message' => 'Error al parsear el XML del comprobante',
                'ruc' => $decoded['ruc'],
                'fecha_emision' => $decoded['fecha_emision'],
                'invoice_number' => $decoded['invoice_number'],
                'supplier_name' => null,
                'subtotal' => null,
                'iva' => null,
                'total' => null,
                'items' => [],
            ];
        }

        $supplierName = (string) ($xml->infoTributaria->razonSocial ?? '');
        $supplierRuc = (string) ($xml->infoTributaria->ruc ?? $decoded['ruc']);

        $subtotal = (float) ($xml->infoFactura->totalSinImpuestos ?? 0);
        $total = (float) ($xml->infoFactura->importeTotal ?? 0);

        $iva = 0;
        if (isset($xml->infoFactura->totalConImpuestos->totalImpuesto)) {
            foreach ($xml->infoFactura->totalConImpuestos->totalImpuesto as $imp) {
                if ((string) $imp->codigo === '2') {
                    $iva += (float) $imp->valor;
                }
            }
        }

        $items = [];
        if (isset($xml->detalles->detalle)) {
            foreach ($xml->detalles->detalle as $detalle) {
                $items[] = [
                    'descripcion' => (string) ($detalle->descripcion ?? ''),
                    'cantidad' => (float) ($detalle->cantidad ?? 0),
                    'precio_unitario' => (float) ($detalle->precioUnitario ?? 0),
                    'descuento' => (float) ($detalle->descuento ?? 0),
                    'subtotal' => (float) ($detalle->precioTotalSinImpuesto ?? 0),
                ];
            }
        }

        return [
            'status' => 'authorized',
            'message' => 'Factura autorizada',
            'ruc' => $supplierRuc,
            'fecha_emision' => $decoded['fecha_emision'],
            'invoice_number' => $decoded['invoice_number'],
            'numero_autorizacion' => (string) ($autorizacion->numeroAutorizacion ?? $claveAcceso),
            'supplier_name' => $supplierName,
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'total' => round($total, 2),
            'items' => $items,
        ];
    }
}
