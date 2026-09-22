{{-- resources/views/public/pages/⚡privacy-policy.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts.app')]
#[Title('Privacy Policy')]
class extends Component {};
?>

@php
    $controller  = config('legal.data_controller');
    $version     = config('legal.privacy_policy_version');
    $lastUpdated = config('legal.privacy_policy_updated_at');
@endphp

<div class="bg-white dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16">

        {{-- Back link --}}
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}"
           wire:navigate
           class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors mb-8 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>

        {{-- Header --}}
        <header class="pb-8 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-primary-600 dark:text-primary-400">
                    Legal
                </span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-white mb-3">
                Privacy Policy
            </h1>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                <span>Effective: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $lastUpdated }}</span></span>
                <span>Last updated: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $lastUpdated }}</span></span>
                <span>Version: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $version }}</span></span>
            </div>
        </header>

        {{-- Body --}}
        <article class="mt-10 space-y-10 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">

            {{-- Intro --}}
            <p>
                {{ $controller['name'] }} ("we", "us", or "our") operates
                <a href="{{ config('app.url') }}" class="text-primary-600 dark:text-primary-400 underline underline-offset-2 hover:text-primary-700 dark:hover:text-primary-300">
                    {{ config('app.url') }}
                </a>.
                This Privacy Policy explains how we collect, use, disclose, and safeguard your personal
                data in accordance with the General Data Protection Regulation (EU) 2016/679 ("GDPR")
                and applicable data protection laws.
            </p>

            <section class="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-5">
                <h2 class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-3">
                    Data Controller
                </h2>
                <dl class="space-y-1.5">
                    <div>
                        <dt class="sr-only">Entity</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ $controller['name'] }}</dd>
                    </div>
                    <div>
                        <dt class="sr-only">Country</dt>
                        <dd>Philippines</dd>
                    </div>
                    <div>
                        <dt class="sr-only">Contact email</dt>
                        <dd>
                            Email:
                            <a href="mailto:{{ $controller['email'] }}"
                               class="text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $controller['email'] }}
                            </a>
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- Data We Collect --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Data We Collect
                </h2>
                <p class="mb-4">We collect the following categories of personal data:</p>
                <ul class="space-y-2.5 list-none">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Personal Identity:</strong> Full name, email, phone, mailing address</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Account Credentials:</strong> Username, hashed password</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Payment Information:</strong> Card number (via processor), billing address</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Usage &amp; Analytics:</strong> Page views, session duration, referral source</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Cookies &amp; Tracking:</strong> Session cookies, analytics cookies, ad pixels</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Device &amp; Technical Data:</strong> IP address, user agent, device type</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Location Data:</strong> City, country, approximate coordinates</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Communications:</strong> Email content, chat logs, form submissions</span>
                    </li>
                </ul>
                <p class="mt-4">
                    We only collect data that is necessary for the purposes described in this policy.
                    We follow the principle of data minimization as required under GDPR Article 5(1)(c).
                </p>
            </section>

            {{-- Legal Basis --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Legal Basis for Processing
                </h2>
                <p class="mb-4">We process your personal data based on the following legal grounds under the GDPR:</p>
                <ul class="space-y-2.5 list-none">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Consent (user actively opts in)</strong> (GDPR Art. 6(1)(a))</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Contractual necessity (needed to fulfill a service)</strong> (GDPR Art. 6(1)(b))</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Legal obligation (required by law)</strong> (GDPR Art. 6(1)(c))</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Legitimate interest (business need, balanced against user rights)</strong> (GDPR Art. 6(1)(f))</span>
                    </li>
                </ul>
                <p class="mt-4">
                    Where we rely on consent, you have the right to withdraw your consent at any time.
                    Withdrawal does not affect the lawfulness of processing carried out before the withdrawal.
                </p>
            </section>

            {{-- Cookies --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Cookies and Tracking Technologies
                </h2>
                <p class="mb-4">
                    We use cookies and similar tracking technologies to operate and improve our services.
                    Cookies are small text files stored on your device by your browser.
                </p>

                <h3 class="text-base font-semibold text-gray-900 dark:text-white mt-6 mb-3">Types of Cookies We Use</h3>
                <ul class="space-y-2.5 list-none">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Strictly Necessary Cookies:</strong> Required for the website to function. These cannot be disabled.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Analytics Cookies:</strong> Help us understand how visitors use our site. Data is aggregated and anonymized where possible.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Functional Cookies:</strong> Remember your preferences and settings to enhance your experience.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Marketing Cookies:</strong> Used to deliver relevant advertisements and track campaign performance. These are only set with your explicit consent.</span>
                    </li>
                </ul>

                <h3 class="text-base font-semibold text-gray-900 dark:text-white mt-6 mb-3">Managing Cookies</h3>
                <p>
                    You can manage your cookie preferences through our cookie banner when you first visit
                    our site. You may also configure your browser to block or delete cookies, though this
                    may affect site functionality.
                </p>
            </section>

            {{-- Third-Party Services --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Third-Party Services
                </h2>
                <p class="mb-4">
                    We may share your data with trusted third-party service providers who assist us in
                    operating our website, conducting our business, or serving our users. These providers
                    are contractually obligated to:
                </p>
                <ul class="space-y-2.5 list-none">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Process data only on our instructions</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Maintain appropriate security measures</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Not use data for their own purposes</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Delete or return data upon termination of the agreement</span>
                    </li>
                </ul>
                <p class="mt-4">
                    Examples of third-party services may include hosting providers, analytics platforms,
                    payment processors, and email delivery services. All third-party processors are
                    required to comply with GDPR and have signed Data Processing Agreements (DPAs) with us.
                </p>
            </section>

            {{-- International Transfers --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    International Data Transfers
                </h2>
                <p class="mb-4">
                    Some of our third-party service providers may be located outside the European Economic
                    Area (EEA). When we transfer personal data outside the EEA, we ensure appropriate
                    safeguards are in place, including:
                </p>
                <ul class="space-y-2.5 list-none">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Standard Contractual Clauses (SCCs)</strong> approved by the European Commission</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Adequacy decisions</strong> where the European Commission has determined that a country provides an adequate level of data protection</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Other legally recognized transfer mechanisms under GDPR Chapter V</span>
                    </li>
                </ul>
                <p class="mt-4">
                    You may request details about the specific safeguards applied to your data transfers
                    by contacting us.
                </p>
            </section>

            {{-- Retention --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Data Retention
                </h2>
                <p class="mb-4">
                    We retain your personal data only for as long as necessary to fulfill the purposes
                    for which it was collected, including to satisfy any legal, accounting, or reporting
                    requirements.
                </p>
                <p class="mb-4">To determine the appropriate retention period, we consider:</p>
                <ul class="space-y-2.5 list-none">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>The amount, nature, and sensitivity of the data</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>The risk of harm from unauthorized use or disclosure</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>The purposes for which we process the data</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Whether we can achieve those purposes through other means</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Applicable legal, regulatory, tax, accounting, or other requirements</span>
                    </li>
                </ul>
                <p class="mt-4">
                    When personal data is no longer needed, we securely delete or anonymize it.
                </p>
            </section>

            {{-- Rights --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Your Rights Under GDPR
                </h2>
                <p class="mb-4">As a data subject, you have the following rights under the GDPR:</p>
                <ul class="space-y-2.5 list-none">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Right of Access (Art. 15):</strong> Request a copy of your personal data we hold.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Right to Rectification (Art. 16):</strong> Request correction of inaccurate or incomplete data.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Right to Erasure (Art. 17):</strong> Request deletion of your personal data ("right to be forgotten").</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Right to Restrict Processing (Art. 18):</strong> Request that we limit how we use your data.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Right to Data Portability (Art. 20):</strong> Receive your data in a structured, machine-readable format.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Right to Object (Art. 21):</strong> Object to processing based on legitimate interest or direct marketing.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Right to Withdraw Consent (Art. 7):</strong> Withdraw consent at any time where processing is based on consent.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span><strong class="text-gray-900 dark:text-white">Right to Lodge a Complaint:</strong> File a complaint with your local supervisory authority.</span>
                    </li>
                </ul>
                <p class="mt-4">
                    To exercise any of these rights, contact us at
                    <a href="mailto:{{ $controller['email'] }}"
                       class="text-primary-600 dark:text-primary-400 underline underline-offset-2 hover:text-primary-700 dark:hover:text-primary-300">
                        {{ $controller['email'] }}
                    </a>.
                    We will respond to your request within 30 days.
                </p>
            </section>

            {{-- Children --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Children's Privacy
                </h2>
                <p>
                    Our services are not directed to individuals under the age of 16. We do not knowingly
                    collect personal data from children under 16. If we learn that we have collected
                    personal data from a child under 16 without verified parental consent, we will take
                    steps to delete that data promptly. If you believe we have collected data from a
                    child, please contact us immediately.
                </p>
            </section>

            {{-- Security --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Data Security
                </h2>
                <p class="mb-4">
                    We implement appropriate technical and organizational measures to protect your
                    personal data against unauthorized access, alteration, disclosure, or destruction.
                    These measures include:
                </p>
                <ul class="space-y-2.5 list-none">
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Encryption of data in transit (TLS/SSL) and at rest where applicable</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Access controls limiting data access to authorized personnel</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Regular security assessments and testing</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="mt-2 w-1 h-1 rounded-full bg-primary-500 shrink-0"></span>
                        <span>Incident response procedures for data breaches</span>
                    </li>
                </ul>
                <p class="mt-4">
                    In the event of a personal data breach that poses a risk to your rights and freedoms,
                    we will notify the relevant supervisory authority within 72 hours and affected
                    individuals without undue delay, as required by GDPR Articles 33 and 34.
                </p>
            </section>

            {{-- Changes --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Changes to This Policy
                </h2>
                <p>
                    We may update this Privacy Policy from time to time. We will notify you of significant
                    changes by posting a notice on our website or sending you an email. The "Last Updated"
                    date at the top of this policy indicates when it was last revised.
                </p>
                <p class="mt-4">
                    We encourage you to review this Privacy Policy periodically to stay informed about
                    how we protect your data.
                </p>
            </section>

            {{-- Contact --}}
            <section>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                    Contact Us
                </h2>
                <p class="mb-4">
                    If you have questions about this Privacy Policy or wish to exercise your data
                    protection rights, please contact us:
                </p>
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 p-5">
                    <p class="font-semibold text-gray-900 dark:text-white mb-1">{{ $controller['name'] }}</p>
                    <p>
                        Email:
                        <a href="mailto:{{ $controller['email'] }}"
                           class="text-primary-600 dark:text-primary-400 hover:underline">
                            {{ $controller['email'] }}
                        </a>
                    </p>
                </div>
                <p class="mt-4">
                    You also have the right to lodge a complaint with your local data protection
                    supervisory authority.
                </p>
            </section>

        </article>

        {{-- Footer actions --}}
        <div class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row gap-3">
            <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-4 py-2.5 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print this policy
            </button>
            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 text-sm font-semibold transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Back to Home
            </a>
        </div>
    </div>
</div>