<?php

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractLetter;
use App\Models\ContractMilestone;
use App\Models\ContractualEvent;
use App\Services\RiskScoreService;
use Tests\Fixtures\ContratoEnDisputa;
use Tests\Fixtures\ContratoSano;

describe('RiskScoreService — escenarios completos', function () {

    it('contrato sano tiene score 0 y nivel bajo', function () {
        $fixture = ContratoSano::crear($this->adminUser);
        $service = app(RiskScoreService::class);

        $score = $service->calculate($fixture->contract);

        expect($score->score_value)->toBe(0)
            ->and($score->score_level)->toBe('bajo');
    });

    it('contrato en disputa tiene score 100 y nivel critico', function () {
        $fixture = ContratoEnDisputa::crear($this->adminUser);
        $service = app(RiskScoreService::class);

        $score = $service->calculate($fixture->contract);

        expect($score->score_value)->toBe(100)
            ->and($score->score_level)->toBe('critico');
    });

});

describe('RiskScoreService — factor 1: eventos sin resolver', function () {

    it('0 eventos pendientes → 0 puntos', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $score    = app(RiskScoreService::class)->calculate($contract);

        expect($score->factors['eventos_sin_resolver']['points'])->toBe(0);
    });

    it('2 eventos pendientes > 15 días → 10 puntos', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        ContractualEvent::factory()->count(2)->create([
            'contract_id'       => $contract->id,
            'occurred_at'       => now()->subDays(20),
            'resolution_status' => 'pendiente',
            'created_by'        => $this->adminUser->id,
        ]);

        $score = app(RiskScoreService::class)->calculate($contract);
        expect($score->factors['eventos_sin_resolver']['points'])->toBe(10);
    });

    it('más de 5 eventos pendientes > 15 días → 20 puntos (máximo)', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        ContractualEvent::factory()->count(6)->create([
            'contract_id'       => $contract->id,
            'occurred_at'       => now()->subDays(20),
            'resolution_status' => 'pendiente',
            'created_by'        => $this->adminUser->id,
        ]);

        $score = app(RiskScoreService::class)->calculate($contract);
        expect($score->factors['eventos_sin_resolver']['points'])->toBe(20);
    });

});

describe('RiskScoreService — factor 2: cartas vencidas', function () {

    it('1 carta vencida → 10 puntos', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        ContractLetter::factory()->vencida()->create([
            'contract_id' => $contract->id,
            'created_by'  => $this->adminUser->id,
        ]);

        $score = app(RiskScoreService::class)->calculate($contract);
        expect($score->factors['cartas_vencidas']['points'])->toBe(10);
    });

    it('4 cartas vencidas → 20 puntos (máximo)', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        ContractLetter::factory()->vencida()->count(4)->create([
            'contract_id' => $contract->id,
            'created_by'  => $this->adminUser->id,
        ]);

        $score = app(RiskScoreService::class)->calculate($contract);
        expect($score->factors['cartas_vencidas']['points'])->toBe(20);
    });

});

describe('RiskScoreService — niveles de riesgo', function () {

    it('score 0-25 → nivel bajo', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $score    = app(RiskScoreService::class)->calculate($contract);

        expect($score->score_level)->toBe('bajo');
    });

    it('score persiste en la base de datos', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $score    = app(RiskScoreService::class)->calculate($contract);

        $fromDb = \App\Models\ClaimRiskScore::find($score->id);

        expect($fromDb)->not->toBeNull()
            ->and($fromDb->contract_id)->toBe($contract->id)
            ->and($fromDb->score_value)->toBe($score->score_value);
    });

});
