<?php

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salon_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'alpha_dash:ascii', 'unique:tenants,slug'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:tenant_users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Este nombre de subdominio ya está en uso.',
            'slug.alpha_dash' => 'El subdominio solo puede contener letras, números, guiones y guiones bajos.',
            'email.unique' => 'Este email ya está registrado.',
        ];
    }
}
