<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractMilestone> */
class ContractMilestoneFactory extends Factory
{
    protected $model = ContractMilestone::class;

    public function definition(): array
    {
        return [
            'contract_id'            => Contract::factory(),
            'name'                   => fake()->sentence(4),
            'planned_date'           => now()->addMonth(),
            'actual_date'            => null,
            'progress_percentage'    => 0,
            'is_critical'            => false,
            'generates_notification' => false,
            'status'                 => 'pendiente',
            'source'                 => 'manual',
        ];
    }

    public function atrasado(): static
    {
        return $this->state([
            'planned_date' => now()->subDays(10),
            'status'       => 'atrasado',
        ]);
    }

    public function completado(): static
    {
        return $this->state([
            'status'              => 'completado',
            'progress_percentage' => 100,
            'actual_date'         => now()->subDays(3),
        ]);
    }
}
