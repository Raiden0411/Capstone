{{-- resources/views/public/pages/⚡privacy-policy.blade.php --}}
<?php

use App\Models\SiteSetting;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts.app')]
#[Title('Privacy Policy')]
class extends Component
{
    /**
     * Branding source of truth — same read the public header uses.
     * Resolves the "Capstone" / LEGAL_CONTROLLER_NAME default away in
     * favour of whatever the superadmin has set for the platform name.
     */
    #[Computed]
    public function siteName(): string
    {
        return (string) SiteSetting::getValue(
            'site_name',
            config('app.name', 'Victorias City Tourism'),
        );
    }

    /**
     * Data-controller block. Name comes from SiteSetting (consistent with
     * the header). Contact + DPO come from config/legal.php, which is
     * populated from .env via LEGAL_CONTROLLER_EMAIL / LEGAL_DPO_EMAIL.
     *
     * @return array{name: string, email: string, country: string, dpo_email: ?string}
     */
    #[Computed]
    public function controller(): array
    {
        $c = config('legal.data_controller');

        $email = (is_array($c) && ! empty($c['email']))
            ? (string) $c['email']
            : 'tourism.management.ph@gmail.com';

        $dpoEmail = (is_array($c) && ! empty($c['dpo_email']))
            ? (string) $c['dpo_email']
            : null;

        return [
            'name'      => $this->siteName,
            'email'     => $email,
            'country'   => 'Philippines',
            'dpo_email' => $dpoEmail,
        ];
    }

    #[Computed]
    public function version(): string
    {
        return (string) config('legal.privacy_policy_version', '1.0');
    }

    #[Computed]
    public function lastUpdated(): string
    {
        $v = config('legal.privacy_policy_updated_at');

        return is_string($v) && $v !== ''
            ? $v
            : now()->format('F j, Y');
    }

    /**
     * @return array<int, array{id: string, num: string, label: string}>
     */
    #[Computed]
    public function sections(): array
    {
        return [
            ['id' => 'data-we-collect', 'num' => '01', 'label' => 'Data We Collect'],
            ['id' => 'legal-basis',     'num' => '02', 'label' => 'Legal Basis for Processing'],
            ['id' => 'cookies',         'num' => '03', 'label' => 'Cookies & Local Storage'],
            ['id' => 'third-parties',   'num' => '04', 'label' => 'Third-Party Services'],
            ['id' => 'storage',         'num' => '05', 'label' => 'Where Your Data Is Stored'],
            ['id' => 'retention',       'num' => '06', 'label' => 'Data Retention'],
            ['id' => 'rights',          'num' => '07', 'label' => 'Your Rights Under the DPA'],
            ['id' => 'children',        'num' => '08', 'label' => "Children's Privacy"],
            ['id' => 'security',        'num' => '09', 'label' => 'Data Security'],
            ['id' => 'changes',         'num' => '10', 'label' => 'Policy Changes'],
            ['id' => 'contact',         'num' => '11', 'label' => 'Contact Us'],
        ];
    }
};
?>

