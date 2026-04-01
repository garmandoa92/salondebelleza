<?php

namespace App\Services\Sri;

use App\Models\SriInvoice;

class SriRideGenerator
{
    /**
     * Generate RIDE PDF HTML.
     * Full PDF generation with dompdf will be implemented when
     * S3 storage is configured. For now, returns HTML string.
     */
    public function generateHtml(SriInvoice $invoice, array $tenantConfig, array $saleItems): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
        $html .= 'body{font-family:Arial,sans-serif;font-size:12px;margin:20px}';
        $html .= 'table{width:100%;border-collapse:collapse;margin:10px 0}';
        $html .= 'th,td{border:1px solid #ddd;padding:6px;text-align:left}';
        $html .= 'th{background:#f5f5f5}';
        $html .= '.header{text-align:center;margin-bottom:20px}';
        $html .= '.totals{text-align:right}';
        $html .= '</style></head><body>';

        // Header
        $html .= '<div class="header">';
        $html .= '<h2>' . htmlspecialchars($tenantConfig['razon_social'] ?? 'SALON') . '</h2>';
        $html .= '<p>RUC: ' . htmlspecialchars($tenantConfig['ruc'] ?? '') . '</p>';
        $html .= '<p>' . htmlspecialchars($tenantConfig['direccion_matriz'] ?? '') . '</p>';
        $html .= '<hr>';
        $html .= '<h3>FACTURA ELECTRONICA</h3>';
        $html .= '<p>' . $invoice->full_number . '</p>';
        $html .= '<p>Clave de acceso: ' . $invoice->access_key . '</p>';
        if ($invoice->sri_authorization_number) {
            $html .= '<p>N° Autorizacion: ' . $invoice->sri_authorization_number . '</p>';
        }
        $html .= '<p>Fecha: ' . $invoice->issue_date->format('d/m/Y') . '</p>';
        $html .= '<p>Ambiente: ' . ($invoice->environment->value === 'production' ? 'PRODUCCION' : 'PRUEBAS') . '</p>';
        $html .= '</div>';

        // Buyer
        $html .= '<table><tr><th>Comprador</th><td>' . htmlspecialchars($invoice->buyer_name ?? 'CONSUMIDOR FINAL') . '</td>';
        $html .= '<th>Identificacion</th><td>' . htmlspecialchars($invoice->buyer_identification ?? '9999999999999') . '</td></tr></table>';

        // Items
        $html .= '<table><thead><tr><th>Descripcion</th><th>Cant.</th><th>P. Unit.</th><th>Desc.</th><th>Subtotal</th></tr></thead><tbody>';
        foreach ($saleItems as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['name']) . '</td>';
            $html .= '<td>' . $item['quantity'] . '</td>';
            $html .= '<td>$' . number_format((float) $item['unit_price'], 2) . '</td>';
            $html .= '<td>$' . number_format((float) ($item['discount_amount'] ?? 0), 2) . '</td>';
            $html .= '<td>$' . number_format((float) $item['subtotal'], 2) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Totals
        $html .= '<div class="totals">';
        if ((float) $invoice->subtotal_0 > 0) {
            $html .= '<p>Subtotal IVA 0%: $' . number_format((float) $invoice->subtotal_0, 2) . '</p>';
        }
        $html .= '<p>Subtotal IVA 15%: $' . number_format((float) $invoice->subtotal_iva, 2) . '</p>';
        $html .= '<p>IVA 15%: $' . number_format((float) $invoice->iva_amount, 2) . '</p>';
        $html .= '<p><strong>TOTAL: $' . number_format((float) $invoice->total, 2) . '</strong></p>';
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }
}
