<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless\Api;

use Kirby\Cms\App;
use Kirby\Http\Header;
use Kirby\Http\Response;
use Kirby\Toolkit\A;

final readonly class Api
{
    /**
     * Creates an API handler that processes middleware functions sequentially.
     *
     * A middleware yields `null` to pass the request on and an array to add to
     * the context every later middleware receives. Anything else is the answer
     * and ends the chain.
     */
    public static function createHandler(callable ...$middlewares): callable
    {
        return function (...$args) use ($middlewares) {
            // The handler outlives the request it was registered in, so the
            // app has to be looked up per call
            $context = [
                'kirby' => App::instance()
            ];

            foreach ($middlewares as $middleware) {
                $result = $middleware($context, $args);

                if (is_array($result)) {
                    $context = A::merge($context, $result);
                    continue;
                }

                if ($result !== null) {
                    return $result;
                }
            }
        };
    }

    /**
     * Wraps data in the JSON envelope every endpoint answers with.
     */
    public static function createResponse(int $code, mixed $data = null, array $headers = []): Response
    {
        $body = [
            'code' => $code,
            'status' => self::getStatusMessage($code)
        ];

        if ($data !== null) {
            $body['result'] = $data;
        }

        return Response::json(
            body: $body,
            code: $code,
            headers: $headers
        );
    }

    public static function clientAllowsCache(): bool
    {
        return App::instance()->request()->header('X-Cacheable') !== 'false';
    }

    /**
     * Checks whether the current request may be answered from the pages cache.
     *
     * Mirrors `Page::isCacheable()`: an endpoint whose cache key does not cover
     * the request must not answer from the cache once the request carries data.
     */
    public static function isCacheable(): bool
    {
        $request = App::instance()->request();

        return self::clientAllowsCache() &&
            in_array($request->method(), ['GET', 'HEAD'], true) &&
            $request->data() === [] &&
            $request->params()->isEmpty();
    }

    /**
     * Returns a cached value, building and storing it when the request may be
     * answered from the cache.
     *
     * A request the key cannot account for neither reads a stranger's answer
     * nor becomes one.
     */
    public static function getOrSet(string $key, callable $build): mixed
    {
        $cache = App::instance()->cache('pages');
        $isCacheable = self::isCacheable();
        $value = $isCacheable ? $cache->get($key) : null;

        if ($value === null) {
            $value = $build();

            if ($isCacheable) {
                $cache->set($key, $value);
            }
        }

        return $value;
    }

    /**
     * A route composed with `createHandler()` may answer with any code, so an
     * unlisted one names its class rather than taking the response down.
     */
    private static function getStatusMessage(int $code): string
    {
        // The three codes Kirby's own table leaves out
        $messages = [
            204 => 'No Content',
            409 => 'Conflict',
            422 => 'Unprocessable Entity'
        ];

        return Header::$codes['_' . $code]
            ?? $messages[$code]
            ?? match (intdiv($code, 100)) {
                1 => 'Informational',
                2 => 'Success',
                3 => 'Redirection',
                4 => 'Client Error',
                5 => 'Server Error',
                default => 'Unknown'
            };
    }
}
