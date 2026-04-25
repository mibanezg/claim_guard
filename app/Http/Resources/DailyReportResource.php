<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'contract_id'           => $this->contract_id,
            'report_date'           => $this->report_date?->format('d/m/Y'),
            'report_date_raw'       => $this->report_date?->toDateString(),
            'report_number'         => $this->report_number,
            'weather'               => $this->weather,
            'weather_label'         => $this->weather_label,
            'weather_icon'          => \App\Models\DailyReport::WEATHER_ICONS[$this->weather] ?? 'cloud',
            'temperature'           => $this->temperature,
            'work_executed'         => $this->work_executed,
            'personnel_on_site'     => $this->personnel_on_site ?? [],
            'equipment_on_site'     => $this->equipment_on_site ?? [],
            'total_personnel'       => $this->total_personnel,
            'materials_received'    => $this->materials_received,
            'instructions_received' => $this->instructions_received,
            'issues_encountered'    => $this->issues_encountered,
            'safety_incidents'      => $this->safety_incidents,
            'visitors'              => $this->visitors,
            'general_notes'         => $this->general_notes,
            'events'                => $this->whenLoaded('events', fn () =>
                $this->events->map(fn ($e) => [
                    'id'         => $e->id,
                    'type_label' => $e->type_label,
                    'occurred_at'=> $e->occurred_at?->format('d/m/Y'),
                ])
            ),
            'event_ids'             => $this->whenLoaded('events', fn () => $this->events->pluck('id')->toArray()),
            'created_by'            => $this->created_by,
            'created_at'            => $this->created_at?->format('d/m/Y'),
            'has_instructions'      => !empty($this->instructions_received),
            'has_issues'            => !empty($this->issues_encountered),
            'has_incidents'         => !empty($this->safety_incidents),
        ];
    }
}
