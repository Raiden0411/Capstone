<?php

namespace Database\Factories;

use App\Models\TypeOfTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TypeOfTenantFactory extends Factory
{
    protected $model = TypeOfTenant::class;

    public function definition(): array
    {
        return [
            'type' => fake()->unique()->randomElement(['Inn', 'Resort', 'Eco Park', 'Mangrove', 'Restaurant']),
            'description' => fake()->sentence(),
        ];
    }
}