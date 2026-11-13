<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless;

use Kirby\Cms\App;

final readonly class FrontendUrl
{
    /**
     * Points a Kirby URL at the configured frontend instead.
     */
    public static function resolve(string $url): string|null
    {
        $kirby = App::instance();
        $frontendUrl = $kirby->option('headless.panel.frontendUrl');

        if ($frontendUrl === null || $frontendUrl === '') {
            return null;
        }

        $baseUrl = rtrim($kirby->url(), '/');

        if (!str_starts_with($url, $baseUrl)) {
            return $url;
        }

        return rtrim($frontendUrl, '/') . substr($url, strlen($baseUrl));
    }
}
