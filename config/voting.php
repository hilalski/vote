<?php

return [
    'shared_url' => env('VOTING_URL'),
    'timezone' => 'Asia/Jakarta',
    'token_lifetime_days' => (int) env('VOTING_TOKEN_LIFETIME_DAYS', 90),
];
