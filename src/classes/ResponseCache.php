<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless;

use Kirby\Cms\App;
use Kirby\Cms\Responder;

/**
 * Pages cache for rendered responses, kept the way `Page::render()` keeps them.
 */
final readonly class ResponseCache
{
    /**
     * Returns the cached body, or `null` for a missing entry or one private to
     * another client.
     */
    public static function replay(string $key, Responder $response): string|null
    {
        $cachedEntry = App::instance()->cache('pages')->get($key);

        if (!is_array($cachedEntry)) {
            return null;
        }

        $body = $cachedEntry['response']['body'] ?? null;

        if ($body === null) {
            return null;
        }

        if (Responder::isPrivate($cachedEntry['usesAuth'] ?? false, $cachedEntry['usesCookies'] ?? [])) {
            return null;
        }

        $response->fromArray($cachedEntry['response']);

        return $body;
    }

    public static function store(string $key, Responder $response): void
    {
        if ($response->cache() !== true) {
            return;
        }

        App::instance()->cache('pages')->set($key, [
            'response' => $response->toCacheArray(),
            'usesAuth' => $response->usesAuth(),
            'usesCookies' => $response->usesCookies()
        ], $response->expires() ?? 0);
    }
}
