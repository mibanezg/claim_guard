<?php

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractLetter;
use App\Services\LetterNumberService;
use App\Services\LetterService;

describe('LetterNumberService::generate', function () {

    it('genera el primer número correctamente', function () {
        $contract = Contract::factory()->create([
            'number'     => '2026-001',
            'created_by' => $this->adminUser->id,
        ]);
        $service = app(LetterNumberService::class);

        expect($service->generate($contract))->toBe('CTR-2026-001-C-0001');
    });

    it('incrementa el correlativo por contrato', function () {
        $contract = Contract::factory()->create([
            'number'     => '2026-042',
            'created_by' => $this->adminUser->id,
        ]);

        // Crear 4 cartas previas para el contrato
        ContractLetter::factory()->count(4)->create([
            'contract_id' => $contract->id,
            'created_by'  => $this->adminUser->id,
        ]);

        $service = app(LetterNumberService::class);
        expect($service->generate($contract))->toBe('CTR-2026-042-C-0005');
    });

    it('el correlativo es independiente por contrato', function () {
        $c1 = Contract::factory()->create(['number' => '2026-001', 'created_by' => $this->adminUser->id]);
        $c2 = Contract::factory()->create(['number' => '2026-002', 'created_by' => $this->adminUser->id]);

        ContractLetter::factory()->count(3)->create([
            'contract_id' => $c1->id,
            'created_by'  => $this->adminUser->id,
        ]);

        $service = app(LetterNumberService::class);

        expect($service->generate($c1))->toBe('CTR-2026-001-C-0004')
            ->and($service->generate($c2))->toBe('CTR-2026-002-C-0001');
    });

});

describe('LetterService::create', function () {

    it('calcula response_deadline en días hábiles desde issued_at', function () {
        $contract    = Contract::factory()->create(['created_by' => $this->adminUser->id]);
        $mandante    = Company::factory()->mandante()->create();
        $contratista = Company::factory()->contratista()->create();
        $service     = app(LetterService::class);

        // issued_at = lunes 2026-05-04, response_days = 5 → deadline = 2026-05-11
        $letter = $service->create($contract, [
            'letter_number'  => 'CTR-TEST-C-0001',
            'type'           => 'notificacion',
            'subject'        => 'Notificación de atraso',
            'from_company_id'=> $mandante->id,
            'to_company_id'  => $contratista->id,
            'issued_at'      => '2026-05-04',
            'response_days'  => 5,
            'status'         => 'emitida',
        ], $this->adminUser->id);

        expect($letter->response_deadline->toDateString())->toBe('2026-05-11');
    });

    it('deja response_deadline null si no hay issued_at', function () {
        $contract    = Contract::factory()->create(['created_by' => $this->adminUser->id]);
        $mandante    = Company::factory()->mandante()->create();
        $contratista = Company::factory()->contratista()->create();
        $service     = app(LetterService::class);

        $letter = $service->create($contract, [
            'letter_number'  => 'CTR-TEST-C-0001',
            'type'           => 'notificacion',
            'subject'        => 'Borrador sin fecha',
            'from_company_id'=> $mandante->id,
            'to_company_id'  => $contratista->id,
            'issued_at'      => null,
            'response_days'  => 5,
            'status'         => 'borrador',
        ], $this->adminUser->id);

        expect($letter->response_deadline)->toBeNull();
    });

});
