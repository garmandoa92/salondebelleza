<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::orderBy('is_system', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $category = ExpenseCategory::create([
            ...$validated,
            'is_system' => false,
            'sort_order' => (ExpenseCategory::max('sort_order') ?? 0) + 1,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->is_system) {
            return response()->json(['error' => 'Las categorías del sistema no se pueden editar'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $expenseCategory->update($validated);

        return response()->json($expenseCategory);
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->is_system) {
            return response()->json(['error' => 'Las categorías del sistema no se pueden eliminar'], 403);
        }

        if ($expenseCategory->expenses()->exists()) {
            return response()->json([
                'error' => 'No se puede eliminar una categoría con gastos registrados',
            ], 422);
        }

        $expenseCategory->delete();

        return response()->json(['message' => 'Categoría eliminada']);
    }
}
