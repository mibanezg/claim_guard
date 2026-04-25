<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'type'                   => ['required', 'in:obra,suministro,servicios,EPC,mixto'],
            'mandante_company_id'    => ['required', 'integer', 'exists:tenant.companies,id'],
            'contractor_company_id'  => ['required', 'integer', 'exists:tenant.companies,id', 'different:mandante_company_id'],
            'original_amount'        => ['required', 'numeric', 'min:1'],
            'currency'               => ['required', 'in:CLP,USD'],
            'contractual_start_date' => ['required', 'date'],
            'contractual_end_date'   => ['required', 'date', 'after:contractual_start_date'],
            'actual_start_date'      => ['nullable', 'date'],
            'projected_end_date'     => ['nullable', 'date'],
            'notification_days'      => ['required', 'integer', 'min:1', 'max:90'],
            'status'                 => ['sometimes', 'in:borrador,vigente,suspendido,terminado,en_disputa'],
            'applicable_law'         => ['nullable', 'string', 'max:255'],
            'jurisdiction'           => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                  => 'El nombre del contrato es obligatorio.',
            'type.required'                  => 'El tipo de contrato es obligatorio.',
            'mandante_company_id.required'   => 'Debe seleccionar el mandante.',
            'contractor_company_id.required' => 'Debe seleccionar el contratista.',
            'contractor_company_id.different'=> 'El mandante y el contratista no pueden ser la misma empresa.',
            'original_amount.required'       => 'El monto original es obligatorio.',
            'original_amount.min'            => 'El monto debe ser mayor a cero.',
            'contractual_start_date.required'=> 'La fecha de inicio contractual es obligatoria.',
            'contractual_end_date.required'  => 'La fecha de término contractual es obligatoria.',
            'contractual_end_date.after'     => 'La fecha de término debe ser posterior a la de inicio.',
            'notification_days.required'     => 'Los días de notificación son obligatorios.',
        ];
    }
}
