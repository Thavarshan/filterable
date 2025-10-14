<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Feature Toggles
    |--------------------------------------------------------------------------
    |
    | Configure which Filter features should be enabled by default when a
    | filter is constructed. Individual filters can still call enableFeature/
    | disableFeature to override these values per-instance.
    |
    */

    'defaults' => [
        'features' => [
            'validation' => false,
            'permissions' => false,
            'rateLimit' => false,
            'caching' => false,
            'logging' => false,
            'performance' => false,
            'optimization' => false,
            'memoryManagement' => false,
            'filterChaining' => false,
            'valueTransformation' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Default Runtime Options
        |--------------------------------------------------------------------------
        |
        | Runtime options are applied to the Filter instance during construction.
        | These values populate the $options array, and can be overridden per
        | filter via setOption()/setOptions().
        |
        */

        'options' => [
            //
        ],

        /*
        |--------------------------------------------------------------------------
        | Cache Behaviour Defaults
        |--------------------------------------------------------------------------
        |
        | Adjust the default cache TTL (expressed in minutes) used by the
        | InteractsWithCache concern. Individual filters can still call
        | setCacheExpiration() to override this value.
        |
        */

        'cache' => [
            'ttl' => null,
        ],
    ],
];
