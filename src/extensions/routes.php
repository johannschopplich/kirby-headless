<?php

use JohannSchopplich\Headless\Api\Api;
use JohannSchopplich\Headless\Api\Middlewares;

return [
    /**
     * Global catch-all route for headless JSON responses.
     *
     * Validates the bearer token first, so neither file nor page resolution
     * can hand out anything ahead of the gate.
     *
     * Kirby registers its own catch-all for every method, so this route has
     * to match every method too – otherwise anything but `GET` falls straight
     * through to Kirby's resolver, past the gate.
     *
     * Media URLs never reach this route – Kirby serves them from its own
     * `before` routes, which plugins cannot overwrite.
     */
    [
        'pattern' => '(:all)',
        'method' => 'ALL',
        'language' => '*',
        'action' => Api::createHandler(
            Middlewares::hasBearerToken(true),
            Middlewares::tryResolveFiles(...),
            Middlewares::tryResolvePage(...)
        )
    ]
];
