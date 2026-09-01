<?php

namespace Database\Factories;

use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyTypeFactory extends Factory
{
    protected $model = PropertyType::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'name' => fake()->unique()->randomElement(['Standard Room', 'Deluxe Room', 'Family Suite', 'Cottage']),
        ];
    }
}