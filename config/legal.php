<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Privacy Policy Version
    |--------------------------------------------------------------------------
    |
    | Bump this when the privacy policy content materially changes.
    | Existing users who accepted an older version can then be prompted
    | to re-accept the current one (that flow isn't wired yet — this
    | column is just the audit trail for now).
    |
    | Format: YYYY-MM — no patch level needed for a legal doc.
    */
    'privacy_policy_version' => '2025-09',

    /*
    |--------------------------------------------------------------------------
    | Privacy Policy Last Updated
    |--------------------------------------------------------------------------
    | Human-readable date shown at the top of the policy page.
    */
    'privacy_policy_updated_at' => 'September 15, 2025',

    /*
    |--------------------------------------------------------------------------
    | Data Controller
    |--------------------------------------------------------------------------
    | The entity legally responsible for the personal data collected by the
    | platform. Values come from the .env file so they can be changed
    | without a code deploy. The defaults match the values entered in the
    | Kalcify generator and shown in the published policy.
    |
    | To change any of these, update .env (LEGAL_CONTROLLER_*, LEGAL_DPO_*)
    | and run `php artisan config:clear`.
    */
    'data_controller' => [
        'name'      => env('LEGAL_CONTROLLER_NAME', 'Capstone'),
        'email'     => env('LEGAL_CONTROLLER_EMAIL', 'tourism.management.ph@gmail.com'),
        'dpo_name'  => env('LEGAL_DPO_NAME', ''),
        'dpo_email' => env('LEGAL_DPO_EMAIL', ''),
    ],
];