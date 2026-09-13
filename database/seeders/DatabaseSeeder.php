<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use App\Services\KybVerificationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Cache of TypeOfTenant ids keyed by type name, built during seeding. */
    protected array $tenantTypeIds = [];

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        $this->seedMarkerCategories();
        $this->seedTenantTypes();
        $this->seedGlobalPropertyTypes();

        $this->seedSuperAdmin();

        /*
        |------------------------------------------------------------------
        | Three non-overlapping user groups
        |------------------------------------------------------------------
        |
        |   • owners      — dual-role (tourist + admin), each owns one
        |                   tenant. They never have a KYB application.
        |   • tourists    — pure tourists. No tenant, no application.
        |   • applicants  — tourists currently going through KYB.
        |
        | This mirrors the real user lifecycle: Tourist → Applicant → Owner.
        */

        $owners     = $this->seedBusinessOwners();    // 4 users
        $tourists   = $this->seedPureTourists();      // 3 users
        $applicants = $this->seedKybApplicants();     // 4 users

        $this->seedTenants($owners);

        // Bookings — anyone can book a place they don't own.
        $bookers = array_merge($owners, $tourists, $applicants);
        $this->seedBookingsForAllTenants($bookers);

        $this->seedEvents();

        $this->seedBusinessApplications($applicants);
    }

    // ═════════════════════════════════════════════════════════
    //  Site content
    // ═════════════════════════════════════════════════════════

    protected function seedMarkerCategories(): void
    {
        $svgStart = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
        $svgEnd   = '</svg>';

        $categories = [
            ['key' => 'restaurant', 'label' => 'Restaurant',          'color' => '#f97316', 'svg' => $svgStart . '<path d="M3 2v7c0 2.2 1.8 4 4 4h0a4 4 0 0 0 4-4V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>' . $svgEnd],
            ['key' => 'cafe',       'label' => 'Café',                'color' => '#a855f7', 'svg' => $svgStart . '<path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4ZM6 2v2M10 2v2M14 2v2"/>' . $svgEnd],
            ['key' => 'inn',        'label' => 'Inn / Hotel',         'color' => '#3b82f6', 'svg' => $svgStart . '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>' . $svgEnd],
            ['key' => 'shop',       'label' => 'Shopping & Retail',   'color' => '#14b8a6', 'svg' => $svgStart . '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4ZM3 6h18M16 10a4 4 0 0 1-8 0"/>' . $svgEnd],
            ['key' => 'viewpoint',  'label' => 'Nature & Parks',      'color' => '#eab308', 'svg' => $svgStart . '<path d="m17 14 3 3.3a1 1 0 0 1-.7 1.7H4.7a1 1 0 0 1-.7-1.7L7 14h-.3a1 1 0 0 1-.7-1.7L9 9h-.2A1 1 0 0 1 8 7.3L12 3l4 4.3a1 1 0 0 1-.8 1.7H15l3 3.3a1 1 0 0 1-.8 1.7H17ZM12 19v3"/>' . $svgEnd],
            ['key' => 'parking',    'label' => 'Parking',             'color' => '#64748b', 'svg' => $svgStart . '<circle cx="12" cy="12" r="10"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>' . $svgEnd],
            ['key' => 'entrance',   'label' => 'Entrance / Exit',     'color' => '#10b981', 'svg' => $svgStart . '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>' . $svgEnd],
            ['key' => 'hospital',   'label' => 'Hospital & Medical',  'color' => '#ef4444', 'svg' => $svgStart . '<path d="M12 6v4M10 8h4M21 21v-4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v4M2 21h20M3 21V9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12"/>' . $svgEnd],
            ['key' => 'transit',    'label' => 'Transit & Bus',       'color' => '#f59e0b', 'svg' => $svgStart . '<path d="M8 6v6M15 6v6M2 12h19.6M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3M4 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0ZM14 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0Z"/>' . $svgEnd],
            ['key' => 'culture',    'label' => 'Monuments & Culture', 'color' => '#8b5cf6', 'svg' => $svgStart . '<path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>' . $svgEnd],
            ['key' => 'other',      'label' => 'Other',               'color' => '#94a3b8', 'svg' => $svgStart . '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20zM12 8v4M12 16h.01"/>' . $svgEnd],
        ];

        $storedCategories = [];

        foreach ($categories as $cat) {
            $fileName = 'marker-icons/' . $cat['key'] . '.svg';
            Storage::disk('public')->put($fileName, $cat['svg']);

            $storedCategories[] = [
                'key'       => $cat['key'],
                'label'     => $cat['label'],
                'color'     => $cat['color'],
                'icon_path' => $fileName,
                'icon_svg'  => $cat['svg'],
            ];
        }

        SiteSetting::setValue('marker_categories', $storedCategories);
    }

    // ═════════════════════════════════════════════════════════
    //  Placeholder image generator
    // ═════════════════════════════════════════════════════════

    /**
     * Icon path library for placeholder images. 24×24 viewBox, stroke-friendly.
     *
     * @return array<string, string>
     */
    protected function placeholderIcons(): array
    {
        return [
            'leaf'       => '<path d="M11 20A7 7 0 0 1 4 13c0-6 7-10 16-10 0 9-4 16-10 16z"/>',
            'mountain'   => '<path d="M3 20h18L14 8l-4 6-2-3z"/>',
            'palm'       => '<path d="M12 22V10M8 6c2-2 6-2 8 0M12 10c-2-2-5-2-7 0M12 10c2-2 5-2 7 0"/>',
            'ferris'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/>',
            'star'       => '<path d="M12 2l3 7h7l-6 4 2 7-5-4-5 4 2-7-6-4h7z"/>',
            'sprout'     => '<path d="M12 22V12m0 0a5 5 0 005-5V4h-3a5 5 0 00-5 5v3M12 12a5 5 0 01-5-5V4h3a5 5 0 015 5v3"/>',
            'basketball' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3v18M5 5c3 3 3 11 0 14M19 5c-3 3-3 11 0 14"/>',
            'moon'       => '<path d="M20 14a8 8 0 11-10-10 7 7 0 0010 10z"/>',
            'bed'        => '<path d="M3 15v5h18v-5M3 15v-3a2 2 0 012-2h3a2 2 0 012 2v3M10 15h8a2 2 0 012 2H3"/>',
            'crown'      => '<path d="M3 18h18l-2-10-4 5-3-7-3 7-4-5z"/>',
            'home'       => '<path d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10"/>',
            'music'      => '<circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/><path d="M9 18V6l12-2v14"/>',
            'camera'     => '<path d="M3 7h4l2-2h6l2 2h4v13H3z"/><circle cx="12" cy="13" r="4"/>',
        ];
    }

    /**
     * Write an SVG placeholder to the public disk.
     *
     * The generated file is a subtle diagonal gradient with a centered
     * white-stroke icon and a two-line caption. It reads as a real image
     * at a glance and is thematically matched to the entity it represents,
     * so seeded content doesn't look like it's missing assets.
     *
     * The file is written unconditionally — the SVG is deterministic, so
     * overwriting it on re-seed is harmless (and self-heals if a
     * developer deleted the file from disk).
     */
    protected function writeSvgPlaceholder(
        string $relativePath,
        string $caption,
        string $from,
        string $to,
        string $iconKey = '',
        array  $size = [1600, 1200],
        string $subCaption = '',
    ): string {
        [$w, $h] = $size;

        $icons    = $this->placeholderIcons();
        $iconPath = $icons[$iconKey] ?? '';

        $captionEsc    = htmlspecialchars($caption,    ENT_QUOTES | ENT_XML1, 'UTF-8');
        $subCaptionEsc = htmlspecialchars($subCaption, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $minDim   = min($w, $h);
        $iconSize = (int) ($minDim * 0.22);
        $fontSize = max(20, (int) ($minDim * 0.070));
        $subSize  = max(14, (int) ($minDim * 0.042));

        $cx    = (int) ($w / 2);
        $iconX = (int) ($cx - $iconSize / 2);
        $iconY = (int) ($h / 2 - $iconSize * 0.75);
        $textY = (int) ($h / 2 + $iconSize * 0.55);
        $subY  = $textY + (int) ($fontSize * 1.55);

        $iconSvg = $iconPath
            ? sprintf(
                '<g transform="translate(%d, %d) scale(%s)" fill="none" stroke="white" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity="0.92">%s</g>',
                $iconX,
                $iconY,
                number_format($iconSize / 24, 4, '.', ''),
                $iconPath,
            )
            : '';

        $subTextSvg = $subCaptionEsc
            ? sprintf(
                '<text x="%d" y="%d" text-anchor="middle" fill="white" opacity="0.72" font-family="Inter, system-ui, -apple-system, sans-serif" font-size="%d" font-weight="500" letter-spacing="2">%s</text>',
                $cx,
                $subY,
                $subSize,
                $subCaptionEsc,
            )
            : '';

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" preserveAspectRatio="xMidYMid slice">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$from}"/>
      <stop offset="100%" stop-color="{$to}"/>
    </linearGradient>
  </defs>
  <rect width="{$w}" height="{$h}" fill="url(#g)"/>
  {$iconSvg}
  <text x="{$cx}" y="{$textY}" text-anchor="middle" fill="white" font-family="Inter, system-ui, -apple-system, sans-serif" font-size="{$fontSize}" font-weight="700" opacity="0.96" letter-spacing="2">{$captionEsc}</text>
  {$subTextSvg}
</svg>
SVG;

        Storage::disk('public')->put($relativePath, $svg);

        return $relativePath;
    }

    // ═════════════════════════════════════════════════════════
    //  Lookups
    // ═════════════════════════════════════════════════════════

    protected function seedTenantTypes(): void
    {
        $types = [
            ['type' => 'Eco Park',   'description' => 'Nature park'],
            ['type' => 'Resort',     'description' => 'Leisure resort'],
            ['type' => 'Amusement',  'description' => 'Sports & amusement center'],
            ['type' => 'Mangrove',   'description' => 'Mangrove eco-trail'],
            ['type' => 'Inn',        'description' => 'Small lodging'],
            ['type' => 'Restaurant', 'description' => 'Food establishment'],
        ];

        foreach ($types as $data) {
            $row = TypeOfTenant::firstOrCreate(
                ['type' => $data['type']],
                ['description' => $data['description']],
            );

            $this->tenantTypeIds[$data['type']] = $row->id;
        }
    }

    protected function seedGlobalPropertyTypes(): void
    {
        foreach (['Standard Room', 'Deluxe Room', 'Family Suite', 'Cottage'] as $name) {
            PropertyType::firstOrCreate(['name' => $name, 'tenant_id' => null]);
        }
    }

    // ═════════════════════════════════════════════════════════
    //  Users — 3 distinct groups
    // ═════════════════════════════════════════════════════════

    protected function seedSuperAdmin(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name'      => 'System Super Admin',
                'password'  => Hash::make('password'),
                'tenant_id' => null,
                'is_active' => true,
            ]
        );

        $superAdmin->syncRoles(['super-admin']);
    }

    /**
     * @return array<int, User>  Indexed [0..3] → owner1..owner4
     */
    protected function seedBusinessOwners(): array
    {
        $owners = [];

        for ($i = 1; $i <= 4; $i++) {
            $owner = User::firstOrCreate(
                ['email' => "owner{$i}@gmail.com"],
                [
                    'name'        => "Business Owner {$i}",
                    'password'    => Hash::make('password'),
                    'tenant_id'   => null,
                    'active_mode' => User::MODE_BUSINESS,
                    'is_active'   => true,
                ]
            );

            $owner->syncRoles(['tourist', 'admin']);

            $owners[] = $owner;
        }

        return $owners;
    }

    /**
     * @return array<int, User>  Indexed [0..2] → tourist1..tourist3
     */
    protected function seedPureTourists(): array
    {
        $tourists = [];

        for ($i = 1; $i <= 3; $i++) {
            $tourist = User::firstOrCreate(
                ['email' => "tourist{$i}@gmail.com"],
                [
                    'name'        => "Tourist {$i}",
                    'password'    => Hash::make('password'),
                    'tenant_id'   => null,
                    'active_mode' => User::MODE_TOURIST,
                    'is_active'   => true,
                ]
            );

            $tourist->syncRoles(['tourist']);

            $tourists[] = $tourist;
        }

        return $tourists;
    }

    /**
     * @return array<int, User>  Indexed [0..3] → applicant1..applicant4
     */
    protected function seedKybApplicants(): array
    {
        $applicants = [];

        for ($i = 1; $i <= 4; $i++) {
            $applicant = User::firstOrCreate(
                ['email' => "applicant{$i}@gmail.com"],
                [
                    'name'        => "Applicant {$i}",
                    'password'    => Hash::make('password'),
                    'tenant_id'   => null,
                    'active_mode' => User::MODE_TOURIST,
                    'is_active'   => true,
                ]
            );

            $applicant->syncRoles(['tourist']);

            $applicants[] = $applicant;
        }

        return $applicants;
    }

    // ═════════════════════════════════════════════════════════
    //  Tenants
    // ═════════════════════════════════════════════════════════

    /**
     * @param array<int, User> $owners  Indexed [0..3] → owner1..owner4
     */
    protected function seedTenants(array $owners): void
    {
        // Themed logo placeholders — one config per tenant.
        $logoConfig = [
            'baybay-mangrove-eco-trail' => [
                'icon' => 'leaf',     'from' => '#10b981', 'to' => '#047857',
                'cap'  => 'Baybay',   'sub'  => 'Mangrove Eco-Trail',
            ],
            'gawahon-eco-park' => [
                'icon' => 'mountain', 'from' => '#22c55e', 'to' => '#065f46',
                'cap'  => 'Gawahon',  'sub'  => 'Eco Park',
            ],
            'victorias-city-resort' => [
                'icon' => 'palm',     'from' => '#3b82f6', 'to' => '#1e3a8a',
                'cap'  => 'Victorias City', 'sub' => 'Resort',
            ],
            'victorias-city-sports-and-amusement-center' => [
                'icon' => 'ferris',   'from' => '#8b5cf6', 'to' => '#6d28d9',
                'cap'  => 'Sports & Amusement', 'sub' => 'Center',
            ],
        ];

        $tenants = [
            [
                'owner_index'    => 0,
                'name'           => 'Baybay Mangrove Eco-Trail',
                'slug'           => 'baybay-mangrove-eco-trail',
                'type'           => 'Mangrove',
                'address'        => 'Coastal Road, Barangay II (Barangay 2), Victorias City, Negros Occidental',
                'barangay'       => 'Barangay II',
                'contact_number' => '034-399-9999',
                'email'          => 'mangrove@gmail.com',
                'coordinates'    => [
                    ['lat' => 10.92, 'lng' => 123.06, 'name' => 'Baybay Mangrove Eco-Trail', 'type' => 'parent'],
                ],
            ],
            [
                'owner_index'    => 1,
                'name'           => 'Gawahon Eco Park',
                'slug'           => 'gawahon-eco-park',
                'type'           => 'Eco Park',
                'address'        => 'Sitio Malingin, Barangay XIII (Barangay 13), Victorias City, Negros Occidental',
                'barangay'       => 'Barangay XIII',
                'contact_number' => '034-399-2830',
                'email'          => 'gawahon@gmail.com',
                'coordinates'    => [
                    ['lat' => 10.79, 'lng' => 123.18, 'name' => 'Gawahon Eco Park', 'type' => 'parent'],
                ],
            ],
            [
                'owner_index'    => 2,
                'name'           => 'Victorias City Resort',
                'slug'           => 'victorias-city-resort',
                'type'           => 'Resort',
                'address'        => 'Along the Main Highway, Barangay XIII (Barangay 13), Victorias City, Negros Occidental',
                'barangay'       => 'Barangay XIII',
                'contact_number' => '034-409-1234',
                'email'          => 'resort@gmail.com',
                'coordinates'    => [
                    ['lat' => 10.89, 'lng' => 123.05, 'name' => 'Victorias City Resort (Victorias Aquatic Center)', 'type' => 'parent'],
                ],
            ],
            [
                'owner_index'    => 3,
                'name'           => 'Victorias City Sports And Amusement Center',
                'slug'           => 'victorias-city-sports-and-amusement-center',
                'type'           => 'Amusement',
                'address'        => 'Poblacion, Barangay I (Barangay 1), Victorias City, Negros Occidental',
                'barangay'       => 'Barangay I',
                'contact_number' => '034-399-5678',
                'email'          => 'amusementcenter@gmail.com',
                'coordinates'    => [
                    ['lat' => 10.89, 'lng' => 123.05, 'name' => 'Victorias City Sports And Amusement Center', 'type' => 'parent'],
                ],
            ],
        ];

        foreach ($tenants as $data) {
            /** @var User|null $owner */
            $owner = $owners[$data['owner_index']] ?? null;

            if (!$owner) {
                continue;
            }

            // ── 0. Write the tenant logo placeholder ──────────
            $logoCfg  = $logoConfig[$data['slug']];
            $logoPath = 'placeholders/tenants/' . $data['slug'] . '.svg';

            $this->writeSvgPlaceholder(
                $logoPath,
                $logoCfg['cap'],
                $logoCfg['from'],
                $logoCfg['to'],
                $logoCfg['icon'],
                [600, 600],
                $logoCfg['sub'],
            );

            // ── 1. Tenant ─────────────────────────────────────
            $tenant = Tenant::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'name'              => $data['name'],
                    'type_of_tenant_id' => $this->tenantTypeIds[$data['type']] ?? null,
                    'address'           => $data['address'],
                    'barangay'          => $data['barangay'],
                    'contact_number'    => $data['contact_number'],
                    'email'             => $data['email'],
                    'coordinates'       => $data['coordinates'],
                    'logo'              => $logoPath,
                    'is_active'         => true,
                    'verified_at'       => now(),
                ],
            );

            // Self-heal: if the tenant exists without a logo, or the file
            // was manually deleted, restore the placeholder.
            if (!$tenant->logo || !Storage::disk('public')->exists($tenant->logo)) {
                $tenant->update(['logo' => $logoPath]);
            }

            // ── 2. Link the owner ─────────────────────────────
            $owner->update([
                'tenant_id'   => $tenant->id,
                'active_mode' => User::MODE_BUSINESS,
            ]);

            $owner->syncRoles(['tourist', 'admin']);

            // ── 3. Properties, services, employees ────────────
            $this->seedTenantDemoData($tenant);
        }
    }

    protected function seedTenantDemoData(Tenant $tenant): void
    {
        if (Property::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $typeIds = PropertyType::query()
            ->whereIn('name', ['Standard Room', 'Deluxe Room', 'Family Suite', 'Cottage'], 'and', false)
            ->whereNull('tenant_id')
            ->pluck('id', 'name');

        $props = [
            ['name' => 'Standard Room', 'type' => 'Standard Room', 'price' => 1200, 'capacity' => 2, 'desc' => 'Cozy room for two'],
            ['name' => 'Deluxe Room',   'type' => 'Deluxe Room',   'price' => 2000, 'capacity' => 3, 'desc' => 'Spacious with garden view'],
            ['name' => 'Family Suite',  'type' => 'Family Suite',  'price' => 3500, 'capacity' => 5, 'desc' => 'Two bedrooms, perfect for families'],
            ['name' => 'Cottage',       'type' => 'Cottage',       'price' => 800,  'capacity' => 4, 'desc' => 'Rustic cottage near the lake'],
        ];

        $propertyImageConfig = [
            'Standard Room' => ['icon' => 'bed',  'from' => '#64748b', 'to' => '#334155'],
            'Deluxe Room'   => ['icon' => 'bed',  'from' => '#eab308', 'to' => '#a16207'],
            'Family Suite'  => ['icon' => 'home', 'from' => '#3b82f6', 'to' => '#1e40af'],
            'Cottage'       => ['icon' => 'home', 'from' => '#b45309', 'to' => '#78350f'],
        ];

        foreach ($props as $p) {
            $property = Property::create([
                'tenant_id'        => $tenant->id,
                'property_type_id' => $typeIds[$p['type']],
                'name'             => $p['name'],
                'description'      => $p['desc'],
                'price'            => $p['price'],
                'capacity'         => $p['capacity'],
                'quantity'         => 1,
                'status'           => 'available',
                'is_active'        => true,
            ]);

            $imgCfg      = $propertyImageConfig[$p['type']];
            $relativeImg = 'placeholders/properties/' . $p['type'] . '.svg';

            $this->writeSvgPlaceholder(
                $relativeImg,
                $p['name'],
                $imgCfg['from'],
                $imgCfg['to'],
                $imgCfg['icon'],
                [1600, 1200],
                $tenant->name,
            );

            PropertyImage::create([
                'tenant_id'   => $tenant->id,
                'property_id' => $property->id,
                'image_path'  => $relativeImg,
            ]);
        }

        $now = now();

        Service::insert([
            ['tenant_id' => $tenant->id, 'name' => 'Breakfast Buffet', 'price' => 250, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->id, 'name' => 'Airport Transfer', 'price' => 500, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->id, 'name' => 'Guided Tour',      'price' => 300, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->id, 'name' => 'Bike Rental',      'price' => 150, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->seedEmployee($tenant, 'rico',   'Rico Reception',      'Receptionist', '0917-111-1111', ['front desk']);
        $this->seedEmployee($tenant, 'hannah', 'Hannah Housekeeping', 'Housekeeping', '0917-222-2222', []);
        $this->seedEmployee($tenant, 'megan',  'Megan Manager',       'Manager',      '0917-333-3333', ['property manager']);
    }

    /**
     * @param array<int, string> $roles
     */
    protected function seedEmployee(
        Tenant $tenant,
        string $handle,
        string $name,
        string $role,
        string $phone,
        array $roles,
    ): void {
        $email = "{$handle}+{$tenant->id}@gmail.com";

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'      => $name,
                'password'  => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ],
        );

        if (!$user->tenant_id) {
            $user->update(['tenant_id' => $tenant->id]);
        }

        if (!empty($roles)) {
            $user->syncRoles($roles);
        }

        Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            [
                'code'      => 'EMP-' . strtoupper(Str::random(6)),
                'name'      => $name,
                'role'      => $role,
                'phone'     => $phone,
                'is_active' => true,
            ],
        );
    }

    // ═════════════════════════════════════════════════════════
    //  Bookings
    // ═════════════════════════════════════════════════════════

    /**
     * @param array<int, User> $bookers
     */
    protected function seedBookingsForAllTenants(array $bookers): void
    {
        Tenant::query()->each(function (Tenant $tenant) use ($bookers) {
            $this->seedTenantBookings($tenant, $bookers);
        });
    }

    /**
     * @param array<int, User> $bookers
     */
    protected function seedTenantBookings(Tenant $tenant, array $bookers): void
    {
        if (Booking::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $propertyIds    = Property::query()->where('tenant_id', $tenant->id)->pluck('id')->toArray();
        $propertyPrices = Property::query()->where('tenant_id', $tenant->id)->pluck('price', 'id')->toArray();
        $serviceIds     = Service::query()->where('tenant_id', $tenant->id)->pluck('id')->toArray();
        $servicePrices  = Service::query()->where('tenant_id', $tenant->id)->pluck('price', 'id')->toArray();

        if (empty($propertyIds) || empty($serviceIds)) {
            return;
        }

        $candidates = array_values(array_filter(
            $bookers,
            fn (User $u) => $u->tenant_id !== $tenant->id,
        ));

        if (empty($candidates)) {
            return;
        }

        shuffle($candidates);
        $sample = array_slice($candidates, 0, 5);

        $statusPlan = [
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_PENDING,
            Booking::STATUS_RESERVED,
            Booking::STATUS_CANCELLED,
        ];

        foreach ($sample as $index => $booker) {
            $status = $statusPlan[$index % count($statusPlan)];

            $isPast = in_array($status, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED], true);

            $checkIn = $isPast
                ? Carbon::now()->subDays(random_int(5, 30))
                : Carbon::now()->addDays(random_int(3, 21));

            $checkOut = $checkIn->copy()->addDays(random_int(1, 4));

            $roomId    = $propertyIds[array_rand($propertyIds)];
            $roomPrice = $propertyPrices[$roomId];
            $nights    = $checkIn->diffInDays($checkOut) ?: 1;
            $total     = $roomPrice * $nights;

            $bookingType = $status === Booking::STATUS_RESERVED
                ? Booking::TYPE_RESERVATION
                : Booking::TYPE_FULL;

            $booking = Booking::create([
                'tenant_id'         => $tenant->id,
                'user_id'           => $booker->id,
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $checkIn,
                'check_out'         => $checkOut,
                'total_amount'      => $total,
                'status'            => $status,
                'booking_type'      => $bookingType,
                'created_at'        => $checkIn->copy()->subDays(random_int(1, 5)),
            ]);

            BookingItem::create([
                'tenant_id'   => $tenant->id,
                'booking_id'  => $booking->id,
                'property_id' => $roomId,
                'price'       => $roomPrice,
                'quantity'    => 1,
                'subtotal'    => $total,
            ]);

            for ($j = 0, $max = random_int(0, 2); $j < $max; $j++) {
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
            }

            if ($total !== (float) $booking->total_amount) {
                $booking->update(['total_amount' => $total]);
            }

            /*
             * Canonical `payment_status` values are only 'pending' and 'paid'.
             */
            $paymentStatus = match ($status) {
                Booking::STATUS_CANCELLED => 'pending',
                Booking::STATUS_PENDING   => random_int(0, 1) ? 'paid' : 'pending',
                Booking::STATUS_RESERVED,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_COMPLETED => 'paid',
                default                   => 'pending',
            };

            $paymentAmount = $bookingType === Booking::TYPE_RESERVATION
                ? round($total * 0.20, 2)
                : $total;

            Payment::create([
                'tenant_id'        => $tenant->id,
                'booking_id'       => $booking->id,
                'amount'           => $paymentAmount,
                'payment_method'   => collect(['cash', 'gcash', 'card'])->random(),
                'payment_type'     => $bookingType,
                'payment_status'   => $paymentStatus,
                'paid_at'          => $paymentStatus === 'paid'
                    ? $booking->created_at->copy()->addHours(random_int(1, 10))
                    : null,
                'reference_number' => $paymentStatus === 'paid'
                    ? 'TXN-' . Str::upper(Str::random(10))
                    : null,
                'created_at'       => $booking->created_at,
                'updated_at'       => now(),
            ]);
        }
    }

    // ═════════════════════════════════════════════════════════
    //  Events
    // ═════════════════════════════════════════════════════════

    protected function seedEvents(): void
    {
        $resortTenantId   = Tenant::query()->where('slug', 'victorias-city-resort')->value('id');
        $mangroveTenantId = Tenant::query()->where('slug', 'baybay-mangrove-eco-trail')->value('id');

        $eventImageConfig = [
            'Sinulog Festival' => [
                'icon' => 'star',  'from' => '#f59e0b', 'to' => '#b45309',
                'cap'  => 'Sinulog Festival', 'sub' => 'Cultural Fiesta',
            ],
            'Mangrove Planting Day' => [
                'icon' => 'sprout', 'from' => '#10b981', 'to' => '#065f46',
                'cap'  => 'Mangrove Planting', 'sub' => 'Community Event',
            ],
            'Summer Sports Fest' => [
                'icon' => 'basketball', 'from' => '#f97316', 'to' => '#9a3412',
                'cap'  => 'Summer Sports Fest', 'sub' => 'Inter-Barangay',
            ],
            'Gawahon Eco-Trail Fun Run' => [
                'icon' => 'basketball', 'from' => '#14b8a6', 'to' => '#0f766e',
                'cap'  => 'Eco-Trail Fun Run', 'sub' => '5K Trail',
            ],
            'Victorias City Resort Summer Nights' => [
                'icon' => 'music', 'from' => '#a855f7', 'to' => '#4c1d95',
                'cap'  => 'Summer Nights', 'sub' => 'Live DJ · Poolside',
            ],
            'Mangrove Night Walk' => [
                'icon' => 'moon',  'from' => '#0f172a', 'to' => '#1e3a8a',
                'cap'  => 'Night Walk', 'sub' => 'Fireflies Tour',
            ],
        ];

        foreach ($eventImageConfig as $eventName => $cfg) {
            $slug = Str::slug($eventName);
            $this->writeSvgPlaceholder(
                'placeholders/events/' . $slug . '.svg',
                $cfg['cap'],
                $cfg['from'],
                $cfg['to'],
                $cfg['icon'],
                [1600, 900],
                $cfg['sub'],
            );
        }

        $events = [
            [
                'name'        => 'Sinulog Festival',
                'barangay'    => 'Barangay Santo Niño',
                'description' => 'A vibrant cultural and religious festival honoring the Santo Niño, featuring street dancing and fluvial parade.',
                'type'        => 'fiesta',
                'start_date'  => Carbon::now()->addDays(30),
                'end_date'    => Carbon::now()->addDays(32),
                'coordinates' => ['lat' => 10.9090, 'lng' => 123.0770],
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
                'coordinates' => ['lat' => 10.92, 'lng' => 123.06],
                'tenant_id'   => null,
                'is_active'   => true,
                'featured'    => false,
            ],
            [
                'name'        => 'Summer Sports Fest',
                'barangay'    => 'Barangay VI',
                'description' => 'Inter-barangay basketball and volleyball tournament with live music and food stalls.',
                'type'        => 'sports',
                'start_date'  => Carbon::now()->addDays(45),
                'end_date'    => Carbon::now()->addDays(47),
                'coordinates' => ['lat' => 10.89, 'lng' => 123.05],
                'tenant_id'   => null,
                'is_active'   => true,
                'featured'    => false,
            ],
            [
                'name'        => 'Gawahon Eco-Trail Fun Run',
                'barangay'    => 'Barangay XIII',
                'description' => 'A 5K fun run through the scenic trails of Gawahon Eco Park. Open to all ages!',
                'type'        => 'sports',
                'start_date'  => Carbon::now()->addDays(21),
                'end_date'    => Carbon::now()->addDays(21),
                'coordinates' => ['lat' => 10.79, 'lng' => 123.18],
                'tenant_id'   => null,
                'is_active'   => true,
                'featured'    => false,
            ],
            [
                'name'        => 'Victorias City Resort Summer Nights',
                'barangay'    => 'Barangay XIII',
                'description' => 'Exclusive resort party with live DJ, poolside cocktails, and fireworks.',
                'type'        => 'entertainment',
                'start_date'  => Carbon::now()->addDays(60),
                'end_date'    => Carbon::now()->addDays(60),
                'coordinates' => ['lat' => 10.89, 'lng' => 123.05],
                'tenant_id'   => $resortTenantId,
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
                'coordinates' => ['lat' => 10.92, 'lng' => 123.06],
                'tenant_id'   => $mangroveTenantId,
                'is_active'   => true,
                'featured'    => false,
            ],
        ];

        foreach ($events as $data) {
            $imagePath = 'placeholders/events/' . Str::slug($data['name']) . '.svg';

            $event = Event::firstOrCreate(
                ['name' => $data['name'], 'start_date' => $data['start_date']],
                $data + ['image_path' => $imagePath],
            );

            if (!$event->image_path || !Storage::disk('public')->exists($event->image_path)) {
                $event->update(['image_path' => $imagePath]);
            }
        }
    }

    // ═════════════════════════════════════════════════════════
    //  KYB applications
    // ═════════════════════════════════════════════════════════

    /**
     * @param array<int, User> $applicants  Indexed [0..3] → applicant1..applicant4
     */
    protected function seedBusinessApplications(array $applicants): void
    {
        $scenarios = [
            [
                'user'           => $applicants[0] ?? null,
                'status'         => BusinessApplication::STATUS_DRAFT,
                'business_name'  => 'Lakeside Kiosk',
                'business_type'  => 'dti',
                'tenant_type'    => 'Restaurant',
                'with_documents' => false,
            ],
            [
                'user'               => $applicants[1] ?? null,
                'status'             => BusinessApplication::STATUS_NEEDS_REVISION,
                'business_name'      => 'Sunrise Café',
                'business_type'      => 'dti',
                'tenant_type'        => 'Restaurant',
                'with_documents'     => true,
                'revision_notes'     => 'Please re-upload BIR Form 2303 — the attached scan is unreadable. Ensure the TIN is clearly visible.',
                'submitted_days_ago' => 5,
            ],
            [
                'user'               => $applicants[2] ?? null,
                'status'             => BusinessApplication::STATUS_PENDING,
                'business_name'      => 'Hilltop Inn',
                'business_type'      => 'dti',
                'tenant_type'        => 'Inn',
                'with_documents'     => true,
                // Scenario-supplied TIN is honored so the automated
                // TIN-vs-BIR-2303 match produces a positive result.
                'tin_number'         => '123-456-789-000',
                'bir_number'         => '123-456-789-000',
                'run_verification'   => true,
                'submitted_days_ago' => 2,
            ],
            [
                'user'               => $applicants[3] ?? null,
                'status'             => BusinessApplication::STATUS_REJECTED,
                'business_name'      => 'Downtown Bar',
                'business_type'      => 'dti',
                'tenant_type'        => 'Restaurant',
                'with_documents'     => true,
                'rejection_reason'   => 'TIN on BIR Form 2303 does not match the submitted TIN. Please correct and reapply.',
                'submitted_days_ago' => 10,
            ],
        ];

        foreach ($scenarios as $s) {
            /** @var User|null $user */
            $user = $s['user'];

            if (!$user) {
                continue;
            }

            $application = BusinessApplication::firstOrCreate(
                [
                    'user_id'       => $user->id,
                    'business_name' => $s['business_name'],
                ],
                $this->buildApplicationPayload($s, $user),
            );

            if (($s['with_documents'] ?? false) && !$application->documents()->exists()) {
                $this->attachDemoDocuments($application, $user, $s['bir_number'] ?? null);
            }

            if (($s['run_verification'] ?? false) && !$application->verifications()->exists()) {
                app(KybVerificationService::class)->verify($application->fresh(['documents']));
            }
        }
    }

    /**
     * Build the create() payload for a KYB scenario.
     *
     * Deterministic TINs and Registration Numbers:
     *
     *   Every submitted application gets a unique TIN and Reg No. that
     *   are (a) format-valid per the KYB form's regex rules, (b) distinct
     *   across the seeded users, and (c) stable across re-seeds.
     *
     *   The numbers are derived from the user's id, so user A always gets
     *   the same TIN regardless of how many times the seeder runs. This
     *   matters for the `assertUniqueBusinessIdentifiers()` check in
     *   BusinessApplicationService::submit() — if two seeded applications
     *   shared a TIN, an in-app resubmit would fail.
     *
     *   Scenario-supplied `tin_number` wins (used by Hilltop Inn to match
     *   its BIR Form 2303 for the automated-verification demo).
     */
    protected function buildApplicationPayload(array $s, User $user): array
    {
        $status      = $s['status'];
        $isSubmitted = in_array($status, [
            BusinessApplication::STATUS_PENDING,
            BusinessApplication::STATUS_UNDER_REVIEW,
            BusinessApplication::STATUS_NEEDS_REVISION,
            BusinessApplication::STATUS_REJECTED,
        ], true);

        $reviewed = in_array($status, [
            BusinessApplication::STATUS_NEEDS_REVISION,
            BusinessApplication::STATUS_REJECTED,
        ], true);

        // Deterministic TIN — 12 digits in NNN-NNN-NNN-NNN grouping.
        // Derived from the user id so it's unique per seeded user.
        $tinNumber = $s['tin_number'] ?? sprintf(
            '100-%03d-%03d-%03d',
            intdiv($user->id, 1_000_000) % 1000,
            intdiv($user->id, 1_000) % 1000,
            $user->id % 1000,
        );

        // Deterministic Registration No. — format matches the placeholder
        // shown in the KYB form for the chosen business_type.
        $regNumber = match ($s['business_type']) {
            'dti'   => sprintf('DTI-2024-%06d', $user->id),
            'sec'   => sprintf('CS2024%06d',    $user->id),
            'cda'   => sprintf('CDA-2024-%05d', $user->id),
            default => sprintf('REG-2024-%06d', $user->id),
        };

        return [
            'user_id'                      => $user->id,
            'business_name'                => $s['business_name'],
            'business_type'                => $s['business_type'],
            'business_registration_number' => $isSubmitted ? $regNumber : null,
            'tin_number'                   => $isSubmitted ? $tinNumber : null,
            'owner_full_name'              => $isSubmitted ? $user->name : null,
            'owner_id_type'                => $isSubmitted ? 'drivers_license' : null,
            'owner_id_number'              => $isSubmitted ? 'N01-23-456789' : null,
            'owner_birthdate'              => $isSubmitted ? now()->subYears(35)->subDays(random_int(0, 365)) : null,
            'contact_email'                => $user->email,
            'contact_phone'                => '0917' . random_int(1000000, 9999999),
            'address'                      => $isSubmitted ? 'Street address, Victorias City' : null,
            'barangay'                     => $isSubmitted ? 'Barangay V' : null,
            'city'                         => $isSubmitted ? 'Victorias City' : null,
            'province'                     => $isSubmitted ? 'Negros Occidental' : null,
            'type_of_tenant_id'            => $isSubmitted
                ? ($this->tenantTypeIds[$s['tenant_type']] ?? null)
                : null,
            'status'                       => $status,
            'revision_notes'               => $s['revision_notes'] ?? null,
            'rejection_reason'             => $s['rejection_reason'] ?? null,
            'submitted_at'                 => $isSubmitted
                ? now()->subDays($s['submitted_days_ago'] ?? 1)
                : null,
            'reviewed_at'                  => $reviewed
                ? now()->subDays(max(1, ($s['submitted_days_ago'] ?? 1) - 1))
                : null,
            'reviewed_by'                  => $reviewed
                ? User::query()->where('email', 'superadmin@gmail.com')->value('id')
                : null,
        ];
    }

    /**
     * Write a tiny placeholder JPG per required document so the
     * super-admin's View / Download links resolve.
     */
    protected function attachDemoDocuments(BusinessApplication $application, User $user, ?string $birNumber = null): void
    {
        $placeholderJpg = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        );

        $documents = [
            BusinessDocument::TYPE_DTI_SEC_CDA   => 'DTI-Registration-2024.jpg',
            BusinessDocument::TYPE_BIR_2303      => 'BIR-Form-2303.jpg',
            BusinessDocument::TYPE_MAYORS_PERMIT => 'Mayors-Permit-2024.jpg',
            BusinessDocument::TYPE_OWNER_ID      => 'Owner-Valid-ID.jpg',
        ];

        foreach ($documents as $type => $originalFilename) {
            $storedPath      = "kyb-documents/{$application->id}/{$type}.jpg";
            $watermarkedPath = "kyb-documents/watermarked/{$application->id}-{$type}.jpg";

            Storage::disk('public')->put($storedPath, $placeholderJpg);
            Storage::disk('public')->put($watermarkedPath, $placeholderJpg);

            BusinessDocument::create([
                'business_application_id' => $application->id,
                'user_id'                 => $user->id,
                'document_type'           => $type,
                'original_filename'       => $originalFilename,
                'stored_path'             => $storedPath,
                'watermarked_path'        => $watermarkedPath,
                'mime_type'               => 'image/jpeg',
                'file_size'               => strlen($placeholderJpg),
                'file_hash'               => hash('sha256', $placeholderJpg),
                'document_number'         => $type === BusinessDocument::TYPE_BIR_2303 ? $birNumber : null,
                'issued_at'               => now()->subMonths(3),
                'expires_at'              => $type === BusinessDocument::TYPE_MAYORS_PERMIT
                    ? now()->addMonths(9)
                    : null,
                'verification_status'     => BusinessDocument::STATUS_PENDING,
                'watermarked_at'          => now(),
            ]);
        }
    }
}