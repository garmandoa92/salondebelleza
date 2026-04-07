<?php

namespace App\Exports\ExpensesExport;

use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class RecurringExpensesSheet implements FromCollection, WithHeadings, WithTitle, WithMapping
{
    public function title(): string
    {
        return 'Gastos Recurrentes';
    }

    public function headings(): array
    {
        return ['Descripción', 'Categoría', 'Monto', 'Frecuencia', 'Día del mes', 'Proveedor', 'Deducible'];
    }

    public function collection()
    {
        return Expense::with('category')
            ->where('is_recurring', true)
            ->whereNull('parent_expense_id')
            ->orderBy('description')
            ->get();
    }

    public function map($expense): array
    {
        return [
            $expense->description,
            $expense->category->name,
            $expense->total_amount,
            match ($expense->recurrence_type) {
                'monthly' => 'Mensual',
                'bimonthly' => 'Bimestral',
                'quarterly' => 'Trimestral',
                'annual' => 'Anual',
                default => '—',
            },
            $expense->recurrence_day ?? '—',
            $expense->supplier_name ?? '—',
            $expense->is_deductible ? 'Sí' : 'No',
        ];
    }
}
