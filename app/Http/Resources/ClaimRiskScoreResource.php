<?php

namespace App\Http\Resources;

use App\Models\ClaimRiskScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimRiskScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $config = ClaimRiskScore::LEVEL_CONFIG[$this->score_level] ?? [];

        return [
            'id'             => $this->id,
            'contract_id'    => $this->contract_id,
            'score_level'    => $this->score_level,
            'score_value'    => $this->score_value,
            'level_label'    => $config['label']  ?? $this->score_level,
            'level_color'    => $config['color']  ?? '#22c55e',
            'level_bg'       => $config['bg']     ?? 'rgba(34,197,94,0.12)',
            'factors'        => $this->factors,
            'recommendations'=> $this->recommendations,
            'calculated_at'  => $this->calculated_at?->toIso8601String(),
        ];
    }
}
