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

        // ── Tenant Types ───────────────────────────────
        $typeInn      = TypeOfTenant::firstOrCreate(['type' => 'Inn'],      ['description' => 'Small lodging']);
        $typeResort   = TypeOfTenant::firstOrCreate(['type' => 'Resort'],   ['description' => 'Leisure resort']);
        $typeEcoPark  = TypeOfTenant::firstOrCreate(['type' => 'Eco Park'], ['description' => 'Nature park']);
        $typeMangrove = TypeOfTenant::firstOrCreate(['type' => 'Mangrove'], ['description' => 'Mangrove area']);

        // ── Global Property Types (tenant_id = null) ──
        $propTypeStandard = PropertyType::firstOrCreate(
            ['name' => 'Standard Room'],
            ['tenant_id' => null]
        );
        $propTypeDeluxe   = PropertyType::firstOrCreate(
            ['name' => 'Deluxe Room'],
            ['tenant_id' => null]
        );
        $propTypeSuite    = PropertyType::firstOrCreate(
            ['name' => 'Family Suite'],
            ['tenant_id' => null]
        );
        $propTypeCottage  = PropertyType::firstOrCreate(
            ['name' => 'Cottage'],
            ['tenant_id' => null]
        );

        // ── Super Admin ────────────────────────────────
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name'      => 'System Super Admin',
                'password'  => Hash::make('password'),
                'tenant_id' => null,
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('super-admin');

        // ── Tourist User ───────────────────────────────
        $touristUser = User::firstOrCreate(
            ['email' => 'tourist@gmail.com'],
            [
                'name'      => 'Juan Tourist',
                'password'  => Hash::make('password'),
                'tenant_id' => null,
                'is_active' => true,
            ]
        );
        $touristUser->assignRole('tourist');

        // ── Demo Tenants ───────────────────────────────
        $tenants = [
            [
                'name'             => 'Victorias Eco Park',
                'slug'             => 'victorias-eco-park',
                'type_of_tenant_id'=> $typeEcoPark->id,
                'address'          => 'Sitio Malingin, Brgy. XIII, Victorias City',
                'contact_number'   => '034-399-2830',
                'email'            => 'eco@gmail.com',
                'coordinates'      => [
                    ['lat' => 10.9089, 'lng' => 123.0762, 'name' => 'Gawahon Falls', 'type' => 'parent'],
                    ['lat' => 10.9095, 'lng' => 123.0770, 'name' => 'Main Office',   'type' => 'child'],
                    ['lat' => 10.9080, 'lng' => 123.0750, 'name' => 'Parking Lot',   'type' => 'child'],
                ],
                'is_active'        => true,
                'is_recommended'   => true,
            ],
            [
                'name'             => 'Casa de Palma Resort',
                'slug'             => 'casa-de-palma',
                'type_of_tenant_id'=> $typeResort->id,
                'address'          => 'Brgy. VI, Victorias City',
                'contact_number'   => '034-409-1234',
                'email'            => 'resort@gmail.com',
                'coordinates'      => [
                    ['lat' => 10.8956, 'lng' => 123.0710, 'name' => 'Resort Main',   'type' => 'parent'],
                    ['lat' => 10.8960, 'lng' => 123.0720, 'name' => 'Swimming Pool', 'type' => 'child'],
                ],
                'is_active'        => true,
                'is_recommended'   => false,
            ],
            [
                'name'             => 'Mangrove Eco-Tourism Park',
                'slug'             => 'mangrove-park',
                'type_of_tenant_id'=> $typeMangrove->id,
                'address'          => 'Brgy. II, Victorias City',
                'contact_number'   => '034-399-5678',
                'email'            => 'mangrove@gmail.com',
                'coordinates'      => [
                    ['lat' => 10.9002, 'lng' => 123.0685, 'name' => 'Mangrove Centre', 'type' => 'parent'],
                ],
                'is_active'        => true,
                'is_recommended'   => false,
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
                    'is_active' => true,
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
                'tenant_id'   => Tenant::where('slug', '=', 'casa-de-palma', 'and')->value('id'),
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
                'tenant_id'   => Tenant::where('slug', '=', 'mangrove-park', 'and')->value('id'),
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

    private function seedTenantDemoData(Tenant $tenant): void
    {
        if (Property::where('tenant_id', '=', $tenant->id, 'and')->count() > 0) {
            return;
        }

        // Retrieve global property types (tenant_id = null) to avoid accidentally picking tenant-specific ones
        $standard = PropertyType::whereNull('tenant_id', 'and', false)->where('name', '=', 'Standard Room', 'and')->firstOrFail();
        $deluxe   = PropertyType::whereNull('tenant_id', 'and', false)->where('name', '=', 'Deluxe Room', 'and')->firstOrFail();
        $suite    = PropertyType::whereNull('tenant_id', 'and', false)->where('name', '=', 'Family Suite', 'and')->firstOrFail();
        $cottage  = PropertyType::whereNull('tenant_id', 'and', false)->where('name', '=', 'Cottage', 'and')->firstOrFail();

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
                'is_active'        => true,
            ]);
        }

        Service::insert([
            ['tenant_id' => $tenant->id, 'name' => 'Breakfast Buffet', 'price' => 250, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Airport Transfer', 'price' => 500, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Guided Tour',      'price' => 300, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenant->id, 'name' => 'Bike Rental',      'price' => 150, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Custom Roles (Front Desk) ────────────────
        $customRoles = TenantSetting::where('tenant_id', '=', $tenant->id, 'and')
                                    ->where('key', '=', 'custom_roles', 'and')
                                    ->first();
        $customRolesArray = $customRoles && is_array($customRoles->value) ? $customRoles->value : [];
        $frontDeskExists = collect($customRolesArray)->contains('name', 'Front Desk');
        if (!$frontDeskExists) {
            $customRolesArray[] = ['name' => 'Front Desk', 'permissions' => ['view bookings', 'create bookings', 'view customers', 'create customers']];
            TenantSetting::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => 'custom_roles'],
                ['value' => $customRolesArray]
            );
        }

        // ── Employees ────────────────────────────────
        $base = Str::slug($tenant->name);
        $receptionEmail = $base . '.reception@gmail.com';
        $housekeepingEmail = $base . '.housekeeping@gmail.com';
        $managerEmail = $base . '.manager@gmail.com';

        $emp1User = User::firstOrCreate(
            ['email' => $receptionEmail],
            ['name' => 'Rico Reception', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => true]
        );
        $emp1User->syncPermissions(['view bookings', 'create bookings', 'view customers', 'create customers']);
        Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $emp1User->id],
            ['name' => 'Rico Reception', 'role' => 'Receptionist', 'phone' => '0917-111-1111', 'is_active' => true]
        );

        $emp2User = User::firstOrCreate(
            ['email' => $housekeepingEmail],
            ['name' => 'Hannah Housekeeping', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => true]
        );
        Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $emp2User->id],
            ['name' => 'Hannah Housekeeping', 'role' => 'Housekeeping', 'phone' => '0917-222-2222', 'is_active' => true]
        );

        $mgrUser = User::firstOrCreate(
            ['email' => $managerEmail],
            ['name' => 'Megan Manager', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => true]
        );
        $mgrUser->syncPermissions(['view bookings', 'create bookings', 'view customers', 'view properties', 'view services', 'view payments', 'view employees', 'view analytics']);
        Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $mgrUser->id],
            ['name' => 'Megan Manager', 'role' => 'Manager', 'phone' => '0917-333-3333', 'is_active' => true]
        );

        // ── Bookings ─────────────────────────────────
        $touristUser = User::where('email', '=', 'tourist@gmail.com', 'and')->first();
        $propertyIds     = Property::where('tenant_id', '=', $tenant->id, 'and')->pluck('id')->toArray();
        $propertyPrices  = Property::where('tenant_id', '=', $tenant->id, 'and')->pluck('price', 'id')->toArray();
        $serviceIds      = Service::where('tenant_id', '=', $tenant->id, 'and')->pluck('id')->toArray();
        $servicePrices   = Service::where('tenant_id', '=', $tenant->id, 'and')->pluck('price', 'id')->toArray();

        for ($i = 0; $i < 30; $i++) {
            $isPast = $i < 10;

            if ($isPast) {
                $checkOut = Carbon::now()->subDays(rand(1, 60));
                $checkIn  = $checkOut->copy()->subDays(rand(1, 4));
                $status   = collect(['completed', 'cancelled'])->random();
                $bookingType = Booking::TYPE_FULL;
                $createdAt = $checkIn->copy()->subDays(rand(1, 5));
            } else {
                $checkIn  = Carbon::now()->addDays(rand(0, 45));
                $nights   = rand(1, 4);
                $checkOut = $checkIn->copy()->addDays($nights);
                $status   = collect(['pending', 'confirmed', 'reserved', 'cancelled'])->random();
                $createdAt = Carbon::now()->subMinutes(rand(0, 20));
            }

            $roomId    = $propertyIds[array_rand($propertyIds)];
            $roomPrice = $propertyPrices[$roomId];
            $nightsStayed = max(1, $checkIn->diffInDays($checkOut));
            $total     = $roomPrice * $nightsStayed;

            if ($status === 'reserved') {
                $bookingType = Booking::TYPE_RESERVATION;
            } elseif ($status === 'confirmed') {
                $bookingType = Booking::TYPE_FULL;
            } elseif ($status === 'pending') {
                $bookingType = rand(0, 1) ? Booking::TYPE_FULL : Booking::TYPE_RESERVATION;
            } else {
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
                'created_at'        => $createdAt,
            ]);

            BookingItem::create([
                'tenant_id'   => $tenant->id,
                'booking_id'  => $booking->id,
                'property_id' => $roomId,
                'price'       => $roomPrice,
                'quantity'    => 1,
                'subtotal'    => $roomPrice * $nightsStayed,
            ]);

            $numServices = rand(0, 2);
            for ($j = 0; $j < $numServices; $j++) {
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

            // Payment logic
            $paymentType = $bookingType;
            $paymentStatus = 'unpaid';
            $paymentAmount = 0;

            if ($status === 'completed' || $status === 'confirmed') {
                $paymentStatus = 'paid';
                $paymentAmount = $booking->total_amount;
            } elseif ($status === 'reserved') {
                $paymentStatus = 'paid';
                $paymentAmount = round($booking->total_amount * 0.20, 2);
            }

            if ($paymentAmount > 0) {
                Payment::create([
                    'tenant_id'        => $tenant->id,
                    'booking_id'       => $booking->id,
                    'amount'           => $paymentAmount,
                    'payment_method'   => collect(['cash', 'gcash', 'card'])->random(),
                    'payment_type'     => $paymentType,
                    'payment_status'   => $paymentStatus,
                    'paid_at'          => $paymentStatus === 'paid' ? $booking->created_at->addMinutes(rand(1, 20)) : null,
                    'reference_number' => $paymentStatus === 'paid' ? 'TXN-' . Str::random(10) : null,
                    'created_at'       => $booking->created_at,
                    'updated_at'       => now(),
                ]);
            }
        }
    }
}