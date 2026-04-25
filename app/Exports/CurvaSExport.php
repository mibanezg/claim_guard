<?php

namespace App\Exports;

use App\Models\Contract;
use App\Models\ContractMilestone;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CurvaSExport implements WithMultipleSheets
{
    public function __construct(private int $contractId) {}

    public function sheets(): array
    {
        return [
            new CurvaSDataSheet($this->contractId),
        ];
    }
}
