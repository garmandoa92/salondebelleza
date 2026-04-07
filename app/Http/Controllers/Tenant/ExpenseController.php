<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use App\Services\SriInvoiceQueryService;
use App\Exports\ExpensesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $service) {}

    public function index(Request $request)
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);
        $categoryId = $request->input('category_id');

        $query = Expense::with('category')
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month)
            ->orderBy('expense_date', 'desc');

        if ($categoryId) {
            $query->where('expense_category_id', $categoryId);
        }

        $expenses = $query->paginate(25);
        $categories = ExpenseCategory::orderBy('is_system', 'desc')->orderBy('sort_order')->orderBy('name')->get();
        $pl = $this->service->getProfitAndLoss($year, $month);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'pl' => $pl,
            'filters' => compact('month', 'year', 'categoryId'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'iva_amount' => 'nullable|numeric|min:0',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:cash,transfer,card,check',
            'is_deductible' => 'boolean',
            'has_sri_invoice' => 'boolean',
            'sri_invoice_number' => 'nullable|string|max:20',
            'sri_authorization_number' => 'nullable|string|max:49',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_ruc' => 'nullable|string|max:13',
            'has_retention' => 'boolean',
            'retention_percentage' => 'nullable|numeric|min:0|max:100',
            'is_recurring' => 'boolean',
            'recurrence_type' => 'nullable|in:monthly,bimonthly,quarterly,annual',
            'recurrence_day' => 'nullable|integer|min:1|max:28',
            'notes' => 'nullable|string|max:1000',
            'branch_id' => 'nullable|exists:branches,id',
            'receipt_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $file = $request->file('receipt_file');
        $expense = $this->service->create($validated, $file);

        return response()->json($expense->load('category'), 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'iva_amount' => 'nullable|numeric|min:0',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:cash,transfer,card,check',
            'is_deductible' => 'boolean',
            'has_sri_invoice' => 'boolean',
            'sri_invoice_number' => 'nullable|string|max:20',
            'sri_authorization_number' => 'nullable|string|max:49',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_ruc' => 'nullable|string|max:13',
            'has_retention' => 'boolean',
            'retention_percentage' => 'nullable|numeric|min:0|max:100',
            'is_recurring' => 'boolean',
            'recurrence_type' => 'nullable|in:monthly,bimonthly,quarterly,annual',
            'recurrence_day' => 'nullable|integer|min:1|max:28',
            'notes' => 'nullable|string|max:1000',
            'receipt_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $file = $request->file('receipt_file');
        $updated = $this->service->update($expense, $validated, $file);

        return response()->json($updated->load('category'));
    }

    public function destroy(Expense $expense)
    {
        $this->service->delete($expense);

        return response()->json(['message' => 'Gasto eliminado']);
    }

    public function downloadReceipt(Expense $expense)
    {
        if (!$expense->receipt_file_path) {
            abort(404, 'No hay comprobante adjunto');
        }

        return Storage::disk('local')->download($expense->receipt_file_path);
    }

    public function pl(Request $request)
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        return response()->json($this->service->getProfitAndLoss($year, $month));
    }

    public function export(Request $request)
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);
        $filename = 'gastos-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xlsx';

        return Excel::download(new ExpensesExport($year, $month), $filename);
    }

    public function querySriInvoice(Request $request, SriInvoiceQueryService $sriService)
    {
        $request->validate([
            'clave' => 'required|string|size:49|regex:/^[0-9]+$/',
        ]);

        try {
            $data = $sriService->queryInvoice($request->clave);

            return response()->json($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'invalid',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error consultando SRI: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar el SRI. Ingresa los datos manualmente.',
            ], 500);
        }
    }
}
