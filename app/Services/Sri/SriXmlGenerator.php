<?php

namespace App\Services\Sri;

use App\Models\SriInvoice;
use DOMDocument;

class SriXmlGenerator
{
    private array $paymentMethodCodes = [
        'cash' => '01',
        'transfer' => '20',
        'card_debit' => '16',
        'card_credit' => '19',
        'other' => '20',
    ];

    private array $buyerIdCodes = [
        'final_consumer' => '07',
        'cedula' => '05',
        'RUC' => '04',
        'passport' => '06',
    ];

    public function generate(SriInvoice $invoice, array $tenantConfig, array $saleItems, array $payments): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('factura');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '2.1.0');
        $doc->appendChild($root);

        // infoTributaria
        $infoTrib = $doc->createElement('infoTributaria');
        $this->addElement($doc, $infoTrib, 'ambiente', $invoice->environment->value === 'production' ? '2' : '1');
        $this->addElement($doc, $infoTrib, 'tipoEmision', '1');
        $this->addElement($doc, $infoTrib, 'razonSocial', $tenantConfig['razon_social'] ?? 'SALON');
        $this->addElement($doc, $infoTrib, 'nombreComercial', $tenantConfig['nombre_comercial'] ?? $tenantConfig['razon_social'] ?? 'SALON');
        $this->addElement($doc, $infoTrib, 'ruc', $tenantConfig['ruc'] ?? '0000000000001');
        $this->addElement($doc, $infoTrib, 'claveAcceso', $invoice->access_key);
        $this->addElement($doc, $infoTrib, 'codDoc', '01');
        $this->addElement($doc, $infoTrib, 'estab', $invoice->establishment);
        $this->addElement($doc, $infoTrib, 'ptoEmi', $invoice->emission_point);
        $this->addElement($doc, $infoTrib, 'secuencial', $invoice->sequential);
        $this->addElement($doc, $infoTrib, 'dirMatriz', $tenantConfig['direccion_matriz'] ?? 'Ecuador');

        $regimen = $tenantConfig['regimen_tributario'] ?? 'general';
        if ($regimen === 'rimpe_emprendedor') {
            $this->addElement($doc, $infoTrib, 'regimenMicroempresa', 'CONTRIBUYENTE REGIMEN MICROEMPRESAS');
        } elseif ($regimen === 'rimpe_negocio_popular') {
            $this->addElement($doc, $infoTrib, 'contribuyenteRimpe', 'CONTRIBUYENTE NEGOCIO POPULAR');
        }

        $root->appendChild($infoTrib);

        // infoFactura
        $infoFact = $doc->createElement('infoFactura');
        $this->addElement($doc, $infoFact, 'fechaEmision', $invoice->issue_date->format('d/m/Y'));
        $this->addElement($doc, $infoFact, 'dirEstablecimiento', $tenantConfig['direccion_matriz'] ?? 'Ecuador');
        $this->addElement($doc, $infoFact, 'obligadoContabilidad', $tenantConfig['obligado_contabilidad'] ?? 'NO');

        $buyerIdCode = $this->buyerIdCodes[$invoice->buyer_identification_type->value ?? 'final_consumer'] ?? '07';
        $this->addElement($doc, $infoFact, 'tipoIdentificacionComprador', $buyerIdCode);
        $this->addElement($doc, $infoFact, 'razonSocialComprador', $invoice->buyer_name ?? 'CONSUMIDOR FINAL');
        $this->addElement($doc, $infoFact, 'identificacionComprador', $invoice->buyer_identification ?? '9999999999999');
        $this->addElement($doc, $infoFact, 'totalSinImpuestos', number_format((float) $invoice->subtotal_iva + (float) $invoice->subtotal_0, 2, '.', ''));
        $this->addElement($doc, $infoFact, 'totalDescuento', '0.00');

        // totalConImpuestos
        $totalImpuestos = $doc->createElement('totalConImpuestos');
        if ((float) $invoice->subtotal_iva > 0) {
            $totalImp = $doc->createElement('totalImpuesto');
            $this->addElement($doc, $totalImp, 'codigo', '2');
            $this->addElement($doc, $totalImp, 'codigoPorcentaje', '4');
            $this->addElement($doc, $totalImp, 'baseImponible', number_format((float) $invoice->subtotal_iva, 2, '.', ''));
            $this->addElement($doc, $totalImp, 'valor', number_format((float) $invoice->iva_amount, 2, '.', ''));
            $totalImpuestos->appendChild($totalImp);
        }
        if ((float) $invoice->subtotal_0 > 0) {
            $totalImp0 = $doc->createElement('totalImpuesto');
            $this->addElement($doc, $totalImp0, 'codigo', '2');
            $this->addElement($doc, $totalImp0, 'codigoPorcentaje', '0');
            $this->addElement($doc, $totalImp0, 'baseImponible', number_format((float) $invoice->subtotal_0, 2, '.', ''));
            $this->addElement($doc, $totalImp0, 'valor', '0.00');
            $totalImpuestos->appendChild($totalImp0);
        }
        $infoFact->appendChild($totalImpuestos);

        $this->addElement($doc, $infoFact, 'propina', '0.00');
        $this->addElement($doc, $infoFact, 'importeTotal', number_format((float) $invoice->total, 2, '.', ''));
        $this->addElement($doc, $infoFact, 'moneda', 'DOLAR');

        // pagos
        $pagos = $doc->createElement('pagos');
        foreach ($payments as $payment) {
            $pago = $doc->createElement('pago');
            $code = $this->paymentMethodCodes[$payment['method'] ?? 'cash'] ?? '01';
            $this->addElement($doc, $pago, 'formaPago', $code);
            $this->addElement($doc, $pago, 'total', number_format((float) $payment['amount'], 2, '.', ''));
            $this->addElement($doc, $pago, 'plazo', '0');
            $this->addElement($doc, $pago, 'unidadTiempo', 'dias');
            $pagos->appendChild($pago);
        }
        $infoFact->appendChild($pagos);
        $root->appendChild($infoFact);

        // detalles
        $detalles = $doc->createElement('detalles');
        foreach ($saleItems as $i => $item) {
            $detalle = $doc->createElement('detalle');
            $this->addElement($doc, $detalle, 'codigoPrincipal', 'SRV-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT));
            $this->addElement($doc, $detalle, 'descripcion', $item['name']);
            $this->addElement($doc, $detalle, 'cantidad', number_format((float) $item['quantity'], 6, '.', ''));
            $this->addElement($doc, $detalle, 'precioUnitario', number_format((float) $item['unit_price'], 6, '.', ''));
            $this->addElement($doc, $detalle, 'descuento', number_format((float) ($item['discount_amount'] ?? 0), 2, '.', ''));
            $this->addElement($doc, $detalle, 'precioTotalSinImpuesto', number_format((float) $item['subtotal'], 2, '.', ''));

            $impuestos = $doc->createElement('impuestos');
            $impuesto = $doc->createElement('impuesto');
            $this->addElement($doc, $impuesto, 'codigo', '2');
            $this->addElement($doc, $impuesto, 'codigoPorcentaje', '4');
            $this->addElement($doc, $impuesto, 'tarifa', '15.00');
            $this->addElement($doc, $impuesto, 'baseImponible', number_format((float) $item['subtotal'], 2, '.', ''));
            $this->addElement($doc, $impuesto, 'valor', number_format((float) ($item['iva_amount'] ?? 0), 2, '.', ''));
            $impuestos->appendChild($impuesto);
            $detalle->appendChild($impuestos);

            $detalles->appendChild($detalle);
        }
        $root->appendChild($detalles);

        // infoAdicional
        if ($invoice->buyer_email || $invoice->buyer_identification) {
            $infoAdicional = $doc->createElement('infoAdicional');
            if ($invoice->buyer_email) {
                $campo = $doc->createElement('campoAdicional', $invoice->buyer_email);
                $campo->setAttribute('nombre', 'Email');
                $infoAdicional->appendChild($campo);
            }
            $root->appendChild($infoAdicional);
        }

        return $doc->saveXML();
    }

    private function addElement(DOMDocument $doc, \DOMElement $parent, string $name, string $value): void
    {
        $parent->appendChild($doc->createElement($name, htmlspecialchars($value)));
    }
}
