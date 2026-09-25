<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Profile
    |--------------------------------------------------------------------------
    |
    | The render profile used when no profile name is given to the facade,
    | the Blade directives, the component, the cast or the Str macros.
    |
    */

    'default' => env('CARVE_PROFILE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Render Profiles
    |--------------------------------------------------------------------------
    |
    | Each profile becomes its own configured converter. Options:
    |
    | - safe_mode:        true (escape raw HTML, block dangerous URLs), 'strict'
    |                     (also strips raw HTML and blocks style attributes) or
    |                     false for fully trusted content only.
    | - preset:           carve feature preset restricting which markup is
    |                     allowed: null, 'full', 'article', 'comment', 'minimal'.
    | - on_disallowed:    what a preset does with disallowed markup: 'to_text'
    |                     (default), 'strip' or 'error' (throws).
    | - mode:             'interactive' or 'static' (print, e-mail, PDF).
    | - soft_break_mode:  null, 'newline', 'space' or 'br'.
    | - smart_typography: false keeps "--" and straight quotes as written.
    | - xhtml:            self-closing void tags.
    | - source_lines:     add data-source-line attributes (editor scroll-sync).
    | - symbols:          trusted, unescaped HTML for :name: shortcodes.
    | - extensions:       list of extension names, ['type' => name, ...options]
    |                     arrays (options in snake_case), or extension classes.
    |
    */

    'profiles' => [

        'default' => [
            'safe_mode' => true,
            'preset' => null,
            'extensions' => [
                'autolink',
            ],
        ],

        // User generated content: comments, chat, reviews.
        'comment' => [
            'safe_mode' => 'strict',
            'preset' => 'comment',
            'extensions' => [
                'autolink',
                ['type' => 'external_links', 'nofollow' => true],
            ],
        ],

        // Trusted content written by your team: docs, pages, posts.
        'trusted' => [
            'safe_mode' => false,
            'extensions' => [
                'autolink',
                'admonition',
                'details',
                'heading_permalinks',
                'tabs',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache rendered output keyed by profile configuration and source hash.
    | Changing a profile changes its key, so stale HTML is never served.
    | "ttl" is in seconds; null keeps entries until the store evicts them.
    |
    */

    'cache' => [
        'enabled' => env('CARVE_CACHE', false),
        'store' => env('CARVE_CACHE_STORE'),
        'ttl' => null,
        'prefix' => 'carve',
    ],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | Register a view engine so `view('docs.intro')` renders intro.crv.
    | "profile" null uses the default profile.
    |
    */

    'views' => [
        'enabled' => true,
        'extension' => 'crv',
        'profile' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Includes
    |--------------------------------------------------------------------------
    |
    | Absolute directory that `{{ file.crv }}` include directives may read
    | from when rendering files (views, Carve::renderFile, carve:render).
    | Null leaves include directives as literal text.
    |
    */

    'include_root' => null,

];
