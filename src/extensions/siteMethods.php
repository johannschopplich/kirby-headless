<?php

use JohannSchopplich\Headless\FrontendUrl;

return [
    /**
     * Returns the frontend URL for the site based on configuration.
     *
     * @kql-allowed
     */
    'frontendUrl' => function (): string|null {
        /** @var \Kirby\Cms\Site $this */
        return FrontendUrl::resolve($this->url());
    }
];
