<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'contract_id'             => $this->contract_id,
            'type'                    => $this->type,
            'type_label'              => $this->type_label,
            'occurred_at'             => $this->occurred_at?->format('d/m/Y'),
            'occurred_at_raw'         => $this->occurred_at?->toDateString(),
            'description'             => $this->description,
            'contractual_basis'       => $this->contractual_basis,
            'contractual_basis_doc'   => $this->contractual_basis_doc,
            'contractual_basis_doc_label' => \App\Models\ContractualEvent::BASIS_DOC_LABELS[$this->contractual_basis_doc] ?? null,
            'responsible_party'       => $this->responsible_party,
            'party_label'             => $this->party_label,
            'schedule_impact_days'    => $this->schedule_impact_days,
            'cost_impact'             => $this->cost_impact / 100,
            'resolution_status'       => $this->resolution_status,
            'resolution_label'        => $this->resolution_label,
            'resolution_notes'        => $this->resolution_notes,
            'notice_deadline'         => $this->notice_deadline?->format('d/m/Y'),
            'notice_deadline_raw'     => $this->notice_deadline?->toDateString(),
            'notified_at'             => $this->notified_at?->format('d/m/Y'),
            'notified_at_raw'         => $this->notified_at?->toDateString(),
            'notification_status'     => $this->notification_status,
            'notification_label'      => $this->notification_label,
            'notice_days_remaining'   => $this->notice_days_remaining,
            'is_notice_overdue'       => $this->is_notice_overdue,
            'days_open'               => $this->days_open,
            'rights_reserved'         => $this->rights_reserved,
            'rights_reserved_at'      => $this->rights_reserved_at?->format('d/m/Y'),
            'rights_reserved_at_raw'  => $this->rights_reserved_at?->toDateString(),
            'rights_letters_count'    => $this->whenCounted('rightsLetter'),
            'letters_count'           => $this->whenCounted('letters'),
            'change_orders_count'     => $this->whenCounted('changeOrders'),
            'created_by'              => $this->created_by,
            'created_at'              => $this->created_at?->format('d/m/Y'),
        ];
    }
}
