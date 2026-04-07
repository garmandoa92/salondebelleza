<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    public function create(array $data, $receiptFile = null): Expense
    {
        return DB::transaction(function () use ($data, $receiptFile) {
            $retentionAmount = '0.00';
            if (!empty($data['has_retention']) && $data['has_retention']) {
                $retentionAmount = bcmul(
                    (string) $data['amount'],
                    bcdiv((string) ($data['retention_percentage'] ?? 0), '100', 4),
                    2
                );
            }

            $total = bcadd((string) $data['amount'], (string) ($data['iva_amount'] ?? 0), 2);

            $expense = Expense::create([
                ...$data,
                'total_amount' => $total,
                'retention_amount' => $retentionAmount,
            ]);

            if ($receiptFile) {
                $path = $receiptFile->store(
                    'expenses/' . $expense->expense_date->format('Y/m'),
                    'local'
                );
                $expense->update(['receipt_file_path' => $path]);
            }

            return $expense;
        });
    }

    public function update(Expense $expense, array $data, $receiptFile = null): Expense
    {
        return DB::transaction(function () use ($expense, $data, $receiptFile) {
            $retentionAmount = '0.00';
            if (!empty($data['has_retention']) && $data['has_retention']) {
                $retentionAmount = bcmul(
                    (string) $data['amount'],
                    bcdiv((string) ($data['retention_percentage'] ?? 0), '100', 4),
                    2
                );
            }

            $total = bcadd((string) $data['amount'], (string) ($data['iva_amount'] ?? 0), 2);

            $expense->update([
                ...$data,
                'total_amount' => $total,
                'retention_amount' => $retentionAmount,
            ]);

            if ($receiptFile) {
                if ($expense->receipt_file_path) {
                    Storage::disk('local')->delete($expense->receipt_file_path);
                }
                $path = $receiptFile->store(
                    'expenses/' . $expense->expense_date->format('Y/m'),
                    'local'
                );
                $expense->update(['receipt_file_path' => $path]);
            }

            return $expense->fresh();
        });
    }

    public function delete(Expense $expense): void
    {
        if ($expense->receipt_file_path) {
            Storage::disk('local')->delete($expense->receipt_file_path);
        }
        $expense->delete();
    }

    /**
     * P&L mensual — IVA cobrado calculado desde SaleItems reales.
     *
     * CRÍTICO: El IVA cobrado NO es un porcentaje fijo sobre el total de ventas.
     * Se suma el iva_amount de cada SaleItem del mes.
     */
    public function getProfitAndLoss(int $year, int $month, ?string $branchId = null): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        // ── INGRESOS ────────────────────────────────────────────────────
        $salesQuery = Sale::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed');

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }

        $sales = $salesQuery->get();
        $saleIds = $sales->pluck('id');

        // IVA cobrado real: suma de iva_amount de cada SaleItem
        $ivaCobratoReal = (float) SaleItem::whereIn('sale_id', $saleIds)->sum('iva_amount');

        $ingresosTotalesConIva = (float) $sales->sum('total');
        $ingresosNetosBase = (float) $sales->sum('subtotal');

        // ── GASTOS ──────────────────────────────────────────────────────
        $expensesQuery = Expense::whereBetween('expense_date', [
            $startDate->toDateString(),
            $endDate->toDateString(),
        ]);

        if ($branchId) {
            $expensesQuery->where('branch_id', $branchId);
        }

        $expenses = $expensesQuery->with('category')->get();
        $totalGastos = (float) $expenses->sum('amount');
        $ivaEnGastos = (float) $expenses->sum('iva_amount');
        $retenciones = (float) $expenses->sum('retention_amount');

        // Gastos por categoría (para gráfico dona)
        $gastosPorCategoria = $expenses->groupBy('expense_category_id')
            ->map(fn ($items) => [
                'category' => $items->first()->category->name,
                'color' => $items->first()->category->color,
                'total' => $items->sum('amount'),
                'count' => $items->count(),
            ])->values();

        // Comisiones del mes
        $comisiones = (float) DB::table('commissions')
            ->whereBetween('period_start', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        // Costo productos e insumos
        $costoProductosId = DB::table('expense_categories')
            ->where('name', 'Productos e insumos')->value('id');

        $costoProductos = $costoProductosId
            ? (float) $expenses->where('expense_category_id', $costoProductosId)->sum('amount')
            : 0;

        $gananciaBruta = (float) bcsub((string) $ingresosNetosBase, (string) $costoProductos, 2);
        $gastosOperativos = (float) bcsub((string) $totalGastos, (string) $costoProductos, 2);
        $utilidadOperacional = (float) bcsub(
            bcsub((string) $gananciaBruta, (string) $gastosOperativos, 2),
            (string) $comisiones,
            2
        );

        // IVA neto a pagar al SRI
        $ivaNetoSri = max(0, (float) bcsub((string) $ivaCobratoReal, (string) $ivaEnGastos, 2));

        return [
            'ingresos_con_iva' => round($ingresosTotalesConIva, 2),
            'iva_cobrado_clientes' => round($ivaCobratoReal, 2),
            'ingresos_netos_base' => round($ingresosNetosBase, 2),
            'costo_productos' => round($costoProductos, 2),
            'ganancia_bruta' => round($gananciaBruta, 2),
            'gastos_operativos' => round($gastosOperativos, 2),
            'comisiones' => round($comisiones, 2),
            'total_gastos' => round($totalGastos, 2),
            'utilidad_operacional' => round($utilidadOperacional, 2),
            'iva_pagado_compras' => round($ivaEnGastos, 2),
            'iva_neto_sri' => round($ivaNetoSri, 2),
            'retenciones_emitidas' => round($retenciones, 2),
            'gastos_por_categoria' => $gastosPorCategoria,
            'total_ventas' => $sales->count(),
            'month' => $month,
            'year' => $year,
        ];
    }

    public function generateRecurringExpenses(int $year, int $month): int
    {
        $generated = 0;
        $today = Carbon::create($year, $month, 1);

        $recurrentes = Expense::where('is_recurring', true)
            ->whereNull('parent_expense_id')
            ->get();

        foreach ($recurrentes as $original) {
            $existe = Expense::where('parent_expense_id', $original->id)
                ->whereYear('expense_date', $year)
                ->whereMonth('expense_date', $month)
                ->exists();

            if ($existe) {
                continue;
            }
            if (!$this->shouldGenerateThisMonth($original, $year, $month)) {
                continue;
            }

            $day = min($original->recurrence_day ?? 1, $today->daysInMonth);
            $expenseDate = Carbon::create($year, $month, $day)->toDateString();

            Expense::create([
                'expense_category_id' => $original->expense_category_id,
                'branch_id' => $original->branch_id,
                'description' => $original->description . ' — ' . $today->translatedFormat('F Y'),
                'amount' => $original->amount,
                'iva_amount' => $original->iva_amount,
                'total_amount' => $original->total_amount,
                'expense_date' => $expenseDate,
                'payment_method' => $original->payment_method,
                'is_deductible' => $original->is_deductible,
                'has_sri_invoice' => false,
                'supplier_name' => $original->supplier_name,
                'supplier_ruc' => $original->supplier_ruc,
                'has_retention' => $original->has_retention,
                'retention_percentage' => $original->retention_percentage,
                'retention_amount' => $original->retention_amount,
                'is_recurring' => false,
                'parent_expense_id' => $original->id,
                'notes' => 'Generado automáticamente desde gasto recurrente #' . $original->id,
            ]);

            $generated++;
        }

        return $generated;
    }

    private function shouldGenerateThisMonth(Expense $expense, int $year, int $month): bool
    {
        $originalDate = Carbon::parse($expense->expense_date);
        $monthsDiff = (($year - $originalDate->year) * 12) + ($month - $originalDate->month);

        if ($monthsDiff <= 0) {
            return false;
        }

        return match ($expense->recurrence_type) {
            'monthly' => true,
            'bimonthly' => $monthsDiff % 2 === 0,
            'quarterly' => $monthsDiff % 3 === 0,
            'annual' => $monthsDiff % 12 === 0,
            default => false,
        };
    }
}
