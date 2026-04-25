<?php

namespace App\Services;

use App\Imports\MilestonesImport;
use App\Models\Contract;
use App\Models\ContractMilestone;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class MilestoneService
{
    public function create(Contract $contract, array $data): ContractMilestone
    {
        return $contract->milestones()->create([
            'name'                   => $data['name'],
            'description'            => $data['description'] ?? null,
            'planned_date'           => $data['planned_date'],
            'actual_date'            => $data['actual_date'] ?? null,
            'progress_percentage'    => (int) ($data['progress_percentage'] ?? 0),
            'is_critical'            => (bool) ($data['is_critical'] ?? false),
            'generates_notification' => (bool) ($data['generates_notification'] ?? false),
            'status'                 => $data['status'] ?? 'pendiente',
            'source'                 => 'manual',
        ]);
    }

    public function update(ContractMilestone $milestone, array $data): ContractMilestone
    {
        $milestone->update([
            'name'                   => $data['name'],
            'description'            => $data['description'] ?? null,
            'planned_date'           => $data['planned_date'],
            'actual_date'            => $data['actual_date'] ?? null,
            'progress_percentage'    => (int) ($data['progress_percentage'] ?? 0),
            'is_critical'            => (bool) ($data['is_critical'] ?? false),
            'generates_notification' => (bool) ($data['generates_notification'] ?? false),
            'status'                 => $data['status'] ?? $milestone->status,
        ]);
        return $milestone->fresh();
    }

    public function delete(ContractMilestone $milestone): void
    {
        $milestone->delete();
    }

    public function importFromExcel(Contract $contract, UploadedFile $file): int
    {
        $countBefore = $contract->milestones()->count();
        Excel::import(new MilestonesImport($contract), $file);
        return $contract->milestones()->count() - $countBefore;
    }
}
