<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reown / WalletConnect Project ID
    |--------------------------------------------------------------------------
    | Get yours at https://cloud.reown.com
    */
    'project_id' => env('WALLETCONNECT_PROJECT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Wallet metadata (shown in the AppKit modal)
    |--------------------------------------------------------------------------
    */
    'wallet' => [
        'name'        => env('WALLET_NAME', 'My App'),
        'description' => env('WALLET_DESCRIPTION', ''),
        'icon'        => env('WALLET_ICON', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI configuration
    |--------------------------------------------------------------------------
    */
    'theme' => env('WALLETCONNECT_THEME', 'light'),   // 'light' | 'dark'
    'lang'  => env('WALLETCONNECT_LANG', 'es'),

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    | 'email'   — enable email-based login in AppKit
    | 'socials' — array of social providers: ['google', 'x', 'github', 'discord', ...]
    |             Empty array disables social login.
    |             Set via WALLETCONNECT_SOCIALS as a comma-separated list, e.g.
    |             WALLETCONNECT_SOCIALS=google,x,github
    */
    'features' => [
        'email'   => env('WALLETCONNECT_EMAIL', false),
        'socials' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('WALLETCONNECT_SOCIALS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chain whitelist
    |--------------------------------------------------------------------------
    | Numeric chainIds to expose in the AppKit widget — fewer chains means less
    | data embedded in the page and fewer networks for AppKit/Wagmi to
    | initialize, which noticeably speeds up the widget on pages that only
    | ever need one or two networks.
    | Leave empty (WALLETCONNECT_CHAINS unset) to include all chains from
    | dvzambrano/chainid (or chains_data).
    |
    | Set via WALLETCONNECT_CHAINS as a comma-separated list, e.g.
    | WALLETCONNECT_CHAINS=56            (BSC only)
    | WALLETCONNECT_CHAINS=1,137,56      (Ethereum, Polygon, BSC)
    */
    'chains' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('WALLETCONNECT_CHAINS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Static chain data (fallback when dvzambrano/chainid is not installed)
    |--------------------------------------------------------------------------
    | Keyed by numeric chainId. Each entry should have at least:
    |   chainId, name, nativeCurrency.{name, symbol, decimals}, rpc (array), explorers
    */
    'chains_data' => [],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    | Controls the wallet-address login/register endpoints.
    | 'user_model' — the Eloquent model used for registration and login.
    |               Must have: name, email, password, timezone, email_verified_at columns.
    */
    'auth' => [
        'enabled'    => true,
        'user_model' => \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Published asset path (relative to public/)
    |--------------------------------------------------------------------------
    */
    'assets' => [
        'path' => 'vendor/dvzambrano/walletconnect/js',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled'    => true,
        'prefix'     => 'walletconnect',
        'middleware' => ['web'],
    ],

];
