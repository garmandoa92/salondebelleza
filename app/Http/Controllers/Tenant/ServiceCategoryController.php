<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceCategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $maxOrder = ServiceCategory::max('sort_order') ?? 0;
        $data['sort_order'] = $maxOrder + 1;

        ServiceCategory::create($data);

        return back()->with('success', 'Categoria creada.');
    }

    public function update(Request $request, ServiceCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $category->update($data);

        return back()->with('success', 'Categoria actualizada.');
    }

    public function destroy(ServiceCategory $category)
    {
        if ($category->services()->exists()) {
            return back()->withErrors(['category' => 'No se puede eliminar una categoria con servicios.']);
        }

        $category->delete();

        return back()->with('success', 'Categoria eliminada.');
    }
}
