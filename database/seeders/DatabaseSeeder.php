<?php

namespace Database\Seeders;

use App\Models\AccountDeletionRequest;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\BusinessMembership;
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
use App\Models\UserNotification;
use App\Services\ImageCompressionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    protected array $tenantTypeIds = [];

    protected array $compressionStats = [
        'compressed' => 0,
        'skipped'    => 0,
        'failed'     => 0,
    ];

    public function run(): void
    {
        $this->call([RoleSeeder::class]);

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

        // Second business for owner1 — demonstrates the multi-business
        // feature end to end. Both businesses are in Victorias City.
        $this->seedOwner1SecondBusiness();

        $bookers = array_merge($owners, $tourists, $applicants);
        $this->seedBookingsForAllTenants($bookers);

        $this->seedBusinessApplications($applicants);
        $this->seedAccountDeletionRequests($owners, $tourists);
        $this->seedPermitRenewalReminders();

        $this->seedDocumentRenewals();
        $this->seedUserNotifications();

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
        $this->command->line('    Multi-business: owner1@gmail.com (owns 2 businesses)');
        $this->command->line('    Tourist       : tourist1@gmail.com .. tourist120@gmail.com');
        $this->command->line('    Applicant     : applicant1@gmail.com .. applicant60@gmail.com');
        $this->command->newLine();
    }

    // ═════════════════════════════════════════════════════════
    //  Identifier generators — deterministic, collision-safe
    // ═════════════════════════════════════════════════════════

    protected function generateTin(string $seed): string
    {
        $digits = str_pad(
            (string) (abs(crc32($seed)) % 1000000000000),
            12,
            '0',
            STR_PAD_LEFT,
        );

        return substr($digits, 0, 3)
            . '-' . substr($digits, 3, 3)
            . '-' . substr($digits, 6, 3)
            . '-' . substr($digits, 9, 3);
    }

    protected function generateRegistrationNumber(string $seed, string $prefix = 'DTI'): string
    {
        $hash = strtoupper(substr(md5($seed), 0, 6));

        return sprintf('%s-%d-%s', $prefix, now()->year, $hash);
    }

    protected function mobileNumber(string $seed): string
    {
        $digits = str_pad(
            (string) (abs(crc32($seed)) % 1000000000),
            9,
            '0',
            STR_PAD_LEFT,
        );

        return '09' . substr($digits, -9);
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

    protected function downloadAndCompressOnce(
        string $url,
        string $relativePath,
        ?string $context = null,
        bool $forceCompress = true,
    ): bool {
        $disk = Storage::disk('public');

        if ($disk->exists($relativePath)) {
            if ($forceCompress && $context !== null) {
                $this->compressStoredFile($disk->path($relativePath), $relativePath, $context, true);
            }
            return true;
        }

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

        if ($context !== null) {
            $this->compressStoredFile($disk->path($relativePath), $relativePath, $context, $forceCompress);
        }

        return true;
    }

    protected function compressStoredFile(
        string $absolutePath,
        string $relativePath,
        string $context,
        bool $force = false,
    ): void {
        try {
            $before = @filesize($absolutePath) ?: 0;
            $did = app(ImageCompressionService::class)->compressInPlace($absolutePath, $context, force: $force);
            clearstatcache(true, $absolutePath);
            $after = @filesize($absolutePath) ?: 0;

            if ($did) {
                $this->compressionStats['compressed']++;
                $saved = $before - $after;
                $pct   = $before > 0 ? round(($saved / $before) * 100, 1) : 0;
                $this->command?->getOutput()->writeln(sprintf(
                    '  <fg=green;options=bold>✓</> <fg=gray>%s</> %s → %s <fg=gray>(−%s%%)</>',
                    $context, $this->humanBytes($before), $this->humanBytes($after), $pct,
                ));
            } else {
                $this->compressionStats['skipped']++;
                $this->command?->getOutput()->writeln(sprintf(
                    '  <fg=gray>·</> <fg=gray>%s</> %s <fg=gray>(no change needed)</>',
                    $context, $this->humanBytes($before),
                ));
            }
        } catch (\Throwable $e) {
            $this->compressionStats['failed']++;
            Log::warning("Seeder: compression failed for {$relativePath}: {$e->getMessage()}");
            $this->command?->getOutput()->writeln(sprintf(
                '  <fg=red>✗</> <fg=gray>%s</> %s <fg=gray>(%s)</>',
                $context, $relativePath, $e->getMessage(),
            ));
        }
    }

    protected function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
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
        $this->downloadAndCompressOnce($this->picsum("logo-{$slug}", 600, 600), $relativePath, 'tenant-logo');
        return $relativePath;
    }

    protected function fetchSiteLogo(): string
    {
        $relativePath = 'placeholders/site/logo.jpg';
        $this->downloadAndCompressOnce($this->picsum('site-logo-victorias', 600, 600), $relativePath, 'site');
        return $relativePath;
    }

    protected function fetchSiteHero(): string
    {
        $relativePath = 'placeholders/site/hero.jpg';
        $this->downloadAndCompressOnce($this->picsum('site-hero-victorias', 1920, 1080), $relativePath, 'site');
        return $relativePath;
    }

    protected function fetchSiteSideImage(int $index): string
    {
        $relativePath = "placeholders/site/side-{$index}.jpg";
        $this->downloadAndCompressOnce($this->picsum("site-side-{$index}", 800, 800), $relativePath, 'site');
        return $relativePath;
    }

    protected function fetchSpotCover(string $tenantSlug): string
    {
        $relativePath = "placeholders/covers/{$tenantSlug}.jpg";
        $this->downloadAndCompressOnce($this->picsum("cover-{$tenantSlug}", 1920, 900), $relativePath, 'tenant-cover');
        return $relativePath;
    }

    protected function fetchGalleryImage(string $tenantSlug, int $index): string
    {
        $relativePath = "placeholders/gallery/{$tenantSlug}-{$index}.jpg";
        $this->downloadAndCompressOnce($this->picsum("gallery-{$tenantSlug}-{$index}", 1200, 900), $relativePath, 'property');
        return $relativePath;
    }

    protected function fetchPropertyImage(string $tenantSlug, string $propertyName): string
    {
        $propSlug     = Str::slug($propertyName);
        $relativePath = "placeholders/properties/{$tenantSlug}-{$propSlug}.jpg";
        $this->downloadAndCompressOnce($this->picsum("{$tenantSlug}-{$propertyName}", 1600, 1200), $relativePath, 'property');
        return $relativePath;
    }

    protected function fetchEventImage(string $eventName): string
    {
        $slug         = Str::slug($eventName);
        $relativePath = "placeholders/events/{$slug}.jpg";
        $this->downloadAndCompressOnce($this->picsum("event-{$eventName}", 1600, 900), $relativePath, 'event');
        return $relativePath;
    }

    protected function fetchAvatar(string $email): string
    {
        $relativePath = 'placeholders/avatars/' . md5(strtolower($email)) . '.jpg';
        $this->downloadAndCompressOnce($this->randomUserUrl($email), $relativePath, 'avatars');
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

    // ═════════════════════════════════════════════════════════
    //  Marker categories — 70 entries
    // ═════════════════════════════════════════════════════════

    protected function seedMarkerCategories(): void
    {
        $s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
        $e = '</svg>';

        $rows = [
            ['restaurant',    'Restaurant',           '#f97316', '<path d="M3 2v7c0 2.2 1.8 4 4 4a4 4 0 0 0 4-4V2M7 2v20M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>'],
            ['cafe',          'Café',                 '#a855f7', '<path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4ZM6 2v2M10 2v2M14 2v2"/>'],
            ['coffee',        'Coffee Shop',          '#92400e', '<path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>'],
            ['tea',           'Tea House',            '#84cc16', '<path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4ZM6 2v2M10 2v2"/>'],
            ['bar',           'Bar & Restobar',       '#ec4899', '<path d="M5 3h14l-7 8-7-8zM12 11v9M8 20h8"/>'],
            ['brewery',       'Brewery',              '#b45309', '<path d="M8 22h8M12 15v7M6 3h12l-1 6a5 5 0 0 1-10 0z"/>'],
            ['winery',        'Winery & Distillery',  '#7c2d12', '<path d="M8 22h8M12 15v7M6 3h12l-1 6a5 5 0 0 1-10 0z"/>'],
            ['bakery',        'Bakery',               '#d97706', '<path d="M4 10s1.5-6 8-6 8 6 8 6H4zM4 10v10h16V10M8 14h8"/>'],
            ['pastry',        'Pastry Shop',          '#f59e0b', '<path d="M4 10s1.5-6 8-6 8 6 8 6H4zM4 10v10h16V10M8 14h8"/>'],
            ['icecream',      'Ice Cream Shop',       '#f472b6', '<path d="M12 3a4 4 0 0 0-4 4v1a3 3 0 1 0 3 3v-1a3 3 0 0 1 3-3 4 4 0 0 0-2-4zM9 15h6l-3 6z"/>'],
            ['fastfood',      'Fast Food',            '#ea580c', '<path d="M3 2v7c0 2.2 1.8 4 4 4a4 4 0 0 0 4-4V2M7 2v20"/>'],
            ['foodcourt',     'Food Court',           '#dc2626', '<path d="M3 6h18M3 12h18M3 18h18"/>'],
            ['foodtruck',     'Food Truck',           '#ef4444', '<path d="M2 17V7h11v10M13 11h5l4 4v2h-9M6 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0ZM16 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0Z"/>'],
            ['market',        'Public Market',        '#65a30d', '<path d="M3 9l1.5-6h15L21 9M3 9h18M5 9v11h14V9M9 20v-6h6v6"/>'],
            ['mall',          'Shopping Mall',        '#14b8a6', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4ZM3 6h18"/>'],
            ['shop',          'Shopping & Retail',    '#0d9488', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4ZM3 6h18M16 10a4 4 0 0 1-8 0"/>'],
            ['souvenir',      'Souvenir Shop',        '#0891b2', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>'],
            ['craft',         'Craft Shop',           '#0e7490', '<path d="M12 2 2 7l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>'],
            ['inn',           'Inn / Hotel',          '#3b82f6', '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>'],
            ['hotel',         'Hotel',                '#2563eb', '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>'],
            ['resort',        'Resort',               '#1d4ed8', '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>'],
            ['hostel',        'Hostel',               '#6366f1', '<path d="M3 21V8l9-6 9 6v13M3 21h18M9 21v-6h6v6"/>'],
            ['motel',         'Motel',                '#4f46e5', '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>'],
            ['guesthouse',    'Guesthouse',           '#7c3aed', '<path d="M3 21V8l9-6 9 6v13M3 21h18M9 21v-6h6v6"/>'],
            ['viewpoint',     'Nature & Parks',       '#eab308', '<path d="m17 14 3 3.3a1 1 0 0 1-.7 1.7H4.7a1 1 0 0 1-.7-1.7L7 14h-.3a1 1 0 0 1-.7-1.7L9 9h-.2A1 1 0 0 1 8 7.3L12 3l4 4.3a1 1 0 0 1-.8 1.7H15l3 3.3a1 1 0 0 1-.8 1.7H17ZM12 19v3"/>'],
            ['mountain',      'Mountain',             '#ca8a04', '<path d="m17 14 3 3.3a1 1 0 0 1-.7 1.7H4.7a1 1 0 0 1-.7-1.7L7 14h-.3a1 1 0 0 1-.7-1.7L9 9h-.2A1 1 0 0 1 8 7.3L12 3l4 4.3a1 1 0 0 1-.8 1.7H15l3 3.3a1 1 0 0 1-.8 1.7H17ZM12 19v3"/>'],
            ['waterfall',     'Waterfall',            '#0ea5e9', '<path d="M4 4h6l-2 4h6l-2 4h6l-2 4M8 20l2-4M12 20l2-4"/>'],
            ['beach',         'Beach',                '#06b6d4', '<path d="M2 20h20M4 20c0-4 4-6 8-6s8 2 8 6M8 10l4-6 4 6"/>'],
            ['island',        'Island',               '#0891b2', '<path d="M2 20h20M6 20c0-5 3-8 6-8s6 3 6 8"/>'],
            ['park',          'Park',                 '#22c55e', '<path d="M12 2v20M7 8l5-6 5 6M7 16l5-6 5 6"/>'],
            ['garden',        'Botanical Garden',     '#16a34a', '<path d="M12 22V8M12 8c-3 0-5-2-5-5s2-3 5-3 5 0 5 3-2 5-5 5zM12 12c3 0 5 2 5 5s-2 4-5 4-5-1-5-4 2-5 5-5z"/>'],
            ['farm',          'Farm & Agri-Tourism',  '#84cc16', '<path d="M3 12h18M12 3v18M5 7l7 5 7-5M5 17l7-5 7 5"/>'],
            ['orchard',       'Orchard',              '#65a30d', '<path d="M12 22V12M8 12a4 4 0 1 1 8 0 4 4 0 0 1-8 0zM6 6a4 4 0 1 1 6 3 4 4 0 0 1-6-3zM18 6a4 4 0 1 0-6 3 4 4 0 0 0 6-3z"/>'],
            ['vineyard',      'Vineyard',             '#7c2d12', '<path d="M12 22V8M7 8a5 5 0 1 1 10 0 5 5 0 0 1-10 0zM9 14a3 3 0 1 1 6 0 3 3 0 0 1-6 0z"/>'],
            ['campsite',      'Campsite',             '#a16207', '<path d="M12 3 3 20h18ZM12 3v17M7 12l5-5 5 5"/>'],
            ['trail',         'Hiking Trail',         '#a3651e', '<path d="M4 20c2-4 6-4 8-8s2-6 6-8M4 12h4M16 4h4"/>'],
            ['picnic',        'Picnic Ground',        '#65a30d', '<path d="M3 18h18M5 18v-6h14v6M8 12V6l4-3 4 3v6"/>'],
            ['culture',       'Monuments & Culture',  '#8b5cf6', '<path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>'],
            ['museum',        'Museum',               '#7c3aed', '<path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>'],
            ['gallery',       'Art Gallery',          '#6d28d9', '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>'],
            ['church',        'Church',               '#a855f7', '<path d="M12 2v20M8 8h8M6 22V10l6-4 6 4v12M10 22v-4h4v4"/>'],
            ['shrine',        'Shrine',               '#9333ea', '<path d="M12 2 4 7v15h16V7ZM9 22v-6h6v6"/>'],
            ['monument',      'Monument',             '#a21caf', '<path d="M12 2v20M6 22h12M8 8h8M9 8v14M15 8v14"/>'],
            ['historical',    'Historical Site',      '#c026d3', '<path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>'],
            ['theater',       'Theater',              '#db2777', '<path d="M4 4h16v12H4zM2 20h20M8 16v4M16 16v4"/>'],
            ['cinema',        'Cinema',               '#be185d', '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20M7 6v4M17 6v4M12 18l-2-2 2-2 2 2z"/>'],
            ['library',       'Library',              '#0d9488', '<path d="M3 4h6v18H3zM9 4h6v18H9zM15 4h6v18h-6z"/>'],
            ['school',        'School',               '#0284c7', '<path d="M3 12h18M12 3 3 8l9 5 9-5zM3 12v8h18v-8M9 20v-4h6v4"/>'],
            ['university',    'University',           '#0369a1', '<path d="M3 12h18M12 3 3 8l9 5 9-5zM3 12v8h18v-8M9 20v-4h6v4"/>'],
            ['hospital',      'Hospital & Medical',   '#ef4444', '<path d="M12 6v4M10 8h4M21 21v-4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v4M2 21h20M3 21V9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12"/>'],
            ['pharmacy',      'Pharmacy',             '#dc2626', '<path d="M12 6v12M6 12h12M4 4h16v16H4z"/>'],
            ['bank',          'Bank',                 '#065f46', '<path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>'],
            ['atm',           'ATM',                  '#047857', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20M6 15h3"/>'],
            ['police',        'Police Station',       '#1e40af', '<path d="M12 2 4 6v8a10 10 0 0 0 8 8 10 10 0 0 0 8-8V6z"/>'],
            ['fire',          'Fire Station',         '#b91c1c', '<path d="M12 2s2 4 2 6a4 4 0 0 1-8 0c0-2 2-6 2-6s-2 6 2 6 4-4 2-6zM12 22c4 0 6-2 6-6H6c0 4 2 6 6 6z"/>'],
            ['gas',           'Gas Station',          '#475569', '<rect x="3" y="6" width="12" height="16" rx="1"/><path d="M15 12h3v6a2 2 0 0 1-4 0v-4M6 10h6M6 14h6"/>'],
            ['parking',       'Parking',              '#64748b', '<circle cx="12" cy="12" r="10"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>'],
            ['entrance',      'Entrance / Exit',      '#10b981', '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>'],
            ['transit',       'Transit & Bus',        '#f59e0b', '<path d="M8 6v6M15 6v6M2 12h19.6M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3M4 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0ZM14 19a2 2 0 1 0 4 0 2 2 0 0 0-4 0Z"/>'],
            ['ferry',         'Ferry Port',           '#0369a1', '<path d="M3 14l1-4h16l1 4M3 14l2 6h14l2-6M3 14h18M8 10V4h8v6"/>'],
            ['marina',        'Marina / Yacht Club',  '#0891b2', '<path d="M12 2v20M5 12a7 7 0 0 0 14 0M8 22h8"/>'],
            ['gym',           'Gym & Fitness',        '#7c3aed', '<path d="M6 6v12M18 6v12M3 10h3M3 14h3M18 10h3M18 14h3M6 12h12"/>'],
            ['stadium',       'Stadium',              '#9333ea', '<ellipse cx="12" cy="12" rx="10" ry="6"/><ellipse cx="12" cy="12" rx="5" ry="3"/>'],
            ['sports',        'Sports Facility',      '#7c3aed', '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/>'],
            ['pool',          'Swimming Pool',        '#0ea5e9', '<path d="M2 18c2 0 2 2 4 2s2-2 4-2 2 2 4 2 2-2 4-2 2 2 4 2M4 10h16M8 6h8"/>'],
            ['playground',    'Playground',           '#f97316', '<path d="M4 22V6l8-4 8 4v16M4 12h16M8 22v-6h8v6"/>'],
            ['zoo',           'Zoo',                  '#65a30d', '<path d="M4 12a8 8 0 0 1 16 0M4 12v8h16v-8M8 20v-4h8v4M9 8V5M15 8V5"/>'],
            ['aquarium',      'Aquarium',             '#0284c7', '<path d="M2 12s3-6 10-6 10 6 10 6-3 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2"/>'],
            ['plaza',         'Plaza',                '#a855f7', '<path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>'],
            ['bridge',        'Bridge',               '#64748b', '<path d="M2 18h20M4 18v-6a8 8 0 0 1 16 0v6M12 4v8"/>'],
            ['tower',         'Tower',                '#475569', '<path d="M12 2v20M8 22h8M9 8h6M9 12h6M9 16h6"/>'],
            ['lighthouse',    'Lighthouse',           '#dc2626', '<path d="M12 2v20M8 22h8M9 6h6l-2 4h-2zM8 20l1-10M16 20l-1-10"/>'],
            ['other',         'Other',                '#94a3b8', '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20zM12 8v4M12 16h.01"/>'],
        ];

        $stored = [];
        foreach ($rows as $row) {
            $stored[] = [
                'key'       => $row[0],
                'label'     => $row[1],
                'color'     => $row[2],
                'icon_path' => null,
                'icon_svg'  => $s . $row[3] . $e,
            ];
        }

        SiteSetting::setValue('marker_categories', $stored);
    }

    // ═════════════════════════════════════════════════════════
    //  Tenant types — 85 entries
    // ═════════════════════════════════════════════════════════

    protected function seedTenantTypes(): void
    {
        $types = [
            ['Eco-Tourism & Nature Park',           'Upland nature reserves with trails and waterfalls'],
            ['Eco-Tourism & Coastal Reserve',       'Coastal ecosystems with boardwalks and mangrove conservation'],
            ['Birdwatching & Wildlife Sanctuary',   'Protected habitats for endemic and migratory species'],
            ['Farm & Agri-Tourism',                 'Working farms open for educational tours and produce sales'],
            ['Winery & Distillery',                 'Local producers of fruit wines and spirits'],
            ['Cultural & Heritage Landmark',        'Historic churches, monuments, and heritage sites'],
            ['Modern Art & Architecture',           'Significant modernist and contemporary structures'],
            ['Industrial Heritage Site',            'Preserved industrial landmarks and museums'],
            ['Recreation & Entertainment Park',     'Multi-purpose venues with sports, pools, and event facilities'],
            ['Sports & Events Arena',               'Large-capacity venues for sports and concerts'],
            ['Public Park & Town Center',           'Civic squares and community gathering spaces'],
            ['Food & Beverage Producer',            'Commercial food production and processing'],
            ['Inn',                                 'Small lodging'],
            ['Restaurant',                          'Food establishment'],
            ['Resort',                              'Leisure resort'],
            ['Hotel',                               'Full-service accommodation'],
            ['Hostel',                              'Budget backpacker lodging'],
            ['Motel',                               'Roadside accommodation'],
            ['Guesthouse',                          'Private home converted to lodging'],
            ['Bed & Breakfast',                     'Overnight stay with breakfast included'],
            ['Beach Resort',                        'Seaside resort with beach access'],
            ['Mountain Resort',                     'Upland resort with panoramic views'],
            ['Eco-Resort',                          'Environmentally sustainable resort'],
            ['Boutique Hotel',                      'Small, stylish, personalised hotel'],
            ['Business Hotel',                      'Hotel targeting corporate travellers'],
            ['Budget Hotel',                        'Economy accommodation'],
            ['Luxury Hotel',                        'High-end five-star accommodation'],
            ['Café',                                'Coffee shop with light meals'],
            ['Coffee Shop',                         'Beverage-focused establishment'],
            ['Tea House',                           'Specialty tea service'],
            ['Bar',                                 'Alcohol-focused establishment'],
            ['Restobar',                            'Combined restaurant and bar'],
            ['Brewery',                             'On-site beer production and tasting'],
            ['Bakery',                              'Fresh breads and pastries'],
            ['Pastry Shop',                         'Cakes and dessert specialty'],
            ['Ice Cream Shop',                      'Frozen dessert vendor'],
            ['Food Court',                          'Multiple food stalls in one venue'],
            ['Fast Food',                           'Quick-service restaurant'],
            ['Food Truck',                          'Mobile food vendor'],
            ['Museum',                              'Curated historical or scientific exhibits'],
            ['Art Gallery',                         'Exhibition space for visual art'],
            ['Theater',                             'Live performance venue'],
            ['Cinema',                              'Film screening venue'],
            ['Monument',                            'Commemorative public structure'],
            ['Church',                              'Religious place of worship'],
            ['Shrine',                              'Sacred site for pilgrimage'],
            ['Temple',                              'Non-Christian religious structure'],
            ['Historical Site',                     'Preserved historical location'],
            ['Cultural Center',                     'Multi-use arts and cultural venue'],
            ['Beach',                               'Public seaside recreation area'],
            ['Island',                              'Offshore landmass for tourism'],
            ['Cove',                                'Small sheltered bay'],
            ['Waterfall',                           'Natural cascade attraction'],
            ['Mountain',                            'Peak or elevated landform'],
            ['Nature Reserve',                      'Protected natural area'],
            ['Wildlife Sanctuary',                  'Protected fauna habitat'],
            ['Marine Sanctuary',                    'Protected underwater ecosystem'],
            ['Botanical Garden',                    'Curated plant collection'],
            ['Zoo',                                 'Captive animal exhibition'],
            ['Aquarium',                            'Underwater exhibit venue'],
            ['Amusement Park',                      'Rides and attractions'],
            ['Water Park',                          'Water-based amusement venue'],
            ['Sports Complex',                      'Multi-sport facility'],
            ['Gym',                                 'Fitness facility'],
            ['Swimming Pool',                       'Public or private pool facility'],
            ['Recreation Center',                   'Community recreation venue'],
            ['Shopping Mall',                       'Multi-tenant retail complex'],
            ['Public Market',                       'Local goods and produce market'],
            ['Souvenir Shop',                       'Tourism merchandise vendor'],
            ['Craft Shop',                          'Handmade goods vendor'],
            ['Transportation Hub',                  'Central transport interchange'],
            ['Bus Terminal',                        'Inter-city bus departure point'],
            ['Ferry Port',                          'Water-based transport terminal'],
            ['Marina',                              'Yacht and small boat harbour'],
            ['Hospital',                            'Medical care facility'],
            ['Pharmacy',                            'Medication retail'],
            ['School',                              'Primary or secondary education'],
            ['University',                          'Higher education institution'],
            ['Library',                             'Public book and media repository'],
            ['Government Office',                   'Public administration facility'],
            ['Other',                               'Unclassified establishment'],
        ];

        foreach ($types as $data) {
            $row = TypeOfTenant::firstOrCreate(
                ['type' => $data[0]],
                ['description' => $data[1]],
            );
            $this->tenantTypeIds[$data[0]] = $row->id;
        }
    }

    // ═════════════════════════════════════════════════════════
    //  Property types — 130 entries
    // ═════════════════════════════════════════════════════════

    protected function seedGlobalPropertyTypes(): void
    {
        $types = [
            'Single Room', 'Double Room', 'Twin Room', 'Triple Room', 'Quad Room',
            'Standard Room', 'Deluxe Room', 'Superior Room', 'Executive Room', 'Premium Room',
            'Suite', 'Family Suite', 'Junior Suite', 'Executive Suite', 'Presidential Suite',
            'Honeymoon Suite', 'Studio Room', 'Loft Room', 'Penthouse', 'Dormitory',
            'Bunk Bed', 'Private Room', 'Shared Room', 'Cabin', 'Cottage',
            'Bungalow', 'Chalet', 'Villa', 'Pool Villa', 'Beach Villa',
            'Garden Villa', 'Tent', 'Glamping Tent', 'Camper Van', 'Treehouse',
            'Houseboat', 'Floating Cottage', 'Apartment', 'Studio Apartment', 'One-Bedroom Apartment',
            'Day Pass', 'Night Pass', 'Half-Day Pass', 'Full-Day Pass', 'Weekend Pass',
            'Week Pass', 'Monthly Pass', 'Annual Pass', 'Pool Pass', 'Beach Pass',
            'Adventure Pass', 'All-Access Pass', 'VIP Pass', 'Backpacker Pass', 'Family Pass',
            'Guided Tour', 'Self-Guided Tour', 'Audio Tour', 'Group Tour', 'Private Tour',
            'VIP Tour', 'Walking Tour', 'Bike Tour', 'Boat Tour', 'Food Tour',
            'Cultural Tour', 'Heritage Tour', 'Historical Tour', 'Photography Tour', 'Sunset Tour',
            'Sunrise Tour', 'Stargazing Tour', 'Night Tour', 'City Tour', 'Island Hopping Tour',
            'Activity Package', 'Adventure Package', 'Family Package', 'Couple Package', 'Solo Package',
            'Group Package', 'Weekend Package', 'Holiday Package', 'Farm Tour', 'Orchard Tour',
            'Vineyard Tour', 'Wine Tasting', 'Beer Tasting', 'Coffee Tasting', 'Food Tasting',
            'Cooking Class', 'Workshop', 'Class', 'Lesson', 'Birdwatching Tour',
            'Whale Watching', 'Dolphin Watching', 'Snorkeling', 'Scuba Diving', 'Freediving',
            'Fishing', 'Kayaking', 'Paddleboarding', 'Surfing', 'Sailing',
            'Boat Rental', 'Jetski', 'Waterfall Trek', 'Mountain Trek', 'Eco-Trek',
            'Event Space', 'Conference Room', 'Meeting Room', 'Wedding Venue', 'Party Venue',
            'Banquet Hall', 'Function Room', 'Sports Court Rental', 'Sports Field Rental', 'Hiking Trail Pass',
            'Equipment Rental', 'Bike Rental', 'Car Rental', 'Motorcycle Rental', 'Tent Rental',
            'Souvenir Package', 'Gift Set', 'Gift Basket', 'Camping Package', 'Picnic Package',
            'BBQ Package', 'Breakfast Package', 'Dinner Package', 'Buffet Package', 'Catering Package',
        ];

        foreach ($types as $name) {
            PropertyType::firstOrCreate(['name' => $name, 'tenant_id' => null]);
        }
    }

    // ═════════════════════════════════════════════════════════
    //  Users — 8-month backdate range
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

            // Super-admin pivots land at team 0 (tenant_id null) —
            // that is exactly the team the platform sentinel uses.
            $user->syncRoles(['super-admin']);

            $user->forceFill([
                'created_at' => now()->subDays(240)->subHours(random_int(0, 23))->subMinutes(random_int(0, 59)),
                'updated_at' => now()->subDays(240),
            ])->save();
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

            // NO syncRoles here. At this point tenant_id is null, so
            // the pivots would land at team 0 and poison later
            // hasRole('admin') checks under a tenant context. Role
            // assignment happens in seedTenants(), after tenant_id
            // is set on the user.

            $joinedAt = now()
                ->subDays(230 - ($i * 15))
                ->subHours(random_int(0, 23))
                ->subMinutes(random_int(0, 59));

            $owner->forceFill([
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ])->save();

            $owners[] = $owner;
        }

        return $owners;
    }

    protected function seedPureTourists(): array
    {
        $tourists = [];

        for ($i = 1; $i <= 120; $i++) {
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

            $joinedAt = now()
                ->subDays(238 - ($i * 2))
                ->subHours(random_int(0, 23))
                ->subMinutes(random_int(0, 59));

            $tourist->forceFill([
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ])->save();

            $tourists[] = $tourist;
        }

        return $tourists;
    }

    protected function seedKybApplicants(): array
    {
        $applicants = [];

        for ($i = 1; $i <= 60; $i++) {
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

            $joinedAt = now()
                ->subDays(150 - ($i * 2))
                ->subHours(random_int(0, 23))
                ->subMinutes(random_int(0, 59));

            $applicant->forceFill([
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ])->save();

            $applicants[] = $applicant;
        }

        return $applicants;
    }

    // ═════════════════════════════════════════════════════════
    //  Tourist spots — 5 spots × 30 properties + 3 nearby each
    // ═════════════════════════════════════════════════════════

    protected function touristSpots(): array
    {
        $mangrove = [
            ['Boardwalk Day Pass',     'Day Pass',           50,   50, 'Full-day access to the mangrove boardwalk.'],
            ['Family Boardwalk Pass',  'Family Pass',        180,  4,  'Family bundle for four to the mangrove boardwalk.'],
            ['Student Day Pass',       'Day Pass',           30,   1,  'Discounted entry for students with valid ID.'],
            ['Senior Day Pass',        'Day Pass',           35,   1,  'Discounted entry for senior citizens.'],
            ['Weekend Pass',           'Weekend Pass',       120,  2,  'Two-day weekend access for one.'],
            ['Birdwatching Tour',      'Birdwatching Tour',  250,  10, 'Guided 2-hour birdwatching tour with binoculars.'],
            ['Educational Group Tour', 'Guided Tour',        150,  30, 'Curriculum-linked eco-tour for school groups.'],
            ['Sunset Kayak Rental',    'Kayaking',           400,  2,  'Single kayak rental for a guided sunset paddle.'],
            ['Mangrove Night Walk',    'Night Tour',         200,  15, 'Guided night walk to spot fireflies.'],
            ['Photography Walk',       'Photography Tour',   350,  8,  'Golden-hour photography walk with a guide.'],
            ['Family Kayak Bundle',    'Kayaking',           700,  4,  'Two tandem kayaks for the family.'],
            ['Paddleboarding Session', 'Paddleboarding',     300,  1,  'Two-hour paddleboard rental.'],
            ['Boat Tour Extension',    'Boat Tour',          500,  6,  'Extended boat tour of the mangroves.'],
            ['Mangrove Planting Kit',  'Activity Package',   150,  1,  'Seedling, tools, and certificate.'],
            ['Corporate Retreat',      'Event Space',        5000, 40, 'Corporate retreat booking with full board.'],
            ['Birthday Party Setup',   'Party Venue',        2500, 30, 'Picnic party setup with decorations.'],
            ['Picnic Cottage',         'Cottage',            800,  8,  'Shaded cottage near the boardwalk.'],
            ['Hammock Rental',         'Equipment Rental',   100,  1,  'Cotton hammock for a lazy afternoon.'],
            ['Binoculars Rental',      'Equipment Rental',   100,  1,  'Binoculars for birdwatching.'],
            ['Snack Basket',           'Picnic Package',     200,  2,  'Local snacks, water, and fruit.'],
            ['Lunch Bundle',           'BBQ Package',        350,  4,  'Grilled seafood and rice bundle.'],
            ['Souvenir Postcard Pack', 'Souvenir Package',   80,   1,  'Set of five mangrove-themed postcards.'],
            ['Eco-Trail Snorkeling',   'Snorkeling',         450,  6,  'Snorkel the mangrove reef with a guide.'],
            ['Mangrove Conservation Talk', 'Workshop',       100,  20, 'Environmental lecture on mangroves.'],
            ['Crab Catching Tour',     'Activity Package',   300,  8,  'Learn traditional crab-catching methods.'],
            ['Fishing Charter',        'Fishing',            800,  4,  'Half-day fishing charter in the mangroves.'],
            ['Sunrise Yoga Session',   'Class',              250,  15, 'Outdoor yoga on the boardwalk at dawn.'],
            ['Local Cooking Demo',     'Cooking Class',      500,  10, 'Cook regional seafood dishes.'],
            ['Bamboo Raft Ride',       'Boat Tour',          200,  4,  'Traditional bamboo raft through the channels.'],
        ];

        $gawahon = [
            ['Day Tour Pass',           'Day Pass',           80,   100, 'Full-day access to all seven waterfalls.'],
            ['Family Day Pass',         'Family Pass',        280,  4,   'Family bundle for four.'],
            ['Student Day Pass',        'Day Pass',           60,   1,   'Discounted student admission.'],
            ['Senior Day Pass',         'Day Pass',           60,   1,   'Senior discount admission.'],
            ['Two-Day Adventure Pass',  'Adventure Pass',     250,  1,   'Two-day access with overnight camping.'],
            ['Picnic Cottage',          'Cottage',            500,  10,  'Shaded day-use cottage near the main falls.'],
            ['Family Cottage',          'Cottage',            800,  15,  'Larger cottage for family reunions.'],
            ['Guided Waterfall Trek',   'Waterfall Trek',     300,  20,  'Guided 3-hour trek to all seven waterfalls.'],
            ['Sunrise Trek',            'Mountain Trek',      400,  12,  'Pre-dawn hike to catch the sunrise.'],
            ['Night Trek',              'Mountain Trek',      350,  10,  'Headlamp-guided night hike.'],
            ['Birdwatcher Tour',        'Birdwatching Tour',  450,  12,  'Spot over 106 species with an expert.'],
            ['Camping Package',         'Camping Package',    1000, 4,   'Overnight camping with tent and firewood.'],
            ['Glamping Tent Rental',    'Glamping Tent',      1500, 2,   'Pre-pitched tent with bed and linens.'],
            ['Koi Pond Feeding',        'Activity Package',   50,   5,   'Fish feed packet for the koi pond.'],
            ['Horseback Riding',        'Activity Package',   600,  1,   'Guided horseback ride through trails.'],
            ['ATV Adventure',           'Adventure Package',  1200, 2,   'Two-seater ATV for the mountain trail.'],
            ['Photography Tour',        'Photography Tour',   500,  8,   'Waterfall photography tour with a pro.'],
            ['Rock Climbing Intro',     'Class',              800,  6,   'Beginner rock climbing session.'],
            ['Zip Line Ride',           'Adventure Package',  350,  1,   'Zip line across the gorge.'],
            ['Stargazing Night',        'Stargazing Tour',    400,  15,  'Telescope stargazing tour.'],
            ['Bonfire Package',         'Camping Package',    500,  10,  'Firewood and setup for a bonfire.'],
            ['Cooking Fee',             'Catering Package',   150,  1,   'Use of cooking facilities.'],
            ['Trek Guide',              'Guided Tour',        400,  8,   'Private trek guide for the day.'],
            ['Camping Kit Rental',      'Equipment Rental',   250,  2,   'Tent, mat, and sleeping bag.'],
            ['Photography Guide',       'Guided Tour',        350,  4,   'Personal photography assistant.'],
            ['Binoculars Rental',       'Equipment Rental',   150,  1,   'High-power birding binoculars.'],
            ['Mountain Bike Rental',    'Bike Rental',        400,  1,   'Full-day mountain bike rental.'],
            ['Canyoneering Tour',       'Adventure Package',  1500, 6,   'Guided canyoneering down the falls.'],
            ['Waterfall Rappel',        'Adventure Package',  1800, 4,   'Rappel down a waterfall with a guide.'],
        ];

        $resort = [
            ['Standard Room',       'Standard Room',       1200,  2,   'Cozy room for two with garden view.'],
            ['Deluxe Room',         'Deluxe Room',         2000,  3,   'Spacious deluxe room with poolside view.'],
            ['Family Suite',        'Family Suite',        3500,  5,   'Two-bedroom suite, perfect for families.'],
            ['Executive Suite',     'Executive Suite',     4500,  3,   'Suite with lounge and workspace.'],
            ['Presidential Suite',  'Presidential Suite',  12000, 6,   'Top-floor suite with panoramic views.'],
            ['Honeymoon Suite',     'Honeymoon Suite',     5500,  2,   'Romantic suite with jacuzzi.'],
            ['Pool Villa',          'Pool Villa',          8000,  4,   'Private villa with personal pool.'],
            ['Garden Villa',        'Garden Villa',        5000,  4,   'Villa set in tropical garden.'],
            ['Cabana',              'Cabin',               2200,  2,   'Poolside cabana with bed and AC.'],
            ['Pool Day Pass',       'Pool Pass',           150,   50,  'Day access to all pools and slides.'],
            ['Beach Day Pass',      'Beach Pass',          100,   30,  'Full-day beach access.'],
            ['Weekend Pool Pass',   'Weekend Pass',        280,   2,   'Two-day pool and beach pass.'],
            ['Sports Court Rental', 'Sports Court Rental', 500,   20,  'Basketball or volleyball court.'],
            ['Tennis Court',        'Sports Court Rental', 400,   4,   'Tennis court rental, one hour.'],
            ['Badminton Court',     'Sports Court Rental', 350,   4,   'Badminton court with shuttlecocks.'],
            ['Event Pavilion',      'Event Space',         8000,  200, 'Covered event venue for 200.'],
            ['Wedding Package',     'Wedding Venue',       25000, 150, 'Full wedding package with catering.'],
            ['Conference Room',     'Conference Room',     3000,  40,  'Business conference room with AV.'],
            ['Birthday Party Package', 'Party Venue',      6000,  60,  'Birthday party setup with catering.'],
            ['Buffet Package',      'Buffet Package',      800,   1,   'Per-head buffet package.'],
            ['Breakfast Buffet',    'Breakfast Package',   250,   1,   'Daily breakfast buffet.'],
            ['Dinner Buffet',       'Dinner Package',      450,   1,   'Seafood dinner buffet.'],
            ['Airport Transfer',    'Car Rental',          500,   1,   'One-way airport transfer.'],
            ['Guided City Tour',    'City Tour',           300,   1,   'Half-day guided city tour.'],
            ['Bike Rental',         'Bike Rental',         150,   1,   'Bicycle rental for the day.'],
            ['Massage Service',     'Class',               700,   1,   'In-room massage, one hour.'],
            ['Kids Club Pass',      'Day Pass',            200,   1,   'Full-day access to kids club.'],
            ['Waterslide Pass',     'Adventure Pass',      350,   1,   'Unlimited waterslide access.'],
            ['Cabana Rental',       'Equipment Rental',    600,   6,   'Poolside cabana with loungers.'],
            ['Souvenir Bundle',     'Souvenir Package',    350,   1,   'Resort-branded merchandise bundle.'],
        ];

        $coliseum = [
            ['Event Day Pass',        'Day Pass',            100,   500, 'General admission for coliseum events.'],
            ['VIP Event Pass',        'VIP Pass',            500,   1,   'VIP entry with backstage access.'],
            ['Premium Event Pass',    'Premium Room',        250,   1,   'Premium seating event pass.'],
            ['Concert Series Pass',   'Week Pass',           1500,  1,   'Pass for the monthly concert series.'],
            ['Weekend Pass',          'Weekend Pass',        200,   1,   'Two-day festival admission.'],
            ['Student Ticket',        'Day Pass',            70,    1,   'Discounted student event entry.'],
            ['Senior Ticket',         'Day Pass',            70,    1,   'Discounted senior entry.'],
            ['Court Rental',          'Sports Court Rental', 3000,  40,  'Full court rental for basketball.'],
            ['Volleyball Court',      'Sports Court Rental', 2500,  20,  'Volleyball court rental.'],
            ['Boxing Ring Rental',    'Sports Field Rental', 5000,  10,  'Boxing ring usage for one event.'],
            ['Convention Hall',       'Conference Room',     8000,  200, 'Full-day convention hall.'],
            ['VIP Box Rental',        'Event Space',         15000, 20,  'Private VIP box for 20.'],
            ['Floor Space Rental',    'Event Space',         20000, 500, 'Exhibition floor rental.'],
            ['Expo Booth',            'Event Space',         2500,  4,   'Trade expo booth with power.'],
            ['Rehearsal Slot',        'Event Space',         1500,  50,  'Two-hour rehearsal booking.'],
            ['Press Conference Room', 'Meeting Room',        3500,  30,  'Room with AV and press facilities.'],
            ['Esports Arena Slot',    'Event Space',         4000,  100, 'Esports arena for one match.'],
            ['Food Stall Space',      'Event Space',         800,   4,   'Food concession stall space.'],
            ['Parking Pass',          'Parking',             100,   1,   'Premium event-day parking.'],
            ['Food Voucher',          'Picnic Package',      200,   1,   'Food voucher for events.'],
            ['Event Photography',     'Photography Tour',    500,   1,   'Professional event photography.'],
            ['Videography Package',   'Photography Tour',    1500,  1,   'Full event videography.'],
            ['Livestream Package',    'Photography Tour',    2500,  1,   'Multi-camera livestream setup.'],
            ['Event Merchandise',     'Souvenir Package',    400,   1,   'Event-branded merchandise.'],
            ['Ticket Insurance',      'Souvenir Package',    50,    1,   'Refundable ticket insurance.'],
            ['Meet & Greet',          'VIP Pass',            1200,  1,   'Meet-and-greet with performers.'],
            ['Soundcheck Access',     'VIP Pass',            800,   1,   'Early access to soundcheck.'],
            ['Warmup Court',          'Sports Court Rental', 1000,  10,  'Pre-game warmup court slot.'],
            ['Locker Rental',         'Equipment Rental',    150,   1,   'Private locker for the day.'],
            ['Sports Equipment',      'Equipment Rental',    400,   1,   'Ball, net, and gear rental.'],
        ];

        $cathedral = [
            ['Cathedral Tour',         'Heritage Tour',       150,   30,  'Guided tour of the cathedral and history.'],
            ['Extended Cathedral Tour','Historical Tour',     300,   20,  'In-depth two-hour cathedral tour.'],
            ['Audio Guide',            'Audio Tour',          100,   1,   'Self-paced audio tour.'],
            ['Night Illumination Tour','Night Tour',          250,   25,  'Evening tour with illumination.'],
            ['Photography Permit',     'Photography Tour',    300,   1,   'Professional photography permit.'],
            ['Wedding Package',        'Wedding Venue',       15000, 200, 'Full wedding ceremony package.'],
            ['Baptism Package',        'Event Space',         3000,  50,  'Baptism ceremony with certificate.'],
            ['Confirmation Package',   'Event Space',         2500,  40,  'Confirmation ceremony package.'],
            ['Funeral Mass',           'Event Space',         2000,  100, 'Funeral mass arrangements.'],
            ['Choir Rental',           'Class',               500,   1,   'Cathedral choir for the ceremony.'],
            ['Soloist Package',        'Class',               800,   1,   'Solo soprano or tenor.'],
            ['Organ Performance',      'Class',               1200,  1,   'Pipe organ performance.'],
            ['Flower Arrangement',     'Souvenir Package',    800,   1,   'Altar and aisle flowers.'],
            ['Candle Package',         'Souvenir Package',    200,   1,   'Ceremonial candles bundle.'],
            ['Pilgrim Package',        'Activity Package',    500,   15,  'Full pilgrimage experience.'],
            ['Rosary Workshop',        'Workshop',            150,   20,  'Hands-on rosary-making workshop.'],
            ['Catechism Class',        'Class',               100,   15,  'Weekly catechism session.'],
            ['Family Blessing',        'Event Space',         1000,  20,  'Family blessing ceremony.'],
            ['House Blessing',         'Event Space',         800,   10,  'Blessing at your home.'],
            ['Anointing Service',      'Event Space',         600,   10,  'Anointing of the sick.'],
            ['First Communion',        'Event Space',         1800,  30,  'First communion ceremony.'],
            ['Advent Retreat',         'Weekend Package',     2500,  40,  'Two-day Advent retreat.'],
            ['Lenten Retreat',         'Weekend Package',     2500,  40,  'Two-day Lenten retreat.'],
            ['Youth Camp',             'Camping Package',     1500,  60,  'Parish youth camp.'],
            ['Youth Rally Ticket',     'Day Pass',            150,   200, 'One-day youth rally entry.'],
            ['Souvenir Bundle',        'Souvenir Package',    250,   1,   'Cathedral souvenir set.'],
            ['Religious Book',         'Souvenir Package',    350,   1,   'Commemorative book purchase.'],
            ['Rosary and Medal',       'Souvenir Package',    200,   1,   'Handcrafted rosary and medal.'],
            ['Hall Rental',            'Event Space',         3000,  80,  'Parish hall rental for events.'],
            ['Reception Package',      'Wedding Venue',       5000,  100, 'Wedding reception on-site.'],
        ];

        return [
            ['slug' => 'baybay-mangrove-eco-trail', 'name' => 'Baybay Mangrove Eco-Trail', 'type' => 'Eco-Tourism & Coastal Reserve', 'barangay' => 'Barangay VI-A', 'address' => 'Barangay VI-A, Victorias City, Negros Occidental', 'contact_number' => '034-399-9999', 'email' => 'mangrove@gmail.com', 'description' => 'Boardwalk winding through protected mangrove forests along the coast.', 'coordinates' => ['lat' => 10.92, 'lng' => 123.06],
                'nearby' => [
                    ['name' => 'Tambayan Sa Kamalig Restobar', 'type' => 'bar'],
                    ['name' => 'Mi Kafé Coffee Shop',          'type' => 'cafe'],
                    ['name' => 'Coastal Road Viewpoint',       'type' => 'viewpoint'],
                ],
                'properties' => $mangrove,
            ],
            ['slug' => 'gawahon-eco-park', 'name' => 'Gawahon Eco Park', 'type' => 'Eco-Tourism & Nature Park', 'barangay' => 'Barangay XI', 'address' => 'Barangay XI, Victorias City, Negros Occidental', 'contact_number' => '034-399-2830', 'email' => 'gawahon@gmail.com', 'description' => 'Scenic upland nature park featuring seven natural waterfalls and hiking trails.', 'coordinates' => ['lat' => 10.79, 'lng' => 123.18],
                'nearby' => [
                    ['name' => 'Kabisera Restaurant',  'type' => 'restaurant'],
                    ['name' => 'Matawhay Yard Cafe',   'type' => 'cafe'],
                    ['name' => 'Main Falls Viewpoint', 'type' => 'waterfall'],
                ],
                'properties' => $gawahon,
            ],
            ['slug' => 'victorias-city-resort', 'name' => 'Victorias City Resort & Sports/Amusement Center', 'type' => 'Recreation & Entertainment Park', 'barangay' => 'Barangay XIII', 'address' => 'Barangay XIII, Victorias City, Negros Occidental', 'contact_number' => '034-409-1234', 'email' => 'resort@gmail.com', 'description' => 'Multi-purpose recreation venue with swimming pools, sports facilities, and event venues.', 'coordinates' => ['lat' => 10.89, 'lng' => 123.05],
                'nearby' => [
                    ['name' => 'D\'Breakers Resto',  'type' => 'restaurant'],
                    ['name' => 'El Tio Charles Bar', 'type' => 'bar'],
                    ['name' => 'Resort Pool Deck',   'type' => 'pool'],
                ],
                'properties' => $resort,
            ],
            ['slug' => 'victorias-city-coliseum', 'name' => 'Victorias City Coliseum', 'type' => 'Sports & Events Arena', 'barangay' => 'Barangay V', 'address' => 'City Proper, Victorias City, Negros Occidental', 'contact_number' => '034-399-2222', 'email' => 'coliseum@gmail.com', 'description' => 'A 13,000-capacity coliseum hosting major national sports events, concerts, and the Kadalag-an Festival highlights.', 'coordinates' => ['lat' => 10.903, 'lng' => 123.073],
                'nearby' => [
                    ['name' => 'Elisha\'s Inn',     'type' => 'inn'],
                    ['name' => 'Timoteo\'s Bistro', 'type' => 'restaurant'],
                    ['name' => 'VIP Entrance',      'type' => 'entrance'],
                ],
                'properties' => $coliseum,
            ],
            ['slug' => 'immaculate-concepcion-cathedral', 'name' => 'Immaculate Concepcion Cathedral', 'type' => 'Cultural & Heritage Landmark', 'barangay' => 'Barangay VI', 'address' => 'Canetown Subdivision, Victorias City, Negros Occidental', 'contact_number' => '034-399-6000', 'email' => 'cathedral@gmail.com', 'description' => 'One of the biggest churches in Visayas and Mindanao, a monumental edifice in Canetown Subdivision.', 'coordinates' => ['lat' => 10.91, 'lng' => 123.08],
                'nearby' => [
                    ['name' => 'Jollibee Victorias',     'type' => 'fastfood'],
                    ['name' => 'Cafe Rac\'s Restaurant', 'type' => 'cafe'],
                    ['name' => 'Cathedral Plaza',        'type' => 'plaza'],
                ],
                'properties' => $cathedral,
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
    //  Tenants — backdated to span 8 months
    // ═════════════════════════════════════════════════════════

    protected function seedTenants(array $spots, array $owners): void
    {
        foreach ($spots as $index => $data) {
            $owner = $owners[$index] ?? null;
            if (! $owner) continue;

            $ownerNumber = $index + 1;
            $logoPath    = $this->fetchLogo($data['slug']);
            $coordinates = $this->buildCoordinates($data);

            $tenantJoinedAt = now()
                ->subDays(230 - ($index * 25))
                ->subHours(random_int(0, 23))
                ->subMinutes(random_int(0, 59));

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
                    'verified_at'       => $tenantJoinedAt->copy()->addDays(3),
                    'permit_expires_at' => $tenantJoinedAt->copy()->addMonths(9),
                ],
            );

            $tenant->forceFill([
                'created_at' => $tenantJoinedAt,
                'updated_at' => $tenantJoinedAt,
            ])->save();

            $owner->update(['tenant_id' => $tenant->id, 'active_mode' => User::MODE_BUSINESS]);

            // Role pivot lands at the tenant's team (the owner's
            // active team at this point). Correct — see User::syncRoles.
            $owner->syncRoles(['tourist', 'admin']);

            // Business membership pivot row for the switcher.
            $existingMembership = BusinessMembership::where('user_id', $owner->id)
                ->where('tenant_id', $tenant->id)
                ->first();

            if (! $existingMembership) {
                BusinessMembership::create([
                    'user_id'   => $owner->id,
                    'tenant_id' => $tenant->id,
                    'role'      => BusinessMembership::ROLE_OWNER,
                    'is_active' => true,
                    'joined_at' => $tenantJoinedAt,
                ]);
            }

            $this->seedTenantDemoData($tenant, $data, $ownerNumber);
            $this->seedTenantBusinessInfo($tenant, $data);
            $this->seedTenantGallery($tenant, $data);
            $this->seedTenantKybRecord($tenant, $owner);
        }

        $this->seedEvents();
    }

    /**
     * Second business for owner1@gmail.com — demonstrates the
     * multi-business flow end to end. Both of owner1's businesses
     * are in Victorias City.
     *
     * After seeding:
     *   - owner1.tenant_id           → baybay-mangrove-eco-trail (unchanged)
     *   - owner1 owns two businesses (businessMemberships rows × 2)
     *   - owner1 holds admin at both tenant teams
     *   - Header dropdown shows both businesses on next page load
     */
    protected function seedOwner1SecondBusiness(): void
    {
        $owner = User::query()->where('email', 'owner1@gmail.com')->first();

        if (! $owner || ! $owner->tenant_id) {
            return;
        }

        // Idempotence: if we already created this business, bail.
        if (Tenant::query()->where('slug', 'victorias-heritage-bnb')->exists()) {
            return;
        }

        $data = [
            'slug'           => 'victorias-heritage-bnb',
            'name'           => 'Victorias Heritage Bed & Breakfast',
            'type'           => 'Bed & Breakfast',
            'barangay'       => 'Barangay VI',
            'address'        => 'Canetown Subdivision, Victorias City, Negros Occidental',
            'contact_number' => '034-399-1111',
            'email'          => 'heritage.bnb@gmail.com',
            'description'    => 'A boutique bed-and-breakfast set in a restored heritage home near the Immaculate Concepcion Cathedral.',
            'coordinates'    => ['lat' => 10.912, 'lng' => 123.079],
            'nearby'         => [
                ['name' => 'Cathedral Plaza',       'type' => 'plaza'],
                ['name' => 'Cafe Rac\'s Restaurant', 'type' => 'cafe'],
                ['name' => 'Canetown Park',         'type' => 'park'],
            ],
            'properties' => [
                ['Standard Room',     'Standard Room',    1500, 2, 'Cozy room for two with garden view.'],
                ['Deluxe Room',       'Deluxe Room',      2200, 2, 'Larger room with private balcony.'],
                ['Family Suite',      'Family Suite',     3500, 4, 'Two-bedroom suite for families.'],
                ['Heritage Suite',    'Executive Suite',  4500, 2, 'Restored heritage suite with antique furnishings.'],
                ['Honeymoon Suite',   'Honeymoon Suite',  3800, 2, 'Romantic suite with four-poster bed.'],
                ['Single Room',       'Single Room',      900,  1, 'Budget single room.'],
                ['Twin Room',         'Twin Room',        1700, 2, 'Two single beds.'],
                ['Breakfast Basket',  'Breakfast Package', 350, 1, 'Continental breakfast in-room.'],
                ['Dinner Package',    'Dinner Package',   650,  1, 'Three-course set dinner.'],
                ['Airport Transfer',  'Car Rental',       500,  1, 'One-way transfer to Bacolod airport.'],
                ['Guided City Tour',  'City Tour',        400,  1, 'Half-day heritage city tour.'],
                ['Bike Rental',       'Bike Rental',      150,  1, 'Bicycle rental per day.'],
            ],
        ];

        $logoPath    = $this->fetchLogo($data['slug']);
        $coordinates = $this->buildCoordinates($data);

        $createdAt = $owner->created_at?->copy()->addDays(60) ?? now()->subDays(120);

        $tenant = Tenant::create([
            'name'              => $data['name'],
            'slug'              => $data['slug'],
            'type_of_tenant_id' => $this->tenantTypeIds[$data['type']] ?? null,
            'address'           => $data['address'],
            'barangay'          => $data['barangay'],
            'contact_number'    => $data['contact_number'],
            'email'             => $data['email'],
            'coordinates'       => $coordinates,
            'logo'              => $logoPath,
            'is_active'         => true,
            'verified_at'       => $createdAt->copy()->addDays(3),
            'permit_expires_at' => $createdAt->copy()->addMonths(9),
        ]);

        $tenant->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        // Populate the business like any other tenant.
        $this->seedTenantDemoData($tenant, $data, 6);
        $this->seedTenantBusinessInfo($tenant, $data);
        $this->seedTenantGallery($tenant, $data);
        $this->seedTenantKybRecord($tenant, $owner);

        // ── Multi-business pivots ────────────────────────────
        // BusinessMembership row for the second business,
        // is_active = false — the first business stays active.
        BusinessMembership::create([
            'user_id'   => $owner->id,
            'tenant_id' => $tenant->id,
            'role'      => BusinessMembership::ROLE_OWNER,
            'is_active' => false,
            'joined_at' => $createdAt,
        ]);

        // Admin role pivot at THIS tenant's team, not at the
        // owner's currently-active team. Without assignRoleAtTeam,
        // hasRole('admin') under the new tenant's context would
        // return false and the owner would be locked out of /admin/*
        // after switching.
        $owner->assignRoleAtTeam($tenant->id, 'admin');

        // NOTE: $owner->tenant_id is deliberately NOT updated.
        // Owner1 remains on Baybay Mangrove; they switch via the
        // header dropdown on next page load.
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
        TenantSetting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'spot_cover'], ['value' => $coverPath]);

        $gallery = [];
        for ($i = 1; $i <= 6; $i++) {
            $gallery[] = $this->fetchGalleryImage($data['slug'], $i);
        }

        TenantSetting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'business_gallery'], ['value' => $gallery]);
        TenantSetting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'gallery_title'], ['value' => "Discover {$data['name']}"]);
        TenantSetting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'gallery_subtitle'], ['value' => $data['description'] ?? 'A glimpse of what awaits you.']);
    }

    protected function seedTenantKybRecord(Tenant $tenant, User $owner): void
    {
        $superAdminId = User::query()->where('email', 'superadmin@gmail.com')->value('id');

        $coverPath = $this->fetchSpotCover($tenant->slug);

        $tin          = $this->generateTin($tenant->slug);
        $registration = $this->generateRegistrationNumber($tenant->slug);
        $phone        = $this->mobileNumber($tenant->slug);

        $application = BusinessApplication::firstOrCreate(
            ['approved_tenant_id' => $tenant->id],
            [
                'user_id'                      => $owner->id,
                'business_name'                => $tenant->name,
                'business_type'                => 'dti',
                'type_of_tenant_id'            => $tenant->type_of_tenant_id,
                'business_registration_number' => $registration,
                'tin_number'                   => $tin,
                'owner_full_name'              => $owner->name,
                'owner_id_type'                => 'drivers_license',
                'owner_id_number'              => 'N01-23-456789',
                'owner_birthdate'              => now()->subYears(35),
                'contact_email'                => $owner->email,
                'contact_phone'                => $phone,
                'address'                      => $tenant->address,
                'barangay'                     => $tenant->barangay,
                'city'                         => 'Victorias City',
                'province'                     => 'Negros Occidental',
                'coordinates'                  => $tenant->coordinates,
                'logo_path'                    => $tenant->logo,
                'cover_photo_path'             => $coverPath,
                'owner_avatar_path'            => $owner->avatar,
                'status'                       => BusinessApplication::STATUS_APPROVED,
                'source'                       => BusinessApplication::SOURCE_SUPERADMIN_DIRECT,
                'submitted_at'                 => $tenant->created_at,
                'reviewed_at'                  => $tenant->verified_at,
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
            ->whereNull('tenant_id', 'and', false)
            ->pluck('id', 'name');

        foreach ($data['properties'] as $p) {
            [$name, $type, $price, $capacity, $desc] = $p;

            if (! isset($typeIds[$type])) continue;

            $property = Property::create([
                'tenant_id'        => $tenant->id,
                'property_type_id' => $typeIds[$type],
                'name'             => $name,
                'description'      => $desc,
                'price'            => $price,
                'capacity'         => $capacity,
                'quantity'         => 1,
                'status'           => 'available',
                'is_active'        => true,
            ]);

            PropertyImage::create([
                'tenant_id'   => $tenant->id,
                'property_id' => $property->id,
                'image_path'  => $this->fetchPropertyImage($tenant->slug, $name),
            ]);
        }

        $now = now();

        $services = [
            ['name' => 'Cooking Fee',         'price' => 100],
            ['name' => 'Trek Guide',          'price' => 400],
            ['name' => 'Photography Service', 'price' => 350],
            ['name' => 'Binoculars Rental',   'price' => 150],
            ['name' => 'Equipment Rental',    'price' => 250],
            ['name' => 'Snack Basket',        'price' => 200],
            ['name' => 'Tour Guide',          'price' => 400],
            ['name' => 'Bike Rental',         'price' => 150],
            ['name' => 'Airport Transfer',    'price' => 500],
            ['name' => 'Guided City Tour',    'price' => 300],
            ['name' => 'Massage Service',     'price' => 700],
            ['name' => 'Laundry Service',     'price' => 150],
        ];

        Service::insert(array_map(
            fn ($s) => [
                'tenant_id'  => $tenant->id,
                'name'       => $s['name'],
                'price'      => $s['price'],
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $services,
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
            // syncRoles writes at the user's active team, which is
            // the tenant just set above. Correct.
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

        $joinedAt = $tenant->created_at
            ? $tenant->created_at->copy()->addDays(random_int(5, 40))
            : now()->subDays(random_int(30, 200));

        $user->forceFill(['created_at' => $joinedAt, 'updated_at' => $joinedAt])->save();
    }

    // ═════════════════════════════════════════════════════════
    //  Bookings — spread across 8 months
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
        $sample = array_slice($candidates, 0, 60);

        $paymentCycle = ['cash', 'gcash', 'card'];

        Booking::withoutEvents(function () use (
            $sample, $tenant, $propertyIds, $propertyPrices,
            $serviceIds, $servicePrices, $paymentCycle
        ): void {
            foreach ($sample as $index => $booker) {
                $monthsBack = $index % 8;

                $createdAt = now()
                    ->subMonths($monthsBack)
                    ->subDays(random_int(0, 25))
                    ->subHours(random_int(0, 23))
                    ->subMinutes(random_int(0, 59));

                $checkIn  = $createdAt->copy()->addDays(random_int(1, 30));
                $checkOut = $checkIn->copy()->addDays(random_int(1, 4));

                if ($checkOut->isPast()) {
                    $status = $index % 3 === 0
                        ? Booking::STATUS_CANCELLED
                        : Booking::STATUS_COMPLETED;
                } elseif ($checkIn->isPast() && $checkOut->isFuture()) {
                    $status = Booking::STATUS_CHECKED_IN;
                } else {
                    $status = match ($index % 3) {
                        0       => Booking::STATUS_PENDING,
                        1       => Booking::STATUS_RESERVED,
                        default => Booking::STATUS_CONFIRMED,
                    };
                }

                $roomId    = $propertyIds[$index % count($propertyIds)];
                $roomPrice = $propertyPrices[$roomId];
                $nights    = max(1, (int) $checkIn->diffInDays($checkOut));
                $total     = $roomPrice * $nights;

                $bookingType = $status === Booking::STATUS_RESERVED
                    ? Booking::TYPE_RESERVATION
                    : Booking::TYPE_FULL;

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
                    'created_at'        => $createdAt,
                    'updated_at'        => $createdAt,
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

                $serviceCount = 1 + ($index % 3);
                $usedServices = [];

                for ($j = 0; $j < $serviceCount; $j++) {
                    $svcId = $serviceIds[($index + $j) % count($serviceIds)];
                    if (in_array($svcId, $usedServices, true)) continue;
                    $usedServices[] = $svcId;

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

                $paymentMethod = $paymentCycle[$index % count($paymentCycle)];

                $paidAt = null;
                if ($paymentStatus === 'paid') {
                    $candidate = $createdAt->copy()->addHours(random_int(1, 10));
                    $paidAt = $candidate->isFuture()
                        ? $createdAt->copy()->addMinutes(random_int(5, 60))
                        : $candidate;
                }

                $payment = new Payment();
                $payment->forceFill([
                    'tenant_id'        => $tenant->id,
                    'booking_id'       => $booking->id,
                    'amount'           => $paymentAmount,
                    'payment_method'   => $paymentMethod,
                    'payment_type'     => $bookingType,
                    'payment_status'   => $paymentStatus,
                    'paid_at'          => $paidAt,
                    'reference_number' => $paymentStatus === 'paid'
                        ? 'TXN-' . Str::upper(Str::random(10))
                        : null,
                    'created_at'       => $createdAt,
                    'updated_at'       => $paidAt ?? $createdAt,
                ]);
                $payment->save();
            }
        });
    }

    // ═════════════════════════════════════════════════════════
    //  Events
    // ═════════════════════════════════════════════════════════

    protected function seedEvents(): void
    {
        $tenantIdBySlug = Tenant::query()->pluck('id', 'slug');

        $futureEvents = [
            ['name' => 'Kadalag-an Festival',                 'barangay' => 'Barangay V',    'type' => 'fiesta',        'featured' => true,  'tenant_slug' => null, 'days' => 30,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'Annual celebration of Victorias City\'s charter anniversary every March 21, featuring street dancing, pageantry, and the grand slam-winning Sidlak Kadalag-an Festival dance competition.'],
            ['name' => 'Malihaw Festival',                    'barangay' => 'Barangay IX',   'type' => 'fiesta',        'featured' => true,  'tenant_slug' => null, 'days' => 45,  'coord' => ['lat' => 10.925, 'lng' => 123.05],  'desc' => 'Celebrated every April 26 in honor of the city\'s patroness, Nuestra Señora de las Victorias, featuring a traditional fluvial procession along the Malihaw River.'],
            ['name' => 'Kalamayan Festival',                  'barangay' => 'Barangay V',    'type' => 'fiesta',        'featured' => true,  'tenant_slug' => null, 'days' => 90,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'The city\'s grand year-end fiesta featuring the Sabor Victorias cookfest, cultural shows, and community celebrations at the Public Plaza every December.'],
            ['name' => 'Sabor Victorias Cookfest',            'barangay' => 'Barangay V',    'type' => 'other',         'featured' => false, 'tenant_slug' => null, 'days' => 92,  'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'Annual cookfest organized by Victorias Milling Company Foundation as part of the Kalamayan Festival, showcasing local culinary talent.'],
            ['name' => 'Plaza Christmas Lights Festival',     'barangay' => 'Barangay V',    'type' => 'entertainment', 'featured' => true,  'tenant_slug' => null, 'days' => 120, 'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'The city plaza transforms into a lights-and-sound spectacle for the holiday season, with nightly shows and a holiday market.'],
            ['name' => 'Plaza Weekend Market',                'barangay' => 'Barangay V',    'type' => 'other',         'featured' => false, 'tenant_slug' => null, 'days' => 7,   'coord' => ['lat' => 10.90,  'lng' => 123.07],  'desc' => 'Weekend pop-up market featuring local produce, street food, and handmade crafts from Negros Occidental vendors.'],

            ['name' => 'Gawahon Eco-Trail Fun Run',           'barangay' => 'Barangay XI',   'type' => 'sports',        'featured' => false, 'tenant_slug' => 'gawahon-eco-park',               'days' => 21,  'coord' => ['lat' => 10.79,  'lng' => 123.18],  'desc' => 'A 5K fun run through the scenic trails of Gawahon Eco Park. Open to all ages!'],
            ['name' => 'Gawahon Waterfall Trek Challenge',    'barangay' => 'Barangay XI',   'type' => 'adventure',     'featured' => false, 'tenant_slug' => 'gawahon-eco-park',               'days' => 45,  'coord' => ['lat' => 10.79,  'lng' => 123.18],  'desc' => 'Tag all seven waterfalls in a single day. Finish within 6 hours to earn the Gawahon finisher pin.'],

            ['name' => 'Mangrove Planting Day',               'barangay' => 'Barangay VI-A', 'type' => 'environment',   'featured' => false, 'tenant_slug' => 'baybay-mangrove-eco-trail',      'days' => 10,  'coord' => ['lat' => 10.92,  'lng' => 123.06],  'desc' => 'Join the community in planting mangroves along the coast to preserve the marine ecosystem.'],
            ['name' => 'Mangrove Night Walk',                 'barangay' => 'Barangay VI-A', 'type' => 'adventure',     'featured' => false, 'tenant_slug' => 'baybay-mangrove-eco-trail',      'days' => 15,  'coord' => ['lat' => 10.92,  'lng' => 123.06],  'desc' => 'Guided night walk through the mangrove forest to observe fireflies and nocturnal wildlife.'],

            ['name' => 'Summer Sports Fest',                  'barangay' => 'Barangay XIII', 'type' => 'sports',        'featured' => false, 'tenant_slug' => 'victorias-city-resort',          'days' => 50,  'coord' => ['lat' => 10.89,  'lng' => 123.05],  'desc' => 'Inter-barangay basketball and volleyball tournament with live music and food stalls.'],
            ['name' => 'Victorias City Resort Summer Nights', 'barangay' => 'Barangay XIII', 'type' => 'entertainment', 'featured' => true,  'tenant_slug' => 'victorias-city-resort',          'days' => 75,  'coord' => ['lat' => 10.89,  'lng' => 123.05],  'desc' => 'Exclusive resort party with live DJ, poolside cocktails, and fireworks.'],

            ['name' => 'Coliseum Concert Series',             'barangay' => 'Barangay V',    'type' => 'entertainment', 'featured' => true,  'tenant_slug' => 'victorias-city-coliseum',        'days' => 65,  'coord' => ['lat' => 10.903, 'lng' => 123.073], 'desc' => 'Monthly concert series at the Victorias City Coliseum featuring national and local artists.'],

            ['name' => 'Cathedral Heritage Day',              'barangay' => 'Barangay VI',   'type' => 'entertainment', 'featured' => false, 'tenant_slug' => 'immaculate-concepcion-cathedral', 'days' => 35,  'coord' => ['lat' => 10.91,  'lng' => 123.08],  'desc' => 'Open-house heritage day at the cathedral with guided tours, choral performances, and a photography exhibit.'],

            ['name' => 'Heritage B&B Tea Time',               'barangay' => 'Barangay VI',   'type' => 'other',         'featured' => false, 'tenant_slug' => 'victorias-heritage-bnb',          'days' => 14,  'coord' => ['lat' => 10.912, 'lng' => 123.079], 'desc' => 'Weekly heritage tea time at the Victorias Heritage Bed & Breakfast, featuring local pastries and a short tour of the restored house.'],
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

        $pastEvents = [
            ['name' => 'Kadalag-an Festival 2025',       'barangay' => 'Barangay V',  'type' => 'fiesta',    'featured' => false, 'tenant_slug' => null, 'days' => -90,  'coord' => ['lat' => 10.90,  'lng' => 123.07], 'desc' => 'The 2025 edition of the Kadalag-an Festival — a milestone year celebrating the city\'s heritage.'],
            ['name' => 'Malihaw Festival 2025',          'barangay' => 'Barangay IX', 'type' => 'fiesta',    'featured' => false, 'tenant_slug' => null, 'days' => -75,  'coord' => ['lat' => 10.925, 'lng' => 123.05], 'desc' => 'Last year\'s Malihaw Festival, honoring Nuestra Señora de las Victorias with the traditional fluvial parade.'],
            ['name' => 'Kalamayan Festival 2024',        'barangay' => 'Barangay V',  'type' => 'fiesta',    'featured' => false, 'tenant_slug' => null, 'days' => -200, 'coord' => ['lat' => 10.90,  'lng' => 123.07], 'desc' => 'The 2024 Kalamayan Festival celebration with the Sabor Victorias cookfest and year-end festivities.'],
            ['name' => 'Gawahon Anniversary Trek 2025',  'barangay' => 'Barangay XI', 'type' => 'adventure', 'featured' => false, 'tenant_slug' => 'gawahon-eco-park', 'days' => -45, 'coord' => ['lat' => 10.79, 'lng' => 123.18], 'desc' => 'Commemorative trek celebrating the founding anniversary of Gawahon Eco Park.'],
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

        Event::updateOrCreate(
            ['name' => 'Cancelled: Food Truck Rally 2025'],
            [
                'tenant_id'   => null,
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
    //  KYB applications — 60 applicants get applications
    // ═════════════════════════════════════════════════════════

    protected function seedBusinessApplications(array $applicants): void
    {
        $superAdminId = User::query()->where('email', 'superadmin@gmail.com')->value('id');

        $statuses = [
            BusinessApplication::STATUS_DRAFT,
            BusinessApplication::STATUS_PENDING,
            BusinessApplication::STATUS_UNDER_REVIEW,
            BusinessApplication::STATUS_NEEDS_REVISION,
            BusinessApplication::STATUS_APPROVED,
            BusinessApplication::STATUS_REJECTED,
        ];

        $businessNames = [
            'Lakeside Kiosk', 'Sunrise Café', 'Hilltop Inn', 'Downtown Bar', 'Seaside Grill',
            'Riverside Lodge', 'Mountain View Bistro', 'Garden Café', 'Bayfront Diner', 'Plaza Restaurant',
            'Corner Bakery', 'Heritage Tea House', 'Craft Beer Corner', 'Local Wine Bar', 'Urban Eatery',
            'Tourist Rest House', 'Budget Inn', 'Family Lodge', 'Business Suites', 'Executive Hotel',
            'Boutique B&B', 'Eco Lodge', 'Beachside Cabin', 'Farm Stay', 'Vineyard Cottage',
            'Mango Orchard B&B', 'Coffee Farm House', 'Pastry Corner', 'Pizza Place', 'Burger Joint',
            'Sushi Bar', 'Noodle House', 'Rice Bowl', 'Carenderia Deluxe', 'Snack Shack',
            'Souvenir Central', 'Craft Boutique', 'Fashion Outlet', 'Book Café', 'Art Studio',
            'Photography Studio', 'Yoga Retreat', 'Wellness Spa', 'Massage Parlor', 'Salon',
            'Barber Shop', 'Laundry Hub', 'Car Wash', 'Pet Shop', 'Gadget Store',
            'Electronics Hub', 'Hardware Store', 'Pharmacy Plus', 'Medical Clinic', 'Dental Office',
            'Music School', 'Language Academy', 'Tutorial Center', 'Dance Studio', 'Fitness Gym',
        ];

        $tenantTypes = array_keys($this->tenantTypeIds);

        foreach ($applicants as $i => $user) {
            $status   = $statuses[$i % count($statuses)];
            $bizName  = $businessNames[$i % count($businessNames)] . ' #' . $i;
            $typeName = $tenantTypes[$i % count($tenantTypes)];

            $isSubmitted = $status !== BusinessApplication::STATUS_DRAFT;
            $reviewed    = in_array($status, [
                BusinessApplication::STATUS_NEEDS_REVISION,
                BusinessApplication::STATUS_REJECTED,
                BusinessApplication::STATUS_APPROVED,
            ], true);

            $createdAt = now()->subDays(random_int(10, 200));

            $seed = 'app-' . $user->id . '-' . $i;

            $logoPath   = null;
            $coverPath  = null;
            $avatarPath = null;

            if ($isSubmitted) {
                $logoPath   = $this->fetchLogo(Str::slug($bizName));
                $coverPath  = $this->fetchSpotCover(Str::slug($bizName));
                $avatarPath = $user->avatar;
            }

            $application = BusinessApplication::firstOrCreate(
                ['user_id' => $user->id, 'business_name' => $bizName],
                [
                    'business_type'                => 'dti',
                    'business_registration_number' => $isSubmitted ? $this->generateRegistrationNumber($seed) : null,
                    'tin_number'                   => $isSubmitted ? $this->generateTin($seed)                   : null,
                    'owner_full_name'              => $isSubmitted ? $user->name : null,
                    'owner_id_type'                => $isSubmitted ? 'drivers_license' : null,
                    'owner_id_number'              => $isSubmitted ? 'N01-23-456789' : null,
                    'owner_birthdate'              => $isSubmitted ? now()->subYears(35)->subDays(random_int(0, 365)) : null,
                    'contact_email'                => $user->email,
                    'contact_phone'                => $this->mobileNumber($seed),
                    'address'                      => $isSubmitted ? 'Street address, Victorias City' : null,
                    'barangay'                     => $isSubmitted ? 'Barangay V' : null,
                    'city'                         => $isSubmitted ? 'Victorias City' : null,
                    'province'                     => $isSubmitted ? 'Negros Occidental' : null,
                    'type_of_tenant_id'            => $isSubmitted ? ($this->tenantTypeIds[$typeName] ?? null) : null,
                    'logo_path'                    => $logoPath,
                    'cover_photo_path'             => $coverPath,
                    'owner_avatar_path'            => $avatarPath,
                    'status'                       => $status,
                    'revision_notes'               => $status === BusinessApplication::STATUS_NEEDS_REVISION
                        ? 'Please re-upload BIR Form 2303 — the attached scan is unreadable.'
                        : null,
                    'rejection_reason'             => $status === BusinessApplication::STATUS_REJECTED
                        ? 'TIN on BIR Form 2303 does not match the submitted TIN.'
                        : null,
                    'submitted_at'                 => $isSubmitted ? $createdAt : null,
                    'reviewed_at'                  => $reviewed ? $createdAt->copy()->addDays(random_int(2, 10)) : null,
                    'reviewed_by'                  => $reviewed ? $superAdminId : null,
                ],
            );

            $application->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $application->reviewed_at ?? $createdAt,
            ])->save();

            if ($isSubmitted && ! $application->documents()->exists()) {
                $docStatus = match ($status) {
                    BusinessApplication::STATUS_REJECTED => BusinessDocument::STATUS_REJECTED,
                    BusinessApplication::STATUS_APPROVED => BusinessDocument::STATUS_VERIFIED,
                    default                              => BusinessDocument::STATUS_PENDING,
                };
                $this->attachDemoDocuments($application, $user, null, $docStatus);
            }
        }
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

        if (isset($owners[2])) {
            AccountDeletionRequest::updateOrCreate(
                ['user_id' => $owners[2]->id, 'scope' => AccountDeletionRequest::SCOPE_BOTH],
                [
                    'tenant_id'    => $owners[2]->tenant_id,
                    'status'       => AccountDeletionRequest::STATUS_PENDING,
                    'reason'       => 'Closing down the business — moving abroad.',
                    'review_notes' => null,
                    'reviewed_by'  => null,
                    'reviewed_at'  => null,
                ],
            );
        }

        if (isset($owners[4])) {
            AccountDeletionRequest::updateOrCreate(
                ['user_id' => $owners[4]->id, 'scope' => AccountDeletionRequest::SCOPE_BUSINESS_ONLY],
                [
                    'tenant_id'    => $owners[4]->tenant_id,
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

    // ═════════════════════════════════════════════════════════
    //  Document renewals
    // ═════════════════════════════════════════════════════════

    protected function seedDocumentRenewals(): void
    {
        $superAdminId = User::query()->where('email', 'superadmin@gmail.com')->value('id');

        $plan = [
            ['status' => BusinessDocument::STATUS_PENDING,  'days_ago' => 2,  'reviewed' => null],
            ['status' => BusinessDocument::STATUS_PENDING,  'days_ago' => 5,  'reviewed' => null],
            ['status' => BusinessDocument::STATUS_VERIFIED, 'days_ago' => 12, 'reviewed' => 6],
            ['status' => BusinessDocument::STATUS_REJECTED, 'days_ago' => 20, 'reviewed' => 14],
            ['status' => BusinessDocument::STATUS_PENDING,  'days_ago' => 8,  'reviewed' => null],
        ];

        Tenant::query()->orderBy('id', 'asc')->get()->each(
            function (Tenant $tenant, int $index) use ($plan, $superAdminId): void {
                $step         = $plan[$index % count($plan)];
                $status       = $step['status'];
                $daysAgo      = $step['days_ago'];
                $reviewedDays = $step['reviewed'];

                $original = BusinessDocument::query()
                    ->whereHas('application', fn ($q) => $q->where('approved_tenant_id', $tenant->id))
                    ->where('document_type', BusinessDocument::TYPE_MAYORS_PERMIT)
                    ->where('is_renewal', false)
                    ->first();

                if (! $original) return;

                $alreadyHasRenewal = BusinessDocument::query()
                    ->where('business_application_id', $original->business_application_id)
                    ->where('document_type', $original->document_type)
                    ->where('is_renewal', true)
                    ->exists();

                if ($alreadyHasRenewal) return;

                $renewalStoredPath      = "kyb-documents/renewals/{$tenant->id}-mayors-permit-{$original->id}.jpg";
                $renewalWatermarkedPath = "kyb-documents/watermarked/renewals/{$tenant->id}-mayors-permit-{$original->id}.jpg";

                $disk = Storage::disk('public');

                if (! $disk->exists($renewalStoredPath) && $disk->exists($original->stored_path)) {
                    $disk->copy($original->stored_path, $renewalStoredPath);
                    $this->compressStoredFile(
                        $disk->path($renewalStoredPath),
                        $renewalStoredPath,
                        'renewal-mayors-permit',
                        force: true,
                    );
                }

                if (
                    $original->watermarked_path
                    && ! $disk->exists($renewalWatermarkedPath)
                    && $disk->exists($original->watermarked_path)
                ) {
                    $disk->copy($original->watermarked_path, $renewalWatermarkedPath);
                }

                if (! $disk->exists($renewalStoredPath)) return;

                $submittedAt = now()
                    ->subDays($daysAgo)
                    ->subHours($tenant->id % 8)
                    ->subMinutes(($tenant->id * 7) % 60);

                $reviewedAt = $reviewedDays !== null
                    ? now()
                        ->subDays($reviewedDays)
                        ->subHours($tenant->id % 5)
                        ->subMinutes(($tenant->id * 11) % 60)
                    : null;

                $expiresAt = now()
                    ->addYear()
                    ->subDays($index * 3);

                $document = new BusinessDocument();
                $document->forceFill([
                    'business_application_id' => $original->business_application_id,
                    'user_id'                 => $original->user_id,
                    'document_type'           => BusinessDocument::TYPE_MAYORS_PERMIT,
                    'is_renewal'              => true,
                    'original_filename'       => 'Mayors-Permit-Renewal-' . $submittedAt->year . '.jpg',
                    'stored_path'             => $renewalStoredPath,
                    'watermarked_path'        => $renewalWatermarkedPath,
                    'mime_type'               => 'image/jpeg',
                    'file_size'               => $disk->size($renewalStoredPath),
                    'file_hash'               => hash('sha256', $disk->get($renewalStoredPath)),
                    'document_number'         => null,
                    'issued_at'               => $submittedAt->copy()->subDays(1),
                    'expires_at'              => $expiresAt,
                    'verification_status'     => $status,
                    'verification_notes'      => $status === BusinessDocument::STATUS_REJECTED
                        ? 'Permit scan is unclear around the official seal. Please re-upload with better lighting.'
                        : null,
                    'watermarked_at'          => $submittedAt->copy()->addMinutes(2),
                    'renewal_reviewed_by'     => $reviewedAt ? $superAdminId : null,
                    'renewal_reviewed_at'     => $reviewedAt,
                    'created_at'              => $submittedAt,
                    'updated_at'              => $reviewedAt ?? $submittedAt,
                ]);
                $document->save();
            },
        );
    }

    // ═════════════════════════════════════════════════════════
    //  User notifications
    // ═════════════════════════════════════════════════════════

    protected function seedUserNotifications(): void
    {
        $insert = function (
            User $user,
            string $scope,
            array $tpl,
            Carbon $createdAt,
        ): void {
            $notification = new UserNotification();
            $notification->forceFill([
                'user_id'    => $user->id,
                'scope'      => $scope,
                'type'       => $tpl['type'],
                'title'      => $tpl['title'],
                'message'    => $tpl['message'],
                'url'        => null,
                'icon'       => $tpl['icon'],
                'color'      => $tpl['color'],
                'read_at'    => $tpl['read'] ? $createdAt->copy()->addHours(2) : null,
                'created_at' => $createdAt,
                'updated_at' => $tpl['read'] ? $createdAt->copy()->addHours(2) : $createdAt,
            ]);
            $notification->save();
        };

        $touristTemplates = [
            ['type' => 'booking', 'title' => 'Booking received', 'message' => 'Your booking has been received. Complete payment within 30 minutes.', 'icon' => 'clock', 'color' => 'amber', 'read' => false, 'minutes_ago' => 12],
            ['type' => 'booking', 'title' => 'Booking confirmed', 'message' => 'Your booking is confirmed. See you soon!', 'icon' => 'check-circle', 'color' => 'emerald', 'read' => false, 'minutes_ago' => 60 * 26],
            ['type' => 'kyb_approved', 'title' => 'Business application approved', 'message' => 'Your business application has been approved.', 'icon' => 'check-circle', 'color' => 'emerald', 'read' => true, 'minutes_ago' => 60 * 24 * 5],
        ];

        User::query()
            ->role('tourist')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))
            ->orderBy('id', 'asc')
            ->take(20)
            ->get()
            ->each(function (User $user, int $i) use ($touristTemplates, $insert): void {
                foreach ($touristTemplates as $tpl) {
                    $when = now()->subMinutes($tpl['minutes_ago'] + ($i * 17));
                    $insert($user, UserNotification::SCOPE_TOURIST, $tpl, $when);
                }
            });

        $businessTemplates = [
            ['type' => 'booking', 'title' => 'New booking request', 'message' => 'A new booking was placed for your property.', 'icon' => 'inbox', 'color' => 'blue', 'read' => false, 'minutes_ago' => 8],
            ['type' => 'renewal_approved', 'title' => "Mayor's Permit renewal approved", 'message' => 'Your renewal was approved. Your permit on file is up to date.', 'icon' => 'check-circle', 'color' => 'emerald', 'read' => false, 'minutes_ago' => 60 * 22],
            ['type' => 'renewal_rejected', 'title' => "Mayor's Permit renewal needs attention", 'message' => 'Your renewal was rejected. Reason: The permit scan is unclear around the official seal.', 'icon' => 'x-circle', 'color' => 'rose', 'read' => false, 'minutes_ago' => 60 * 24 * 3],
            ['type' => 'permit_expiry_60d_' . now()->year, 'title' => 'Permit expires soon', 'message' => "Your Mayor's Permit expires in 60 days. Consider starting the renewal early.", 'icon' => 'clock', 'color' => 'amber', 'read' => true, 'minutes_ago' => 60 * 24 * 9],
        ];

        User::query()
            ->role('admin')
            ->whereNotNull('tenant_id', 'and')
            ->orderBy('id', 'asc')
            ->get()
            ->each(function (User $user, int $i) use ($businessTemplates, $insert): void {
                $subset = $i % 2 === 0 ? $businessTemplates : array_slice($businessTemplates, 0, 2);
                foreach ($subset as $tpl) {
                    $when = now()->subMinutes($tpl['minutes_ago'] + ($i * 23));
                    $insert($user, UserNotification::SCOPE_BUSINESS, $tpl, $when);
                }
            });

        $platformTemplates = [
            ['type' => 'business_application', 'title' => 'New business application', 'message' => 'A new business application was submitted for review.', 'icon' => 'inbox', 'color' => 'blue', 'read' => false, 'minutes_ago' => 4],
            ['type' => 'deletion_request', 'title' => 'New account deletion request', 'message' => 'A business owner requested account deletion. Review the impact before approving.', 'icon' => 'alert', 'color' => 'rose', 'read' => false, 'minutes_ago' => 60 * 5],
            ['type' => 'renewal', 'title' => "Permit renewal awaiting review", 'message' => "A tenant submitted a Mayor's Permit renewal for review.", 'icon' => 'clock', 'color' => 'amber', 'read' => false, 'minutes_ago' => 60 * 24 * 2],
            ['type' => 'tenant', 'title' => 'New tenant verified', 'message' => 'A new tenant was verified and is now publicly listed.', 'icon' => 'check-circle', 'color' => 'emerald', 'read' => true, 'minutes_ago' => 60 * 24 * 6],
        ];

        User::query()
            ->role('super-admin')
            ->orderBy('id', 'asc')
            ->get()
            ->each(function (User $user, int $i) use ($platformTemplates, $insert): void {
                foreach ($platformTemplates as $tpl) {
                    $when = now()->subMinutes($tpl['minutes_ago'] + ($i * 13));
                    $insert($user, UserNotification::SCOPE_PLATFORM, $tpl, $when);
                }
            });
    }
}