<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contract> */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;
        $amount = 10_000_000_000; // 100 M CLP en centavos

        return [
            'name'                   => 'Contrato ' . fake()->words(3, true),
            'number'                 => now()->year . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT),
            'type'                   => fake()->randomElement(['obra', 'suministro', 'servicios', 'EPC', 'mixto']),
            'mandante_company_id'    => Company::factory()->mandante(),
            'contractor_company_id'  => Company::factory()->contratista(),
            'original_amount'        => $amount,
            'current_amount'         => $amount,
            'currency'               => 'CLP',
            'contractual_start_date' => now()->subYear(),
            'contractual_end_date'   => now()->addYear(),
            'notification_days'      => 5,
            'status'                 => 'vigente',
            'created_by'             => 1, // se sobreescribe en fixtures
        ];
    }

    public function vigente(): static
    {
        return $this->state(['status' => 'vigente']);
    }

    public function enDisputa(): static
    {
        return $this->state(['status' => 'en_disputa']);
    }

    public function borrador(): static
    {
        return $this->state(['status' => 'borrador']);
    }
}
