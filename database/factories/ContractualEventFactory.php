<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractualEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractualEvent> */
class ContractualEventFactory extends Factory
{
    protected $model = ContractualEvent::class;

    public function definition(): array
    {
        return [
            'contract_id'          => Contract::factory(),
            'type'                 => fake()->randomElement(['orden_cambio', 'atraso_mandante', 'atraso_contratista', 'disputa', 'otro']),
            'occurred_at'          => now()->subDays(5),
            'description'          => fake()->sentence(),
            'responsible_party'    => fake()->randomElement(['mandante', 'contratista', 'fuerza_mayor']),
            'schedule_impact_days' => 0,
            'cost_impact'          => 0,
            'resolution_status'    => 'pendiente',
            'notification_status'  => 'pendiente',
            'created_by'           => 1,
        ];
    }

    public function pendiente(): static
    {
        return $this->state(['resolution_status' => 'pendiente']);
    }

    public function resuelto(): static
    {
        return $this->state(['resolution_status' => 'resuelto']);
    }

    public function viejoPendiente(): static
    {
        // Evento sin resolver mayor a 15 días → activa el factor de riesgo
        return $this->state([
            'occurred_at'       => now()->subDays(20),
            'resolution_status' => 'pendiente',
        ]);
    }

    public function imputableMandante(): static
    {
        return $this->state(['responsible_party' => 'mandante']);
    }

    public function disputa(): static
    {
        return $this->state(['type' => 'disputa']);
    }
}
