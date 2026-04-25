<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'category'         => $this->category,
            'file_type'        => $this->file_type,
            'file_size'        => $this->file_size,
            'file_size_human'  => $this->file_size_human,
            'sharepoint_id'    => $this->sharepoint_id,
            'sharepoint_url'   => $this->sharepoint_url,
            'local_path'       => $this->local_path,
            'storage_url'      => $this->storage_url,
            'uploader_name'    => $this->uploader?->name,
            'created_at'       => $this->created_at?->toDateString(),
        ];
    }
}
