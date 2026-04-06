<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStylistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['uuid'],
            'specialties' => ['nullable', 'array'],
            'specialties.*' => ['uuid', 'exists:service_categories,id'],
            'schedule' => ['nullable', 'array'],
            'commission_rules' => ['nullable', 'array'],
            'commission_rules.default' => ['required_with:commission_rules', 'numeric', 'between:0,100'],
            'commission_rules.by_category' => ['nullable', 'array'],
            'commission_rules.by_category.*' => ['numeric', 'between:0,100'],
        ];
    }
}
