<?php
declare(strict_types=1);

/**
 * Default SaaS plans. Prices are placeholders until commercialization.
 */
return [
    [
        'code' => 'starter',
        'name' => 'Starter',
        'price_monthly' => 0,
        'features' => ['ppdb', 'master_data', 'academic', 'analytics'],
        'max_students' => 500,
        'max_users' => 25,
        'ai_quota' => 100,
    ],
    [
        'code' => 'professional',
        'name' => 'Professional',
        'price_monthly' => 0,
        'features' => ['ppdb', 'master_data', 'academic', 'finance', 'letters', 'analytics', 'communication', 'ai'],
        'max_students' => 2000,
        'max_users' => 150,
        'ai_quota' => 2000,
    ],
    [
        'code' => 'enterprise',
        'name' => 'Enterprise',
        'price_monthly' => 0,
        'features' => ['*'],
        'max_students' => 0,
        'max_users' => 0,
        'ai_quota' => 20000,
    ],
];
