<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Api;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Http\Response;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ApiHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        new App([
            'roots' => ['index' => __DIR__],
            'site' => [
                'files' => [['filename' => 'banner.jpg']]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function merges_array_results_into_context_for_later_middlewares(): void
    {
        $captured = null;
        $handler = Api::createHandler(
            fn (array $context, array $args) => ['body' => 'x'],
            function (array $context, array $args) use (&$captured): void {
                $captured = $context;
            }
        );

        $handler();

        $this->assertArrayHasKey('kirby', $captured);
        $this->assertSame('x', $captured['body']);
    }

    #[Test]
    public function short_circuits_and_returns_response_without_running_later_middlewares(): void
    {
        $reached = false;
        $handler = Api::createHandler(
            fn (array $context, array $args) => Api::createResponse(401),
            function (array $context, array $args) use (&$reached): void {
                $reached = true;
            }
        );

        $result = $handler();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(401, $result->code());
        $this->assertFalse($reached);
    }

    #[Test]
    public function short_circuits_and_returns_file_without_running_later_middlewares(): void
    {
        $reached = false;
        $handler = Api::createHandler(
            fn (array $context, array $args) => App::instance()->site()->file('banner.jpg'),
            function (array $context, array $args) use (&$reached): void {
                $reached = true;
            }
        );

        $result = $handler();

        $this->assertInstanceOf(File::class, $result);
        $this->assertFalse($reached);
    }

    #[Test]
    public function passes_route_args_to_each_middleware(): void
    {
        $captured = null;
        $handler = Api::createHandler(
            function (array $context, array $args) use (&$captured): void {
                $captured = $args;
            }
        );

        $handler('all', 'foo');

        $this->assertSame(['all', 'foo'], $captured);
    }
}
