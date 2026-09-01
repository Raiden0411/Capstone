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
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        $this->seedMarkerCategories(); // ★ seed dynamic marker categories with SVG icons

        $typeInn      = TypeOfTenant::firstOrCreate(['type' => 'Inn'],      ['description' => 'Small lodging']);
        $typeResort   = TypeOfTenant::firstOrCreate(['type' => 'Resort'],   ['description' => 'Leisure resort']);
        $typeEcoPark  = TypeOfTenant::firstOrCreate(['type' => 'Eco Park'], ['description' => 'Nature park']);
        $typeMangrove = TypeOfTenant::firstOrCreate(['type' => 'Mangrove'], ['description' => 'Mangrove area']);
        $typeRestaurant = TypeOfTenant::firstOrCreate(['type' => 'Restaurant'], ['description' => 'Food establishment']);

        // Global property types
        $propTypeStandard = PropertyType::firstOrCreate(['name' => 'Standard Room', 'tenant_id' => null]);
        $propTypeDeluxe   = PropertyType::firstOrCreate(['name' => 'Deluxe Room',   'tenant_id' => null]);
        $propTypeSuite    = PropertyType::firstOrCreate(['name' => 'Family Suite',  'tenant_id' => null]);
        $propTypeCottage  = PropertyType::firstOrCreate(['name' => 'Cottage',       'tenant_id' => null]);

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
                    ['lat' => 10.9095, 'lng' => 123.0770, 'name' => 'Main Office',          'type' => 'entrance'],
                    ['lat' => 10.9080, 'lng' => 123.0750, 'name' => 'Parking Lot',          'type' => 'parking'],
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
                    ['lat' => 10.8960, 'lng' => 123.0720, 'name' => 'Swimming Pool',        'type' => 'viewpoint'],
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

            $this->seedTenantDemoData($tenant, $touristUser);
        }

        $this->seedEvents();
    }

    /**
     * Seed marker categories with professional inline SVG icons.
     */
    protected function seedMarkerCategories(): void
    {
        // Standardized SVG wrapper to ensure consistent rendering across all icons
        $svgStart = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
        $svgEnd = '</svg>';

        $categories = [
            [
                'key'   => 'restaurant',
                'label' => 'Restaurant',
                'color' => '#f97316',
                'svg'   => $svgStart . '<path d="M3 2v7c0 2.2 1.8 4 4 4h0a4 4 0 0 0 4-4V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>' . $svgEnd,
            ],
            [
                'key'   => 'cafe',
                'label' => 'Café',
                'color' => '#a855f7',
                'svg'   => $svgStart . '<path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z M6 2v2M10 2v2M14 2v2"/>' . $svgEnd,
            ],
            [
                'key'   => 'inn',
                'label' => 'Inn / Hotel',
                'color' => '#3b82f6',
                'svg'   => $svgStart . '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>' . $svgEnd,
            ],
            [
                'key'   => 'shop',
                'label' => 'Shopping & Retail',
                'color' => '#14b8a6',
                'svg'   => $svgStart . '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4ZM3 6h18M16 10a4 4 0 0 1-8 0"/>' . $svgEnd,
            ],
            [
                'key'   => 'viewpoint',
                'label' => 'Nature & Parks',
                'color' => '#eab308',
                'svg'   => $svgStart . '<path d="m17 14 3 3.3a1 1 0 0 1-.7 1.7H4.7a1 1 0 0 1-.7-1.7L7 14h-.3a1 1 0 0 1-.7-1.7L9 9h-.2A1 1 0 0 1 8 7.3L12 3l4 4.3a1 1 0 0 1-.8 1.7H15l3 3.3a1 1 0 0 1-.8 1.7H17ZM12 19v3"/>' . $svgEnd,
            ],
            [
                'key'   => 'parking',
                'label' => 'Parking',
                'color' => '#64748b',
                'svg'   => $svgStart . '<circle cx="12" cy="12" r="10"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>' . $svgEnd,
            ],
            [
                'key'   => 'entrance',
                'label' => 'Entrance / Exit',
                'color' => '#10b981',
                'svg'   => $svgStart . '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>' . $svgEnd,
            ],
            [
                'key'   => 'hospital',
                'label' => 'Hospital & Medical',
                'color' => '#ef4444',
                'svg'   => $svgStart . '<path d="M12 6v4M10 8h4M21 21v-4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v4M2 21h20M3 21V9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12"/>' . $svgEnd,
            ],
            [
                'key'   => 'transit',
                'label' => 'Transit & Bus',
                'color' => '#f59e0b',
                'svg'   => $svgStart . '<path d="M8 6v6M15 6v6M2 12h19.6M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3M4 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0ZM14 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0Z"/>' . $svgEnd,
            ],
            [
                'key'   => 'culture',
                'label' => 'Monuments & Culture',
                'color' => '#8b5cf6',
                'svg'   => $svgStart . '<path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>' . $svgEnd,
            ],
            [
                'key'   => 'other',
                'label' => 'Other',
                'color' => '#94a3b8',
                'svg'   => $svgStart . '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20zM12 8v4M12 16h.01"/>' . $svgEnd,
            ],
        ];

        $storedCategories = [];

        foreach ($categories as $cat) {
            // Write the SVG file to public storage (overwrites if exists)
            $fileName = 'marker-icons/' . $cat['key'] . '.svg';
            Storage::disk('public')->put($fileName, $cat['svg']);

            // Store category metadata including the inline SVG content
            $storedCategories[] = [
                'key'       => $cat['key'],
                'label'     => $cat['label'],
                'color'     => $cat['color'],
                'icon_path' => $fileName,
                'icon_svg'  => $cat['svg'], // ★ critical for inline rendering
            ];
        }

        // Save to site_settings
        SiteSetting::setValue('marker_categories', $storedCategories);
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
    private function seedTenantDemoData(Tenant $tenant, User $touristUser): void
    {
        if (Property::query()->where('tenant_id', $tenant->id)->count() > 0) {
            return;
        }

        $standard = PropertyType::query()->where('name', 'Standard Room')->whereNull('tenant_id')->firstOrFail();
        $deluxe   = PropertyType::query()->where('name', 'Deluxe Room')->whereNull('tenant_id')->firstOrFail();
        $suite    = PropertyType::query()->where('name', 'Family Suite')->whereNull('tenant_id')->firstOrFail();
        $cottage  = PropertyType::query()->where('name', 'Cottage')->whereNull('tenant_id')->firstOrFail();

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

        // Create employee users and employees with firstOrCreate to avoid duplicates on re-run
        $emp1User = User::firstOrCreate(
            ['email' => 'rico@gmail.com'],
            ['name' => 'Rico Reception', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => 1]
        );
        $emp1User->syncPermissions(['view bookings', 'create bookings', 'view customers', 'create customers']);
        Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $emp1User->id],
            ['name' => 'Rico Reception', 'role' => 'Receptionist', 'phone' => '0917-111-1111', 'is_active' => 1]
        );

        $emp2User = User::firstOrCreate(
            ['email' => 'hannah@gmail.com'],
            ['name' => 'Hannah Housekeeping', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => 1]
        );
        Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $emp2User->id],
            ['name' => 'Hannah Housekeeping', 'role' => 'Housekeeping', 'phone' => '0917-222-2222', 'is_active' => 1]
        );

        $mgrUser = User::firstOrCreate(
            ['email' => 'megan@gmail.com'],
            ['name' => 'Megan Manager', 'password' => Hash::make('password'), 'tenant_id' => $tenant->id, 'is_active' => 1]
        );
        $mgrUser->syncPermissions(['view bookings', 'create bookings', 'view customers', 'view properties', 'view services', 'view payments', 'view employees', 'view analytics']);
        Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $mgrUser->id],
            ['name' => 'Megan Manager', 'role' => 'Manager', 'phone' => '0917-333-3333', 'is_active' => 1]
        );

        // Bookings (using the tourist user)
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