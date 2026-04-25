<?php

use App\Models\Contract;
use App\Models\ContractLetter;
use App\Services\LetterService;

describe('LetterService::markExpired', function () {

    it('marca como vencida una carta emitida cuyo deadline ya pasó', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        $carta = ContractLetter::factory()->conDeadlinePasado()->create([
            'contract_id' => $contract->id,
            'created_by'  => $this->adminUser->id,
        ]);

        $expired = app(LetterService::class)->markExpired();

        expect($expired)->toHaveCount(1)
            ->and($carta->fresh()->status)->toBe('vencida');
    });

    it('NO marca una carta emitida cuyo deadline aún no llegó', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        $carta = ContractLetter::factory()->conDeadlineFuturo()->create([
            'contract_id' => $contract->id,
            'created_by'  => $this->adminUser->id,
        ]);

        $expired = app(LetterService::class)->markExpired();

        expect($expired)->toHaveCount(0)
            ->and($carta->fresh()->status)->toBe('emitida');
    });

    it('NO modifica cartas ya respondidas aunque el deadline pasó', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        ContractLetter::factory()->respondida()->create([
            'contract_id'       => $contract->id,
            'response_deadline' => now()->subDays(5)->toDateString(),
            'created_by'        => $this->adminUser->id,
        ]);

        $expired = app(LetterService::class)->markExpired();

        expect($expired)->toHaveCount(0);
    });

    it('NO modifica cartas sin response_deadline', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        ContractLetter::factory()->create([
            'contract_id'       => $contract->id,
            'status'            => 'emitida',
            'response_deadline' => null,
            'created_by'        => $this->adminUser->id,
        ]);

        $expired = app(LetterService::class)->markExpired();

        expect($expired)->toHaveCount(0);
    });

    it('procesa múltiples cartas vencidas de contratos distintos en un solo llamado', function () {
        $c1 = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $c2 = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        ContractLetter::factory()->conDeadlinePasado()->count(2)->create([
            'contract_id' => $c1->id,
            'created_by'  => $this->adminUser->id,
        ]);

        ContractLetter::factory()->conDeadlinePasado()->create([
            'contract_id' => $c2->id,
            'created_by'  => $this->adminUser->id,
        ]);

        $expired = app(LetterService::class)->markExpired();

        expect($expired)->toHaveCount(3);
    });

});
