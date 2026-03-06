<?php

return [
    'name' => 'ZentroTraderBot',
    'theme' => env('TRADER_BOT_THEME', 'FlexStart'),
    'bot' => env('TRADER_BOT_NAME', 'KashioBot'),
    // Credenciales
    '0x_api_key' => env('ZERO_EX_API_KEY'),
    '0x_treasury_wallet' => env('TREASURY_WALLET'),
    '0x_swap_fee_percentage' => env('SWAP_FEE_PERCENTAGE'),
    'alchemy_api_key' => env('ALCHEMY_API_KEY'),
    'alchemy_signing_key' => env('ALCHEMY_SIGNING_KEY'),
    'alchemy_auth_token' => env('ALCHEMY_AUTH_TOKEN'),
];
