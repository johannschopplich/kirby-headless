<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless\Api;

use Kirby\Cms\App;
use Kirby\Exception\Exception;
use Kirby\Http\Response;
use Kirby\Toolkit\A;

final readonly class Api
{
    /**
     * Creates an API handler that processes middleware functions sequentially.
     *
     * A middleware yields `null` to pass the request on and an array to add to
     * the context every later middleware receives. Anything else is the answer
     * and ends the chain – Kirby's router knows what to do with responses,
     * files, pages and plain strings alike.
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
     * Creates a consistent JSON API response.
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

    /**
     * Checks whether the client accepts a cached answer.
     *
     * `X-Cacheable: false` is how a caller asks for a freshly built response.
     */
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
     * A request the cache key cannot account for is built fresh and left
     * unstored, so it neither reads a stranger's answer nor becomes one.
     */
    public static function cached(string $key, callable $build): mixed
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
     * Returns the status message for a given HTTP status code.
     *
     * @throws \Kirby\Exception\Exception If the status code is not supported
     */
    private static function getStatusMessage(int $code): string
    {
        $messages = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error'
        ];

        if (!isset($messages[$code])) {
            throw new Exception('Unknown status code: ' . $code);
        }

        return $messages[$code];
    }
}
