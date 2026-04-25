<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractPdfRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'], // 50 MB
        ];
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Selecciona un archivo PDF.',
            'pdf.mimes'    => 'El archivo debe ser un PDF.',
            'pdf.max'      => 'El archivo no puede superar los 50 MB.',
        ];
    }
}
