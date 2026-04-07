<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'expense_category_id',
        'branch_id',
        'description',
        'amount',
        'iva_amount',
        'total_amount',
        'expense_date',
        'payment_method',
        'is_deductible',
        'has_sri_invoice',
        'sri_invoice_number',
        'sri_authorization_number',
        'supplier_name',
        'supplier_ruc',
        'receipt_file_path',
        'has_retention',
        'retention_percentage',
        'retention_amount',
        'is_recurring',
        'recurrence_type',
        'recurrence_day',
        'parent_expense_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'retention_percentage' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'expense_date' => 'date',
            'is_deductible' => 'boolean',
            'has_sri_invoice' => 'boolean',
            'has_retention' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_day' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function parentExpense()
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    }

    public function childExpenses()
    {
        return $this->hasMany(Expense::class, 'parent_expense_id');
    }
}
