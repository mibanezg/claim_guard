<?php

namespace Tests\Fixtures;

use App\Models\ChangeOrder;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractLetter;
use App\Models\ContractMilestone;
use App\Models\ContractualEvent;
use App\Models\User;

/**
 * Escenario A — Contrato saludable, sin exposición al claim.
 *
 * Resultado esperado del RiskScoreService:
 *   score_value = 0   →  nivel = 'bajo'
 */
class ContratoSano
{
    public Contract $contract;
    public Company  $mandante;
    public Company  $contratista;

    public static function crear(User $creator): self
    {
        $fixture = new self();

        $fixture->mandante    = Company::factory()->mandante()->create();
        $fixture->contratista = Company::factory()->contratista()->create();

        $fixture->contract = Contract::factory()->vigente()->create([
            'mandante_company_id'    => $fixture->mandante->id,
            'contractor_company_id'  => $fixture->contratista->id,
            'original_amount'        => 10_000_000_000,
            'current_amount'         => 10_000_000_000,
            'contractual_start_date' => now()->subYear(),
            'contractual_end_date'   => now()->addYear(),
            'projected_end_date'     => null,
            'created_by'             => $creator->id,
        ]);

        // Todos los eventos resueltos, con responsabilidad distribuida (F6 = 0)
        foreach (['mandante', 'contratista', 'fuerza_mayor'] as $party) {
            ContractualEvent::factory()->create([
                'contract_id'       => $fixture->contract->id,
                'resolution_status' => 'resuelto',
                'responsible_party' => $party,
                'cost_impact'       => 0,
                'created_by'        => $creator->id,
            ]);
        }

        // Cartas respondidas a tiempo
        ContractLetter::factory()->respondida()->count(2)->create([
            'contract_id'    => $fixture->contract->id,
            'from_company_id'=> $fixture->mandante->id,
            'to_company_id'  => $fixture->contratista->id,
            'created_by'     => $creator->id,
        ]);

        // Hitos al día
        ContractMilestone::factory()->count(3)->create([
            'contract_id' => $fixture->contract->id,
            'status'      => 'pendiente',
            'planned_date'=> now()->addMonths(2),
        ]);

        // OC aprobadas
        ChangeOrder::factory()->aprobada()->count(2)->create([
            'contract_id' => $fixture->contract->id,
            'cost_impact' => 50_000_000,
            'created_by'  => $creator->id,
        ]);

        return $fixture;
    }
}
