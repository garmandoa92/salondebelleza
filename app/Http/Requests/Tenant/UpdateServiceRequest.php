<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_category_id' => ['required', 'uuid', 'exists:service_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'preparation_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'is_visible' => ['boolean'],
            'requires_consultation' => ['boolean'],
            'iva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'image' => ['nullable', 'image', 'max:2048'],
            'recipe' => ['nullable', 'array'],
            'recipe.*.product_id' => ['required_with:recipe', 'uuid'],
            'recipe.*.quantity' => ['required_with:recipe', 'numeric', 'min:0.01'],
            'recipe.*.unit' => ['required_with:recipe', 'string'],
        ];
    }
}
