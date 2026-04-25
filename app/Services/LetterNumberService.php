<?php

namespace App\Services;

use App\Models\Contract;

class LetterNumberService
{
    /**
     * Genera el próximo número de carta para el contrato.
     * Formato: CTR-{numero_contrato}-C-{correlativo 4 dígitos}
     * Ejemplo: CTR-2025-001-C-0042
     */
    public function generate(Contract $contract): string
    {
        $count = $contract->letters()->withTrashed()->count() + 1;
        return sprintf('CTR-%s-C-%04d', $contract->number, $count);
    }
}
