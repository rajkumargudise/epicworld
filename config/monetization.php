<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monetization
    |--------------------------------------------------------------------------
    |
    | Provider-agnostic. The safe default is fully disabled: nothing
    | here causes a request to any external advertising provider, no
    | publisher ID appears anywhere in the codebase or its output, and
    | no ad script loads, until this is explicitly turned on with a
    | real provider configured - which this milestone deliberately
    | does not do (see MONETIZATION_ENABLED in .env.example).
    |
    | "provider" only names which future adapter would serve these
    | slots (e.g. "google-adsense") - nothing in this codebase yet
    | knows how to talk to any provider, and this value never appears
    | in rendered HTML (see AdSlotRegistry / the <x-ad-slot> component).
    | Adding a real adapter and a real publisher ID is later, explicit
    | configuration work, not part of this milestone.
    |
    */

    'enabled' => (bool) env('MONETIZATION_ENABLED', false),

    'provider' => env('MONETIZATION_PROVIDER'),

    // Whether a future provider script should load lazily (deferred /
    // intersection-observer-driven) rather than blocking the initial
    // page render. Read by whichever milestone actually adds a
    // provider script; unused by anything in this one.
    'lazy' => (bool) env('MONETIZATION_LAZY_LOAD', true),

    /*
    |--------------------------------------------------------------------------
    | Slots
    |--------------------------------------------------------------------------
    |
    | One entry per editorial placement that actually exists in the
    | public layouts today (resources/views/layouts/public.blade.php,
    | public/home.blade.php, public/article.blade.php) - not a
    | speculative list of every place an ad could go. "context" is the
    | explicit page type(s) this slot is eligible on, matched against
    | the $context a template passes to <x-ad-slot context="...">,
    | never inferred from the request URL or path.
    |
    | "dimensions" are reserved space for a future ad, in pixels, so a
    | later provider script filling this slot doesn't shift the
    | surrounding content - see AdSlotRegistry::dimensions(). They are
    | only ever used when the slot is actually eligible to render;
    | while monetization is disabled (the default) nothing reserves
    | this space at all.
    |
    */

    'slots' => [

        'site_top' => [
            'enabled' => true,
            'context' => ['home', 'latest', 'article', 'category', 'tag', 'search'],
            'dimensions' => [
                'desktop' => ['width' => 728, 'height' => 90],
                'mobile' => ['width' => 320, 'height' => 50],
            ],
        ],

        'site_footer' => [
            'enabled' => true,
            'context' => ['home', 'latest', 'article', 'category', 'tag', 'search'],
            'dimensions' => [
                'desktop' => ['width' => 728, 'height' => 90],
                'mobile' => ['width' => 320, 'height' => 50],
            ],
        ],

        'home_between_sections' => [
            'enabled' => true,
            'context' => ['home'],
            'dimensions' => [
                'desktop' => ['width' => 970, 'height' => 250],
                'mobile' => ['width' => 300, 'height' => 250],
            ],
        ],

        'article_top' => [
            'enabled' => true,
            'context' => ['article'],
            'dimensions' => [
                'desktop' => ['width' => 336, 'height' => 280],
                'mobile' => ['width' => 300, 'height' => 250],
            ],
        ],

        'article_bottom' => [
            'enabled' => true,
            'context' => ['article'],
            'dimensions' => [
                'desktop' => ['width' => 336, 'height' => 280],
                'mobile' => ['width' => 300, 'height' => 250],
            ],
        ],

    ],

];
