<?php

return [
    'central_email' => env('AHAIC_CENTRAL_EMAIL', 'info@ahaic.org'),
    'finance_email' => env('AHAIC_FINANCE_EMAIL', 'finance@ahaic.org'),
    'billing_contact_email' => env('AHAIC_BILLING_CONTACT_EMAIL', 'ahaic@amref.org'),
    'billing_contact_name' => env('AHAIC_BILLING_CONTACT_NAME', 'AHAIC Secretariat'),
    'billing_entity_name' => env('AHAIC_BILLING_ENTITY_NAME', 'Amref Health Africa'),
    'billing_tax_registration_number' => env('AHAIC_TAX_REGISTRATION_NUMBER', 'P051092247U'),
    'billing_project_cost_centre' => env('AHAIC_PROJECT_COST_CENTRE', 'AHAIC'),
    'team_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AHAIC_TEAM_EMAILS', 'info@ahaic.org,finance@ahaic.org')),
    ))),
    // Mailboxes copied on change-request decisions, alongside the actual
    // admin/partnerships user accounts. Defaults to the central inbox only —
    // the generic team_emails list includes finance, who do not own schedule
    // decisions.
    'change_request_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AHAIC_CHANGE_REQUEST_EMAILS', env('AHAIC_CENTRAL_EMAIL', 'info@ahaic.org'))),
    ))),
    'invoice_due_days' => (int) env('AHAIC_INVOICE_DUE_DAYS', 30),

    /*
     * Where uploads and generated documents are written.
     *
     * Every upload names its disk through these two keys rather than relying on
     * FILESYSTEM_DISK, because the two categories differ: 'private' holds
     * signed agreements, payment proofs and generated invoices, which are only
     * ever served through a controller after an authorisation check; 'public'
     * holds partner logos and branding assets, which the browser loads directly.
     *
     * Defaults keep everything on the local filesystem. To move uploads to S3,
     * set PRIVATE_FILES_DISK=s3 and PUBLIC_FILES_DISK=s3_public.
     */
    'disks' => [
        'private' => env('PRIVATE_FILES_DISK', 'local'),
        'public' => env('PUBLIC_FILES_DISK', 'public'),
    ],
    'bank_details' => [
        'bank_name' => env('AHAIC_BANK_NAME', 'Standard Chartered Bank Limited'),
        'account_name' => env('AHAIC_ACCOUNT_NAME', 'Amref Health Africa'),
        'account_number' => env('AHAIC_ACCOUNT_NUMBER', '8708003408904'),
        'swift_code' => env('AHAIC_SWIFT_CODE', 'SCBLKENXXXX'),
        'branch' => env('AHAIC_BANK_BRANCH', 'Upper Hill'),
        'branch_code' => env('AHAIC_BRANCH_CODE', ''),
        'currency' => env('AHAIC_BANK_CURRENCY', 'USD'),
    ],
];
