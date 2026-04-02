<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Sale;
use App\Models\SriInvoice;
use App\Models\Stylist;
use Carbon\Carbon;
class PrintService
{
    public function generateSaleReceipt(Sale $sale): string
    {
        $sale->load(['items.stylist', 'client', 'sriInvoice', 'tipStylist']);

        $settings = tenant()->settings ?? [];
        $salonName = mb_strtoupper(tenant()->name ?? 'SALON');
        $address = tenant()->address ?? '';
        $phone = tenant()->phone ?? '';
        $ruc = tenant()->ruc ?? '';
        $message = $settings['printer_message'] ?? 'Gracias por visitarnos! Te esperamos pronto.';
        $paperSize = $settings['printer_paper_size'] ?? '80mm';

        $stylistNames = $sale->items->pluck('stylist.name')->filter()->unique()->implode(', ');
        $clientName = $sale->client
            ? "{$sale->client->first_name} {$sale->client->last_name}"
            : 'CONSUMIDOR FINAL';

        $itemsHtml = '';
        foreach ($sale->items as $item) {
            $qty = (int) $item->quantity > 1 ? " x{$item->quantity}" : '';
            $name = e($item->name) . $qty;
            $amount = number_format((float) $item->subtotal, 2);
            $itemsHtml .= "<tr><td class=\"item-name\">{$name}</td><td class=\"item-price\">\${$amount}</td></tr>";
        }

        $paymentsHtml = '';
        foreach ($sale->payment_methods ?? [] as $p) {
            $label = match ($p['method'] ?? 'cash') {
                'cash' => 'Efectivo',
                'transfer' => 'Transferencia',
                'card_debit' => 'T. Debito',
                'card_credit' => 'T. Credito',
                default => 'Otro',
            };
            $paymentsHtml .= "<div class=\"line\"><span>{$label}:</span><span>\$" . number_format((float) $p['amount'], 2) . "</span></div>";
        }

        // Calculate change for cash payments
        $changeHtml = '';
        $cashPayment = collect($sale->payment_methods ?? [])->firstWhere('method', 'cash');
        if ($cashPayment && isset($cashPayment['received']) && (float) $cashPayment['received'] > (float) $cashPayment['amount']) {
            $change = (float) $cashPayment['received'] - (float) $cashPayment['amount'];
            $changeHtml = "<div class=\"line\"><span>Vuelto:</span><span>\$" . number_format($change, 2) . "</span></div>";
        }

        // Package info
        $packageHtml = '';
        $packageItem = $sale->items->first(fn ($i) => ($i->type->value ?? $i->type) === 'package');
        if ($packageItem) {
            $packageHtml = '<div class="divider"></div><div class="section-title">PAQUETE</div>'
                . '<div>' . e($packageItem->name) . '</div>';
        }

        // SRI Invoice info
        $invoiceHtml = '';
        if ($sale->sriInvoice) {
            $inv = $sale->sriInvoice;
            $status = $inv->sri_status->value ?? $inv->sri_status ?? 'draft';
            $invoiceHtml = '<div class="divider"></div><div class="section-title">FACTURA ELECTRONICA</div>';
            $invoiceHtml .= "<div>Factura: {$inv->establishment}-{$inv->emission_point}-{$inv->sequential}</div>";
            if ($status === 'authorized' && $inv->sri_authorization_number) {
                $invoiceHtml .= "<div class=\"small\">Aut: {$inv->sri_authorization_number}</div>";
            } else {
                $invoiceHtml .= "<div class=\"small\">Estado: {$status}</div>";
            }
        }

        $date = $sale->completed_at
            ? $sale->completed_at->format('d/m/Y H:i')
            : now()->format('d/m/Y H:i');

        return $this->wrapHtml("Recibo - {$salonName}", $paperSize, "
            <div class=\"header\">
                <div class=\"salon-name\">{$salonName}</div>
                " . ($address ? "<div>{$address}</div>" : '') . "
                " . ($phone ? "<div>Tel: {$phone}</div>" : '') . "
                " . ($ruc ? "<div>RUC: {$ruc}</div>" : '') . "
            </div>
            <div class=\"divider\"></div>
            <div class=\"section-title\">RECIBO DE VENTA</div>
            <div>Fecha: {$date}</div>
            " . ($stylistNames ? "<div>Atendido por: {$stylistNames}</div>" : '') . "
            <div class=\"divider-light\"></div>
            <div>CLIENTE: {$clientName}</div>
            <div class=\"divider\"></div>
            <div class=\"section-title\">DETALLE:</div>
            <table class=\"items\">{$itemsHtml}</table>
            <div class=\"divider-light\"></div>
            <div class=\"line\"><span>Subtotal:</span><span>\$" . number_format((float) $sale->subtotal, 2) . "</span></div>
            " . ((float) $sale->discount_amount > 0 ? "<div class=\"line text-red\"><span>Descuento:</span><span>-\$" . number_format((float) $sale->discount_amount, 2) . "</span></div>" : '') . "
            " . ((float) $sale->iva_amount > 0 ? "<div class=\"line\"><span>IVA " . (int) $sale->iva_rate . "%:</span><span>\$" . number_format((float) $sale->iva_amount, 2) . "</span></div>" : '') . "
            <div class=\"line total\"><span>TOTAL:</span><span>\$" . number_format((float) $sale->total, 2) . "</span></div>
            " . ((float) ($sale->tip ?? 0) > 0 ? "<div class=\"line\"><span>Propina:</span><span>+\$" . number_format((float) $sale->tip, 2) . "</span></div>" : '') . "
            <div class=\"divider\"></div>
            <div class=\"section-title\">FORMA DE PAGO:</div>
            {$paymentsHtml}
            {$changeHtml}
            {$packageHtml}
            {$invoiceHtml}
            <div class=\"divider\"></div>
            <div class=\"footer\">{$message}</div>
        ");
    }

    public function generateAppointmentTicket(Appointment $appointment): string
    {
        $appointment->load(['client', 'service', 'stylist']);

        $settings = tenant()->settings ?? [];
        $salonName = mb_strtoupper(tenant()->name ?? 'SALON');
        $phone = tenant()->phone ?? '';
        $paperSize = $settings['printer_paper_size'] ?? '80mm';

        $clientName = $appointment->client
            ? "{$appointment->client->first_name} {$appointment->client->last_name}"
            : 'Sin cliente';

        $date = Carbon::parse($appointment->starts_at);
        $dayName = $date->translatedFormat('l');
        $dateStr = $date->format('d/m/Y');
        $time = $date->format('H:i');
        $duration = $appointment->service->duration_minutes ?? 30;

        return $this->wrapHtml("Cita - {$salonName}", $paperSize, "
            <div class=\"header\">
                <div class=\"salon-name\">{$salonName}</div>
            </div>
            <div class=\"divider\"></div>
            <div class=\"section-title\">CONFIRMACION DE CITA</div>
            <br>
            <div>Cliente: {$clientName}</div>
            <div>Servicio: " . e($appointment->service->name ?? '-') . "</div>
            <div>Estilista: " . e($appointment->stylist->name ?? '-') . "</div>
            <div>Fecha: {$dayName} {$dateStr} a las {$time}</div>
            <div>Duracion: {$duration} minutos</div>
            <div class=\"divider\"></div>
            <div class=\"footer\">
                Para cancelar o reagendar:
                " . ($phone ? "<br>WhatsApp: {$phone}" : '') . "
            </div>
        ");
    }

    public function generateDailyClosing(Carbon $date): string
    {
        $settings = tenant()->settings ?? [];
        $salonName = mb_strtoupper(tenant()->name ?? 'SALON');
        $paperSize = $settings['printer_paper_size'] ?? '80mm';

        $sales = Sale::whereDate('completed_at', $date)
            ->where('status', 'completed')
            ->with(['items.stylist', 'items.commission'])
            ->get();

        $totalSales = $sales->count();
        $totalAmount = $sales->sum('total');
        $totalIva = $sales->sum('iva_amount');
        $totalTips = $sales->sum('tip');

        // By payment method
        $byMethod = ['cash' => 0, 'transfer' => 0, 'card_debit' => 0, 'card_credit' => 0];
        foreach ($sales as $sale) {
            foreach ($sale->payment_methods ?? [] as $p) {
                $method = $p['method'] ?? 'cash';
                $byMethod[$method] = ($byMethod[$method] ?? 0) + (float) $p['amount'];
            }
        }

        $methodLabels = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card_debit' => 'Tarjeta debito', 'card_credit' => 'Tarjeta credito'];
        $methodsHtml = '';
        foreach ($byMethod as $method => $amount) {
            if ($amount > 0) {
                $methodsHtml .= "<div class=\"line\"><span>{$methodLabels[$method]}:</span><span>\$" . number_format($amount, 2) . "</span></div>";
            }
        }

        // By stylist
        $stylistData = [];
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $sName = $item->stylist->name ?? 'Sin asignar';
                if (!isset($stylistData[$sName])) {
                    $stylistData[$sName] = ['count' => 0, 'total' => 0, 'commission' => 0];
                }
                $stylistData[$sName]['count']++;
                $stylistData[$sName]['total'] += (float) $item->subtotal;
                if ($item->commission) {
                    $stylistData[$sName]['commission'] += (float) $item->commission->amount;
                }
            }
        }

        $stylistsHtml = '';
        foreach ($stylistData as $name => $data) {
            $stylistsHtml .= "<div class=\"stylist-row\">"
                . "<div class=\"stylist-name\">[{$name}]</div>"
                . "<div>  Servicios: {$data['count']} · Total: \$" . number_format($data['total'], 2) . "</div>"
                . "<div>  Comision: \$" . number_format($data['commission'], 2) . "</div>"
                . "</div>";
        }

        $dateStr = $date->format('d/m/Y');
        $now = now()->format('d/m/Y H:i');
        $user = auth()->user()->name ?? 'Sistema';

        return $this->wrapHtml("Cierre de Caja - {$dateStr}", $paperSize, "
            <div class=\"header\">
                <div class=\"section-title\">CIERRE DE CAJA</div>
                <div class=\"salon-name\">{$salonName}</div>
                <div>{$dateStr}</div>
            </div>
            <div class=\"divider\"></div>
            <div class=\"section-title\">RESUMEN DE VENTAS:</div>
            <div class=\"line\"><span>Ventas completadas:</span><span>{$totalSales}</span></div>
            <div class=\"line total\"><span>Total facturado:</span><span>\$" . number_format($totalAmount, 2) . "</span></div>
            <div class=\"divider-light\"></div>
            <div class=\"section-title\">POR METODO DE PAGO:</div>
            {$methodsHtml}
            <div class=\"divider-light\"></div>
            <div class=\"line\"><span>IVA generado:</span><span>\$" . number_format($totalIva, 2) . "</span></div>
            <div class=\"line\"><span>Propinas del dia:</span><span>\$" . number_format($totalTips, 2) . "</span></div>
            <div class=\"divider\"></div>
            <div class=\"section-title\">POR ESTILISTA:</div>
            {$stylistsHtml}
            <div class=\"divider\"></div>
            <div>Impreso: {$now}</div>
            <div>Usuario: {$user}</div>
        ");
    }

    public function generateCommissionReport(Stylist $stylist, Carbon $from, Carbon $to): string
    {
        $settings = tenant()->settings ?? [];
        $salonName = mb_strtoupper(tenant()->name ?? 'SALON');
        $paperSize = $settings['printer_paper_size'] ?? '80mm';

        $commissions = Commission::where('stylist_id', $stylist->id)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('saleItem.sale')
            ->get();

        $totalAmount = $commissions->sum('amount');
        $totalServices = $commissions->count();
        $totalBase = $commissions->sum(fn ($c) => (float) ($c->saleItem->subtotal ?? 0));

        $detailHtml = '';
        foreach ($commissions as $c) {
            $dateStr = $c->created_at->format('d/m');
            $name = e($c->saleItem->name ?? '-');
            $amount = number_format((float) $c->amount, 2);
            $rate = (int) $c->rate;
            $detailHtml .= "<div class=\"small\">{$dateStr} {$name} ({$rate}%) \${$amount}</div>";
        }

        return $this->wrapHtml("Comisiones - {$stylist->name}", $paperSize, "
            <div class=\"header\">
                <div class=\"section-title\">LIQUIDACION DE COMISIONES</div>
                <div class=\"salon-name\">{$salonName}</div>
            </div>
            <div class=\"divider\"></div>
            <div>Estilista: " . e($stylist->name) . "</div>
            <div>Periodo: {$from->format('d/m/Y')} - {$to->format('d/m/Y')}</div>
            <div class=\"divider\"></div>
            <div class=\"line\"><span>Servicios:</span><span>{$totalServices}</span></div>
            <div class=\"line\"><span>Base total:</span><span>\$" . number_format($totalBase, 2) . "</span></div>
            <div class=\"line total\"><span>Total comision:</span><span>\$" . number_format($totalAmount, 2) . "</span></div>
            <div class=\"divider\"></div>
            <div class=\"section-title\">DETALLE:</div>
            {$detailHtml}
            <div class=\"divider\"></div>
            <div class=\"small\">Impreso: " . now()->format('d/m/Y H:i') . "</div>
        ");
    }

    public function generateInvoiceRide(SriInvoice $invoice): string
    {
        $invoice->load(['sale.items', 'sale.client']);

        $settings = tenant()->settings ?? [];
        $salonName = mb_strtoupper(tenant()->razon_social ?? tenant()->name ?? 'SALON');
        $ruc = tenant()->ruc ?? '';
        $address = tenant()->address ?? '';
        $paperSize = $settings['printer_paper_size'] ?? '80mm';
        $obligado = $settings['obligado_contabilidad'] ?? 'NO';
        $message = $settings['printer_message'] ?? 'Gracias por su compra';

        $fullNumber = "{$invoice->establishment}-{$invoice->emission_point}-{$invoice->sequential}";
        $env = ($invoice->environment->value ?? $invoice->environment) === 'production' ? 'PRODUCCION' : 'PRUEBAS';
        $status = $invoice->sri_status->value ?? $invoice->sri_status ?? 'draft';
        $date = $invoice->issue_date?->format('d/m/Y') ?? '-';
        $client = $invoice->sale?->client;

        // Items with code, qty x price format
        $itemsHtml = '';
        $idx = 1;
        foreach ($invoice->sale?->items ?? [] as $item) {
            $code = 'SRV-' . str_pad((string) $idx, 3, '0', STR_PAD_LEFT);
            $qty = number_format((float) $item->quantity, 2);
            $price = number_format((float) $item->unit_price, 2);
            $sub = number_format((float) $item->subtotal, 2);
            $itemsHtml .= '<div class="section-title">' . e($item->name) . '</div>'
                . "<div>Codigo: {$code}</div>"
                . "<div>{$qty} x \${$price} \${$sub}</div>";
            $idx++;
        }

        // Totals breakdown
        $subtotalSinImp = (float) $invoice->subtotal_0 + (float) $invoice->subtotal_iva;
        $totalsHtml = '<div class="line"><span>SUBTOTAL SIN IMPUESTOS:</span><span>$' . number_format($subtotalSinImp, 2) . '</span></div>'
            . '<div class="line"><span>SUBTOTAL IVA 0%:</span><span>$' . number_format((float) $invoice->subtotal_0, 2) . '</span></div>'
            . '<div class="line"><span>SUBTOTAL IVA ' . (int) $invoice->iva_rate . '%:</span><span>$' . number_format((float) $invoice->subtotal_iva, 2) . '</span></div>'
            . '<div class="line"><span>TOTAL DESCUENTO:</span><span>$' . number_format((float) ($invoice->sale?->discount_amount ?? 0), 2) . '</span></div>'
            . '<div class="line"><span>ICE:</span><span>$0.00</span></div>'
            . '<div class="line"><span>IVA ' . (int) $invoice->iva_rate . '%:</span><span>$' . number_format((float) $invoice->iva_amount, 2) . '</span></div>'
            . '<div class="line"><span>IRBPNR:</span><span>$0.00</span></div>'
            . '<div class="line"><span>PROPINA:</span><span>$' . number_format((float) ($invoice->sale?->tip ?? 0), 2) . '</span></div>';

        // Payment methods
        $paymentLabels = ['cash' => 'EFECTIVO', 'transfer' => 'OTROS CON UTILIZACION DEL SISTEMA FINANCIERO', 'card_debit' => 'TARJETA DE DEBITO', 'card_credit' => 'TARJETA DE CREDITO', 'other' => 'OTROS'];
        $paymentsHtml = '';
        foreach ($invoice->sale?->payment_methods ?? [['method' => 'cash', 'amount' => (float) $invoice->total]] as $p) {
            $label = $paymentLabels[$p['method'] ?? 'cash'] ?? 'OTROS';
            $paymentsHtml .= "<div>{$label}</div><div>\$" . number_format((float) $p['amount'], 2) . '</div>';
        }

        // Authorization
        $authHtml = '';
        if ($status === 'authorized' && $invoice->sri_authorization_number) {
            $authHtml = '<div class="divider"></div>'
                . '<div class="section-title" style="text-align:center">AUTORIZACION SRI</div>'
                . '<div>No. Autorizacion:</div>'
                . '<div style="word-break:break-all;">' . $invoice->sri_authorization_number . '</div>'
                . '<div>Fecha: ' . ($invoice->sri_authorization_date?->format('d/m/Y H:i') ?? '-') . '</div>';
        }

        // Access key
        $accessKey = $invoice->access_key ?? '';
        $claveHtml = '<div class="divider"></div>'
            . '<div class="section-title" style="text-align:center">CLAVE DE ACCESO</div>'
            . '<div style="word-break:break-all;">' . $accessKey . '</div>';

        return $this->wrapHtml("RIDE - {$fullNumber}", $paperSize, "
            <div class=\"header\">
                <div class=\"salon-name\">{$salonName}</div>
                <div>RUC: {$ruc}</div>
                <div>{$address}</div>
            </div>
            <div class=\"divider\"></div>
            <div class=\"header\">
                <div class=\"section-title\">FACTURA</div>
                <div class=\"total\">{$fullNumber}</div>
            </div>
            <div class=\"divider-light\"></div>
            <div>Fecha: {$date}</div>
            <div>Ambiente: {$env}</div>
            <div class=\"divider-light\"></div>
            <div class=\"section-title\" style=\"text-align:center\">CLIENTE</div>
            <div>" . e($invoice->buyer_name ?? 'CONSUMIDOR FINAL') . "</div>
            <div>CE/RUC: " . e($invoice->buyer_identification ?? '9999999999999') . "</div>
            " . ($client && $client->email ? '<div>Email: ' . e($client->email) . '</div>' : '') . "
            " . ($client && $client->phone ? '<div>Tel: ' . e($client->phone) . '</div>' : '') . "
            <div class=\"divider\"></div>
            <div class=\"section-title\" style=\"text-align:center\">DETALLE</div>
            {$itemsHtml}
            <div class=\"divider-light\"></div>
            {$totalsHtml}
            <div class=\"divider\"></div>
            <div class=\"line total\"><span>VALOR TOTAL</span><span>\$" . number_format((float) $invoice->total, 2) . "</span></div>
            <div class=\"divider\"></div>
            <div class=\"section-title\" style=\"text-align:center\">FORMA DE PAGO</div>
            {$paymentsHtml}
            {$authHtml}
            {$claveHtml}
            <div class=\"divider-light\"></div>
            <div>Obligado a llevar contabilidad: {$obligado}</div>
            <div class=\"divider\"></div>
            <div class=\"footer\">{$message}</div>
        ");
    }

    private function wrapHtml(string $title, string $paperSize, string $body): string
    {
        $width = $paperSize === '58mm' ? '54mm' : '76mm';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        @media print {
            @page { size: {$paperSize} 2000mm; margin: 0; }
            .no-print { display: none !important; }
            html, body { margin: 0; padding: 0; width: {$paperSize}; }
            .ticket { padding: 2mm; width: {$width}; }
        }
        @media screen {
            body { display: flex; flex-direction: column; align-items: center; background: #f3f4f6; padding: 20px; }
            .ticket { box-shadow: 0 2px 8px rgba(0,0,0,0.15); background: #fff; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: monospace; font-size: 16px; color: #000; line-height: 1.4; }
        .ticket { width: {$width}; padding: 4mm; }
        .header { text-align: center; margin-bottom: 3mm; }
        .salon-name { font-size: 20px; font-weight: bold; }
        .section-title { font-weight: bold; font-size: 16px; margin: 3mm 0 2mm; }
        .divider { border-top: 2px dashed #000; margin: 4mm 0; }
        .divider-light { border-top: 1px dotted #999; margin: 3mm 0; }
        .line { display: flex; justify-content: space-between; margin: 2px 0; font-size: 15px; }
        .total { font-size: 20px; font-weight: bold; margin: 3mm 0; }
        .small { font-size: 12px; color: #333; }
        .text-red { color: #c00; }
        .footer { text-align: center; font-size: 14px; margin-top: 3mm; }
        .items { width: 100%; border-collapse: collapse; font-size: 15px; }
        .items td { padding: 3px 0; }
        .item-name { }
        .item-price { text-align: right; white-space: nowrap; }
        .stylist-row { margin-bottom: 3mm; }
        .stylist-name { font-weight: bold; font-size: 16px; }
        .btn-print {
            display: block; width: 100%; padding: 12px; margin: 16px 0;
            background: #2563eb; color: #fff; border: none; border-radius: 8px;
            font-size: 16px; cursor: pointer; font-weight: bold;
        }
        .btn-print:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="no-print" style="width: {$width}; margin-bottom: 8px;">
        <button class="btn-print" onclick="window.print()">Imprimir</button>
    </div>
    <div class="ticket">{$body}</div>
    <div class="no-print" style="width: {$width}; margin-top: 8px;">
        <button class="btn-print" onclick="window.close()" style="background: #6b7280;">Cerrar</button>
    </div>
</body>
</html>
HTML;
    }
}
