<?php

use App\Jobs\RecalculateRiskScoreJob;
use App\Models\Contract;
use App\Services\EventService;
use Illuminate\Support\Facades\Queue;

describe('EventService::create', function () {

    it('crea el evento y dispara recálculo de riesgo', function () {
        Queue::fake();

        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $service  = app(EventService::class);

        $event = $service->create($contract, [
            'type'              => 'atraso_mandante',
            'occurred_at'       => now()->subDays(3)->toDateString(),
            'description'       => 'Atraso en entrega de frente de trabajo.',
            'responsible_party' => 'mandante',
            'resolution_status' => 'pendiente',
            'cost_impact'       => 0,
        ], $this->adminUser->id);

        expect($event->contract_id)->toBe($contract->id)
            ->and($event->resolution_status)->toBe('pendiente');

        Queue::assertPushed(RecalculateRiskScoreJob::class);
    });

    it('convierte cost_impact a centavos', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $service  = app(EventService::class);

        $event = $service->create($contract, [
            'type'              => 'trabajo_adicional',
            'occurred_at'       => now()->subDays(2)->toDateString(),
            'description'       => 'Trabajo adicional no previsto.',
            'responsible_party' => 'mandante',
            'resolution_status' => 'pendiente',
            'cost_impact'       => 1_000_000, // 1 M CLP en pesos
        ], $this->adminUser->id);

        expect($event->cost_impact)->toBe(100_000_000); // almacenado en centavos
    });

    it('calcula notice_deadline en días hábiles según notification_days del contrato', function () {
        $contract = Contract::factory()->vigente()->create([
            'notification_days' => 5,
            'created_by'        => $this->adminUser->id,
        ]);
        $service = app(EventService::class);

        // Usamos lunes 2026-05-04 → 5 días hábiles sin feriados → 2026-05-11
        $event = $service->create($contract, [
            'type'               => 'atraso_mandante',
            'occurred_at'        => '2026-05-04',
            'description'        => 'Evento de prueba.',
            'responsible_party'  => 'mandante',
            'resolution_status'  => 'pendiente',
            'cost_impact'        => 0,
            'notification_status'=> 'pendiente',
        ], $this->adminUser->id);

        expect($event->notice_deadline)->not->toBeNull()
            ->and($event->notice_deadline->toDateString())->toBe('2026-05-11');
    });

    it('deriva notification_status correctamente', function () {
        $contract = Contract::factory()->vigente()->create([
            'notification_days' => 5,
            'created_by'        => $this->adminUser->id,
        ]);
        $service = app(EventService::class);

        // Notificado antes del deadline → a tiempo
        $eventAtiempo = $service->create($contract, [
            'type'                => 'atraso_mandante',
            'occurred_at'         => '2026-05-04',
            'description'         => 'A tiempo.',
            'responsible_party'   => 'mandante',
            'resolution_status'   => 'pendiente',
            'cost_impact'         => 0,
            'notified_at'         => '2026-05-08',   // antes del deadline 2026-05-11
            'notification_status' => 'pendiente',     // el servicio lo recalcula
        ], $this->adminUser->id);

        expect($eventAtiempo->notification_status)->toBe('notificado_a_tiempo');
    });

});

describe('EventService::delete', function () {

    it('el delete es suave — el evento persiste con deleted_at', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $service  = app(EventService::class);

        $event = $service->create($contract, [
            'type'              => 'otro',
            'occurred_at'       => now()->toDateString(),
            'description'       => 'Para borrar.',
            'responsible_party' => 'mandante',
            'resolution_status' => 'pendiente',
            'cost_impact'       => 0,
        ], $this->adminUser->id);

        $id = $event->id;
        $service->delete($event);

        expect(\App\Models\ContractualEvent::find($id))->toBeNull()
            ->and(\App\Models\ContractualEvent::withTrashed()->find($id))->not->toBeNull();
    });

});
