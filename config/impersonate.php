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
    | Redirection Paths
    |--------------------------------------------------------------------------
    |
    | Define the default redirection paths/routes when beginning or ending
    | an impersonation session.
    |
    */
    'redirect_to' => '/',
    'return_to' => null, // null will default to redirecting back
];
