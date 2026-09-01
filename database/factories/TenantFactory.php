<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TypeOfTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type_of_tenant_id' => TypeOfTenant::factory(),
            'address' => fake()->address(),
            'barangay' => fake()->randomElement(['Barangay I', 'Barangay II', 'Barangay VI', 'Barangay XIII']),
            'contact_number' => '09' . fake()->numerify('#########'),
            'email' => fake()->unique()->safeEmail(),
            'logo' => null,
            'coordinates' => [
                ['lat' => 10.9000, 'lng' => 123.0700, 'name' => 'Main', 'type' => 'parent'],
            ],
            'is_active' => true,
            'is_recommended' => false,
        ];
    }
}