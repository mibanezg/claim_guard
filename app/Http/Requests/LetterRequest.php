<?php

namespace App\Http\Requests;

use App\Models\ContractLetter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LetterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'letter_number'   => ['required', 'string', 'max:100'],
            'type'            => ['required', Rule::in(array_keys(ContractLetter::TYPE_LABELS))],
            'subject'         => ['required', 'string', 'max:500'],
            'from_company_id' => ['required', 'integer'],
            'to_company_id'   => ['required', 'integer', 'different:from_company_id'],
            'issued_at'       => ['nullable', 'date'],
            'received_at'     => ['nullable', 'date'],
            'response_days'   => ['nullable', 'integer', 'min:0'],
            'status'          => ['required', Rule::in(array_keys(ContractLetter::STATUS_LABELS))],
            'clauses_referenced' => ['nullable'],
            'content_draft'   => ['nullable', 'string'],
            'contractual_event_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'letter_number.required'   => 'El número de carta es obligatorio.',
            'type.required'            => 'El tipo de carta es obligatorio.',
            'subject.required'         => 'El asunto es obligatorio.',
            'from_company_id.required' => 'La empresa emisora es obligatoria.',
            'to_company_id.required'   => 'La empresa receptora es obligatoria.',
            'to_company_id.different'  => 'La empresa receptora debe ser diferente a la emisora.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convierte el string de cláusulas a array JSON
        if ($this->filled('clauses_referenced') && is_string($this->clauses_referenced)) {
            $clauses = array_map('trim', explode(',', $this->clauses_referenced));
            $this->merge(['clauses_referenced' => array_filter($clauses)]);
        }
    }
}
