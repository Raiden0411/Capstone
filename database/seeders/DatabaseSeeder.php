<?php

namespace Database\Seeders;

use App\Models\AccountDeletionRequest;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Payment;
use App\Models\PermitRenewalReminder;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\KybVerificationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Cache of TypeOfTenant ids keyed by type name, built during seeding. */
    protected array $tenantTypeIds = [];

    /** Compression summary counters, printed at the end of run(). */
    protected array $compressionStats = [
        'compressed' => 0,
        'skipped'    => 0,
        'failed'     => 0,
    ];

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        $this->seedMarkerCategories();
        $this->seedTenantTypes();
        $this->seedGlobalPropertyTypes();
        $this->seedSiteContent();

        $this->seedSuperAdmins();

        $spots      = $this->touristSpots();
        $owners     = $this->seedBusinessOwners(count($spots));
        $tourists   = $this->seedPureTourists();
        $applicants = $this->seedKybApplicants();

        $this->seedTenants($spots, $owners);

        $bookers = array_merge($owners, $tourists, $applicants);
        $this->seedBookingsForAllTenants($bookers);

        $this->seedBusinessApplications($applicants);
        $this->seedAccountDeletionRequests($owners, $tourists);
        $this->seedPermitRenewalReminders();

        $this->printSummary();
    }

    protected function printSummary(): void
    {
        $this->command->newLine();
        $this->command->info('✓ Seed complete.');

        $this->command->newLine();
        $this->command->line('  <fg=cyan;options=bold>Image compression</>');
        $this->command->line(sprintf('    <fg=green>Compressed:</> %d', $this->compressionStats['compressed']));
        $this->command->line(sprintf('    <fg=gray>Skipped   :</> %d', $this->compressionStats['skipped']));
        $this->command->line(sprintf('    <fg=red>Failed    :</> %d', $this->compressionStats['failed']));

        $this->command->newLine();
        $this->command->line('  <fg=cyan;options=bold>Test credentials</> (password = <fg=yellow>password</>)');
        $this->command->line('    Superadmin    : superadmin@gmail.com');
        $this->command->line('    Business owner: owner1@gmail.com .. owner' . count($this->touristSpots()) . '@gmail.com');
        $this->command->line('    Tourist       : tourist1@gmail.com .. tourist6@gmail.com');
        $this->command->line('    Applicant     : applicant1@gmail.com .. applicant4@gmail.com');
        $this->command->newLine();
    }

    // ═════════════════════════════════════════════════════════
    //  Image fetching — download, compress, store locally
    // ═════════════════════════════════════════════════════════

    protected function fallbackJpg(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        );
    }

    /**
     * Download a remote URL to the public disk, compress it in place using
     * the same ImageCompressionService that user uploads flow through, and
     * return true on success.
     *
     * Idempotent: if the target file already exists, the download is
     * skipped. Compression is also skipped on subsequent seeds unless
     * $forceCompress is true — that flag bypasses the "already under
     * the ceiling" short-circuit in ImageCompressionService.
     *
     * Force-compress is the seeder's default so every downloaded image
     * gets re-encoded — the compressed file has EXIF stripped, is
     * auto-oriented, and goes through the quality ladder. This is the
     * fastest way to verify the compressor is wired up end-to-end.
     */
    protected function downloadAndCompressOnce(
        string $url,
        string $relativePath,
        ?string $context = null,
        bool $forceCompress = true,
    ): bool {
        $disk = Storage::disk('public');

        // Already on disk — optionally force a re-compress, then bail.
        if ($disk->exists($relativePath)) {
            if ($forceCompress && $context !== null) {
                $this->compressStoredFile($disk->path($relativePath), $relativePath, $context, true);
            }
            return true;
        }

        // ── 1. Download ──────────────────────────────────────
        try {
            $response = Http::timeout(20)
                ->withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'CapstoneSeeder/1.0'])
                ->get($url);

            if (! $response->successful()) {
                Log::warning("Seeder: HTTP {$response->status()} for {$url}");
                $disk->put($relativePath, $this->fallbackJpg());
                return false;
            }

            $disk->put($relativePath, $response->body());
        } catch (\Throwable $e) {
            Log::warning("Seeder: failed to download {$url}: {$e->getMessage()}");
            $disk->put($relativePath, $this->fallbackJpg());
            return false;
        }

        // ── 2. Compress in place via the system's own service ──
        if ($context !== null) {
            $this->compressStoredFile($disk->path($relativePath), $relativePath, $context, $forceCompress);
        }

        return true;
    }

    /**
     * Run a stored file through the compression service and report the
     * result. Never throws — a compression failure leaves the file intact.
     */
    protected function compressStoredFile(
        string $absolutePath,
        string $relativePath,
        string $context,
        bool $force = false,
    ): void {
        try {
            $before = @filesize($absolutePath) ?: 0;

            $did = app(ImageCompressionService::class)
                ->compressInPlace($absolutePath, $context, force: $force);

            clearstatcache(true, $absolutePath);
            $after = @filesize($absolutePath) ?: 0;

            if ($did) {
                $this->compressionStats['compressed']++;

                $saved = $before - $after;
                $pct   = $before > 0 ? round(($saved / $before) * 100, 1) : 0;

                $this->command?->getOutput()->writeln(sprintf(
                    '  <fg=green;options=bold>✓</> <fg=gray>%s</> %s → %s <fg=gray>(−%s%%)</>',
                    $context,
                    $this->humanBytes($before),
                    $this->humanBytes($after),
                    $pct,
                ));
            } else {
                $this->compressionStats['skipped']++;

                $this->command?->getOutput()->writeln(sprintf(
                    '  <fg=gray>·</> <fg=gray>%s</> %s <fg=gray>(no change needed)</>',
                    $context,
                    $this->humanBytes($before),
                ));
            }
        } catch (\Throwable $e) {
            $this->compressionStats['failed']++;

            Log::warning("Seeder: compression failed for {$relativePath}: {$e->getMessage()}");

            $this->command?->getOutput()->writeln(sprintf(
                '  <fg=red>✗</> <fg=gray>%s</> %s <fg=gray>(%s)</>',
                $context,
                $relativePath,
                $e->getMessage(),
            ));
        }
    }

    protected function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1024 / 1024, 2) . ' MB';
    }

    protected function picsum(string $seed, int $width = 1200, int $height = 800): string
    {
        $slug = Str::slug($seed) ?: 'placeholder';
        return "https://picsum.photos/seed/{$slug}/{$width}/{$height}";
    }

    protected function randomUserUrl(string $email): string
    {
        $hash  = crc32(strtolower($email));
        $index = ($hash % 99) + 1;
        $sex   = ($hash % 2 === 0) ? 'men' : 'women';

        return "https://randomuser.me/api/portraits/{$sex}/{$index}.jpg";
    }

    protected function fetchLogo(string $slug): string
    {
        $relativePath = "placeholders/tenants/{$slug}.jpg";
        $this->downloadAndCompressOnce(
            $this->picsum("logo-{$slug}", 600, 600),
            $relativePath,
            'tenant-logo',
        );
        return $relativePath;
    }

    protected function fetchSiteLogo(): string
    {
        $relativePath = 'placeholders/site/logo.jpg';
        $this->downloadAndCompressOnce(
            $this->picsum('site-logo-victorias', 600, 600),
            $relativePath,
            'site',
        );
        return $relativePath;
    }

    protected function fetchSiteHero(): string
    {
        $relativePath = 'placeholders/site/hero.jpg';
        $this->downloadAndCompressOnce(
            $this->picsum('site-hero-victorias', 1920, 1080),
            $relativePath,
            'site',
        );
        return $relativePath;
    }

    protected function fetchSiteSideImage(int $index): string
    {
        $relativePath = "placeholders/site/side-{$index}.jpg";
        $this->downloadAndCompressOnce(
            $this->picsum("site-side-{$index}", 800, 800),
            $relativePath,
            'site',
        );
        return $relativePath;
    }

    protected function fetchSpotCover(string $tenantSlug): string
    {
        $relativePath = "placeholders/covers/{$tenantSlug}.jpg";
        $this->downloadAndCompressOnce(
            $this->picsum("cover-{$tenantSlug}", 1920, 900),
            $relativePath,
            'tenant-cover',
        );
        return $relativePath;
    }

    protected function fetchGalleryImage(string $tenantSlug, int $index): string
    {
        $relativePath = "placeholders/gallery/{$tenantSlug}-{$index}.jpg";
        $this->downloadAndCompressOnce(
            $this->picsum("gallery-{$tenantSlug}-{$index}", 1200, 900),
            $relativePath,
            'property',
        );
        return $relativePath;
    }

    protected function fetchPropertyImage(string $tenantSlug, string $propertyName): string
    {
        $propSlug     = Str::slug($propertyName);
        $relativePath = "placeholders/properties/{$tenantSlug}-{$propSlug}.jpg";

        $this->downloadAndCompressOnce(
            $this->picsum("{$tenantSlug}-{$propertyName}", 1600, 1200),
            $relativePath,
            'property',
        );
        return $relativePath;
    }

    protected function fetchEventImage(string $eventName): string
    {
        $slug         = Str::slug($eventName);
        $relativePath = "placeholders/events/{$slug}.jpg";

        $this->downloadAndCompressOnce(
            $this->picsum("event-{$eventName}", 1600, 900),
            $relativePath,
            'event',
        );
        return $relativePath;
    }

    protected function fetchAvatar(string $email): string
    {
        $relativePath = 'placeholders/avatars/' . md5(strtolower($email)) . '.jpg';

        $this->downloadAndCompressOnce(
            $this->randomUserUrl($email),
            $relativePath,
            'avatars',
        );
        return $relativePath;
    }

    // ═════════════════════════════════════════════════════════
    //  Site content
    // ═════════════════════════════════════════════════════════

    protected function seedSiteContent(): void
    {
        $logoPath = $this->fetchSiteLogo();
        $heroPath = $this->fetchSiteHero();

        SiteSetting::setValue('site_name', 'Victorias City Tourism');
        SiteSetting::setValue('site_logo', $logoPath);
        SiteSetting::setValue('hero_background_image', $heroPath);

        SiteSetting::setValue('hero_title', 'Welcome to the North');
        SiteSetting::setValue('hero_subtitle', 'Victorias City');
        SiteSetting::setValue(
            'hero_description',
            'Escape into a world where the air is scented with sugar cane and the mountains hum with hidden waterfalls. A breathtaking sanctuary in Negros Occidental.'
        );

        for ($i = 1; $i <= 4; $i++) {
            SiteSetting::setValue("hero_side_image_{$i}", $this->fetchSiteSideImage($i));
        }

        SiteSetting::setValue('discover_title', 'The City of Smiles & Heritage');
        SiteSetting::setValue(
            'discover_description',
            'Victorias is more than just an industrial hub; it is a blend of natural sanctuary, deep-rooted history, and warm hospitality. Experience the unique charm that makes this city a hidden gem in Western Visayas.'
        );
    }

    protected function seedMarkerCategories(): void
    {
        $svgStart = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
        $svgEnd   = '</svg>';

        $categories = [
            ['key' => 'restaurant', 'label' => 'Restaurant',          'color' => '#f97316', 'svg' => $svgStart . '<path d="M3 2v7c0 2.2 1.8 4 4 4h0a4 4 0 0 0 4-4V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>' . $svgEnd],
            ['key' => 'cafe',       'label' => 'Café',                'color' => '#a855f7', 'svg' => $svgStart . '<path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4ZM6 2v2M10 2v2M14 2v2"/>' . $svgEnd],
            ['key' => 'bar',        'label' => 'Bar & Restobar',      'color' => '#ec4899', 'svg' => $svgStart . '<path d="M5 3h14l-7 8-7-8zM12 11v9M8 20h8"/>' . $svgEnd],
            ['key' => 'inn',        'label' => 'Inn / Hotel',         'color' => '#3b82f6', 'svg' => $svgStart . '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>' . $svgEnd],
            ['key' => 'shop',       'label' => 'Shopping & Retail',   'color' => '#14b8a6', 'svg' => $svgStart . '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4ZM3 6h18M16 10a4 4 0 0 1-8 0"/>' . $svgEnd],
            ['key' => 'viewpoint',  'label' => 'Nature & Parks',      'color' => '#eab308', 'svg' => $svgStart . '<path d="m17 14 3 3.3a1 1 0 0 1-.7 1.7H4.7a1 1 0 0 1-.7-1.7L7 14h-.3a1 1 0 0 1-.7-1.7L9 9h-.2A1 1 0 0 1 8 7.3L12 3l4 4.3a1 1 0 0 1-.8 1.7H15l3 3.3a1 1 0 0 1-.8 1.7H17ZM12 19v3"/>' . $svgEnd],
            ['key' => 'parking',    'label' => 'Parking',             'color' => '#64748b', 'svg' => $svgStart . '<circle cx="12" cy="12" r="10"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>' . $svgEnd],
            ['key' => 'entrance',   'label' => 'Entrance / Exit',     'color' => '#10b981', 'svg' => $svgStart . '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>' . $svgEnd],
            ['key' => 'hospital',   'label' => 'Hospital & Medical',  'color' => '#ef4444', 'svg' => $svgStart . '<path d="M12 6v4M10 8h4M21 21v-4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v4M2 21h20M3 21V9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12"/>' . $svgEnd],
            ['key' => 'transit',    'label' => 'Transit & Bus',       'color' => '#f59e0b', 'svg' => $svgStart . '<path d="M8 6v6M15 6v6M2 12h19.6M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3M4 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0ZM14 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0Z"/>' . $svgEnd],
            ['key' => 'culture',    'label' => 'Monuments & Culture', 'color' => '#8b5cf6', 'svg' => $svgStart . '<path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>' . $svgEnd],
            ['key' => 'farm',       'label' => 'Farm & Agri-Tourism', 'color' => '#84cc16', 'svg' => $svgStart . '<path d="M3 12h18M12 3v18M5 7l7 5 7-5M5 17l7-5 7 5"/>' . $svgEnd],
            ['key' => 'wine',       'label' => 'Winery & Distillery', 'color' => '#7c2d12', 'svg' => $svgStart . '<path d="M8 22h8M12 15v7M6 3h12l-1 6a5 5 0 0 1-10 0z"/>' . $svgEnd],
            ['key' => 'other',      'label' => 'Other',               'color' => '#94a3b8', 'svg' => $svgStart . '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20zM12 8v4M12 16h.01"/>' . $svgEnd],
        ];

        $storedCategories = [];

        foreach ($categories as $cat) {
            $storedCategories[] = [
                'key'       => $cat['key'],
                'label'     => $cat['label'],
                'color'     => $cat['color'],
                'icon_path' => null,
                'icon_svg'  => $cat['svg'],
            ];
        }

        SiteSetting::setValue('marker_categories', $storedCategories);
    }

    // ═════════════════════════════════════════════════════════
    //  Lookups
    // ═════════════════════════════════════════════════════════

    protected function seedTenantTypes(): void
    {
        $types = [
            ['type' => 'Eco-Tourism & Nature Park',           'description' => 'Upland nature reserves with trails and waterfalls'],
            ['type' => 'Eco-Tourism & Coastal Reserve',       'description' => 'Coastal ecosystems with boardwalks and mangrove conservation'],
            ['type' => 'Birdwatching & Wildlife Sanctuary',   'description' => 'Protected habitats for endemic and migratory species'],
            ['type' => 'Farm & Agri-Tourism',                 'description' => 'Working farms open for educational tours and produce sales'],
            ['type' => 'Winery & Distillery',                 'description' => 'Local producers of fruit wines and spirits'],
            ['type' => 'Cultural & Heritage Landmark',        'description' => 'Historic churches, monuments, and heritage sites'],
            ['type' => 'Modern Art & Architecture',           'description' => 'Significant modernist and contemporary structures'],
            ['type' => 'Industrial Heritage Site',            'description' => 'Preserved industrial landmarks and museums'],
            ['type' => 'Recreation & Entertainment Park',     'description' => 'Multi-purpose venues with sports, pools, and event facilities'],
            ['type' => 'Sports & Events Arena',               'description' => 'Large-capacity venues for sports and concerts'],
            ['type' => 'Public Park & Town Center',           'description' => 'Civic squares and community gathering spaces'],
            ['type' => 'Food & Beverage Producer',            'description' => 'Commercial food production and processing'],
            ['type' => 'Inn',                                 'description' => 'Small lodging'],
            ['type' => 'Restaurant',                          'description' => 'Food establishment'],
            ['type' => 'Resort',                              'description' => 'Leisure resort'],
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
        $types = [
            'Standard Room', 'Deluxe Room', 'Family Suite', 'Cottage',
            'Day Pass', 'Pool Pass', 'Beach Pass',
            'Guided Tour', 'Activity Package', 'Farm Tour', 'Wine Tasting',
            'Birdwatching Tour', 'Heritage Tour', 'Eco-Trek', 'Waterfall Trek',
            'Event Space', 'Sports Court Rental', 'Equipment Rental',
            'Souvenir Package',
        ];

        foreach ($types as $name) {
            PropertyType::firstOrCreate(['name' => $name, 'tenant_id' => null]);
        }
    }

    // ═════════════════════════════════════════════════════════
    //  Users
    // ═════════════════════════════════════════════════════════

    protected function seedSuperAdmins(): void
    {
        $superAdmins = [
            ['name' => 'System Super Admin', 'email' => 'superadmin@gmail.com'],
            ['name' => 'Tourism Admin',      'email' => 'tourism.management.ph@gmail.com'],
        ];

        foreach ($superAdmins as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'      => $data['name'],
                    'password'  => Hash::make('password'),
                    'tenant_id' => null,
                    'is_active' => true,
                ]
            );

            if (! ($user->avatar && ! str_starts_with($user->avatar, 'http') && Storage::disk('public')->exists($user->avatar))) {
                $user->update(['avatar' => $this->fetchAvatar($data['email'])]);
            }

            $user->syncRoles(['super-admin']);
        }
    }

    protected function seedBusinessOwners(int $count): array
    {
        $owners = [];

        for ($i = 1; $i <= $count; $i++) {
            $email = "owner{$i}@gmail.com";

            $owner = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'        => "Business Owner {$i}",
                    'password'    => Hash::make('password'),
                    'tenant_id'   => null,
                    'active_mode' => User::MODE_BUSINESS,
                    'is_active'   => true,
                ]
            );

            if (! ($owner->avatar && ! str_starts_with($owner->avatar, 'http') && Storage::disk('public')->exists($owner->avatar))) {
                $owner->update(['avatar' => $this->fetchAvatar($email)]);
            }

            $owner->syncRoles(['tourist', 'admin']);
            $owners[] = $owner;
        }

        return $owners;
    }

    protected function seedPureTourists(): array
    {
        $tourists = [];

        for ($i = 1; $i <= 6; $i++) {
            $email = "tourist{$i}@gmail.com";

            $tourist = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'        => "Tourist {$i}",
                    'password'    => Hash::make('password'),
                    'tenant_id'   => null,
                    'active_mode' => User::MODE_TOURIST,
                    'is_active'   => true,
                ]
            );

            if (! ($tourist->avatar && ! str_starts_with($tourist->avatar, 'http') && Storage::disk('public')->exists($tourist->avatar))) {
                $tourist->update(['avatar' => $this->fetchAvatar($email)]);
            }

            $tourist->syncRoles(['tourist']);
            $tourists[] = $tourist;
        }

        return $tourists;
    }

    protected function seedKybApplicants(): array
    {
        $applicants = [];

        for ($i = 1; $i <= 4; $i++) {
            $email = "applicant{$i}@gmail.com";

            $applicant = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'        => "Applicant {$i}",
                    'password'    => Hash::make('password'),
                    'tenant_id'   => null,
                    'active_mode' => User::MODE_TOURIST,
                    'is_active'   => true,
                ]
            );

            if (! ($applicant->avatar && ! str_starts_with($applicant->avatar, 'http') && Storage::disk('public')->exists($applicant->avatar))) {
                $applicant->update(['avatar' => $this->fetchAvatar($email)]);
            }

            $applicant->syncRoles(['tourist']);
            $applicants[] = $applicant;
        }

        return $applicants;
    }

    // ═════════════════════════════════════════════════════════
    //  Tourist spots
    // ═════════════════════════════════════════════════════════

    protected function touristSpots(): array
    {
        return [
            ['slug' => 'gawahon-eco-park', 'name' => 'Gawahon Eco Park', 'type' => 'Eco-Tourism & Nature Park', 'barangay' => 'Barangay XI', 'address' => 'Barangay XI, Victorias City, Negros Occidental', 'contact_number' => '034-399-2830', 'email' => 'gawahon@gmail.com', 'description' => 'Scenic upland nature park featuring seven natural waterfalls and hiking trails.', 'coordinates' => ['lat' => 10.79, 'lng' => 123.18],
                'nearby' => [
                    ['name' => 'Kabisera Restaurant',                  'type' => 'restaurant'],
                    ['name' => 'Matawhay Yard Cafe',                   'type' => 'cafe'],
                    ['name' => 'Masskara Chicken Inasal - Victorias',  'type' => 'restaurant'],
                    ['name' => 'Gawahon Eco-Lodge & Staff Cottages',   'type' => 'inn'],
                ],
                'properties' => [
                    ['name' => 'Day Tour Pass',          'type' => 'Day Pass',            'price' => 80,   'capacity' => 100, 'desc' => 'Full-day access to all seven waterfalls and hiking trails.'],
                    ['name' => 'Picnic Cottage',         'type' => 'Cottage',             'price' => 500,  'capacity' => 10,  'desc' => 'Shaded day-use cottage near the main falls.'],
                    ['name' => 'Guided Waterfall Trek',  'type' => 'Waterfall Trek',      'price' => 300,  'capacity' => 20,  'desc' => 'Guided 3-hour trek to all seven waterfalls.'],
                    ['name' => 'Birder\'s Paradise Tour','type' => 'Birdwatching Tour',    'price' => 450,  'capacity' => 12,  'desc' => 'Guided birdwatching tour to spot 106+ bird species.'],
                    ['name' => 'Camping Package',        'type' => 'Activity Package',     'price' => 1000, 'capacity' => 4,   'desc' => 'Overnight camping with tent, firewood, and breakfast.'],
                    ['name' => 'Koi Pond Feeding',       'type' => 'Equipment Rental',     'price' => 50,   'capacity' => 5,   'desc' => 'Fish feed packet for the Gawahon koi pond.'],
                ],
                'services' => [
                    ['name' => 'Cooking Fee',        'price' => 100],
                    ['name' => 'Trek Guide',         'price' => 400],
                    ['name' => 'Camping Kit Rental', 'price' => 250],
                    ['name' => 'Photography Guide',  'price' => 350],
                    ['name' => 'Binoculars Rental',  'price' => 150],
                ],
            ],
            ['slug' => 'baybay-mangrove-eco-trail', 'name' => 'Baybay Mangrove Eco-Trail', 'type' => 'Eco-Tourism & Coastal Reserve', 'barangay' => 'Barangay VI-A', 'address' => 'Barangay VI-A, Victorias City, Negros Occidental', 'contact_number' => '034-399-9999', 'email' => 'mangrove@gmail.com', 'description' => 'Boardwalk winding through protected mangrove forests along the coast.', 'coordinates' => ['lat' => 10.92, 'lng' => 123.06],
                'nearby' => [
                    ['name' => 'Tambayan Sa Kamalig Restobar', 'type' => 'bar'],
                    ['name' => 'Mi Kafé Coffee Shop',          'type' => 'cafe'],
                    ['name' => 'Teaman Cafe',                  'type' => 'cafe'],
                    ['name' => 'Purpaul Cafe',                 'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Boardwalk Day Pass',     'type' => 'Day Pass',         'price' => 50,   'capacity' => 50, 'desc' => 'Full-day access to the mangrove boardwalk.'],
                    ['name' => 'Birdwatching Tour',      'type' => 'Birdwatching Tour', 'price' => 250,  'capacity' => 10, 'desc' => 'Guided 2-hour birdwatching tour with binoculars included.'],
                    ['name' => 'Educational Group Tour', 'type' => 'Guided Tour',     'price' => 150,  'capacity' => 30, 'desc' => 'Curriculum-linked eco-tour for school groups.'],
                    ['name' => 'Sunset Kayak Rental',    'type' => 'Equipment Rental', 'price' => 400,  'capacity' => 2,  'desc' => 'Single kayak rental for a guided sunset paddle.'],
                ],
                'services' => [
                    ['name' => 'Binoculars Rental',   'price' => 100],
                    ['name' => 'Photography Guide',   'price' => 300],
                    ['name' => 'Snack Basket',        'price' => 200],
                    ['name' => 'Boat Tour Extension', 'price' => 500],
                ],
            ],
            ['slug' => 'st-joseph-worker-parish-church', 'name' => 'St. Joseph the Worker Parish Church (Angry Christ Church)', 'type' => 'Cultural & Heritage Landmark', 'barangay' => 'Barangay XVI', 'address' => 'VMC Compound, Barangay XVI, Victorias City, Negros Occidental', 'contact_number' => '034-399-5000', 'email' => 'vmcchurch@gmail.com', 'description' => 'Famous church featuring the renowned "Angry Christ" mural painted by Alfonso Ossorio.', 'coordinates' => ['lat' => 10.90, 'lng' => 123.07],
                'nearby' => [
                    ['name' => 'Cheriza\'s Refreshment',        'type' => 'restaurant'],
                    ['name' => 'Gloria\'s Eatery Store',        'type' => 'restaurant'],
                    ['name' => 'VMC Club House & Dining Hall',  'type' => 'restaurant'],
                    ['name' => 'Cafe Rac\'s',                   'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Heritage Guided Tour',  'type' => 'Heritage Tour',   'price' => 200,  'capacity' => 20,  'desc' => 'Guided 45-minute tour of the church, mural, and VMC history.'],
                    ['name' => 'Mural Viewing Package', 'type' => 'Activity Package','price' => 500,  'capacity' => 10,  'desc' => 'Private viewing of the Angry Christ mural with a curator.'],
                    ['name' => 'Group Pilgrimage',      'type' => 'Guided Tour',     'price' => 300,  'capacity' => 40,  'desc' => 'Spiritual pilgrimage package with a parish guide.'],
                    ['name' => 'Event Hall Rental',     'type' => 'Event Space',     'price' => 5000, 'capacity' => 100, 'desc' => 'Parish hall rental for weddings, baptisms, and gatherings.'],
                ],
                'services' => [
                    ['name' => 'Audio Guide',        'price' => 100],
                    ['name' => 'Souvenir Bundle',    'price' => 250],
                    ['name' => 'Photography Permit', 'price' => 300],
                    ['name' => 'Event Catering',     'price' => 1500],
                ],
            ],
            ['slug' => 'victorias-public-plaza', 'name' => 'Victorias Public Plaza', 'type' => 'Public Park & Town Center', 'barangay' => 'Barangay V', 'address' => 'City Proper, Victorias City, Negros Occidental', 'contact_number' => '034-399-1111', 'email' => 'plaza@gmail.com', 'description' => 'Central community square surrounded by municipal halls and local commercial hubs.', 'coordinates' => ['lat' => 10.90, 'lng' => 123.07],
                'nearby' => [
                    ['name' => 'Elisha\'s Inn',         'type' => 'inn'],
                    ['name' => 'SJ Tourist Inn',       'type' => 'inn'],
                    ['name' => 'Malihaw Inn',          'type' => 'inn'],
                    ['name' => '3 Aces Cozy Condo',    'type' => 'inn'],
                    ['name' => 'Timoteo\'s Bistro',    'type' => 'restaurant'],
                    ['name' => 'Cafe Casa Javelosa',   'type' => 'cafe'],
                    ['name' => 'Tawhay Balay Kapehan', 'type' => 'cafe'],
                    ['name' => '18th Coffee',          'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Weekend Market Stall',   'type' => 'Event Space',    'price' => 500,  'capacity' => 10,  'desc' => 'Covered stall for the Saturday and Sunday market.'],
                    ['name' => 'Food Kiosk Rental',      'type' => 'Event Space',    'price' => 800,  'capacity' => 20,  'desc' => 'Food kiosk with power and water hookup.'],
                    ['name' => 'Event Pavilion',         'type' => 'Event Space',    'price' => 2500, 'capacity' => 100, 'desc' => 'Open-air pavilion for civic and cultural events.'],
                    ['name' => 'Fountain Photo Pass',    'type' => 'Day Pass',       'price' => 50,   'capacity' => 4,   'desc' => 'Evening photo session at the plaza fountain.'],
                    ['name' => 'Heritage Square Tour',   'type' => 'Heritage Tour',  'price' => 100,  'capacity' => 15,  'desc' => 'Guided walking tour of the Victorias Heritage Square.'],
                ],
                'services' => [
                    ['name' => 'Food Voucher',     'price' => 150],
                    ['name' => 'Souvenir Package', 'price' => 200],
                    ['name' => 'Parking Pass',     'price' => 50],
                    ['name' => 'Event Assistance', 'price' => 300],
                ],
            ],
            ['slug' => 'victorias-city-resort', 'name' => 'Victorias City Resort & Sports/Amusement Center', 'type' => 'Recreation & Entertainment Park', 'barangay' => 'Barangay XIII', 'address' => 'Barangay XIII, Victorias City, Negros Occidental', 'contact_number' => '034-409-1234', 'email' => 'resort@gmail.com', 'description' => 'Multi-purpose recreation venue with swimming pools, sports facilities, and event venues.', 'coordinates' => ['lat' => 10.89, 'lng' => 123.05],
                'nearby' => [
                    ['name' => 'D\'Breakers Resto',                      'type' => 'restaurant'],
                    ['name' => '@Missy\'s Restaurant Herbs and Spices',  'type' => 'restaurant'],
                    ['name' => 'Triple R Restobar & Catering Services',  'type' => 'bar'],
                    ['name' => 'El Tio Charles Bar and Restaurant',      'type' => 'bar'],
                    ['name' => 'BOK Seafood Grill and Resto Bar',        'type' => 'restaurant'],
                ],
                'properties' => [
                    ['name' => 'Standard Room',       'type' => 'Standard Room',       'price' => 1200, 'capacity' => 2,   'desc' => 'Cozy room for two with garden view.'],
                    ['name' => 'Deluxe Room',         'type' => 'Deluxe Room',         'price' => 2000, 'capacity' => 3,   'desc' => 'Spacious deluxe room with poolside view.'],
                    ['name' => 'Family Suite',        'type' => 'Family Suite',        'price' => 3500, 'capacity' => 5,   'desc' => 'Two-bedroom suite, perfect for families.'],
                    ['name' => 'Pool Day Pass',       'type' => 'Pool Pass',           'price' => 150,  'capacity' => 50,  'desc' => 'Day access to all pools and waterslides.'],
                    ['name' => 'Sports Court Rental', 'type' => 'Sports Court Rental','price' => 500,  'capacity' => 20,  'desc' => 'Basketball or volleyball court, one hour slot.'],
                    ['name' => 'Event Pavilion',      'type' => 'Event Space',         'price' => 8000, 'capacity' => 200, 'desc' => 'Covered event venue for parties and reunions.'],
                ],
                'services' => [
                    ['name' => 'Breakfast Buffet', 'price' => 250],
                    ['name' => 'Airport Transfer', 'price' => 500],
                    ['name' => 'Guided City Tour', 'price' => 300],
                    ['name' => 'Bike Rental',      'price' => 150],
                ],
            ],
            ['slug' => 'immaculate-concepcion-cathedral', 'name' => 'Immaculate Concepcion Cathedral', 'type' => 'Cultural & Heritage Landmark', 'barangay' => 'Barangay VI', 'address' => 'Canetown Subdivision, Victorias City, Negros Occidental', 'contact_number' => '034-399-6000', 'email' => 'cathedral@gmail.com', 'description' => 'One of the biggest churches in Visayas and Mindanao, a monumental edifice in Canetown Subdivision.', 'coordinates' => ['lat' => 10.91, 'lng' => 123.08],
                'nearby' => [
                    ['name' => 'Canetown Eatery',        'type' => 'restaurant'],
                    ['name' => 'Jollibee Victorias',     'type' => 'restaurant'],
                    ['name' => 'Chowking Victorias',     'type' => 'restaurant'],
                    ['name' => 'Cafe Rac\'s Restaurant', 'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Cathedral Tour',       'type' => 'Heritage Tour',  'price' => 150,  'capacity' => 30,  'desc' => 'Guided tour of the cathedral and its history.'],
                    ['name' => 'Wedding Package',      'type' => 'Event Space',    'price' => 15000,'capacity' => 200, 'desc' => 'Full wedding ceremony package with reception.'],
                    ['name' => 'Baptism Package',      'type' => 'Event Space',    'price' => 3000, 'capacity' => 50,  'desc' => 'Baptism ceremony with certificate and souvenirs.'],
                ],
                'services' => [
                    ['name' => 'Choir Rental',       'price' => 500],
                    ['name' => 'Flower Arrangement', 'price' => 800],
                    ['name' => 'Photography Permit', 'price' => 300],
                ],
            ],
            ['slug' => 'carabao-sundial', 'name' => 'Carabao Sundial', 'type' => 'Modern Art & Architecture', 'barangay' => 'Barangay XVI', 'address' => 'Millsite Plaza, VMC Compound, Victorias City, Negros Occidental', 'contact_number' => '034-399-5001', 'email' => 'sundial@gmail.com', 'description' => 'A functional art installation built in 1975 by Don Bosco students, featuring a worker carrying sugar cane atop a carabao head.', 'coordinates' => ['lat' => 10.879, 'lng' => 123.075],
                'nearby' => [
                    ['name' => 'VMC Club House & Dining Hall', 'type' => 'restaurant'],
                    ['name' => 'Cheriza\'s Refreshment',       'type' => 'restaurant'],
                    ['name' => 'Millsite Plaza',               'type' => 'viewpoint'],
                ],
                'properties' => [
                    ['name' => 'Sundial Photo Session', 'type' => 'Activity Package', 'price' => 200, 'capacity' => 10, 'desc' => 'Professional photo session at the historic Carabao Sundial.'],
                    ['name' => 'Historical Marker Tour', 'type' => 'Heritage Tour',  'price' => 100, 'capacity' => 20, 'desc' => 'Guided tour of the sundial and surrounding historical markers.'],
                ],
                'services' => [
                    ['name' => 'Instant Print Photo', 'price' => 150],
                    ['name' => 'Souvenir Postcard',   'price' => 50],
                ],
            ],
            ['slug' => 'vmc-golf-country-club', 'name' => 'VMC Golf & Country Club', 'type' => 'Recreation & Entertainment Park', 'barangay' => 'Barangay XVI', 'address' => 'VMC Compound, Barangay XVI, Victorias City, Negros Occidental', 'contact_number' => '034-399-5100', 'email' => 'vmcgolf@gmail.com', 'description' => 'An 18-hole golf course with a lighted driving range, classified as PAR 71, set within the Victorias Milling Company compound.', 'coordinates' => ['lat' => 10.88, 'lng' => 123.07],
                'nearby' => [
                    ['name' => 'VMC Club House & Dining Hall', 'type' => 'restaurant'],
                    ['name' => 'Millsite Plaza',               'type' => 'viewpoint'],
                    ['name' => 'Gloria\'s Eatery Store',       'type' => 'restaurant'],
                ],
                'properties' => [
                    ['name' => 'Green Fee (18 Holes)', 'type' => 'Sports Court Rental', 'price' => 2500, 'capacity' => 4,  'desc' => 'Full 18-hole green fee with cart rental.'],
                    ['name' => 'Driving Range Pass',   'type' => 'Sports Court Rental', 'price' => 500,  'capacity' => 1,  'desc' => 'One-hour driving range session with 100 balls.'],
                    ['name' => 'Golf Lesson',          'type' => 'Activity Package',    'price' => 1500, 'capacity' => 1,  'desc' => 'One-on-one golf lesson with a club pro.'],
                ],
                'services' => [
                    ['name' => 'Caddie Fee',       'price' => 500],
                    ['name' => 'Golf Cart Rental', 'price' => 800],
                    ['name' => 'Club Rental',      'price' => 600],
                ],
            ],
            ['slug' => 'iron-dinosaur-steam-locomotive', 'name' => 'Iron Dinosaur Steam Locomotive No. 13', 'type' => 'Industrial Heritage Site', 'barangay' => 'Barangay XVI', 'address' => 'VMC Compound, Barangay XVI, Victorias City, Negros Occidental', 'contact_number' => '034-399-5101', 'email' => 'irondinosaur@gmail.com', 'description' => 'A 99-year-old steam locomotive built in 1925 by Baldwin Locomotive Works, used by VMC to transport sugarcane. Stands 9 feet tall, weighs 18 tons.', 'coordinates' => ['lat' => 10.881, 'lng' => 123.071],
                'nearby' => [
                    ['name' => 'Millsite Plaza',               'type' => 'viewpoint'],
                    ['name' => 'VMC Club House & Dining Hall', 'type' => 'restaurant'],
                ],
                'properties' => [
                    ['name' => 'Locomotive Photo Pass',  'type' => 'Activity Package',  'price' => 100, 'capacity' => 10, 'desc' => 'Photo session with the historic steam locomotive.'],
                    ['name' => 'Industrial Heritage Tour','type' => 'Heritage Tour',     'price' => 250, 'capacity' => 20, 'desc' => 'Guided tour of the VMC industrial heritage sites.'],
                ],
                'services' => [
                    ['name' => 'Souvenir Train Whistle', 'price' => 250],
                    ['name' => 'Print Photo',            'price' => 150],
                ],
            ],
            ['slug' => 'penalosa-farm', 'name' => 'Peñalosa Farm', 'type' => 'Farm & Agri-Tourism', 'barangay' => 'Barangay V', 'address' => 'Victorias City, Negros Occidental', 'contact_number' => '0917-363-3885', 'email' => 'penalosafarm@gmail.com', 'description' => 'Integrated organic farm producing certified organic vegetables, herbs, and fruits with educational farm tours.', 'coordinates' => ['lat' => 10.895, 'lng' => 123.075],
                'nearby' => [
                    ['name' => 'Kbrew Coffee',          'type' => 'cafe'],
                    ['name' => 'Tawhay Balay Kapehan',  'type' => 'cafe'],
                    ['name' => '18th Coffee',           'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Farm Tour & Lecture',   'type' => 'Farm Tour',       'price' => 200, 'capacity' => 20, 'desc' => 'Day tour with lecture on organic farming.'],
                    ['name' => 'Farm Tour with Snacks', 'type' => 'Farm Tour',       'price' => 350, 'capacity' => 20, 'desc' => 'Day tour with lecture, farm tour, and snacks.'],
                    ['name' => 'Farm Lunch Package',    'type' => 'Activity Package', 'price' => 500, 'capacity' => 15, 'desc' => 'Lunch package for groups of 15+ with advance booking.'],
                ],
                'services' => [
                    ['name' => 'Organic Vegetable Box', 'price' => 300],
                    ['name' => 'Herb Garden Tour',      'price' => 150],
                    ['name' => 'Farming Workshop',      'price' => 500],
                ],
            ],
            ['slug' => 'victorias-city-coliseum', 'name' => 'Victorias City Coliseum', 'type' => 'Sports & Events Arena', 'barangay' => 'Barangay V', 'address' => 'City Proper, Victorias City, Negros Occidental', 'contact_number' => '034-399-2222', 'email' => 'coliseum@gmail.com', 'description' => 'A 13,000-capacity coliseum hosting major national sports events, concerts, and the Kadalag-an Festival highlights.', 'coordinates' => ['lat' => 10.903, 'lng' => 123.073],
                'nearby' => [
                    ['name' => 'Elisha\'s Inn',        'type' => 'inn'],
                    ['name' => 'SJ Tourist Inn',      'type' => 'inn'],
                    ['name' => 'Timoteo\'s Bistro',   'type' => 'restaurant'],
                    ['name' => 'Cafe Casa Javelosa',  'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Event Day Pass',      'type' => 'Day Pass',            'price' => 100,  'capacity' => 500, 'desc' => 'General admission for coliseum events.'],
                    ['name' => 'Court Rental',        'type' => 'Sports Court Rental', 'price' => 3000, 'capacity' => 40,  'desc' => 'Full court rental for basketball or volleyball.'],
                    ['name' => 'VIP Box Rental',      'type' => 'Event Space',         'price' => 15000,'capacity' => 20,  'desc' => 'Private VIP box for 20 with catering.'],
                ],
                'services' => [
                    ['name' => 'Parking Pass',       'price' => 100],
                    ['name' => 'Food Voucher',       'price' => 200],
                    ['name' => 'Event Photography',  'price' => 500],
                ],
            ],
            ['slug' => 'yap-quina-arts-cultural-center', 'name' => 'Don Alejandro Acuña Yap-Quiña Arts and Cultural Center', 'type' => 'Cultural & Heritage Landmark', 'barangay' => 'Barangay V', 'address' => 'City Proper, Victorias City, Negros Occidental', 'contact_number' => '034-399-3333', 'email' => 'culturalcenter@gmail.com', 'description' => 'The city\'s premier arts and cultural venue, hosting award nights, exhibits, performances, and the Kadalag-an Festival Awards.', 'coordinates' => ['lat' => 10.901, 'lng' => 123.071],
                'nearby' => [
                    ['name' => 'Kbrew Coffee',         'type' => 'cafe'],
                    ['name' => 'Tawhay Balay Kapehan', 'type' => 'cafe'],
                    ['name' => '18th Coffee',          'type' => 'cafe'],
                    ['name' => 'Cafe Rac\'s',          'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Gallery Exhibition Pass',  'type' => 'Day Pass',        'price' => 100,  'capacity' => 50, 'desc' => 'Access to rotating art exhibits and cultural displays.'],
                    ['name' => 'Theater Performance Ticket','type' => 'Event Space',     'price' => 300,  'capacity' => 200,'desc' => 'Ticket to scheduled theater and musical performances.'],
                    ['name' => 'Hall Rental',              'type' => 'Event Space',      'price' => 10000,'capacity' => 150,'desc' => 'Full hall rental for conferences and cultural events.'],
                ],
                'services' => [
                    ['name' => 'Audio Guide',       'price' => 150],
                    ['name' => 'Catering Package',  'price' => 1200],
                    ['name' => 'Event Photography', 'price' => 800],
                ],
            ],
            ['slug' => 'millsite-plaza', 'name' => 'Millsite Plaza', 'type' => 'Public Park & Town Center', 'barangay' => 'Barangay XVI', 'address' => 'VMC Compound, Barangay XVI, Victorias City, Negros Occidental', 'contact_number' => '034-399-5002', 'email' => 'millsite@gmail.com', 'description' => 'A tranquil park inside the VMC compound, adjacent to the Carabao Sundial and the Chapel of St. Joseph the Worker.', 'coordinates' => ['lat' => 10.8795, 'lng' => 123.0755],
                'nearby' => [
                    ['name' => 'Cheriza\'s Refreshment',       'type' => 'restaurant'],
                    ['name' => 'VMC Club House & Dining Hall', 'type' => 'restaurant'],
                    ['name' => 'Cafe Rac\'s',                  'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Picnic Table Rental',  'type' => 'Equipment Rental', 'price' => 300, 'capacity' => 8,  'desc' => 'Reserved picnic table for the day.'],
                    ['name' => 'Garden Photo Session', 'type' => 'Activity Package', 'price' => 200, 'capacity' => 5,  'desc' => 'Photo session in the garden surroundings.'],
                ],
                'services' => [
                    ['name' => 'Snack Basket', 'price' => 200],
                    ['name' => 'Parking Pass', 'price' => 50],
                ],
            ],
            ['slug' => 'federicos-island-wine', 'name' => 'Federico\'s Island Wine', 'type' => 'Winery & Distillery', 'barangay' => 'Barangay IX', 'address' => 'Toreno Heights, Basa Subdivision, Brgy. 9, Victorias City, Negros Occidental', 'contact_number' => '034-399-4444', 'email' => 'federicoswine@gmail.com', 'description' => 'Award-winning local winemaker producing bignay wine from handpicked Philippine fruits, named Best Bignay Wine in the 2009 National Tropical Fruit Wine Competition.', 'coordinates' => ['lat' => 10.915, 'lng' => 123.055],
                'nearby' => [
                    ['name' => 'Tambayan Sa Kamalig Restobar', 'type' => 'bar'],
                    ['name' => 'Mi Kafé Coffee Shop',          'type' => 'cafe'],
                    ['name' => 'Teaman Cafe',                  'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Wine Tasting Session',   'type' => 'Wine Tasting',     'price' => 350,  'capacity' => 10, 'desc' => 'Guided tasting of Federico\'s bignay wines.'],
                    ['name' => 'Vineyard Tour',          'type' => 'Guided Tour',      'price' => 250,  'capacity' => 15, 'desc' => 'Tour of the bignay orchard and winemaking process.'],
                    ['name' => 'Wine Gift Set',          'type' => 'Souvenir Package', 'price' => 800,  'capacity' => 1,  'desc' => 'Gift set of three Federico\'s wine bottles.'],
                ],
                'services' => [
                    ['name' => 'Bottled Wine (Bignay)',  'price' => 450],
                    ['name' => 'Wine Pairing Snacks',    'price' => 200],
                    ['name' => 'Custom Label Bottle',    'price' => 600],
                ],
            ],
            ['slug' => 'victorias-foods-corporation', 'name' => 'Victorias Foods Corporation', 'type' => 'Food & Beverage Producer', 'barangay' => 'Barangay XVI', 'address' => 'J.J. Ossorio Street, Brgy. 16, Victorias City, Negros Occidental', 'contact_number' => '034-399-5500', 'email' => 'victoriasfoods@gmail.com', 'description' => 'Producer of some of the best canned sardines, bangus, and meats in the Philippines, including luncheon meat, lechon paksiw, ham, and bacon.', 'coordinates' => ['lat' => 10.882, 'lng' => 123.073],
                'nearby' => [
                    ['name' => 'Millsite Plaza',               'type' => 'viewpoint'],
                    ['name' => 'VMC Club House & Dining Hall', 'type' => 'restaurant'],
                    ['name' => 'Gloria\'s Eatery Store',       'type' => 'restaurant'],
                ],
                'properties' => [
                    ['name' => 'Factory Tour',          'type' => 'Guided Tour',     'price' => 300,  'capacity' => 20, 'desc' => 'Guided tour of the sardine and meat processing facility.'],
                    ['name' => 'Product Sampling',      'type' => 'Activity Package', 'price' => 200,  'capacity' => 15, 'desc' => 'Sampling session of Victorias Foods products.'],
                    ['name' => 'Bulk Gift Pack',        'type' => 'Souvenir Package', 'price' => 1500, 'capacity' => 1,  'desc' => 'Assorted Victorias Foods products gift pack.'],
                ],
                'services' => [
                    ['name' => 'Canned Goods Bundle', 'price' => 500],
                    ['name' => 'Cookbook Purchase',   'price' => 350],
                    ['name' => 'Delivery Service',    'price' => 200],
                ],
            ],
            ['slug' => 'daan-banwa-heritage-site', 'name' => 'Daan Banwa Heritage Site (Old Town)', 'type' => 'Cultural & Heritage Landmark', 'barangay' => 'Barangay IX', 'address' => 'Daan Banwa, Barangay IX, Victorias City, Negros Occidental', 'contact_number' => '034-399-5555', 'email' => 'daanbanwa@gmail.com', 'description' => 'The original settlement of Victorias, a historic fishing village on the Malihaw River where the city\'s story began.', 'coordinates' => ['lat' => 10.925, 'lng' => 123.05],
                'nearby' => [
                    ['name' => 'Malihaw River Walk',      'type' => 'viewpoint'],
                    ['name' => 'Daan Banwa Fishing Pier', 'type' => 'viewpoint'],
                    ['name' => 'Tambayan Sa Kamalig',     'type' => 'bar'],
                ],
                'properties' => [
                    ['name' => 'Heritage Walking Tour', 'type' => 'Heritage Tour',   'price' => 150,  'capacity' => 25, 'desc' => 'Guided walk through the historic old town and river.'],
                    ['name' => 'Fluvial Parade Viewing','type' => 'Day Pass',        'price' => 100,  'capacity' => 30, 'desc' => 'Reserved viewing area for the Malihaw Festival.'],
                    ['name' => 'Historical Photo Walk', 'type' => 'Activity Package', 'price' => 250,  'capacity' => 10, 'desc' => 'Guided photo walk through heritage architecture.'],
                ],
                'services' => [
                    ['name' => 'Local Snack Basket', 'price' => 150],
                    ['name' => 'Boat Ride',          'price' => 200],
                    ['name' => 'Souvenir Postcard',  'price' => 50],
                ],
            ],
            ['slug' => 'elemnan-manok-bay', 'name' => 'Elemnan Manok Bay (Virgin Beach)', 'type' => 'Eco-Tourism & Coastal Reserve', 'barangay' => 'Barangay VI-A', 'address' => 'Coastal Road, Barangay VI-A, Victorias City, Negros Occidental', 'contact_number' => '034-399-6666', 'email' => 'elemnanmanok@gmail.com', 'description' => 'An undeveloped, pristine beach with blue sea water, cold breeze, white sand, and beautiful coral reefs — a hidden gem for nature lovers.', 'coordinates' => ['lat' => 10.935, 'lng' => 123.04],
                'nearby' => [
                    ['name' => 'Coastal Road Viewpoint', 'type' => 'viewpoint'],
                    ['name' => 'Mangrove Eco-Trail',    'type' => 'viewpoint'],
                    ['name' => 'Mi Kafé Coffee Shop',   'type' => 'cafe'],
                ],
                'properties' => [
                    ['name' => 'Beach Day Pass',     'type' => 'Beach Pass',      'price' => 30,   'capacity' => 100, 'desc' => 'Full-day access to the pristine beach.'],
                    ['name' => 'Beach Camping',      'type' => 'Activity Package','price' => 500,  'capacity' => 6,   'desc' => 'Overnight beach camping with basic amenities.'],
                    ['name' => 'Snorkeling Adventure','type' => 'Activity Package','price' => 400,  'capacity' => 10,  'desc' => 'Guided snorkeling tour of the coral reefs.'],
                    ['name' => 'Sunset Picnic Setup', 'type' => 'Event Space',    'price' => 800,  'capacity' => 8,   'desc' => 'Pre-arranged sunset picnic setup on the beach.'],
                ],
                'services' => [
                    ['name' => 'Beach Umbrella Rental', 'price' => 100],
                    ['name' => 'Snorkel Gear Rental',   'price' => 200],
                    ['name' => 'Grill Rental',          'price' => 300],
                ],
            ],
            ['slug' => 'victorias-city-sports-and-amusement-center', 'name' => 'Victorias City Sports And Amusement Center', 'type' => 'Sports & Events Arena', 'barangay' => 'Barangay I', 'address' => 'Poblacion, Barangay I, Victorias City, Negros Occidental', 'contact_number' => '034-399-5678', 'email' => 'amusementcenter@gmail.com', 'description' => 'Sports and amusement complex with courts, rides, and family-friendly facilities in the heart of the city.', 'coordinates' => ['lat' => 10.89, 'lng' => 123.05],
                'nearby' => [
                    ['name' => 'D\'Breakers Resto',        'type' => 'restaurant'],
                    ['name' => 'El Tio Charles Bar',       'type' => 'bar'],
                    ['name' => 'BOK Seafood Grill',        'type' => 'restaurant'],
                ],
                'properties' => [
                    ['name' => 'Sports Court Rental', 'type' => 'Sports Court Rental', 'price' => 500,  'capacity' => 20, 'desc' => 'Basketball or volleyball court, one hour slot.'],
                    ['name' => 'Amusement Ride Pass', 'type' => 'Day Pass',            'price' => 200,  'capacity' => 50, 'desc' => 'All-day access to amusement rides.'],
                    ['name' => 'Event Pavilion',      'type' => 'Event Space',         'price' => 5000, 'capacity' => 150,'desc' => 'Covered event venue for parties and reunions.'],
                ],
                'services' => [
                    ['name' => 'Food Stall Voucher', 'price' => 100],
                    ['name' => 'Game Token Bundle',  'price' => 200],
                ],
            ],
        ];
    }

    protected function buildCoordinates(array $tenant): array
    {
        $base = $tenant['coordinates'];
        $coords = [[
            'lat'  => $base['lat'],
            'lng'  => $base['lng'],
            'name' => $tenant['name'],
            'type' => 'parent',
        ]];

        $nearby = $tenant['nearby'] ?? [];
        $count  = count($nearby);

        if ($count === 0) return $coords;

        $radius = 0.003;

        foreach ($nearby as $i => $place) {
            $angle = (2 * M_PI * $i) / $count;
            $coords[] = [
                'uid'  => (string) Str::uuid(),
                'name' => $place['name'],
                'lat'  => round($base['lat'] + $radius * cos($angle), 6),
                'lng'  => round($base['lng'] + $radius * sin($angle), 6),
                'type' => $place['type'],
            ];
        }

        return $coords;
    }

    // ═════════════════════════════════════════════════════════
    //  Tenants
    // ═════════════════════════════════════════════════════════

    protected function seedTenants(array $spots, array $owners): void
    {
        foreach ($spots as $index => $data) {
            $owner = $owners[$index] ?? null;
            if (! $owner) continue;

            $ownerNumber = $index + 1;
            $logoPath    = $this->fetchLogo($data['slug']);
            $coordinates = $this->buildCoordinates($data);

            $tenant = Tenant::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name'              => $data['name'],
                    'type_of_tenant_id' => $this->tenantTypeIds[$data['type']] ?? null,
                    'address'           => $data['address'],
                    'barangay'          => $data['barangay'],
                    'contact_number'    => $data['contact_number'],
                    'email'             => $data['email'],
                    'coordinates'       => $coordinates,
                    'logo'              => $logoPath,
                    'is_active'         => true,
                    'verified_at'       => now(),
                    'permit_expires_at' => now()->addMonths(9),
                ],
            );

            $owner->update(['tenant_id' => $tenant->id, 'active_mode' => User::MODE_BUSINESS]);
            $owner->syncRoles(['tourist', 'admin']);

            $this->seedTenantDemoData($tenant, $data, $ownerNumber);
            $this->seedTenantBusinessInfo($tenant, $data);
            $this->seedTenantGallery($tenant, $data);
            $this->seedTenantKybRecord($tenant, $owner);
        }

        $this->seedEvents();
    }

    protected function seedTenantBusinessInfo(Tenant $tenant, array $data): void
    {
        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'business_info'],
            ['value' => [
                'description'   => $data['description'] ?? null,
                'opening_hours' => ['opening' => '08:00', 'closing' => '17:00', 'is_24hr' => false],
                'barangay'      => $tenant->barangay,
                'city'          => 'Victorias City',
                'province'      => 'Negros Occidental',
            ]],
        );
    }

    protected function seedTenantGallery(Tenant $tenant, array $data): void
    {
        $coverPath = $this->fetchSpotCover($data['slug']);
        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'spot_cover'],
            ['value' => $coverPath],
        );

        $gallery = [];
        for ($i = 1; $i <= 6; $i++) {
            $gallery[] = $this->fetchGalleryImage($data['slug'], $i);
        }

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'business_gallery'],
            ['value' => $gallery],
        );

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'gallery_title'],
            ['value' => "Discover {$data['name']}"],
        );

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'gallery_subtitle'],
            ['value' => $data['description'] ?? 'A glimpse of what awaits you.'],
        );
    }

    protected function seedTenantKybRecord(Tenant $tenant, User $owner): void
    {
        $superAdminId = User::query()->where('email', 'superadmin@gmail.com')->value('id');

        $application = BusinessApplication::firstOrCreate(
            ['approved_tenant_id' => $tenant->id],
            [
                'user_id'                      => $owner->id,
                'business_name'                => $tenant->name,
                'business_type'                => 'dti',
                'type_of_tenant_id'            => $tenant->type_of_tenant_id,
                'business_registration_number' => sprintf('DTI-2024-%06d', $tenant->id),
                'tin_number'                   => sprintf('100-%03d-%03d-%03d', 0, intdiv($tenant->id, 1000) % 1000, $tenant->id % 1000),
                'owner_full_name'              => $owner->name,
                'owner_id_type'                => 'drivers_license',
                'owner_id_number'              => 'N01-23-456789',
                'owner_birthdate'              => now()->subYears(35),
                'contact_email'                => $tenant->email,
                'contact_phone'                => $tenant->contact_number,
                'address'                      => $tenant->address,
                'barangay'                     => $tenant->barangay,
                'city'                         => 'Victorias City',
                'province'                     => 'Negros Occidental',
                'coordinates'                  => $tenant->coordinates,
                'logo_path'                    => $tenant->logo,
                'status'                       => BusinessApplication::STATUS_APPROVED,
                'source'                       => BusinessApplication::SOURCE_SUPERADMIN_DIRECT,
                'submitted_at'                 => now()->subMonths(1),
                'reviewed_at'                  => now()->subMonths(1),
                'reviewed_by'                  => $superAdminId,
            ],
        );

        if (! $application->documents()->exists()) {
            $this->attachDemoDocuments($application, $owner, null, BusinessDocument::STATUS_VERIFIED);
        }
    }

    protected function seedTenantDemoData(Tenant $tenant, array $data, int $number): void
    {
        $tenant->bookings()->delete();
        $tenant->properties()->delete();
        $tenant->services()->delete();
        Employee::query()->where('tenant_id', $tenant->id)->delete();

        $typeIds = PropertyType::query()
            ->whereIn('name', [
                'Standard Room', 'Deluxe Room', 'Family Suite', 'Cottage',
                'Day Pass', 'Pool Pass', 'Beach Pass',
                'Guided Tour', 'Activity Package', 'Farm Tour', 'Wine Tasting',
                'Birdwatching Tour', 'Heritage Tour', 'Eco-Trek', 'Waterfall Trek',
                'Event Space', 'Sports Court Rental', 'Equipment Rental',
                'Souvenir Package',
            ], 'and', false)
            ->whereNull('tenant_id')
            ->pluck('id', 'name');

        foreach ($data['properties'] as $p) {
            if (! isset($typeIds[$p['type']])) continue;

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

            PropertyImage::create([
                'tenant_id'   => $tenant->id,
                'property_id' => $property->id,
                'image_path'  => $this->fetchPropertyImage($tenant->slug, $p['name']),
            ]);
        }

        $now = now();

        Service::insert(array_map(
            fn ($s) => [
                'tenant_id'  => $tenant->id,
                'name'       => $s['name'],
                'price'      => $s['price'],
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $data['services'],
        ));

        $this->seedEmployee($tenant, "manager{$number}",   "Manager {$number}",    'Manager',      "0917-111-000{$number}", ['property manager']);
        $this->seedEmployee($tenant, "frontdesk{$number}", "Front Desk {$number}", 'Front Desk',   "0917-222-000{$number}", ['front desk']);
        $this->seedEmployee($tenant, "guide{$number}",     "Tour Guide {$number}", 'Tour Guide',   "0917-333-000{$number}", ['guide']);
        $this->seedEmployee($tenant, "analyst{$number}",   "Analyst {$number}",    'Analyst',      "0917-444-000{$number}", ['analyst']);
        $this->seedEmployee($tenant, "noaccess{$number}",  "No Access {$number}",  'Housekeeping', "0917-555-000{$number}", []);
    }

    protected function seedEmployee(
        Tenant $tenant, string $handle, string $name,
        string $role, string $phone, array $roles,
    ): void {
        $email  = "{$handle}@gmail.com";
        $avatar = $this->fetchAvatar($email);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'      => $name,
                'password'  => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'is_active' => true,
                'avatar'    => $avatar,
            ],
        );

        if ($user->tenant_id !== $tenant->id) {
            $user->update(['tenant_id' => $tenant->id]);
        }

        if (! ($user->avatar && ! str_starts_with($user->avatar, 'http') && Storage::disk('public')->exists($user->avatar))) {
            $user->update(['avatar' => $avatar]);
        }

        if (! empty($roles)) {
            $user->syncRoles($roles);
        }

        Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            [
                'code'      => 'EMP-' . strtoupper(Str::random(6)),
                'name'      => $name,
                'role'      => $role,
                'phone'     => $phone,
                'avatar'    => $avatar,
                'is_active' => true,
            ],
        );
    }

    // ═════════════════════════════════════════════════════════
    //  Bookings — FIXED: backdated created_at via forceFill
    // ═════════════════════════════════════════════════════════

    protected function seedBookingsForAllTenants(array $bookers): void
    {
        Tenant::query()->each(function (Tenant $tenant) use ($bookers) {
            $this->seedTenantBookings($tenant, $bookers);
        });
    }

    protected function seedTenantBookings(Tenant $tenant, array $bookers): void
    {
        if (Booking::query()->where('tenant_id', $tenant->id)->exists()) return;

        $propertyIds    = Property::query()->where('tenant_id', $tenant->id)->pluck('id')->toArray();
        $propertyPrices = Property::query()->where('tenant_id', $tenant->id)->pluck('price', 'id')->toArray();
        $serviceIds     = Service::query()->where('tenant_id', $tenant->id)->pluck('id')->toArray();
        $servicePrices  = Service::query()->where('tenant_id', $tenant->id)->pluck('price', 'id')->toArray();

        if (empty($propertyIds) || empty($serviceIds)) return;

        $candidates = array_values(array_filter($bookers, fn (User $u) => $u->tenant_id !== $tenant->id));
        if (empty($candidates)) return;

        shuffle($candidates);
        $sample = array_slice($candidates, 0, 8);

        $statusPlan = [
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_PENDING,
            Booking::STATUS_RESERVED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_CHECKED_IN,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_PENDING,
        ];

        Booking::withoutEvents(function () use ($sample, $tenant, $statusPlan, $propertyIds, $propertyPrices, $serviceIds, $servicePrices): void {
            foreach ($sample as $index => $booker) {
                $status = $statusPlan[$index % count($statusPlan)];
                $isPast = in_array($status, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED], true);

                $checkIn  = $isPast
                    ? Carbon::now()->subDays(random_int(5, 30))
                    : Carbon::now()->addDays(random_int(3, 21));
                $checkOut = $checkIn->copy()->addDays(random_int(1, 4));

                $roomId    = $propertyIds[array_rand($propertyIds)];
                $roomPrice = $propertyPrices[$roomId];
                $nights    = max(1, (int) $checkIn->diffInDays($checkOut));
                $total     = $roomPrice * $nights;

                $bookingType = $status === Booking::STATUS_RESERVED
                    ? Booking::TYPE_RESERVATION
                    : Booking::TYPE_FULL;

                // ── FIX: forceFill bypasses $fillable so created_at sticks ──
                $backdated = $checkIn->copy()->subDays(random_int(1, 5));

                $booking = new Booking();
                $booking->forceFill([
                    'tenant_id'         => $tenant->id,
                    'user_id'           => $booker->id,
                    'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                    'check_in'          => $checkIn,
                    'check_out'         => $checkOut,
                    'total_amount'      => $total,
                    'status'            => $status,
                    'booking_type'      => $bookingType,
                    'created_at'        => $backdated,
                    'updated_at'        => $backdated,
                ]);
                $booking->save();

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

                $paymentStatus = match ($status) {
                    Booking::STATUS_CANCELLED,
                    Booking::STATUS_PENDING   => 'pending',
                    default                   => 'paid',
                };

                $paymentAmount = $bookingType === Booking::TYPE_RESERVATION
                    ? round($total * 0.20, 2)
                    : $total;

                // ── FIX: forceFill for backdated payment timestamps ──
                $payment = new Payment();
                $payment->forceFill([
                    'tenant_id'        => $tenant->id,
                    'booking_id'       => $booking->id,
                    'amount'           => $paymentAmount,
                    'payment_method'   => collect(['cash', 'gcash', 'card'])->random(),
                    'payment_type'     => $bookingType,
                    'payment_status'   => $paymentStatus,
                    'paid_at'          => $paymentStatus === 'paid'
                        ? $backdated->copy()->addHours(random_int(1, 10))
                        : null,
                    'reference_number' => $paymentStatus === 'paid'
                        ? 'TXN-' . Str::upper(Str::random(10))
                        : null,
                    'created_at'       => $backdated,
                    'updated_at'       => now(),
                ]);
                $payment->save();
            }
        });
    }

    // ═════════════════════════════════════════════════════════
    //  Events — expanded with past + inactive variations
    // ═════════════════════════════════════════════════════════

    protected function seedEvents(): void
    {
        $tenantIdBySlug = Tenant::query()->pluck('id', 'slug');

        $futureEvents = [
            ['name' => 'Kadalag-an Festival',                'barangay' => 'Barangay V',     'type' => 'fiesta',        'featured' => true,  'tenant_slug' => 'victorias-public-plaza',          'days' => 30,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'Annual celebration of Victorias City\'s charter anniversary every March 21, featuring street dancing, pageantry, and the grand slam-winning Sidlak Kadalag-an Festival dance competition.'],
            ['name' => 'Malihaw Festival',                   'barangay' => 'Barangay IX',    'type' => 'fiesta',        'featured' => true,  'tenant_slug' => 'daan-banwa-heritage-site',        'days' => 45,  'coord' => ['lat' => 10.925, 'lng' => 123.05],  'desc' => 'Celebrated every April 26 in honor of the city\'s patroness, Nuestra Señora de las Victorias, featuring a traditional fluvial procession along the Malihaw River.'],
            ['name' => 'Kalamayan Festival',                 'barangay' => 'Barangay V',     'type' => 'fiesta',        'featured' => true,  'tenant_slug' => 'victorias-public-plaza',          'days' => 90,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'The city\'s grand year-end fiesta featuring the Sabor Victorias cookfest, cultural shows, and community celebrations at the Public Plaza every December.'],
            ['name' => 'Feast of St. Joseph the Worker',     'barangay' => 'Barangay XVI',   'type' => 'fiesta',        'featured' => false, 'tenant_slug' => 'st-joseph-worker-parish-church',  'days' => 60,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'Annual feast day honoring the city patron, with a dawn procession, high mass, and street celebration around the VMC compound.'],
            ['name' => 'Gawahon Eco-Trail Fun Run',          'barangay' => 'Barangay XI',    'type' => 'sports',        'featured' => false, 'tenant_slug' => 'gawahon-eco-park',                'days' => 21,  'coord' => ['lat' => 10.79,  'lng' => 123.18],  'desc' => 'A 5K fun run through the scenic trails of Gawahon Eco Park. Open to all ages!'],
            ['name' => 'Gawahon Waterfall Trek Challenge',   'barangay' => 'Barangay XI',    'type' => 'adventure',     'featured' => false, 'tenant_slug' => 'gawahon-eco-park',                'days' => 45,  'coord' => ['lat' => 10.79,  'lng' => 123.18],  'desc' => 'Tag all seven waterfalls in a single day. Finish within 6 hours to earn the Gawahon finisher pin.'],
            ['name' => 'Mangrove Planting Day',              'barangay' => 'Barangay VI-A',  'type' => 'environment',   'featured' => false, 'tenant_slug' => 'baybay-mangrove-eco-trail',       'days' => 10,  'coord' => ['lat' => 10.92,  'lng' => 123.06],  'desc' => 'Join the community in planting mangroves along the coast to preserve the marine ecosystem.'],
            ['name' => 'Mangrove Night Walk',                'barangay' => 'Barangay VI-A',  'type' => 'adventure',     'featured' => false, 'tenant_slug' => 'baybay-mangrove-eco-trail',       'days' => 15,  'coord' => ['lat' => 10.92,  'lng' => 123.06],  'desc' => 'Guided night walk through the mangrove forest to observe fireflies and nocturnal wildlife.'],
            ['name' => 'Angry Christ Church Heritage Day',   'barangay' => 'Barangay XVI',   'type' => 'entertainment', 'featured' => true,  'tenant_slug' => 'st-joseph-worker-parish-church',  'days' => 35,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'Open-house heritage day at the church with curator-led tours of the Alfonso Ossorio mural, choral performances, and a photography exhibit.'],
            ['name' => 'Summer Sports Fest',                 'barangay' => 'Barangay XIII',  'type' => 'sports',        'featured' => false, 'tenant_slug' => 'victorias-city-resort',           'days' => 50,  'coord' => ['lat' => 10.89,  'lng' => 123.05],  'desc' => 'Inter-barangay basketball and volleyball tournament with live music and food stalls.'],
            ['name' => 'Victorias City Resort Summer Nights','barangay' => 'Barangay XIII',  'type' => 'entertainment', 'featured' => true,  'tenant_slug' => 'victorias-city-resort',           'days' => 75,  'coord' => ['lat' => 10.89,  'lng' => 123.05],  'desc' => 'Exclusive resort party with live DJ, poolside cocktails, and fireworks.'],
            ['name' => 'Plaza Christmas Lights Festival',    'barangay' => 'Barangay V',     'type' => 'entertainment', 'featured' => true,  'tenant_slug' => 'victorias-public-plaza',          'days' => 120, 'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'The city plaza transforms into a lights-and-sound spectacle for the holiday season, with nightly shows and a holiday market.'],
            ['name' => 'Plaza Weekend Market',               'barangay' => 'Barangay V',     'type' => 'other',         'featured' => false, 'tenant_slug' => 'victorias-public-plaza',          'days' => 7,   'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'Weekend pop-up market featuring local produce, street food, and handmade crafts from Negros Occidental vendors.'],
            ['name' => 'Daan Banwa Fluvial Parade',          'barangay' => 'Barangay IX',    'type' => 'fiesta',        'featured' => false, 'tenant_slug' => 'daan-banwa-heritage-site',        'days' => 46,  'coord' => ['lat' => 10.925, 'lng' => 123.05],  'desc' => 'Traditional fluvial parade along the Malihaw River in honor of Nuestra Señora de las Victorias.'],
            ['name' => 'Peñalosa Organic Farm Day',          'barangay' => 'Barangay V',     'type' => 'other',         'featured' => false, 'tenant_slug' => 'penalosa-farm',                   'days' => 14,  'coord' => ['lat' => 10.895, 'lng' => 123.075], 'desc' => 'Open farm day with lectures on organic farming, farm tours, and fresh produce sales.'],
            ['name' => 'Federico\'s Wine Tasting Weekend',   'barangay' => 'Barangay IX',    'type' => 'entertainment', 'featured' => false, 'tenant_slug' => 'federicos-island-wine',           'days' => 28,  'coord' => ['lat' => 10.915, 'lng' => 123.055], 'desc' => 'Weekend wine tasting event featuring Federico\'s award-winning bignay wine, vineyard tours, and wine pairing sessions.'],
            ['name' => 'Iron Dinosaur Heritage Exhibit',     'barangay' => 'Barangay XVI',   'type' => 'entertainment', 'featured' => false, 'tenant_slug' => 'iron-dinosaur-steam-locomotive',  'days' => 40,  'coord' => ['lat' => 10.881, 'lng' => 123.071], 'desc' => 'Special exhibit on the VMC steam locomotive heritage, featuring guided tours of the historic trains and industrial artifacts.'],
            ['name' => 'Victorias Food Festival',            'barangay' => 'Barangay XVI',   'type' => 'other',         'featured' => false, 'tenant_slug' => 'victorias-foods-corporation',     'days' => 55,  'coord' => ['lat' => 10.882, 'lng' => 123.073], 'desc' => 'Food festival celebrating Victorias Foods products with cooking demos, product sampling, and factory tours.'],
            ['name' => 'Coliseum Concert Series',            'barangay' => 'Barangay V',     'type' => 'entertainment', 'featured' => true,  'tenant_slug' => 'victorias-city-coliseum',         'days' => 65,  'coord' => ['lat' => 10.903, 'lng' => 123.073], 'desc' => 'Monthly concert series at the Victorias City Coliseum featuring national and local artists.'],
            ['name' => 'Sabor Victorias Cookfest',           'barangay' => 'Barangay V',     'type' => 'other',         'featured' => false, 'tenant_slug' => 'victorias-public-plaza',          'days' => 92,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'Annual cookfest organized by Victorias Milling Company Foundation as part of the Kalamayan Festival, showcasing local culinary talent.'],
        ];

        foreach ($futureEvents as $data) {
            $tenantId  = $data['tenant_slug'] ? ($tenantIdBySlug[$data['tenant_slug']] ?? null) : null;
            $imagePath = $this->fetchEventImage($data['name']);

            Event::updateOrCreate(
                ['name' => $data['name']],
                [
                    'tenant_id'   => $tenantId,
                    'barangay'    => $data['barangay'],
                    'description' => $data['desc'],
                    'type'        => $data['type'],
                    'start_date'  => Carbon::now()->addDays($data['days']),
                    'end_date'    => Carbon::now()->addDays($data['days'] + 1),
                    'coordinates' => $data['coord'],
                    'is_active'   => true,
                    'featured'    => $data['featured'],
                    'image_path'  => $imagePath,
                ],
            );
        }

        // ── PAST EVENTS — for the "past" filter on the events page ──
        $pastEvents = [
            ['name' => 'Kadalag-an Festival 2025',          'barangay' => 'Barangay V',   'type' => 'fiesta',        'featured' => false, 'tenant_slug' => 'victorias-public-plaza',   'days' => -90,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'The 2025 edition of the Kadalag-an Festival — a milestone year celebrating the city\'s heritage.'],
            ['name' => 'Malihaw Festival 2025',             'barangay' => 'Barangay IX',  'type' => 'fiesta',        'featured' => false, 'tenant_slug' => 'daan-banwa-heritage-site', 'days' => -75,  'coord' => ['lat' => 10.925, 'lng' => 123.05],  'desc' => 'Last year\'s Malihaw Festival, honoring Nuestra Señora de las Victorias with the traditional fluvial parade.'],
            ['name' => 'Kalamayan Festival 2024',           'barangay' => 'Barangay V',   'type' => 'fiesta',        'featured' => false, 'tenant_slug' => 'victorias-public-plaza',   'days' => -200, 'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'The 2024 Kalamayan Festival celebration with the Sabor Victorias cookfest and year-end festivities.'],
            ['name' => 'Gawahon Anniversary Trek 2025',     'barangay' => 'Barangay XI',  'type' => 'adventure',     'featured' => false, 'tenant_slug' => 'gawahon-eco-park',         'days' => -45,  'coord' => ['lat' => 10.79,  'lng' => 123.18],  'desc' => 'Commemorative trek celebrating the founding anniversary of Gawahon Eco Park.'],
            ['name' => 'Heritage Week Exhibit 2025',        'barangay' => 'Barangay V',   'type' => 'entertainment', 'featured' => false, 'tenant_slug' => 'yap-quina-arts-cultural-center', 'days' => -30, 'coord' => ['lat' => 10.901, 'lng' => 123.071], 'desc' => 'A week-long exhibit of Victorias City\'s cultural heritage at the Arts and Cultural Center.'],
        ];

        foreach ($pastEvents as $data) {
            $tenantId  = $data['tenant_slug'] ? ($tenantIdBySlug[$data['tenant_slug']] ?? null) : null;
            $imagePath = $this->fetchEventImage($data['name']);

            Event::updateOrCreate(
                ['name' => $data['name']],
                [
                    'tenant_id'   => $tenantId,
                    'barangay'    => $data['barangay'],
                    'description' => $data['desc'],
                    'type'        => $data['type'],
                    'start_date'  => Carbon::now()->addDays($data['days']),
                    'end_date'    => Carbon::now()->addDays($data['days'] + 2),
                    'coordinates' => $data['coord'],
                    'is_active'   => true,
                    'featured'    => $data['featured'],
                    'image_path'  => $imagePath,
                ],
            );
        }

        // ── INACTIVE EVENT — for the "inactive" test case ──
        Event::updateOrCreate(
            ['name' => 'Cancelled: Food Truck Rally 2025'],
            [
                'tenant_id'   => $tenantIdBySlug['victorias-public-plaza'] ?? null,
                'barangay'    => 'Barangay V',
                'description' => 'Cancelled food truck rally. Historical record retained for audit.',
                'type'        => 'other',
                'start_date'  => Carbon::now()->addDays(10),
                'end_date'    => Carbon::now()->addDays(11),
                'coordinates' => ['lat' => 10.90, 'lng' => 123.07],
                'is_active'   => false,
                'featured'    => false,
                'image_path'  => $this->fetchEventImage('Cancelled Food Truck Rally'),
            ],
        );
    }

    // ═════════════════════════════════════════════════════════
    //  KYB applications
    // ═════════════════════════════════════════════════════════

    protected function seedBusinessApplications(array $applicants): void
    {
        $scenarios = [
            ['user' => $applicants[0] ?? null, 'status' => BusinessApplication::STATUS_DRAFT,          'business_name' => 'Lakeside Kiosk', 'business_type' => 'dti', 'tenant_type' => 'Restaurant', 'with_documents' => false],
            ['user' => $applicants[1] ?? null, 'status' => BusinessApplication::STATUS_NEEDS_REVISION, 'business_name' => 'Sunrise Café',   'business_type' => 'dti', 'tenant_type' => 'Restaurant', 'with_documents' => true, 'revision_notes' => 'Please re-upload BIR Form 2303 — the attached scan is unreadable. Ensure the TIN is clearly visible.', 'submitted_days_ago' => 5],
            ['user' => $applicants[2] ?? null, 'status' => BusinessApplication::STATUS_PENDING,        'business_name' => 'Hilltop Inn',    'business_type' => 'dti', 'tenant_type' => 'Inn',        'with_documents' => true, 'tin_number' => '123-456-789-000', 'bir_number' => '123-456-789-000', 'run_verification' => true, 'submitted_days_ago' => 2],
            ['user' => $applicants[3] ?? null, 'status' => BusinessApplication::STATUS_REJECTED,       'business_name' => 'Downtown Bar',   'business_type' => 'dti', 'tenant_type' => 'Restaurant', 'with_documents' => true, 'rejection_reason' => 'TIN on BIR Form 2303 does not match the submitted TIN. Please correct and reapply.', 'submitted_days_ago' => 10],
        ];

        foreach ($scenarios as $s) {
            $user = $s['user'];
            if (! $user) continue;

            $application = BusinessApplication::firstOrCreate(
                ['user_id' => $user->id, 'business_name' => $s['business_name']],
                $this->buildApplicationPayload($s, $user),
            );

            if (($s['with_documents'] ?? false) && ! $application->documents()->exists()) {
                $status = match ($s['status']) {
                    BusinessApplication::STATUS_REJECTED => BusinessDocument::STATUS_REJECTED,
                    BusinessApplication::STATUS_NEEDS_REVISION => BusinessDocument::STATUS_PENDING,
                    default => BusinessDocument::STATUS_PENDING,
                };
                $this->attachDemoDocuments($application, $user, $s['bir_number'] ?? null, $status);
            }

            if (($s['run_verification'] ?? false) && ! $application->verifications()->exists()) {
                app(KybVerificationService::class)->verify($application->fresh(['documents']));
            }
        }
    }

    protected function buildApplicationPayload(array $s, User $user): array
    {
        $status      = $s['status'];
        $isSubmitted = in_array($status, [BusinessApplication::STATUS_PENDING, BusinessApplication::STATUS_UNDER_REVIEW, BusinessApplication::STATUS_NEEDS_REVISION, BusinessApplication::STATUS_REJECTED], true);
        $reviewed    = in_array($status, [BusinessApplication::STATUS_NEEDS_REVISION, BusinessApplication::STATUS_REJECTED], true);

        $tinNumber = $s['tin_number'] ?? sprintf('100-%03d-%03d-%03d', intdiv($user->id, 1_000_000) % 1000, intdiv($user->id, 1_000) % 1000, $user->id % 1000);

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
            'type_of_tenant_id'            => $isSubmitted ? ($this->tenantTypeIds[$s['tenant_type']] ?? null) : null,
            'status'                       => $status,
            'revision_notes'               => $s['revision_notes'] ?? null,
            'rejection_reason'             => $s['rejection_reason'] ?? null,
            'submitted_at'                 => $isSubmitted ? now()->subDays($s['submitted_days_ago'] ?? 1) : null,
            'reviewed_at'                  => $reviewed ? now()->subDays(max(1, ($s['submitted_days_ago'] ?? 1) - 1)) : null,
            'reviewed_by'                  => $reviewed ? User::query()->where('email', 'superadmin@gmail.com')->value('id') : null,
        ];
    }

    protected function attachDemoDocuments(
        BusinessApplication $application,
        User $user,
        ?string $birNumber = null,
        string $verificationStatus = BusinessDocument::STATUS_PENDING,
    ): void {
        $placeholderJpg = $this->fallbackJpg();

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

            $expiresAt = $type === BusinessDocument::TYPE_MAYORS_PERMIT
                ? now()->addDays(random_int(20, 40))
                : null;

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
                'expires_at'              => $expiresAt,
                'verification_status'     => $verificationStatus,
                'verification_notes'      => $verificationStatus === BusinessDocument::STATUS_REJECTED
                    ? 'Document is illegible — please re-upload a clearer scan.'
                    : null,
                'watermarked_at'          => now(),
            ]);
        }
    }

    // ═════════════════════════════════════════════════════════
    //  Account deletion requests
    // ═════════════════════════════════════════════════════════

    protected function seedAccountDeletionRequests(array $owners, array $tourists): void
    {
        $superAdminId = User::query()->where('email', 'superadmin@gmail.com')->value('id');

        if (isset($owners[5])) {
            AccountDeletionRequest::updateOrCreate(
                ['user_id' => $owners[5]->id, 'scope' => AccountDeletionRequest::SCOPE_BOTH],
                [
                    'tenant_id'    => $owners[5]->tenant_id,
                    'status'       => AccountDeletionRequest::STATUS_PENDING,
                    'reason'       => 'Closing down the business — moving abroad.',
                    'review_notes' => null,
                    'reviewed_by'  => null,
                    'reviewed_at'  => null,
                ],
            );
        }

        if (isset($owners[7])) {
            AccountDeletionRequest::updateOrCreate(
                ['user_id' => $owners[7]->id, 'scope' => AccountDeletionRequest::SCOPE_BUSINESS_ONLY],
                [
                    'tenant_id'    => $owners[7]->tenant_id,
                    'status'       => AccountDeletionRequest::STATUS_PENDING,
                    'reason'       => 'Selling the property — no longer managing operations.',
                    'review_notes' => null,
                    'reviewed_by'  => null,
                    'reviewed_at'  => null,
                ],
            );
        }

        if (isset($tourists[0])) {
            AccountDeletionRequest::updateOrCreate(
                ['user_id' => $tourists[0]->id, 'scope' => AccountDeletionRequest::SCOPE_BOTH],
                [
                    'tenant_id'    => null,
                    'status'       => AccountDeletionRequest::STATUS_REJECTED,
                    'reason'       => 'Account no longer needed.',
                    'review_notes' => 'You have 2 upcoming bookings. Please complete or cancel them before requesting deletion.',
                    'reviewed_by'  => $superAdminId,
                    'reviewed_at'  => now()->subDays(3),
                ],
            );
        }

        if (isset($tourists[1])) {
            AccountDeletionRequest::updateOrCreate(
                ['user_id' => $tourists[1]->id, 'scope' => AccountDeletionRequest::SCOPE_BOTH],
                [
                    'tenant_id'    => null,
                    'status'       => AccountDeletionRequest::STATUS_CANCELLED,
                    'reason'       => 'Changed my mind.',
                    'review_notes' => null,
                    'reviewed_by'  => null,
                    'reviewed_at'  => null,
                ],
            );
        }
    }

    // ═════════════════════════════════════════════════════════
    //  Permit renewal reminders
    // ═════════════════════════════════════════════════════════

    protected function seedPermitRenewalReminders(): void
    {
        $tenants = Tenant::query()->get(['id', 'permit_expires_at']);

        foreach ($tenants as $index => $tenant) {
            if ($index >= 4) continue;

            $statuses = [
                PermitRenewalReminder::STATUS_PENDING,
                PermitRenewalReminder::STATUS_SENT,
                PermitRenewalReminder::STATUS_ACKNOWLEDGED,
                PermitRenewalReminder::STATUS_EXPIRED,
            ];

            $status = $statuses[$index % count($statuses)];

            PermitRenewalReminder::updateOrCreate(
                ['tenant_id' => $tenant->id, 'year' => now()->year],
                [
                    'permit_expires_at' => $tenant->permit_expires_at,
                    'status'            => $status,
                    'sent_at'           => in_array($status, [
                        PermitRenewalReminder::STATUS_SENT,
                        PermitRenewalReminder::STATUS_ACKNOWLEDGED,
                        PermitRenewalReminder::STATUS_EXPIRED,
                    ], true) ? now()->subDays(15) : null,
                    'acknowledged_at'   => $status === PermitRenewalReminder::STATUS_ACKNOWLEDGED
                        ? now()->subDays(10)
                        : null,
                    'notes'             => match ($status) {
                        PermitRenewalReminder::STATUS_ACKNOWLEDGED => 'Business owner confirmed receipt and will renew.',
                        PermitRenewalReminder::STATUS_EXPIRED      => 'Permit expired — escalation required.',
                        default                                    => null,
                    },
                ],
            );
        }
    }
}