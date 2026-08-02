<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless;

use Kirby\Cms\App;
use Kirby\Toolkit\Str;

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

        return Str::replace($url, $kirby->url(), $frontendUrl);
    }
}
