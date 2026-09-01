<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'property_type_id' => PropertyType::factory(),
            'name' => fake()->randomElement(['Standard Room', 'Deluxe Room', 'Family Suite', 'Cottage']),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 800, 5000),
            'capacity' => fake()->numberBetween(1, 6),
            'status' => 'available',
            'is_active' => true,
        ];
    }

    /**
     * Associate the property with a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}