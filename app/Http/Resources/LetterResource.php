<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LetterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'contract_id'          => $this->contract_id,
            'contractual_event_id' => $this->contractual_event_id,
            'letter_number'        => $this->letter_number,
            'type'                 => $this->type,
            'type_label'           => $this->type_label,
            'subject'              => $this->subject,
            'from_company_id'      => $this->from_company_id,
            'from_company_name'    => $this->fromCompany?->name,
            'to_company_id'        => $this->to_company_id,
            'to_company_name'      => $this->toCompany?->name,
            'issued_at'            => $this->issued_at?->format('d/m/Y'),
            'issued_at_raw'        => $this->issued_at?->toDateString(),
            'received_at'          => $this->received_at?->format('d/m/Y'),
            'received_at_raw'      => $this->received_at?->toDateString(),
            'response_deadline'    => $this->response_deadline?->format('d/m/Y'),
            'response_deadline_raw'=> $this->response_deadline?->toDateString(),
            'response_days'        => $this->response_days,
            'status'               => $this->status,
            'status_label'         => $this->status_label,
            'clauses_referenced'   => $this->clauses_referenced ?? [],
            'clauses_string'       => implode(', ', $this->clauses_referenced ?? []),
            'ai_generated'         => $this->ai_generated,
            'content_draft'        => $this->content_draft,
            'days_until_deadline'  => $this->days_until_deadline,
            'is_overdue'           => $this->is_overdue,
            'created_by'           => $this->created_by,
            'created_at'           => $this->created_at?->format('d/m/Y'),
        ];
    }
}
