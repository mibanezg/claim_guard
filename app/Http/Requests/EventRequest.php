<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'                   => ['required', Rule::in(array_keys(\App\Models\ContractualEvent::TYPE_LABELS))],
            'occurred_at'            => ['required', 'date'],
            'description'            => ['required', 'string', 'min:10'],
            'contractual_basis'      => ['nullable', 'string', 'max:500'],
            'contractual_basis_doc'  => ['nullable', Rule::in(array_keys(\App\Models\ContractualEvent::BASIS_DOC_LABELS))],
            'responsible_party'      => ['required', Rule::in(['mandante', 'contratista', 'fuerza_mayor', 'tercero'])],
            'schedule_impact_days'   => ['required', 'integer', 'min:0'],
            'cost_impact'            => ['required', 'numeric', 'min:0'],
            'resolution_status'      => ['required', Rule::in(['pendiente', 'negociacion', 'resuelto', 'escalado'])],
            'resolution_notes'       => ['nullable', 'string'],
            'notified_at'            => ['nullable', 'date'],
            'notification_status'    => ['required', Rule::in(array_keys(\App\Models\ContractualEvent::NOTIFICATION_LABELS))],
            'rights_reserved'        => ['boolean'],
            'rights_reserved_at'     => ['nullable', 'date', 'required_if:rights_reserved,true'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'              => 'El tipo de evento es obligatorio.',
            'occurred_at.required'       => 'La fecha de ocurrencia es obligatoria.',
            'description.required'       => 'La descripción es obligatoria.',
            'description.min'            => 'La descripción debe tener al menos 10 caracteres.',
            'responsible_party.required' => 'La parte responsable es obligatoria.',
            'cost_impact.min'            => 'El impacto en costo no puede ser negativo.',
        ];
    }
}
