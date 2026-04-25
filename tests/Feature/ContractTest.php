<?php

use App\Models\Company;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Validation\ValidationException;

describe('ContractService::create', function () {

    it('genera número correlativo de contrato por año', function () {
        $service = app(ContractService::class);

        $company = Company::factory()->mandante()->create();
        $contratista = Company::factory()->contratista()->create();

        $c1 = $service->create([
            'name'                   => 'Primer Contrato',
            'type'                   => 'servicios',
            'mandante_company_id'    => $company->id,
            'contractor_company_id'  => $contratista->id,
            'original_amount'        => 1_000_000,
            'currency'               => 'CLP',
            'contractual_start_date' => '2026-01-01',
            'contractual_end_date'   => '2026-12-31',
            'notification_days'      => 5,
            'status'                 => 'vigente',
        ], $this->adminUser);

        $c2 = $service->create([
            'name'                   => 'Segundo Contrato',
            'type'                   => 'obra',
            'mandante_company_id'    => $company->id,
            'contractor_company_id'  => $contratista->id,
            'original_amount'        => 2_000_000,
            'currency'               => 'CLP',
            'contractual_start_date' => '2026-01-01',
            'contractual_end_date'   => '2026-12-31',
            'notification_days'      => 5,
            'status'                 => 'vigente',
        ], $this->adminUser);

        expect($c1->number)->toStartWith(now()->year . '-')
            ->and($c2->number)->not->toBe($c1->number);
    });

    it('guarda el monto original en centavos', function () {
        $service = app(ContractService::class);

        $company     = Company::factory()->mandante()->create();
        $contratista = Company::factory()->contratista()->create();

        $contract = $service->create([
            'name'                   => 'Test Monto',
            'type'                   => 'servicios',
            'mandante_company_id'    => $company->id,
            'contractor_company_id'  => $contratista->id,
            'original_amount'        => 1_000_000,   // 1 M CLP en pesos
            'currency'               => 'CLP',
            'contractual_start_date' => '2026-01-01',
            'contractual_end_date'   => '2026-12-31',
            'notification_days'      => 5,
            'status'                 => 'vigente',
        ], $this->adminUser);

        // El servicio multiplica por 100 para guardar centavos
        expect($contract->original_amount)->toBe(100_000_000);
    });

    it('asigna current_amount igual a original_amount al crear', function () {
        $service     = app(ContractService::class);
        $company     = Company::factory()->mandante()->create();
        $contratista = Company::factory()->contratista()->create();

        $contract = $service->create([
            'name'                   => 'Test Igual',
            'type'                   => 'obra',
            'mandante_company_id'    => $company->id,
            'contractor_company_id'  => $contratista->id,
            'original_amount'        => 500_000,
            'currency'               => 'CLP',
            'contractual_start_date' => '2026-01-01',
            'contractual_end_date'   => '2026-12-31',
            'notification_days'      => 5,
            'status'                 => 'vigente',
        ], $this->adminUser);

        expect($contract->current_amount)->toBe($contract->original_amount);
    });

});

describe('ContractService::changeStatus', function () {

    it('permite transiciones válidas', function () {
        $service  = app(ContractService::class);
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        $updated = $service->changeStatus($contract, 'terminado');

        expect($updated->status)->toBe('terminado');
    });

    it('rechaza transición inválida de terminado a vigente', function () {
        $service  = app(ContractService::class);
        $contract = Contract::factory()->create([
            'status'     => 'terminado',
            'created_by' => $this->adminUser->id,
        ]);

        expect(fn () => $service->changeStatus($contract, 'vigente'))
            ->toThrow(ValidationException::class);
    });

    it('rechaza transición inválida de borrador a terminado', function () {
        $service  = app(ContractService::class);
        $contract = Contract::factory()->borrador()->create(['created_by' => $this->adminUser->id]);

        expect(fn () => $service->changeStatus($contract, 'terminado'))
            ->toThrow(ValidationException::class);
    });

});

describe('Contract soft delete', function () {

    it('el delete es suave — el contrato persiste con deleted_at', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $id       = $contract->id;

        $contract->delete();

        expect(Contract::find($id))->toBeNull()
            ->and(Contract::withTrashed()->find($id))->not->toBeNull()
            ->and(Contract::withTrashed()->find($id)->deleted_at)->not->toBeNull();
    });

});
