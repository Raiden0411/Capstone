<?php

namespace Tests\Browser;

use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BookingFlowTest extends DuskTestCase
{
    public function test_user_can_start_booking_from_offerings_page()
    {
        $unique = uniqid();
        $slug = 'test-resort-' . $unique;
        $email = 'tourist-' . $unique . '@example.com';
        $password = 'password';

        $type = TypeOfTenant::factory()->create(['type' => 'Resort']);
        $tenant = Tenant::factory()->create([
            'type_of_tenant_id' => $type->id,
            'slug' => $slug,
            'name' => 'Test Resort',
        ]);

        $propertyType = PropertyType::factory()->create(['tenant_id' => null, 'name' => 'Standard Room']);
        $property = Property::factory()->create([
            'tenant_id'        => $tenant->id,
            'property_type_id' => $propertyType->id,
            'name'             => 'Deluxe Room',
            'price'            => 1500,
            'is_active'        => true,
        ]);

        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt($password),
            'tenant_id' => null,
        ]);

        $this->browse(function (Browser $browser) use ($tenant, $property, $user, $password) {
            $browser->visit('/login')
                    ->type('email', $user->email)
                    ->type('password', $password)
                    ->press('Login')
                    ->waitForLocation('/')
                    ->visit(route('business.offerings', $tenant->slug))
                    ->scrollIntoView('#activities')
                    ->waitForText('Book Now', 10)
                    ->assertSee('Book Now')
                    ->clickLink('Book Now')
                    ->assertPathIs('/booking/create/'.$property->id)
                    ->assertSee('Complete Your Booking')
                    ->assertSee($property->name);
        });
    }
}