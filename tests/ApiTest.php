<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Api;
use Kirby\Exception\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    #[Test]
    public function create_response_wraps_data_with_code_status_and_result(): void
    {
        $response = Api::createResponse(200, ['foo' => 'bar']);

        $this->assertSame(200, $response->code());
        $this->assertSame('application/json', $response->type());
        $this->assertSame([
            'code' => 200,
            'status' => 'OK',
            'result' => ['foo' => 'bar']
        ], json_decode($response->body(), true));
    }

    #[Test]
    public function create_response_omits_result_key_when_data_is_null(): void
    {
        $response = Api::createResponse(204);
        $body = json_decode($response->body(), true);

        $this->assertSame(204, $response->code());
        $this->assertSame(['code' => 204, 'status' => 'No Content'], $body);
        $this->assertArrayNotHasKey('result', $body);
    }

    #[Test]
    #[DataProvider('supportedStatusCodes')]
    public function create_response_maps_each_supported_code_to_its_status_message(int $code, string $message): void
    {
        $response = Api::createResponse($code);

        $this->assertSame($message, json_decode($response->body(), true)['status']);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function supportedStatusCodes(): array
    {
        return [
            'ok' => [200, 'OK'],
            'created' => [201, 'Created'],
            'no content' => [204, 'No Content'],
            'bad request' => [400, 'Bad Request'],
            'unauthorized' => [401, 'Unauthorized'],
            'forbidden' => [403, 'Forbidden'],
            'not found' => [404, 'Not Found'],
            'method not allowed' => [405, 'Method Not Allowed'],
            'conflict' => [409, 'Conflict'],
            'unprocessable entity' => [422, 'Unprocessable Entity'],
            'internal server error' => [500, 'Internal Server Error']
        ];
    }

    #[Test]
    public function create_response_throws_for_unsupported_status_code(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown status code: 418');

        Api::createResponse(418);
    }

    #[Test]
    public function create_response_passes_custom_headers_through_to_response(): void
    {
        $response = Api::createResponse(200, null, ['X-Foo' => 'bar']);

        $this->assertSame('bar', $response->headers()['X-Foo']);
    }
}
