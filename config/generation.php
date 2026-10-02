<?php

return [
    'rate_limit' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],

    'cost' => [
        'currency' => 'USD',
        'scale' => 12,
        'input_rate_per_million' => [
            'gpt-4o-mini' => '0.15',
        ],
        'output_rate_per_million' => [
            'gpt-4o-mini' => '0.60',
        ],
        'pricing_source' => 'https://developers.openai.com/api/docs/models/gpt-4o-mini',
        'pricing_checked_at' => '2026-10-02',
    ],
];
