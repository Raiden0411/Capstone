{{-- resources/views/public/pages/⚡privacy-policy.blade.php --}}
<?php

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
     * Data controller block. Falls back gracefully if `legal.data_controller`
     * is missing from config — the page still renders with the app name
     * and a placeholder contact, rather than a PHP error.
     *
     * @return array{name: string, email: string, country?: string}
     */
    #[Computed]
    public function controller(): array
    {
        $c = config('legal.data_controller');

        if (!is_array($c) || empty($c['name']) || empty($c['email'])) {
            return [
                'name'    => (string) config('app.name', 'Victorias City Tourism'),
                'email'   => 'privacy@victoriascity.gov.ph',
                'country' => 'Philippines',
            ];
        }

        return [
            'name'    => (string) $c['name'],
            'email'   => (string) $c['email'],
            'country' => (string) ($c['country'] ?? 'Philippines'),
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
     * Table of contents. Each entry's `id` must match the `id` attribute
     * on the corresponding <section> in the view. Kept as data so the
     * TOC and any future search/filter feature share one source of truth.
     *
     * @return array<int, array{id: string, num: string, label: string}>
     */
    #[Computed]
    public function sections(): array
    {
        return [
            ['id' => 'data-we-collect',  'num' => '01', 'label' => 'Data We Collect'],
            ['id' => 'legal-basis',      'num' => '02', 'label' => 'Legal Basis for Processing'],
            ['id' => 'cookies',          'num' => '03', 'label' => 'Cookies & Tracking'],
            ['id' => 'third-parties',    'num' => '04', 'label' => 'Third-Party Services'],
            ['id' => 'transfers',        'num' => '05', 'label' => 'International Transfers'],
            ['id' => 'retention',        'num' => '06', 'label' => 'Data Retention'],
            ['id' => 'rights',           'num' => '07', 'label' => 'Your GDPR Rights'],
            ['id' => 'children',         'num' => '08', 'label' => "Children's Privacy"],
            ['id' => 'security',         'num' => '09', 'label' => 'Data Security'],
            ['id' => 'changes',          'num' => '10', 'label' => 'Policy Changes'],
            ['id' => 'contact',          'num' => '11', 'label' => 'Contact Us'],
        ];
    }
};
?>

@push('styles')
    @once
        <style>
            /* Print: strip chrome, force light, keep content legible. */
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

<div class="bg-white dark:bg-gray-900 min-h-screen" x-data="revealOnScroll">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16 pp-content">

        {{-- ─── Back link ─── --}}
        <a href="{{ route('home') }}" wire:navigate
           class="pp-no-print inline-flex items-center gap-1.5 text-sm font-medium
                  text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                  transition-colors mb-10 -mx-1 px-1 py-1 rounded
                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Home
        </a>

        {{-- ─── Header ─── --}}
        <header data-reveal class="pb-8 border-b border-gray-200 dark:border-gray-800">
            <p class="mb-3 inline-flex items-center gap-2
                      text-xs font-bold uppercase tracking-[0.2em]
                      text-amber-600 dark:text-amber-400">
                <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
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

        {{-- ─── Table of contents ─── --}}
        <nav data-reveal
             style="--reveal-delay: 80ms"
             aria-label="Sections in this policy"
             class="pp-no-print mt-8 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-5 sm:p-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-4">
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
                            <span class="font-mono text-[11px] font-bold tabular-nums text-amber-600 dark:text-amber-400 shrink-0 w-6">
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

        {{-- ─── Body ─── --}}
        <article class="mt-12 space-y-12 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">

            {{-- Intro ── --}}
            <p>
                {{ $this->controller['name'] }} ("we", "us", or "our") operates
                <a href="{{ config('app.url') }}"
                   class="text-primary-600 dark:text-primary-400 underline underline-offset-2 hover:text-primary-700 dark:hover:text-primary-300
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    {{ config('app.url') }}
                </a>.
                This Privacy Policy explains how we collect, use, disclose, and safeguard your personal
                data in accordance with the General Data Protection Regulation (EU) 2016/679 ("GDPR")
                and applicable data protection laws.
            </p>

            {{-- ─── Data controller card ─── --}}
            <section data-reveal class="pp-section rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-5 sm:p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-4">
                    Data Controller
                </p>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-baseline gap-3">
                        <dt class="text-gray-500 dark:text-gray-400 w-20 shrink-0">Entity</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ $this->controller['name'] }}</dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="text-gray-500 dark:text-gray-400 w-20 shrink-0">Country</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $this->controller['country'] }}</dd>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <dt class="text-gray-500 dark:text-gray-400 w-20 shrink-0">Contact</dt>
                        <dd>
                            <a href="mailto:{{ $this->controller['email'] }}"
                               class="text-primary-600 dark:text-primary-400 hover:underline underline-offset-2
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                {{ $this->controller['email'] }}
                            </a>
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- ─── 01 — Data We Collect ─── --}}
            <section id="data-we-collect" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">01</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Data We Collect</h2>
                </div>
                <p class="mb-4">We collect the following categories of personal data:</p>
                <ul class="space-y-2.5">
                    @foreach([
                        ['Personal Identity',   'Full name, email, phone, mailing address'],
                        ['Account Credentials', 'Username, hashed password'],
                        ['Payment Information', 'Card number (via processor), billing address'],
                        ['Usage & Analytics',   'Page views, session duration, referral source'],
                        ['Cookies & Tracking',  'Session cookies, analytics cookies, ad pixels'],
                        ['Device & Technical',  'IP address, user agent, device type'],
                        ['Location Data',       'City, country, approximate coordinates'],
                        ['Communications',      'Email content, chat logs, form submissions'],
                    ] as [$label, $body])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span><strong class="text-gray-900 dark:text-white">{{ $label }}:</strong> {{ $body }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    We only collect data that is necessary for the purposes described in this policy.
                    We follow the principle of data minimization as required under GDPR Article 5(1)(c).
                </p>
            </section>

            {{-- ─── 02 — Legal Basis ─── --}}
            <section id="legal-basis" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">02</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Legal Basis for Processing</h2>
                </div>
                <p class="mb-4">We process your personal data based on the following legal grounds under the GDPR:</p>
                <ul class="space-y-2.5">
                    @foreach([
                        ['Consent',             'User actively opts in',                              'GDPR Art. 6(1)(a)'],
                        ['Contractual necessity','Needed to fulfill a service',                       'GDPR Art. 6(1)(b)'],
                        ['Legal obligation',    'Required by law',                                    'GDPR Art. 6(1)(c)'],
                        ['Legitimate interest','Business need, balanced against user rights',         'GDPR Art. 6(1)(f)'],
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
                    Where we rely on consent, you have the right to withdraw your consent at any time.
                    Withdrawal does not affect the lawfulness of processing carried out before the withdrawal.
                </p>
            </section>

            {{-- ─── 03 — Cookies ─── --}}
            <section id="cookies" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">03</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Cookies and Tracking Technologies</h2>
                </div>
                <p class="mb-4">
                    We use cookies and similar tracking technologies to operate and improve our services.
                    Cookies are small text files stored on your device by your browser.
                </p>

                <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white mt-6 mb-3">
                    Types of cookies we use
                </h3>
                <ul class="space-y-2.5">
                    @foreach([
                        ['Strictly Necessary', 'Required for the website to function. These cannot be disabled.'],
                        ['Analytics',           'Help us understand how visitors use our site. Data is aggregated and anonymized where possible.'],
                        ['Functional',          'Remember your preferences and settings to enhance your experience.'],
                        ['Marketing',           'Used to deliver relevant advertisements and track campaign performance. These are only set with your explicit consent.'],
                    ] as [$label, $body])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span><strong class="text-gray-900 dark:text-white">{{ $label }}:</strong> {{ $body }}</span>
                        </li>
                    @endforeach
                </ul>

                <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white mt-6 mb-3">
                    Managing cookies
                </h3>
                <p>
                    You can manage your cookie preferences through our cookie banner when you first visit
                    our site. You may also configure your browser to block or delete cookies, though this
                    may affect site functionality.
                </p>
            </section>

            {{-- ─── 04 — Third parties ─── --}}
            <section id="third-parties" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">04</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Third-Party Services</h2>
                </div>
                <p class="mb-4">
                    We may share your data with trusted third-party service providers who assist us in
                    operating our website, conducting our business, or serving our users. These providers
                    are contractually obligated to:
                </p>
                <ul class="space-y-2.5">
                    @foreach([
                        'Process data only on our instructions',
                        'Maintain appropriate security measures',
                        'Not use data for their own purposes',
                        'Delete or return data upon termination of the agreement',
                    ] as $item)
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    Examples of third-party services may include hosting providers, analytics platforms,
                    payment processors, and email delivery services. All third-party processors are
                    required to comply with GDPR and have signed Data Processing Agreements (DPAs) with us.
                </p>
            </section>

            {{-- ─── 05 — Transfers ─── --}}
            <section id="transfers" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">05</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">International Data Transfers</h2>
                </div>
                <p class="mb-4">
                    Some of our third-party service providers may be located outside the European Economic
                    Area (EEA). When we transfer personal data outside the EEA, we ensure appropriate
                    safeguards are in place, including:
                </p>
                <ul class="space-y-2.5">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                        <span><strong class="text-gray-900 dark:text-white">Standard Contractual Clauses (SCCs)</strong> approved by the European Commission</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                        <span><strong class="text-gray-900 dark:text-white">Adequacy decisions</strong> where the European Commission has determined that a country provides an adequate level of data protection</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                        <span>Other legally recognized transfer mechanisms under GDPR Chapter V</span>
                    </li>
                </ul>
                <p class="mt-4">
                    You may request details about the specific safeguards applied to your data transfers
                    by contacting us.
                </p>
            </section>

            {{-- ─── 06 — Retention ─── --}}
            <section id="retention" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">06</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Data Retention</h2>
                </div>
                <p class="mb-4">
                    We retain your personal data only for as long as necessary to fulfill the purposes
                    for which it was collected, including to satisfy any legal, accounting, or reporting
                    requirements.
                </p>
                <p class="mb-4">To determine the appropriate retention period, we consider:</p>
                <ul class="space-y-2.5">
                    @foreach([
                        'The amount, nature, and sensitivity of the data',
                        'The risk of harm from unauthorized use or disclosure',
                        'The purposes for which we process the data',
                        'Whether we can achieve those purposes through other means',
                        'Applicable legal, regulatory, tax, accounting, or other requirements',
                    ] as $item)
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    When personal data is no longer needed, we securely delete or anonymize it.
                </p>
            </section>

            {{-- ─── 07 — Rights ─── --}}
            <section id="rights" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">07</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Your Rights Under GDPR</h2>
                </div>
                <p class="mb-4">As a data subject, you have the following rights under the GDPR:</p>
                <ul class="space-y-3">
                    @foreach([
                        ['Right of Access',                 'Art. 15', 'Request a copy of your personal data we hold.'],
                        ['Right to Rectification',          'Art. 16', 'Request correction of inaccurate or incomplete data.'],
                        ['Right to Erasure',                'Art. 17', 'Request deletion of your personal data (the "right to be forgotten").'],
                        ['Right to Restrict Processing',    'Art. 18', 'Request that we limit how we use your data.'],
                        ['Right to Data Portability',       'Art. 20', 'Receive your data in a structured, machine-readable format.'],
                        ['Right to Object',                 'Art. 21', 'Object to processing based on legitimate interest or direct marketing.'],
                        ['Right to Withdraw Consent',       'Art. 7',  'Withdraw consent at any time where processing is based on consent.'],
                        ['Right to Lodge a Complaint',      null,     'File a complaint with your local supervisory authority.'],
                    ] as [$label, $ref, $body])
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span>
                                <strong class="text-gray-900 dark:text-white">{{ $label }}</strong>
                                @if($ref)
                                    <span class="ml-1 font-mono text-[11px] text-gray-400 dark:text-gray-500 whitespace-nowrap">({{ $ref }})</span>
                                @endif
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
                    We will respond to your request within 30 days.
                </p>
            </section>

            {{-- ─── 08 — Children ─── --}}
            <section id="children" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">08</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Children's Privacy</h2>
                </div>
                <p>
                    Our services are not directed to individuals under the age of 16. We do not knowingly
                    collect personal data from children under 16. If we learn that we have collected
                    personal data from a child under 16 without verified parental consent, we will take
                    steps to delete that data promptly. If you believe we have collected data from a
                    child, please contact us immediately.
                </p>
            </section>

            {{-- ─── 09 — Security ─── --}}
            <section id="security" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">09</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Data Security</h2>
                </div>
                <p class="mb-4">
                    We implement appropriate technical and organizational measures to protect your
                    personal data against unauthorized access, alteration, disclosure, or destruction.
                    These measures include:
                </p>
                <ul class="space-y-2.5">
                    @foreach([
                        'Encryption of data in transit (TLS/SSL) and at rest where applicable',
                        'Access controls limiting data access to authorized personnel',
                        'Regular security assessments and testing',
                        'Incident response procedures for data breaches',
                    ] as $item)
                        <li class="flex items-start gap-2.5">
                            <span class="mt-2 w-1 h-1 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    In the event of a personal data breach that poses a risk to your rights and freedoms,
                    we will notify the relevant supervisory authority within 72 hours and affected
                    individuals without undue delay, as required by GDPR Articles 33 and 34.
                </p>
            </section>

            {{-- ─── 10 — Changes ─── --}}
            <section id="changes" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">10</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Changes to This Policy</h2>
                </div>
                <p>
                    We may update this Privacy Policy from time to time. We will notify you of significant
                    changes by posting a notice on our website or sending you an email. The "Last updated"
                    date at the top of this policy indicates when it was last revised.
                </p>
                <p class="mt-4">
                    We encourage you to review this Privacy Policy periodically to stay informed about
                    how we protect your data.
                </p>
            </section>

            {{-- ─── 11 — Contact ─── --}}
            <section id="contact" data-reveal class="pp-section scroll-mt-24">
                <div class="flex items-baseline gap-3 mb-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <span class="font-mono text-xs font-bold tabular-nums text-amber-600 dark:text-amber-400">11</span>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Contact Us</h2>
                </div>
                <p class="mb-4">
                    If you have questions about this Privacy Policy or wish to exercise your data
                    protection rights, please contact us:
                </p>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-5">
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-baseline gap-3">
                            <dt class="text-gray-500 dark:text-gray-400 w-20 shrink-0">Entity</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ $this->controller['name'] }}</dd>
                        </div>
                        <div class="flex items-baseline gap-3">
                            <dt class="text-gray-500 dark:text-gray-400 w-20 shrink-0">Email</dt>
                            <dd>
                                <a href="mailto:{{ $this->controller['email'] }}"
                                   class="text-primary-600 dark:text-primary-400 hover:underline underline-offset-2
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                    {{ $this->controller['email'] }}
                                </a>
                            </dd>
                        </div>
                    </dl>
                </div>
                <p class="mt-4">
                    You also have the right to lodge a complaint with your local data protection
                    supervisory authority.
                </p>
            </section>

        </article>

        {{-- ─── Footer actions ─── --}}
        <div class="pp-no-print mt-16 pt-8 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row gap-3">
            <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl
                           border border-gray-300 dark:border-gray-700
                           bg-white dark:bg-gray-800
                           text-gray-700 dark:text-gray-200
                           text-sm font-semibold
                           hover:bg-gray-50 dark:hover:bg-gray-700
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print this policy
            </button>

            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl
                      bg-primary-600 hover:bg-primary-700
                      text-white text-sm font-semibold
                      shadow-sm shadow-primary-600/20
                      transition-all duration-200 active:scale-95
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                Back to Home
            </a>
        </div>
    </div>
</div>