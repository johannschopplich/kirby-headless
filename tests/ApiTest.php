<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Api;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    #[Test]
    public function wraps_data_in_an_envelope_with_code_and_status(): void
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
    public function omits_the_result_key_when_there_is_no_data(): void
    {
        $response = Api::createResponse(204);

        $this->assertSame(204, $response->code());
        $this->assertSame(['code' => 204, 'status' => 'No Content'], json_decode($response->body(), true));
    }

    #[Test]
    #[DataProvider('supportedStatusCodes')]
    public function names_the_status_of_a_supported_code(int $code, string $message): void
    {
        $response = Api::createResponse($code);

        $this->assertSame($message, json_decode($response->body(), true)['status']);
    }

    /**
     * A route built with the API builder may answer with any code it likes –
     * an unlisted one must not take the whole response down.
     */
    #[Test]
    #[DataProvider('unlistedStatusCodes')]
    public function names_the_class_of_an_unlisted_status_code(int $code, string $message): void
    {
        $response = Api::createResponse($code);

        $this->assertSame($code, $response->code());
        $this->assertSame($message, json_decode($response->body(), true)['status']);
    }

    #[Test]
    public function passes_custom_headers_through(): void
    {
        $response = Api::createResponse(200, null, ['X-Foo' => 'bar']);

        $this->assertSame('bar', $response->headers()['X-Foo']);
    }

    /**
     * Only the three codes the plugin adds itself are worth pinning – the rest
     * is Kirby's `Header::$codes` table, which `ok` stands in for.
     *
     * @return array<string, array{int, string}>
     */
    public static function supportedStatusCodes(): array
    {
        return [
            'ok' => [200, 'OK'],
            'no content' => [204, 'No Content'],
            'conflict' => [409, 'Conflict'],
            'unprocessable entity' => [422, 'Unprocessable Entity']
        ];
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function unlistedStatusCodes(): array
    {
        return [
            'too many requests' => [429, 'Client Error'],
            'insufficient storage' => [507, 'Server Error'],
            'im used' => [226, 'Success']
        ];
    }
}
