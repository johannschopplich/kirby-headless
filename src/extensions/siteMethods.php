<?php

use JohannSchopplich\Headless\FrontendUrl;

return [
    /**
     * Returns the site's URL rebased onto `headless.panel.frontendUrl`,
     * or `null` when that option is unset.
     *
     * @kql-allowed
     */
    'frontendUrl' => function (): string|null {
        /** @var \Kirby\Cms\Site $this */
        return FrontendUrl::resolve($this->url());
    }
];
