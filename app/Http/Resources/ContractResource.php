<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'number'                 => $this->number,
            'description'            => $this->description,
            'type'                   => $this->type,
            'type_label'             => \App\Models\Contract::TYPE_LABELS[$this->type] ?? $this->type,
            'status'                 => $this->status,
            'status_label'           => \App\Models\Contract::STATUS_LABELS[$this->status] ?? $this->status,
            'allowed_transitions'    => $this->allowedTransitions(),
            'mandante_company_id'    => $this->mandante_company_id,
            'contractor_company_id'  => $this->contractor_company_id,
            'mandante'               => $this->whenLoaded('mandante', fn () => [
                'id'   => $this->mandante->id,
                'name' => $this->mandante->name,
            ]),
            'contractor'             => $this->whenLoaded('contractor', fn () => [
                'id'   => $this->contractor->id,
                'name' => $this->contractor->name,
            ]),
            'original_amount'        => $this->original_amount / 100,
            'current_amount'         => $this->current_amount / 100,
            'currency'               => $this->currency,
            'contractual_start_date' => $this->contractual_start_date?->format('Y-m-d'),
            'contractual_end_date'   => $this->contractual_end_date?->format('Y-m-d'),
            'actual_start_date'      => $this->actual_start_date?->format('Y-m-d'),
            'projected_end_date'     => $this->projected_end_date?->format('Y-m-d'),
            'notification_days'         => $this->notification_days,
            'applicable_law'            => $this->applicable_law,
            'jurisdiction'              => $this->jurisdiction,
            'ms_project_imported_at'    => $this->ms_project_imported_at?->format('d/m/Y H:i'),
            'primavera_imported_at'     => $this->primavera_imported_at?->format('d/m/Y H:i'),
            'created_at'                => $this->created_at?->format('d/m/Y'),
            'has_contract_text'         => !is_null($this->contract_text),
            'contract_pdf_name'         => $this->contract_pdf_name,
            'clauses'                   => $this->clauses,
        ];
    }
}
