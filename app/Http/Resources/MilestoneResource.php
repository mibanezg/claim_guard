<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'contract_id'            => $this->contract_id,
            'name'                   => $this->name,
            'description'            => $this->description,
            'planned_date'           => $this->planned_date?->format('d/m/Y'),
            'planned_date_raw'       => $this->planned_date?->toDateString(),
            'actual_date'            => $this->actual_date?->format('d/m/Y'),
            'actual_date_raw'        => $this->actual_date?->toDateString(),
            'progress_percentage'    => $this->progress_percentage,
            'is_critical'            => $this->is_critical,
            'generates_notification' => $this->generates_notification,
            'status'                 => $this->status,
            'status_label'           => $this->status_label,
            'source'                 => $this->source,
            'source_label'           => $this->source_label,
            'is_delayed'             => $this->isDelayed(),
            'created_at'             => $this->created_at?->format('d/m/Y'),
        ];
    }
}
