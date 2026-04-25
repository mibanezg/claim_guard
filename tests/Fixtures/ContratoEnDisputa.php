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
 * Escenario B — Contrato en disputa con máxima exposición al claim.
 *
 * Factores de riesgo activados al máximo:
 *   F1  eventos sin resolver > 15 días   → 6 eventos × mandante  → 20 pts
 *   F2  cartas vencidas sin respuesta     → 4 cartas              → 20 pts
 *   F3  desviación del programa           → 6 atrasados + 35 días → 15 pts
 *   F4  OC rechazadas sin contraoferta    → 3 OC                  → 15 pts
 *   F5  monto en disputa > 10 % contrato  → evento disputa 15 %   → 15 pts
 *   F6  concentración en una parte        → 7/7 = 100 % mandante  → 15 pts
 *
 * Resultado esperado del RiskScoreService:
 *   score_value = 100  →  nivel = 'critico'
 */
class ContratoEnDisputa
{
    public Contract $contract;
    public Company  $mandante;
    public Company  $contratista;

    public static function crear(User $creator): self
    {
        $fixture = new self();

        $fixture->mandante    = Company::factory()->mandante()->create();
        $fixture->contratista = Company::factory()->contratista()->create();

        // Contrato en disputa, proyección 35 días sobre la fecha contractual
        $contractualEnd = now()->addMonths(3);
        $fixture->contract = Contract::factory()->enDisputa()->create([
            'mandante_company_id'    => $fixture->mandante->id,
            'contractor_company_id'  => $fixture->contratista->id,
            'original_amount'        => 10_000_000_000,
            'current_amount'         => 10_000_000_000,
            'contractual_start_date' => now()->subYear(),
            'contractual_end_date'   => $contractualEnd,
            'projected_end_date'     => $contractualEnd->copy()->addDays(35),
            'created_by'             => $creator->id,
        ]);

        // F1 + F6: 6 eventos imputables al mandante, pendientes, > 15 días sin resolver
        ContractualEvent::factory()->count(6)->create([
            'contract_id'       => $fixture->contract->id,
            'type'              => 'atraso_mandante',
            'occurred_at'       => now()->subDays(20),
            'responsible_party' => 'mandante',
            'resolution_status' => 'pendiente',
            'cost_impact'       => 0,
            'created_by'        => $creator->id,
        ]);

        // F5: 1 evento tipo disputa imputable al mandante, costo = 15 % del monto vigente
        ContractualEvent::factory()->create([
            'contract_id'       => $fixture->contract->id,
            'type'              => 'disputa',
            'occurred_at'       => now()->subDays(25),
            'responsible_party' => 'mandante',
            'resolution_status' => 'escalado',
            'cost_impact'       => 1_500_000_000, // 15 M CLP = 15 %
            'created_by'        => $creator->id,
        ]);

        // F2: 4 cartas con status vencida
        ContractLetter::factory()->vencida()->count(4)->create([
            'contract_id'    => $fixture->contract->id,
            'from_company_id'=> $fixture->contratista->id,
            'to_company_id'  => $fixture->mandante->id,
            'created_by'     => $creator->id,
        ]);

        // F3: 6 hitos atrasados
        ContractMilestone::factory()->atrasado()->count(6)->create([
            'contract_id' => $fixture->contract->id,
        ]);

        // F4: 3 OC rechazadas
        ChangeOrder::factory()->rechazada()->count(3)->create([
            'contract_id' => $fixture->contract->id,
            'cost_impact' => 100_000_000,
            'created_by'  => $creator->id,
        ]);

        return $fixture;
    }
}
