<?php

use App\Models\Contract;
use App\Services\ChangeOrderService;

describe('ChangeOrderService::create', function () {

    it('aprobar OC incrementa current_amount del contrato', function () {
        $contract = Contract::factory()->vigente()->create([
            'current_amount' => 10_000_000_000, // 100 M CLP
            'created_by'     => $this->adminUser->id,
        ]);
        $service  = app(ChangeOrderService::class);

        $service->create($contract, [
            'request_number'      => 'OC-0001',
            'requested_by_party'  => 'contratista',
            'description'         => 'Trabajo adicional en cimentación.',
            'schedule_impact_days'=> 0,
            'cost_impact'         => 500_000,   // 5 M CLP en pesos
            'status'              => 'aprobada',
        ], $this->adminUser->id);

        $contract->refresh();

        // 500_000 × 100 = 50_000_000 centavos sumados al monto vigente
        expect($contract->current_amount)->toBe(10_050_000_000);
    });

    it('aprobar OC con impacto de plazo actualiza projected_end_date', function () {
        $baseEnd  = now()->addMonths(6)->startOfDay();
        $contract = Contract::factory()->vigente()->create([
            'contractual_end_date' => $baseEnd,
            'projected_end_date'   => null,
            'created_by'           => $this->adminUser->id,
        ]);
        $service  = app(ChangeOrderService::class);

        $service->create($contract, [
            'request_number'      => 'OC-0002',
            'requested_by_party'  => 'contratista',
            'description'         => 'Extensión de plazo.',
            'schedule_impact_days'=> 30,
            'cost_impact'         => 0,
            'status'              => 'aprobada',
        ], $this->adminUser->id);

        $contract->refresh();

        // Basado en contractual_end_date (no había projected) + 30 días
        expect($contract->projected_end_date->toDateString())
            ->toBe($baseEnd->copy()->addDays(30)->toDateString());
    });

    it('OC solicitada NO modifica el contrato al crearla', function () {
        $contract = Contract::factory()->vigente()->create([
            'current_amount' => 10_000_000_000,
            'created_by'     => $this->adminUser->id,
        ]);
        $service  = app(ChangeOrderService::class);

        $service->create($contract, [
            'request_number'      => 'OC-0003',
            'requested_by_party'  => 'mandante',
            'description'         => 'Solicitud pendiente.',
            'schedule_impact_days'=> 0,
            'cost_impact'         => 1_000_000,
            'status'              => 'solicitada',
        ], $this->adminUser->id);

        $contract->refresh();

        expect($contract->current_amount)->toBe(10_000_000_000); // sin cambio
    });

    it('actualizar OC de solicitada a aprobada sí modifica el contrato', function () {
        $contract = Contract::factory()->vigente()->create([
            'current_amount' => 10_000_000_000,
            'created_by'     => $this->adminUser->id,
        ]);
        $service  = app(ChangeOrderService::class);

        $oc = $service->create($contract, [
            'request_number'      => 'OC-0004',
            'requested_by_party'  => 'contratista',
            'description'         => 'Primero solicitada.',
            'schedule_impact_days'=> 0,
            'cost_impact'         => 200_000, // 2 M CLP
            'status'              => 'solicitada',
        ], $this->adminUser->id);

        $service->update($oc, [
            'status'     => 'aprobada',
            'cost_impact'=> 200_000,
        ]);

        $contract->refresh();

        expect($contract->current_amount)->toBe(10_020_000_000);
    });

    it('OC rechazada no modifica el contrato', function () {
        $contract = Contract::factory()->vigente()->create([
            'current_amount' => 10_000_000_000,
            'created_by'     => $this->adminUser->id,
        ]);
        $service  = app(ChangeOrderService::class);

        $service->create($contract, [
            'request_number'      => 'OC-0005',
            'requested_by_party'  => 'contratista',
            'description'         => 'Rechazada.',
            'schedule_impact_days'=> 0,
            'cost_impact'         => 500_000,
            'status'              => 'rechazada',
        ], $this->adminUser->id);

        $contract->refresh();

        expect($contract->current_amount)->toBe(10_000_000_000);
    });

});
