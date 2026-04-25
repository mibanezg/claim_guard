<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name'          => fake()->company(),
            'rut'           => fake()->numerify('##.###.###-#'),
            'address'       => fake()->address(),
            'contact_name'  => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'type'          => fake()->randomElement(['mandante', 'contratista', 'ambos']),
        ];
    }

    public function mandante(): static
    {
        return $this->state(['type' => 'mandante']);
    }

    public function contratista(): static
    {
        return $this->state(['type' => 'contratista']);
    }
}
