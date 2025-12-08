<?php

namespace JohannSchopplich\Headless\Api;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Exception\Exception;
use Kirby\Http\Response;
use Kirby\Toolkit\A;

class Api
{
    /**
     * Creates an API handler that processes middleware functions sequentially
     */
    public static function createHandler(callable ...$fns): callable
    {
        $context = [
            'kirby' => App::instance()
        ];

        return function (...$args) use ($fns, $context) {
            foreach ($fns as $fn) {
                $result = $fn($context, $args);

                if ($result instanceof Response || $result instanceof File) {
                    return $result;
                }

                if (is_array($result)) {
                    $context = A::merge($context, $result);
                }
            }
        };
    }

    /**
     * Creates a consistent JSON API response
     *
     * Wraps data in a standardized format with code and status
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
     * Returns the status message for a given HTTP status code
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
