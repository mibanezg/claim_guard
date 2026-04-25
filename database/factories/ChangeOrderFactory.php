<?php

namespace Database\Factories;

use App\Models\ChangeOrder;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChangeOrder> */
class ChangeOrderFactory extends Factory
{
    protected $model = ChangeOrder::class;

    public function definition(): array
    {
        return [
            'contract_id'         => Contract::factory(),
            'request_number'      => 'OC-' . fake()->unique()->numerify('####'),
            'requested_by_party'  => fake()->randomElement(['mandante', 'contratista']),
            'description'         => fake()->sentence(),
            'schedule_impact_days'=> 0,
            'cost_impact'         => 0,
            'status'              => 'solicitada',
            'created_by'          => 1,
        ];
    }

    public function aprobada(): static
    {
        return $this->state(['status' => 'aprobada']);
    }

    public function rechazada(): static
    {
        return $this->state(['status' => 'rechazada']);
    }

    public function solicitada(): static
    {
        return $this->state(['status' => 'solicitada']);
    }
}
