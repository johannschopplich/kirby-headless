<?php

use Kirby\Toolkit\Str;

return [
    /**
     * Returns the frontend URL for the site based on configuration
     *
     * Replaces the Kirby site URL with the configured frontend URL
     *
     * @kql-allowed
     */
    'frontendUrl' => function (): string|null {
        /** @var \Kirby\Cms\Site $this */
        $url = $this->kirby()->option('headless.panel.frontendUrl');

        if (empty($url)) {
            return null;
        }

        return Str::replace(
            $this->url(),
            $this->kirby()->url(),
            $url
        );
    }
];
