<?php

use JohannSchopplich\Headless\Api\Api;
use JohannSchopplich\Headless\Api\Middlewares;

return [
    /**
     * Global catch-all route for headless JSON responses.
     *
     * The token gate runs first, so nothing is handed out ahead of it.
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
