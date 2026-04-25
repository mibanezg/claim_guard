<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            'name'          => ['required', 'string', 'max:255'],
            'rut'           => ['required', 'string', 'max:20', Rule::unique('tenant.companies')->ignore($companyId)->withoutTrashed()],
            'address'       => ['nullable', 'string', 'max:500'],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'type'          => ['required', Rule::in(['mandante', 'contratista', 'ambos'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'El nombre de la empresa es obligatorio.',
            'rut.required'   => 'El RUT es obligatorio.',
            'rut.unique'     => 'Ya existe una empresa con este RUT.',
            'type.required'  => 'El tipo de empresa es obligatorio.',
            'type.in'        => 'El tipo debe ser mandante, contratista o ambos.',
        ];
    }
}
