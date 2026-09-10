<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Middleware Groups
    |--------------------------------------------------------------------------
    |
    | The middleware groups that the impersonation middleware will be
    | automatically registered to.
    |
    | Default: ['web', 'api']
    |
    */
    'middleware_groups' => [
        'web',
        'api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware applied to the package's internal impersonation routes.
    |
    | Default: ['web', 'auth']
    |
    */
    'route_middleware' => [
        'web',
        'auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirection Paths
    |--------------------------------------------------------------------------
    |
    | Define the default redirection paths/routes when beginning or ending
    | an impersonation session.
    |
    */
    'redirect_to' => '/',
    'return_to' => null, // null will default to redirecting to the URL where impersonation began
];
