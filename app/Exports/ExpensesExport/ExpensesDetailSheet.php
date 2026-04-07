<?php

namespace App\Exports\ExpensesExport;

use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ExpensesDetailSheet implements FromCollection, WithHeadings, WithTitle, WithMapping
{
    public function __construct(private int $year, private int $month) {}

    public function title(): string
    {
        return 'Detalle Gastos';
    }

    public function headings(): array
    {
        return [
            'Fecha', 'Descripción', 'Categoría', 'Monto Base',
            'IVA Gasto', 'Total', 'Proveedor', 'RUC Proveedor',
            'N° Factura', 'Deducible', 'Retención %', 'Retención $',
            'Método Pago', 'Recurrente',
        ];
    }

    public function collection()
    {
        return Expense::with('category')
            ->whereYear('expense_date', $this->year)
            ->whereMonth('expense_date', $this->month)
            ->orderBy('expense_date')
            ->get();
    }

    public function map($expense): array
    {
        return [
            $expense->expense_date->format('d/m/Y'),
            $expense->description,
            $expense->category->name,
            $expense->amount,
            $expense->iva_amount,
            $expense->total_amount,
            $expense->supplier_name ?? '—',
            $expense->supplier_ruc ?? '—',
            $expense->sri_invoice_number ?? '—',
            $expense->is_deductible ? 'Sí' : 'No',
            $expense->has_retention ? $expense->retention_percentage . '%' : '—',
            $expense->has_retention ? $expense->retention_amount : '—',
            match ($expense->payment_method) {
                'cash' => 'Efectivo',
                'transfer' => 'Transferencia',
                'card' => 'Tarjeta',
                'check' => 'Cheque',
                default => $expense->payment_method,
            },
            $expense->is_recurring ? 'Sí' : 'No',
        ];
    }
}
