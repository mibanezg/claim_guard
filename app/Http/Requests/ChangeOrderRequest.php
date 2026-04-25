<?php

namespace App\Http\Requests;

use App\Models\ChangeOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'request_number'       => ['required', 'string', 'max:100'],
            'requested_by_party'   => ['required', Rule::in(['mandante', 'contratista'])],
            'description'          => ['required', 'string', 'min:10'],
            'schedule_impact_days' => ['required', 'integer'],
            'cost_impact'          => ['required', 'numeric'],
            'status'               => ['required', Rule::in(array_keys(ChangeOrder::STATUS_LABELS))],
            'contractual_event_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_by_party.required' => 'La parte solicitante es obligatoria.',
            'description.required'        => 'La descripción es obligatoria.',
            'description.min'             => 'La descripción debe tener al menos 10 caracteres.',
            'cost_impact.required'        => 'El impacto en costo es obligatorio.',
        ];
    }
}
