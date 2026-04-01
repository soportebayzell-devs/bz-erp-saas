<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Finance defaults
    |--------------------------------------------------------------------------
    | These are fallbacks. Tenant-level overrides live in tenants.settings JSON.
    */

    'default_tax_rate' => (float) env('ERP_DEFAULT_TAX_RATE', 0.12),  // Guatemala IVA 12%
    'default_currency' => env('ERP_DEFAULT_CURRENCY', 'GTQ'),
    'default_timezone' => env('ERP_DEFAULT_TIMEZONE', 'America/Guatemala'),
    'invoice_prefix'   => env('ERP_INVOICE_PREFIX', 'INV'),
    'overdue_days'     => (int) env('ERP_OVERDUE_REMINDER_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Status enumerations
    |--------------------------------------------------------------------------
    | Centralised here so controllers and frontend can consume the same values.
    */

    'lead_statuses' => [
        'new'       => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'converted' => 'Converted',
        'lost'      => 'Lost',
    ],

    'student_statuses' => [
        'active'    => 'Active',
        'suspended' => 'Suspended',
        'graduated' => 'Graduated',
        'dropped'   => 'Dropped',
    ],

    'course_types' => [
        'online'    => 'Online',
        'in_person' => 'In-Person',
        'hybrid'    => 'Hybrid',
    ],

    'course_levels' => [
        'beginner'     => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced'     => 'Advanced',
    ],

    'invoice_statuses' => [
        'pending'   => 'Pending',
        'partial'   => 'Partial',
        'paid'      => 'Paid',
        'overdue'   => 'Overdue',
        'cancelled' => 'Cancelled',
    ],

    'payment_methods' => [
        'cash'          => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'card'          => 'Card',
        'other'         => 'Other',
    ],

    'lead_sources' => [
        'website_form' => 'Website form',
        'referral'     => 'Referral',
        'whatsapp'     => 'WhatsApp',
        'facebook'     => 'Facebook',
        'instagram'    => 'Instagram',
        'api'          => 'API',
        'manual'       => 'Manual',
        'other'        => 'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flags (defaults — can be overridden per tenant in settings JSON)
    |--------------------------------------------------------------------------
    */

    'features' => [
        'crm'        => true,
        'students'   => true,
        'courses'    => true,
        'finance'    => true,
        'attendance' => true,   // Phase 2
        'staff'      => true,   // Phase 2
        'automation' => false,  // Phase 3
        'reports'    => false,  // Phase 3
    ],

];
