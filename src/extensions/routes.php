<?php

use JohannSchopplich\Headless\Api\Api;
use JohannSchopplich\Headless\Api\Middlewares;

return [
    /**
     * Allow preflight requests, mainly for `fetch`
     */
    [
        'pattern' => '(:all)',
        'method' => 'OPTIONS',
        'language' => '*',
        'action' => fn () => Api::createPreflightResponse()
    ],

    /**
     * Return JSON-encoded page data for each request
     */
    [
        'pattern' => '(:all)',
        'language' => '*',
        'action' => Api::createHandler(
            [Middlewares::class, 'tryResolveFiles'],
            [Middlewares::class, 'hasBearerToken'],
            [Middlewares::class, 'tryResolveSite'],
            [Middlewares::class, 'tryResolveSitemap'],
            [Middlewares::class, 'tryResolvePage']
        )
    ]
];
