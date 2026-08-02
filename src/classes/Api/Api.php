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
     *
     * Wraps data in a standardized format with code and status.
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
