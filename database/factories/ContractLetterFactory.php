<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractLetter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractLetter> */
class ContractLetterFactory extends Factory
{
    protected $model = ContractLetter::class;

    public function definition(): array
    {
        return [
            'contract_id'    => Contract::factory(),
            'letter_number'  => 'CTR-TEST-C-' . str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'type'           => fake()->randomElement(['notificacion', 'reserva_derechos', 'respuesta']),
            'subject'        => fake()->sentence(),
            'from_company_id'=> Company::factory()->mandante(),
            'to_company_id'  => Company::factory()->contratista(),
            'status'         => 'borrador',
            'created_by'     => 1,
        ];
    }

    public function emitida(): static
    {
        return $this->state([
            'status'            => 'emitida',
            'issued_at'         => now()->subDays(10),
            'response_days'     => 5,
            'response_deadline' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function vencida(): static
    {
        return $this->state(['status' => 'vencida']);
    }

    public function respondida(): static
    {
        return $this->state(['status' => 'respondida']);
    }

    public function conDeadlinePasado(): static
    {
        // Carta emitida cuyo plazo ya venció — el job la debe marcar como vencida
        return $this->state([
            'status'            => 'emitida',
            'issued_at'         => now()->subDays(15),
            'response_days'     => 5,
            'response_deadline' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function conDeadlineFuturo(): static
    {
        return $this->state([
            'status'            => 'emitida',
            'issued_at'         => now()->subDays(2),
            'response_days'     => 10,
            'response_deadline' => now()->addDays(5)->toDateString(),
        ]);
    }
}
