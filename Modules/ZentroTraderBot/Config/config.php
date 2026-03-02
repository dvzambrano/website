<?php

return [
    'name' => 'ZentroTraderBot',
    'theme' => env('TRADER_BOT_THEME', 'FlexStart'),
    'bot' => env('TRADER_BOT_NAME', 'KashioBot'),
    // Credenciales
    '0x_api_key' => env('TRADER_BOT_ZERO_EX_API_KEY'),
    'wallet_connect_api_key' => env('TRADER_BOT_WALLET_CONNECT_API_KEY'),
];
