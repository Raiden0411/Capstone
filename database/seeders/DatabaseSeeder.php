<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Employee;
use App\Models\Event;
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
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $typeInn      = TypeOfTenant::firstOrCreate(['type' => 'Inn'],      ['description' => 'Small lodging']);
        $typeResort   = TypeOfTenant::firstOrCreate(['type' => 'Resort'],   ['description' => 'Leisure resort']);
        $typeEcoPark  = TypeOfTenant::firstOrCreate(['type' => 'Eco Park'], ['description' => 'Nature park']);
        $typeMangrove = TypeOfTenant::firstOrCreate(['type' => 'Mangrove'], ['description' => 'Mangrove area']);
        $typeRestaurant = TypeOfTenant::firstOrCreate(['type' => 'Restaurant'], ['description' => 'Food establishment']);

        $propTypeStandard = PropertyType::firstOrCreate(['name' => 'Standard Room'], ['tenant_id' => null]);
        $propTypeDeluxe   = PropertyType::firstOrCreate(['name' => 'Deluxe Room'],   ['tenant_id' => null]);
        $propTypeSuite    = PropertyType::firstOrCreate(['name' => 'Family Suite'],  ['tenant_id' => null]);
        $propTypeCottage  = PropertyType::firstOrCreate(['name' => 'Cottage'],       ['tenant_id' => null]);

        // Super admin
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

        // Tourist user
        $touristUser = User::firstOrCreate(
            ['email' => 'tourist@gmail.com'],
            [
                'name'      => 'Juan Tourist',
                'password'  => Hash::make('password'),
                'tenant_id' => null,
                'is_active' => 1,
            ]
        );
        $touristUser->assignRole('tourist');

        $tenants = [
            [
                'name'             => 'Victorias Eco Park',
                'slug'             => 'victorias-eco-park',
                'type_of_tenant_id'=> $typeEcoPark->id,
                'address'          => 'Sitio Malingin, Brgy. XIII, Victorias City',
                'barangay'         => 'Barangay XIII',
                'contact_number'   => '034-399-2830',
                'email'            => 'eco@gmail.com',
                'coordinates'      => [
                    ['lat' => 10.9089, 'lng' => 123.0762, 'name' => 'Gawahon Falls',       'type' => 'parent'],
                    ['lat' => 10.9095, 'lng' => 123.0770, 'name' => 'Main Office',          'type' => 'child'],
                    ['lat' => 10.9080, 'lng' => 123.0750, 'name' => 'Parking Lot',          'type' => 'child'],
                ],
                'is_active'        => true,
            ],
            [
                'name'             => 'Casa de Palma Resort',
                'slug'             => 'casa-de-palma',
                'type_of_tenant_id'=> $typeResort->id,
                'address'          => 'Brgy. VI, Victorias City',
                'barangay'         => 'Barangay VI',
                'contact_number'   => '034-409-1234',
                'email'            => 'resort@gmail.com',
                'coordinates'      => [
                    ['lat' => 10.8956, 'lng' => 123.0710, 'name' => 'Resort Main',          'type' => 'parent'],
                    ['lat' => 10.8960, 'lng' => 123.0720, 'name' => 'Swimming Pool',        'type' => 'child'],
                ],
                'is_active'        => true,
            ],
            [
                'name'             => 'Mangrove Eco-Tourism Park',
                'slug'             => 'mangrove-park',
                'type_of_tenant_id'=> $typeMangrove->id,
                'address'          => 'Brgy. II, Victorias City',
                'barangay'         => 'Barangay II',
                'contact_number'   => '034-399-5678',
                'email'            => 'mangrove@gmail.com',
                'coordinates'      => [
                    ['lat' => 10.9002, 'lng' => 123.0685, 'name' => 'Mangrove Centre',      'type' => 'parent'],
                ],
                'is_active'        => true,
            ],
            [
                'name'             => 'Casa Victoria Restaurant',
                'slug'             => 'casa-victoria',
                'type_of_tenant_id'=> $typeRestaurant->id,
                'address'          => 'Poblacion, Brgy. I, Victorias City',
                'barangay'         => 'Barangay I',
                'contact_number'   => '034-399-9999',
                'email'            => 'resto@gmail.com',
                'coordinates'      => [
                    ['lat' => 10.8900, 'lng' => 123.0700, 'name' => 'Restaurant', 'type' => 'parent'],
                ],
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

            $this->seedTenantDemoData($tenant);
        }

        $this->seedEvents();
    }

    private function seedEvents(): void
    {
        $events = [
            [
                'name'        => 'Sinulog Festival',
                'barangay'    => 'Barangay Santo Niño',
                'description' => 'A vibrant cultural and religious festival honoring the Santo Niño, featuring street dancing and fluvial parade.',
                'type'        => 'fiesta',
                'start_date'  => Carbon::now()->addDays(30),
                'end_date'    => Carbon::now()->addDays(32),
                'coordinates' => json_encode(['lat' => 10.9090, 'lng' => 123.0770]),
                'tenant_id'   => null,
                'is_active'   => true,
                'featured'    => true,
            ],
            [
                'name'        => 'Mangrove Planting Day',
                'barangay'    => 'Barangay II',
                'description' => 'Join the community in planting mangroves along the coast to preserve the marine ecosystem.',
                'type'        => 'environment',
                'start_date'  => Carbon::now()->addDays(10),
                'end_date'    => Carbon::now()->addDays(10),
                'coordinates' => json_encode(['lat' => 10.9005, 'lng' => 123.0680]),
                'tenant_id'   => null,
                'is_active'   => true,
                'featured'    => false,
            ],
            [
                'name'        => 'Summer Sports Fest',
                'barangay'    => 'Barangay VI',
                'description' => 'Inter‑barangay basketball and volleyball tournament with live music and food stalls.',
                'type'        => 'sports',
                'start_date'  => Carbon::now()->addDays(45),
                'end_date'    => Carbon::now()->addDays(47),
                'coordinates' => json_encode(['lat' => 10.8960, 'lng' => 123.0720]),
                'tenant_id'   => null,
                'is_active'   => true,
                'featured'    => false,
            ],
            [
                'name'        => 'Gawahon Eco‑Trail Fun Run',
                'barangay'    => 'Barangay XIII',
                'description' => 'A 5K fun run through the scenic trails of Gawahon Eco Park. Open to all ages!',
                'type'        => 'sports',
                'start_date'  => Carbon::now()->addDays(21),
                'end_date'    => Carbon::now()->addDays(21),
                'coordinates' => json_encode(['lat' => 10.9089, 'lng' => 123.0762]),
                'tenant_id'   => null,
                'is_active'   => true,
                'featured'    => false,
            ],
            [
                'name'        => 'Casa de Palma Summer Nights',
                'barangay'    => 'Barangay VI',
                'description' => 'Exclusive resort party with live DJ, poolside cocktails, and fireworks.',
                'type'        => 'entertainment',
                'start_date'  => Carbon::now()->addDays(60),
                'end_date'    => Carbon::now()->addDays(60),
                'coordinates' => json_encode(['lat' => 10.8956, 'lng' => 123.0710]),
                'tenant_id'   => Tenant::query()->where('slug', 'casa-de-palma')->value('id'),
                'is_active'   => true,
                'featured'    => true,
            ],
            [
                'name'        => 'Mangrove Night Walk',
                'barangay'    => 'Barangay II',
                'description' => 'Guided night walk through the mangrove forest to observe fireflies and nocturnal wildlife.',
                'type'        => 'adventure',
                'start_date'  => Carbon::now()->addDays(15),
                'end_date'    => Carbon::now()->addDays(16),
                'coordinates' => json_encode(['lat' => 10.9002, 'lng' => 123.0685]),
                'tenant_id'   => Tenant::query()->where('slug', 'mangrove-park')->value('id'),
                'is_active'   => true,
                'featured'    => false,
            ],
        ];

        foreach ($events as $data) {
            Event::firstOrCreate(
                ['name' => $data['name'], 'start_date' => $data['start_date']],
                $data
            );
        }
    }

    /** @disregard PHP6613 */
    private function seedTenantDemoData(Tenant $tenant): void
    {
        if (Property::query()->where('tenant_id', $tenant->id)->count() > 0) {
            return;
        }

        $standard = PropertyType::query()->where('name', 'Standard Room')->firstOrFail();
        $deluxe   = PropertyType::query()->where('name', 'Deluxe Room')->firstOrFail();
        $suite    = PropertyType::query()->where('name', 'Family Suite')->firstOrFail();
        $cottage  = PropertyType::query()->where('name', 'Cottage')->firstOrFail();

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

        Service::insert([
            ['tenant_id' => $tenant->id, 'name' => 'Breakfast Buffet', 'price' => 250, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Airport Transfer', 'price' => 500, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Guided Tour',      'price' => 300, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Bike Rental',      'price' => 150, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Employees
        $customRoles = TenantSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'custom_roles')
            ->first();
        $customRolesArray = $customRoles ? $customRoles->value : [];
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

        $mgrUser = User::firstOrCreate(
            ['email' => 'megan@gmail.com'],
            ['name' => 'Megan Manager', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => 1]
        );
        $mgrUser->syncPermissions(['view bookings', 'create bookings', 'view customers', 'view properties', 'view services', 'view payments', 'view employees', 'view analytics']);
        Employee::create(['tenant_id' => $tenant->id, 'user_id' => $mgrUser->id, 'name' => 'Megan Manager', 'role' => 'Manager', 'phone' => '0917-333-3333', 'is_active' => 1]);

        // Bookings (using the tourist user)
        $touristUser = User::query()->where('email', 'tourist@gmail.com')->first();
        $propertyIds     = Property::query()->where('tenant_id', $tenant->id)->pluck('id')->toArray();
        $propertyPrices  = Property::query()->where('tenant_id', $tenant->id)->pluck('price', 'id')->toArray();
        $serviceIds      = Service::query()->where('tenant_id', $tenant->id)->pluck('id')->toArray();
        $servicePrices   = Service::query()->where('tenant_id', $tenant->id)->pluck('price', 'id')->toArray();

        for ($i = 0; $i < 30; $i++) {
            $checkIn  = Carbon::now()->subDays(rand(0, 60))->addDays(rand(0, 30));
            $nights   = rand(1, 4);
            $checkOut = $checkIn->copy()->addDays($nights);

            $roomId    = $propertyIds[array_rand($propertyIds)];
            $roomPrice = $propertyPrices[$roomId];
            $total     = $roomPrice * $nights;

            if ($checkOut->isFuture()) {
                $status = collect(['pending', 'confirmed', 'reserved'])->random();
                $bookingType = $status === 'reserved' ? Booking::TYPE_RESERVATION : Booking::TYPE_FULL;
            } else {
                $status = collect(['completed', 'confirmed', 'cancelled'])->random();
                $bookingType = Booking::TYPE_FULL;
            }

            $booking = Booking::create([
                'tenant_id'         => $tenant->id,
                'user_id'           => $touristUser->id,
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $checkIn,
                'check_out'         => $checkOut,
                'total_amount'      => $total,
                'status'            => $status,
                'booking_type'      => $bookingType,
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

            $paymentType = $bookingType;
            $paymentStatus = ($status === 'cancelled')
                ? 'unpaid'
                : (($status === 'completed' || rand(0, 1)) ? 'paid' : 'unpaid');

            $paymentAmount = $paymentType === Booking::TYPE_RESERVATION
                ? round($total * 0.20, 2)
                : $total;

            Payment::create([
                'tenant_id'        => $tenant->id,
                'booking_id'       => $booking->id,
                'amount'           => $paymentAmount,
                'payment_method'   => collect(['cash', 'gcash', 'card'])->random(),
                'payment_type'     => $paymentType,
                'payment_status'   => $paymentStatus,
                'paid_at'          => $paymentStatus === 'paid' ? $checkIn->copy()->addHours(rand(1, 10)) : null,
                'reference_number' => $paymentStatus === 'paid' ? 'TXN-' . Str::random(10) : null,
                'created_at'       => $booking->created_at,
                'updated_at'       => now(),
            ]);
        }
    }
}