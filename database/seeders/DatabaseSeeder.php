<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles & permissions
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        // 2. Tenant types
        $typeInn      = TypeOfTenant::firstOrCreate(['type' => 'Inn'],      ['description' => 'Small lodging']);
        $typeResort   = TypeOfTenant::firstOrCreate(['type' => 'Resort'],   ['description' => 'Leisure resort']);
        $typeEcoPark  = TypeOfTenant::firstOrCreate(['type' => 'Eco Park'], ['description' => 'Nature park']);
        $typeMangrove = TypeOfTenant::firstOrCreate(['type' => 'Mangrove'], ['description' => 'Mangrove area']);

        // 3. Property types (global)
        $propTypeStandard = PropertyType::firstOrCreate(['name' => 'Standard Room'], ['tenant_id' => null]);
        $propTypeDeluxe   = PropertyType::firstOrCreate(['name' => 'Deluxe Room'],   ['tenant_id' => null]);
        $propTypeSuite    = PropertyType::firstOrCreate(['name' => 'Family Suite'],  ['tenant_id' => null]);
        $propTypeCottage  = PropertyType::firstOrCreate(['name' => 'Cottage'],       ['tenant_id' => null]);

        // 4. Super admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name'      => 'System Super Admin',
                'password'  => Hash::make('password'),
                'tenant_id' => null,
                'is_active' => 1,
            ]
        );
        $superAdmin->assignRole('super-admin');

        // 5. Sample tenants + admin users + full demo data
        $tenants = [
            [
                'name'             => 'Victorias Eco Park',
                'slug'             => 'victorias-eco-park',
                'type_of_tenant_id'=> $typeEcoPark->id,
                'address'          => 'Sitio Malingin, Brgy. XIII, Victorias City',
                'contact_number'   => '034-399-2830',
                'email'            => 'eco@gmail.com',
                'latitude'         => 10.9089,
                'longitude'        => 123.0762,
                'is_active'        => true,
            ],
            [
                'name'             => 'Casa de Palma Resort',
                'slug'             => 'casa-de-palma',
                'type_of_tenant_id'=> $typeResort->id,
                'address'          => 'Brgy. VI, Victorias City',
                'contact_number'   => '034-409-1234',
                'email'            => 'resort@gmail.com',
                'latitude'         => 10.8956,
                'longitude'        => 123.0710,
                'is_active'        => true,
            ],
            [
                'name'             => 'Mangrove Eco-Tourism Park',
                'slug'             => 'mangrove-park',
                'type_of_tenant_id'=> $typeMangrove->id,
                'address'          => 'Brgy. II, Victorias City',
                'contact_number'   => '034-399-5678',
                'email'            => 'mangrove@gmail.com',
                'latitude'         => 10.9002,
                'longitude'        => 123.0685,
                'is_active'        => true,
            ],
        ];

        foreach ($tenants as $data) {
            $tenant = Tenant::firstOrCreate(['slug' => $data['slug']], $data);

            $adminEmail = Str::slug($data['name']) . '@gmail.com';
            $admin = User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name'      => $data['name'] . ' Admin',
                    'password'  => Hash::make('password'),
                    'tenant_id' => $tenant->id,
                    'is_active' => 1,
                ]
            );
            $admin->assignRole('admin');

            // Seed demo data (properties, services, customers, bookings, employees)
            $this->seedTenantDemoData($tenant);
        }

        // 6. Tourist user
        $tourist = User::firstOrCreate(
            ['email' => 'tourist@gmail.com'],
            [
                'name'      => 'Juan Tourist',
                'password'  => Hash::make('password'),
                'tenant_id' => null,
                'is_active' => 1,
            ]
        );
        $tourist->assignRole('tourist');
    }

    /**
     * Seeds properties, services, customers, bookings, payments and employees
     * for a single tenant — but only if not already seeded.
     */
    private function seedTenantDemoData(Tenant $tenant): void
    {
        // Don't double‑seed (using the three‑argument + boolean pattern)
        if (Property::where('tenant_id', '=', $tenant->id, 'and')->count() > 0) {
            return;
        }

        // ── Property types for this tenant (use global types) ──
        $standard = PropertyType::where('name', '=', 'Standard Room', 'and')->firstOrFail();
        $deluxe   = PropertyType::where('name', '=', 'Deluxe Room',   'and')->firstOrFail();
        $suite    = PropertyType::where('name', '=', 'Family Suite',  'and')->firstOrFail();
        $cottage  = PropertyType::where('name', '=', 'Cottage',       'and')->firstOrFail();

        // ── Properties ──
        $props = [
            ['name' => 'Standard Room', 'type' => $standard, 'price' => 1200, 'capacity' => 2, 'desc' => 'Cozy room for two'],
            ['name' => 'Deluxe Room',   'type' => $deluxe,   'price' => 2000, 'capacity' => 3, 'desc' => 'Spacious with garden view'],
            ['name' => 'Family Suite',  'type' => $suite,    'price' => 3500, 'capacity' => 5, 'desc' => 'Two bedrooms, perfect for families'],
            ['name' => 'Cottage',       'type' => $cottage,  'price' => 800,  'capacity' => 4, 'desc' => 'Rustic cottage near the lake'],
        ];
        foreach ($props as $p) {
            Property::create([
                'tenant_id'        => $tenant->id,
                'property_type_id' => $p['type']->id,
                'name'             => $p['name'],
                'description'      => $p['desc'],
                'price'            => $p['price'],
                'capacity'         => $p['capacity'],
                'status'           => 'available',
                'is_active'        => 1,
            ]);
        }

        // ── Services ──
        Service::insert([
            ['tenant_id' => $tenant->id, 'name' => 'Breakfast Buffet', 'price' => 250, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Airport Transfer', 'price' => 500, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Guided Tour',      'price' => 300, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Bike Rental',      'price' => 150, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Customers ──
        Customer::insert([
            ['tenant_id' => $tenant->id, 'name' => 'Juan dela Cruz', 'phone' => '09171234567', 'email' => 'juan@email.com', 'address' => 'Manila', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Maria Santos',    'phone' => '09182345678', 'email' => 'maria@email.com', 'address' => null,     'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Pedro Reyes',     'phone' => '09193456789', 'email' => null,               'address' => null,     'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Ana Gonzales',    'phone' => '09201234567', 'email' => 'ana@email.com',   'address' => 'Cebu',  'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Mark Villanueva', 'phone' => '09213456789', 'email' => null,               'address' => 'Iloilo','created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Employees with login accounts ──
        $customRoles = TenantSetting::where('tenant_id', '=', $tenant->id, 'and')
                                    ->where('key', '=', 'custom_roles', 'and')
                                    ->first();
        $customRolesArray = $customRoles ? $customRoles->value : [];
        // Ensure a "Front Desk" custom role exists
        $frontDeskIndex = null;
        foreach ($customRolesArray as $idx => $role) {
            if ($role['name'] === 'Front Desk') {
                $frontDeskIndex = $idx;
                break;
            }
        }
        if ($frontDeskIndex === null) {
            $customRolesArray[] = ['name' => 'Front Desk', 'permissions' => ['view bookings', 'create bookings', 'view customers', 'create customers']];
            TenantSetting::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => 'custom_roles'],
                ['value' => $customRolesArray]
            );
        }

        // Employee accounts
        $emp1User = User::firstOrCreate(
            ['email' => 'rico@gmail.com'],
            ['name' => 'Rico Reception', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => 1]
        );
        $emp1User->syncPermissions(['view bookings', 'create bookings', 'view customers', 'create customers']);
        Employee::create(['tenant_id' => $tenant->id, 'user_id' => $emp1User->id, 'name' => 'Rico Reception', 'role' => 'Receptionist', 'phone' => '0917-111-1111', 'is_active' => 1]);

        $emp2User = User::firstOrCreate(
            ['email' => 'hannah@gmail.com'],
            ['name' => 'Hannah Housekeeping', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => 1]
        );
        Employee::create(['tenant_id' => $tenant->id, 'user_id' => $emp2User->id, 'name' => 'Hannah Housekeeping', 'role' => 'Housekeeping', 'phone' => '0917-222-2222', 'is_active' => 1]);

        // Manager (has many permissions directly)
        $mgrUser = User::firstOrCreate(
            ['email' => 'megan@gmail.com'],
            ['name' => 'Megan Manager', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => 1]
        );
        $mgrUser->syncPermissions(['view bookings', 'create bookings', 'view customers', 'view properties', 'view services', 'view payments', 'view employees', 'view analytics']);
        Employee::create(['tenant_id' => $tenant->id, 'user_id' => $mgrUser->id, 'name' => 'Megan Manager', 'role' => 'Manager', 'phone' => '0917-333-3333', 'is_active' => 1]);

        // ── Bookings and payments ──
        $customerIds     = Customer::where('tenant_id', '=', $tenant->id, 'and')->pluck('id')->toArray();
        $propertyIds     = Property::where('tenant_id', '=', $tenant->id, 'and')->pluck('id')->toArray();
        $propertyPrices  = Property::where('tenant_id', '=', $tenant->id, 'and')->pluck('price', 'id')->toArray();
        $serviceIds      = Service::where('tenant_id', '=', $tenant->id, 'and')->pluck('id')->toArray();
        $servicePrices   = Service::where('tenant_id', '=', $tenant->id, 'and')->pluck('price', 'id')->toArray();

        for ($i = 0; $i < 30; $i++) {
            $checkIn  = Carbon::now()->subDays(rand(0, 60))->addDays(rand(0, 30)); // mix past & future
            $nights   = rand(1, 4);
            $checkOut = $checkIn->copy()->addDays($nights);

            $roomId    = $propertyIds[array_rand($propertyIds)];
            $roomPrice = $propertyPrices[$roomId];
            $total     = $roomPrice * $nights;

            $status = $checkOut->isFuture()
                ? collect(['pending', 'confirmed'])->random()
                : collect(['completed', 'confirmed', 'cancelled'])->random();

            $booking = Booking::create([
                'tenant_id'         => $tenant->id,
                'customer_id'       => $customerIds[array_rand($customerIds)],
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $checkIn,
                'check_out'         => $checkOut,
                'total_amount'      => $total,
                'status'            => $status,
                'created_at'        => $checkIn->copy()->subDays(rand(1, 5)),
            ]);

            BookingItem::create([
                'tenant_id'   => $tenant->id,
                'booking_id'  => $booking->id,
                'property_id' => $roomId,
                'price'       => $roomPrice,
                'quantity'    => 1,
                'subtotal'    => $total,
            ]);

            // Randomly attach 0‑2 services
            for ($j = 0; $j < rand(0, 2); $j++) {
                $svcId    = $serviceIds[array_rand($serviceIds)];
                $svcPrice = $servicePrices[$svcId];
                BookingService::create([
                    'tenant_id'  => $tenant->id,
                    'booking_id' => $booking->id,
                    'service_id' => $svcId,
                    'quantity'   => 1,
                    'subtotal'   => $svcPrice,
                ]);
                $total += $svcPrice;
                $booking->update(['total_amount' => $total]);
            }

            // Payment
            $paymentStatus = ($status === 'cancelled')
                ? 'unpaid'
                : (($status === 'completed' || rand(0, 1)) ? 'paid' : 'unpaid');
            Payment::create([
                'tenant_id'        => $tenant->id,
                'booking_id'       => $booking->id,
                'amount'           => $total,
                'payment_method'   => collect(['cash', 'gcash', 'card'])->random(),
                'payment_status'   => $paymentStatus,
                'paid_at'          => $paymentStatus === 'paid' ? $checkIn->copy()->addHours(rand(1, 10)) : null,
                'reference_number' => $paymentStatus === 'paid' ? 'TXN-' . Str::random(10) : null,
                'created_at'       => $booking->created_at,
                'updated_at'       => now(),
            ]);
        }
    }
}