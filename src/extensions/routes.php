<?php

use JohannSchopplich\Headless\Api\Api;
use JohannSchopplich\Headless\Api\Middlewares;

return [
    /**
     * Global catch-all route for headless JSON responses
     *
     * Attempts to resolve files first, validates bearer token,
     * then returns page data as JSON
     */
    [
        'pattern' => '(:all)',
        'language' => '*',
        'action' => Api::createHandler(
            Middlewares::tryResolveFiles(...),
            Middlewares::hasBearerToken(true),
            Middlewares::tryResolvePage(...)
        )
    ]
];
