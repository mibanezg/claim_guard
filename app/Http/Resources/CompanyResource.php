<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'rut'           => $this->rut,
            'address'       => $this->address,
            'contact_name'  => $this->contact_name,
            'contact_email' => $this->contact_email,
            'type'          => $this->type,
            'type_label'    => $this->getTypeLabel(),
            'created_at'    => $this->created_at?->format('d/m/Y'),
        ];
    }
}
