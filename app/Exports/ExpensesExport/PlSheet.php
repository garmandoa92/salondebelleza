<?php

namespace App\Exports\ExpensesExport;

use App\Services\ExpenseService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlSheet implements FromArray, WithTitle, WithStyles
{
    private array $pl;

    public function __construct(private int $year, private int $month)
    {
        $this->pl = app(ExpenseService::class)->getProfitAndLoss($year, $month);
    }

    public function title(): string
    {
        return 'P&L';
    }

    public function array(): array
    {
        $pl = $this->pl;
        $periodo = Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y');

        return [
            ["ESTADO DE RESULTADOS — {$periodo}"],
            [],
            ['INGRESOS', '', ''],
            ['Total cobrado a clientes (con IVA)', '', $pl['ingresos_con_iva']],
            ['  (-) IVA cobrado — obligación tributaria SRI', '', -$pl['iva_cobrado_clientes']],
            ['INGRESOS NETOS (base imponible)', '', $pl['ingresos_netos_base']],
            [],
            ['COSTOS Y GASTOS', '', ''],
            ['  Costo de productos e insumos', '', -$pl['costo_productos']],
            ['GANANCIA BRUTA', '', $pl['ganancia_bruta']],
            [],
            ['  Gastos operativos', '', -$pl['gastos_operativos']],
            ['  Comisiones a estilistas', '', -$pl['comisiones']],
            [],
            ['UTILIDAD OPERACIONAL', '', $pl['utilidad_operacional']],
            [],
            ['INFORMACIÓN TRIBUTARIA (referencial)', '', ''],
            ['  IVA cobrado a clientes', '', $pl['iva_cobrado_clientes']],
            ['  IVA pagado en compras (crédito tributario)', '', -$pl['iva_pagado_compras']],
            ['  IVA neto a declarar al SRI', '', $pl['iva_neto_sri']],
            ['  Retenciones en la fuente emitidas', '', $pl['retenciones_emitidas']],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $utilityColor = $this->pl['utilidad_operacional'] >= 0 ? '059669' : 'DC2626';

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            3 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8F4F0']]],
            6 => ['font' => ['bold' => true, 'color' => ['rgb' => '4A7C6F']]],
            8 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8F4F0']]],
            10 => ['font' => ['bold' => true, 'color' => ['rgb' => '4A7C6F']]],
            15 => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $utilityColor]]],
            17 => ['font' => ['bold' => true, 'color' => ['rgb' => '6B7280']]],
        ];
    }
}
