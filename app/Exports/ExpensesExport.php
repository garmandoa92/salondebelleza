<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExpensesExport implements WithMultipleSheets
{
    public function __construct(private int $year, private int $month) {}

    public function sheets(): array
    {
        return [
            new ExpensesExport\PlSheet($this->year, $this->month),
            new ExpensesExport\ExpensesDetailSheet($this->year, $this->month),
            new ExpensesExport\RecurringExpensesSheet(),
        ];
    }
}
