<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        $emailRule = ['required', 'email', 'max:255'];
        $emailRule[] = \Illuminate\Validation\Rule::unique('landlord.users', 'email')
            ->where('tenant_id', \Spatie\Multitenancy\Models\Tenant::current()?->id)
            ->ignore($userId);

        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => $emailRule,
            'password' => $this->isMethod('POST')
                ? ['required', Password::min(8)]
                : ['nullable', Password::min(8)],
            'role'     => ['required', 'string', 'exists:tenant.roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'El nombre es obligatorio.',
            'email.required'    => 'El correo electrónico es obligatorio.',
            'email.unique'      => 'Ya existe un usuario con este correo en el espacio de trabajo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'role.required'     => 'El rol es obligatorio.',
            'role.exists'       => 'El rol seleccionado no es válido.',
        ];
    }
}
