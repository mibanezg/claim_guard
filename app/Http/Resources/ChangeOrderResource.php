<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangeOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'contract_id'          => $this->contract_id,
            'contractual_event_id' => $this->contractual_event_id,
            'request_number'       => $this->request_number,
            'requested_by_party'   => $this->requested_by_party,
            'party_label'          => $this->party_label,
            'description'          => $this->description,
            'schedule_impact_days' => $this->schedule_impact_days,
            'cost_impact'          => $this->cost_impact / 100,
            'status'               => $this->status,
            'status_label'         => $this->status_label,
            'is_pending'           => $this->isPending(),
            'approved_by'          => $this->approved_by,
            'approved_at'          => $this->approved_at?->format('d/m/Y H:i'),
            'rejection_notes'      => $this->rejection_notes,
            'created_by'           => $this->created_by,
            'created_at'           => $this->created_at?->format('d/m/Y'),
        ];
    }
}