@push('styles')
    @once
        <style>
            @media print {
                .pp-no-print { display: none !important; }
                body {
                    background: #ffffff !important;
                    color: #000000 !important;
                }
                .pp-content {
                    max-width: 100% !important;
                    padding: 0 !important;
                }
                .pp-section {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
                .pp-section h2 {
                    page-break-after: avoid;
                }
                .pp-content a {
                    color: #000000 !important;
                    text-decoration: underline !important;
                }
            }
        </style>
    @endonce
@endpush

@php
    $__eyebrow = 'inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400';
    $__rule    = 'h-px w-4 bg-amber-500';
@endphp

<div class="bg-white dark:bg-gray-900 min-h-screen" x-data="revealOnScroll">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16 pp-content">

        <a href="{{ route('home') }}" wire:navigate
           class="pp-no-print relative inline-flex items-center gap-1.5 -mx-1 px-1 py-1 text-sm font-medium
                  text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                  transition-colors mb-10 rounded
                  before:absolute before:content-[''] before:-inset-2 before:rounded
                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Home
        </a>

        <header data-reveal class="pb-8 border-b border-gray-200 dark:border-gray-800">
            <p class="{{ $__eyebrow }} mb-3">
                <span class="{{ $__rule }}" aria-hidden="true"></span>
                Legal
            </p>

            <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-white mb-3">
                Privacy Policy
            </h1>

            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed max-w-2xl">
                How {{ $this->controller['name'] }} collects, uses, and protects your personal data — in plain terms.
            </p>

            <dl class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                <div class="inline-flex items-baseline gap-1.5">
                    <dt>Version</dt>
                    <dd class="font-mono font-medium text-gray-900 dark:text-white">{{ $this->version }}</dd>
                </div>
                <div class="inline-flex items-baseline gap-1.5">
                    <dt>Last updated</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $this->lastUpdated }}</dd>
                </div>
            </dl>
        </header>

        <nav data-reveal
             style="--reveal-delay: 80ms"
             aria-label="Sections in this policy"
             class="pp-no-print mt-8 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-5 sm:p-6">
            <p class="{{ $__eyebrow }} mb-4">
                <span class="{{ $__rule }}" aria-hidden="true"></span>
                In this document
            </p>
            <ol class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5">
                @foreach($this->sections as $section)
                    <li>
                        <a href="#{{ $section['id'] }}"
                           class="group flex items-baseline gap-3 py-1.5 rounded
                                  text-sm text-gray-700 dark:text-gray-300
                                  hover:text-primary-600 dark:hover:text-primary-400
                                  transition-colors
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <span class="font-mono text-[11px] font-bold tabular-nums text-primary-600 dark:text-primary-400 shrink-0 w-6">
                                {{ $section['num'] }}
                            </span>
                            <span class="border-b border-transparent group-hover:border-current transition-colors">
                                {{ $section['label'] }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </nav>

        <article class="mt-12 space-y-12 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">

            <p>
                {{ $this->controller['name'] }} operates a multi-tenant tourism booking
                marketplace serving Victorias City, Negros Occidental, Philippines. The
                platform connects tourists with local businesses — accommodations, tour
                operators, and activity providers — and processes the bookings, payments,
                and business-verification records that make those transactions possible.
            </p>

            <p>
                This Privacy Policy explains how we collect, use, disclose, and safeguard
                your personal data in accordance with the <strong class="text-gray-900 dark:text-white">Data Privacy Act of 2012
                (Republic Act No. 10173)</strong>, its Implementing Rules and Regulations,
                and the issuances of the National Privacy Commission (NPC).
            </p>

            <section data-reveal class="pp-section rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-5 sm:p-6">
                <p class="{{ $__eyebrow }} mb-4">
                    <span class="{{ $__rule }}" aria-hidden="true"></span>
                    Personal Information Controller
                </p>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-baseline gap-3">
                        <dt class="text-gray-500 dark:text-gray-400 w-24 shrink-0">Entity</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ $this->controller['name'] }}</dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="text-gray-500 dark:text-gray-400 w-24 shrink-0">Country</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $this->controller['country'] }}</dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="text-gray-500 dark:text-gray-400 w-24 shrink-0">Contact</dt>
                        <dd>
                            <a href="mailto:{{ $this->controller['email'] }}"
                               class="text-primary-600 dark:text-primary-400 hover:underline underline-offset-2
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                {{ $this->controller['email'] }}
                            </a>
                        </dd>
                    </div>
                    @if($this->controller['dpo_email'])
                        <div class="flex items-baseline gap-3">
                            <dt class="text-gray-500 dark:text-gray-400 w-24 shrink-0">DPO</dt>
                            <dd>
                                <a href="mailto:{{ $this->controller['dpo_email'] }}"
                                   class="text-primary-600 dark:text-primary-400 hover:underline underline-offset-2
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                    {{ $this->controller['dpo_email'] }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section id="data-we-collect" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">01</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Data We Collect</h2>
                </div>
                <p class="mb-4">
                    What we collect depends on how you use the platform — as a tourist,
                    a business owner or employee, or a visitor browsing the public pages.
                </p>
                <ul class="space-y-2.5">
                    @foreach([
                        ['Account Information', 'Full name, email address, mobile number, and a bcrypt-hashed password. Required to create and sign into an account.'],
                        ['Business Identity', 'For business owners: registered business name, business type (DTI, SEC, or CDA), registration number, and TIN. Required to list a business on the platform.'],
                        ['Owner Identification', 'For business owners: full legal name, government-issued ID type and number, and date of birth. Required for regulatory verification.'],
                        ['Business Address & Coordinates', 'Street address, barangay, city, province, and map coordinates. Used to display the business on the public map and to compute distances for tourists.'],
                        ['Booking Details', 'Check-in and check-out dates and time, selected properties, add-on services, quantities, and total amount. Stored against your account for the duration of the booking.'],
                        ['Guest Contact Details', 'Name, mobile number, and — when provided — email address and street address. Required for businesses to contact guests about walk-in bookings.'],
                        ['Payment Records', 'Payment method (GCash, Maya, or Card), amount, currency, and a PayMongo session reference. We never receive or store your card number, CVV, or full billing details.'],
                        ['Uploaded Files', 'Profile photos, business logos, cover photos, gallery images, event images, property images, and Know-Your-Business (KYB) documents. All images are auto-compressed and EXIF metadata is stripped. KYB documents are stored watermarked.'],
                        ['Device & Technical Data', 'IP address, browser user agent, and server-side session records. Collected automatically by the web server for security, rate limiting, and abuse prevention.'],
                        ['Communications', 'Transactional emails (booking confirmations, password resets, business-application updates) and in-app notifications. Server logs are retained for debugging and rotated.'],
                    ] as [$label, $body])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span><strong class="text-gray-900 dark:text-white">{{ $label }}:</strong> {{ $body }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    We only collect data that is necessary for the purposes described in this
                    policy. We follow the principle of proportionality under DPA Section 11.
                </p>
            </section>

            <section id="legal-basis" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">02</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Legal Basis for Processing</h2>
                </div>
                <p class="mb-4">
                    We process personal data on the following grounds under the Data Privacy
                    Act of 2012:
                </p>
                <ul class="space-y-2.5">
                    @foreach([
                        ['Consent',                   'You create an account, accept this policy at registration, and — for business owners — submit your KYB application.',        'DPA Sec. 12(a)'],
                        ['Contractual Necessity',     'Processing required to deliver a booking, a payment receipt, a confirmation email, or a service you have requested.',              'DPA Sec. 12(b)'],
                        ['Legal Obligation',          'Verification of business records, retention of booking and payment records for tax and accounting purposes.',                              'DPA Sec. 12(c)'],
                        ['Legitimate Interests',      'Fraud prevention, platform security, rate limiting, abuse detection, and service improvement — balanced against your rights and freedoms.',  'DPA Sec. 12(f)'],
                        ['Sensitive Personal Data',   'Government-issued ID numbers and dates of birth are processed only as required for KYB verification, under the safeguards in DPA Sec. 13.',  'DPA Sec. 13'],
                    ] as [$label, $body, $ref])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span>
                                <strong class="text-gray-900 dark:text-white">{{ $label }}</strong>
                                <span class="text-gray-500 dark:text-gray-400"> — {{ $body }}</span>
                                <span class="ml-1 font-mono text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap">({{ $ref }})</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    Where we rely on consent, you have the right to withdraw it at any time.
                    Withdrawal does not affect the lawfulness of processing carried out before
                    the withdrawal, and it does not remove records we are legally required to
                    retain.
                </p>
            </section>

            <section id="cookies" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">03</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Cookies and Local Storage</h2>
                </div>
                <p class="mb-4">
                    We do not use analytics cookies, advertising cookies, third-party tracking
                    pixels, or any behavioural-profiling technology. What the platform stores
                    on your device is minimal and strictly functional.
                </p>

                <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white mt-6 mb-3">
                    Cookies we set
                </h3>
                <ul class="space-y-2.5">
                    @foreach([
                        ['Session cookie', 'A single encrypted cookie identifies your logged-in session. It is HTTP-only, SameSite=Lax, and expired on logout or after 120 minutes of inactivity.'],
                        ['CSRF token', 'A companion cookie holds the cross-site request forgery token used to protect form submissions. Rotated on every session regeneration.'],
                    ] as [$label, $body])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span><strong class="text-gray-900 dark:text-white">{{ $label }}:</strong> {{ $body }}</span>
                        </li>
                    @endforeach
                </ul>

                <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white mt-6 mb-3">
                    Local storage preferences
                </h3>
                <p class="mb-3">
                    Two preference values are stored in your browser's <code class="font-mono text-[12px]">localStorage</code>
                    and are never transmitted to our servers:
                </p>
                <ul class="space-y-2.5">
                    @foreach([
                        ['Theme',   'Whether you have selected light or dark mode. Key: hs_theme.'],
                        ['Sidebar', 'Whether the business or platform sidebar is collapsed. Key: tenant_sidebar_minified or sidebar_minified, depending on your role.'],
                    ] as [$label, $body])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span><strong class="text-gray-900 dark:text-white">{{ $label }}:</strong> {{ $body }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-4">
                    You can clear both cookies and local storage at any time through your
                    browser settings. Doing so signs you out and resets your preferences,
                    but does not affect your account data.
                </p>
            </section>

            <section id="third-parties" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">04</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Third-Party Services</h2>
                </div>
                <p class="mb-4">
                    The platform relies on a small set of external processors. Each is used
                    only for the specific function described below, and each is bound by
                    their own terms and privacy commitments.
                </p>
                <ul class="space-y-3">
                    @foreach([
                        ['PayMongo',              'Philippines',   'Payment processing. Card, GCash, and Maya payments are entered on PayMongo\'s hosted checkout page. Our servers receive only the payment status and session reference — never the card number or CVV.'],
                        ['Google Fonts',          'USA',           'Delivers the Inter typeface served from fonts.googleapis.com. Your browser fetches the font files directly from Google\'s CDN.'],
                        ['OpenStreetMap',         'United Kingdom','Base map tiles and reverse-geocoding. Geocoding requests are made server-side with an identifying User-Agent; only coordinates are transmitted.'],
                        ['CARTO',                 'USA',           'Vector map tile delivery for the default and dark map styles.'],
                        ['Esri (ArcGIS)',         'USA',           'Satellite imagery tiles for the map\'s satellite view.'],
                        ['OSRM',                  'Public demo',   'Driving route calculation between two coordinates. Only the origin and destination of a requested route are transmitted.'],
                        ['Google (Gmail SMTP)',   'USA',           'Transactional email delivery — booking confirmations, password resets, and business-application status updates.'],
                    ] as [$name, $country, $purpose])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span>
                                <strong class="text-gray-900 dark:text-white">{{ $name }}</strong>
                                <span class="ml-1 font-mono text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap">({{ $country }})</span>
                                <span class="text-gray-500 dark:text-gray-400"> — {{ $purpose }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    We do not sell personal data, and we do not share it with any party for
                    advertising or unrelated marketing purposes.
                </p>
            </section>

            <section id="storage" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">05</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Where Your Data Is Stored</h2>
                </div>
                <p class="mb-4">
                    The platform's primary application server and database are hosted in the
                    Philippines. Uploaded files — profile photos, business logos, gallery
                    images, and KYB documents — are stored on the same server's local
                    filesystem and are not synced to any third-party storage provider.
                </p>
                <p class="mb-4">
                    Some processors listed in Section 04 operate outside the Philippines
                    (Google, CARTO, Esri, PayMongo's parent infrastructure). When personal
                    data is transferred to or accessed from outside the Philippines, we
                    comply with DPA Section 21 and NPC Circular 16-04 by ensuring:
                </p>
                <ul class="space-y-2.5">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                        <span>The receiving party is bound by a written agreement to apply protections at least equivalent to the DPA.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                        <span>Only the minimum data required for the specific function is transmitted.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                        <span>Requests are made server-side wherever the service allows it, so your IP address is not exposed directly to the processor.</span>
                    </li>
                </ul>
                <p class="mt-4">
                    You may request details of the specific safeguards in place for any
                    processor by contacting us at the address in Section 11.
                </p>
            </section>

            <section id="retention" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">06</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Data Retention</h2>
                </div>
                <p class="mb-4">
                    We retain personal data only for as long as necessary to fulfil the
                    purpose for which it was collected, or as required by law.
                </p>
                <ul class="space-y-2.5">
                    @foreach([
                        ['Active accounts',      'Retained for as long as the account remains open.'],
                        ['Booking records',      'Retained per the Bureau of Internal Revenue\'s record-keeping requirements (generally up to ten years from the end of the taxable year).'],
                        ['Payment records',      'Retained on the same schedule as booking records. Card numbers and CVVs are never stored.'],
                        ['KYB documents',        'Retained for as long as the business remains registered on the platform. If the business is deleted, the associated watermarked documents are removed within 30 days.'],
                        ['Uploaded images',      'Retained until you delete them or close your account. Replaced images are deleted from storage within 30 days.'],
                        ['Sessions',             'Purged after 120 minutes of inactivity, and swept by a nightly cleanup job that removes entries older than 30 days.'],
                        ['In-app notifications', 'Retained until you delete them or close your account.'],
                        ['Server logs',          'Rotated daily. Slow-query logs are aggregated and rotated every five minutes; the raw entries are not retained beyond the aggregation cycle.'],
                    ] as [$label, $body])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span><strong class="text-gray-900 dark:text-white">{{ $label }}:</strong> {{ $body }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    When personal data is no longer needed, we securely delete or anonymize
                    it. Account deletion is processed through our standard deletion workflow
                    and requires superadmin review before the record is removed.
                </p>
            </section>

            <section id="rights" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">07</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Your Rights Under the DPA</h2>
                </div>
                <p class="mb-4">
                    As a data subject under the Data Privacy Act of 2012, you have the
                    following rights:
                </p>
                <ul class="space-y-3">
                    @foreach([
                        ['Right to be Informed',           'Sec. 16(a)', 'Know whether your personal data is being processed, and if so, what data, why, and to whom it may be disclosed.'],
                        ['Right to Object',                'Sec. 16(b)', 'Object to the processing of your personal data, including processing for direct marketing.'],
                        ['Right to Access',                'Sec. 16(c)', 'Reasonable access to the personal data we hold about you, including its source and recipients.'],
                        ['Right to Rectification',         'Sec. 16(d)', 'Dispute inaccurate or erroneous personal data and have it corrected.'],
                        ['Right to Erasure or Blocking',   'Sec. 16(e)', 'Suspend, withdraw, or order the blocking, removal, or destruction of your personal data from our systems, subject to legal retention requirements.'],
                        ['Right to Damages',               'Sec. 16(f)', 'Be indemnified for any damages sustained due to inaccurate, incomplete, outdated, false, unlawfully obtained, or unauthorized use of your personal data.'],
                        ['Right to Data Portability',      'Sec. 16(g)', 'Obtain a copy of your personal data in an electronic or structured format that is commonly used and allows further use.'],
                        ['Right to File a Complaint',      'Sec. 16(h)', 'File a complaint with the National Privacy Commission.'],
                    ] as [$label, $ref, $body])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span>
                                <strong class="text-gray-900 dark:text-white">{{ $label }}</strong>
                                <span class="ml-1 font-mono text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap">({{ $ref }})</span>
                                <span class="text-gray-700 dark:text-gray-300"> — {{ $body }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    To exercise any of these rights, contact us at
                    <a href="mailto:{{ $this->controller['email'] }}"
                       class="text-primary-600 dark:text-primary-400 underline underline-offset-2 hover:text-primary-700 dark:hover:text-primary-300
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        {{ $this->controller['email'] }}
                    </a>.
                    We will respond within fifteen (15) working days as required by the NPC.
                </p>
            </section>

            <section id="children" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">08</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Children's Privacy</h2>
                </div>
                <p>
                    The platform is not intended for individuals under the age of eighteen
                    (18), the age of majority in the Philippines. We do not knowingly
                    collect personal data from minors. Under the DPA and its Implementing
                    Rules and Regulations, a minor's personal data may only be processed
                    with the consent of a parent or legal guardian. If we learn that we
                    have collected personal data from a minor without verified parental
                    consent, we will take steps to delete it promptly. If you believe we
                    have collected data from a minor, please contact us at the address in
                    Section 11.
                </p>
            </section>

            <section id="security" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">09</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Data Security</h2>
                </div>
                <p class="mb-4">
                    We implement the following technical and organizational measures to
                    protect personal data against unauthorized access, alteration,
                    disclosure, or destruction:
                </p>
                <ul class="space-y-2.5">
                    @foreach([
                        'Passwords are hashed with bcrypt at a cost factor of 12 — the plain-text password is never stored or logged.',
                        'Sessions are stored in an encrypted database table, so the session payload is unreadable at rest.',
                        'All HTTP traffic is served over TLS in production. HSTS is enforced on secure connections.',
                        'Every state-changing form submission is protected by a per-session CSRF token.',
                        'Authentication endpoints (login, registration, password reset) are rate-limited by IP address.',
                        'Uploaded KYB documents are watermarked with the platform name so that a leaked document is traceable to its source and cannot be quietly reused.',
                        'Uploaded images are auto-compressed and EXIF metadata — including GPS coordinates and device identifiers — is stripped during processing.',
                        'Response headers set X-Frame-Options, X-Content-Type-Options, Referrer-Policy, and Permissions-Policy. A Content-Security-Policy is emitted in report-only mode.',
                        'Database queries run through parameterized statements via Eloquent. Cross-tenant access is prevented by a global scope on every tenant-owned model.',
                    ] as $item)
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    In the event of a personal data breach that poses a risk to your rights
                    and freedoms, we will notify the National Privacy Commission and
                    affected data subjects within seventy-two (72) hours of discovery, as
                    required by NPC Circular 16-03.
                </p>
            </section>

            <section id="changes" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">10</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Changes to This Policy</h2>
                </div>
                <p>
                    We may update this Privacy Policy from time to time. Significant changes
                    will be announced on the platform or by email. The version number and
                    "Last updated" date at the top of this page indicate the current
                    revision. Registration records store the privacy-policy version you
                    accepted, so an update never silently replaces your original consent.
                </p>
                <p class="mt-4">
                    We encourage you to review this Privacy Policy periodically to stay
                    informed about how we protect your data.
                </p>
            </section>

            <section id="contact" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-primary-600 dark:text-primary-400">11</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Contact Us</h2>
                </div>
                <p class="mb-4">
                    If you have questions about this Privacy Policy, or wish to exercise any
                    of your data-protection rights, please contact us:
                </p>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-5">
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-baseline gap-3">
                            <dt class="text-gray-500 dark:text-gray-400 w-24 shrink-0">Entity</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ $this->controller['name'] }}</dd>
                        </div>
                        <div class="flex items-baseline gap-3">
                            <dt class="text-gray-500 dark:text-gray-400 w-24 shrink-0">Email</dt>
                            <dd>
                                <a href="mailto:{{ $this->controller['email'] }}"
                                   class="text-primary-600 dark:text-primary-400 hover:underline underline-offset-2
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                    {{ $this->controller['email'] }}
                                </a>
                            </dd>
                        </div>
                        @if($this->controller['dpo_email'])
                            <div class="flex items-baseline gap-3">
                                <dt class="text-gray-500 dark:text-gray-400 w-24 shrink-0">DPO</dt>
                                <dd>
                                    <a href="mailto:{{ $this->controller['dpo_email'] }}"
                                       class="text-primary-600 dark:text-primary-400 hover:underline underline-offset-2
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                        {{ $this->controller['dpo_email'] }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
                <p class="mt-4">
                    You also have the right to lodge a complaint with the
                    <a href="https://privacy.gov.ph" target="_blank" rel="noopener noreferrer"
                       class="text-primary-600 dark:text-primary-400 underline underline-offset-2 hover:text-primary-700 dark:hover:text-primary-300
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        National Privacy Commission
                    </a>.
                </p>
            </section>

        </article>

        <div class="pp-no-print mt-16 pt-8 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row gap-3">
            <button type="button"
                    onclick="window.print()"
                    class="btn-secondary min-h-[44px] w-full sm:w-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print this policy
            </button>

            <a href="{{ route('home') }}" wire:navigate
               class="btn-primary min-h-[44px] w-full sm:w-auto">
                Back to Home
            </a>
        </div>
    </div>
</div>