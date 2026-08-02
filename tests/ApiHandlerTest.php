<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Api;
use Kirby\Cms\App;
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
        new App(['roots' => ['index' => __DIR__]]);
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

        $this->assertSame('x', $captured['body']);
    }

    /**
     * The first middleware to answer ends the chain, whatever it answers with:
     * Kirby's router takes more than responses, so a page or a plain string
     * has to reach `App::io()` instead of vanishing.
     */
    #[Test]
    public function returns_any_result_a_middleware_produces(): void
    {
        $reached = false;
        $handler = Api::createHandler(
            fn (array $context, array $args) => 'raw body',
            function (array $context, array $args) use (&$reached): void {
                $reached = true;
            }
        );

        $this->assertSame('raw body', $handler());
        $this->assertFalse($reached);
    }

    /**
     * Handlers are built once when the routes are registered, but the app they
     * hand to the middlewares has to be the one serving the request.
     */
    #[Test]
    public function hands_the_current_app_instance_to_the_middlewares(): void
    {
        $captured = null;
        $handler = Api::createHandler(
            function (array $context, array $args) use (&$captured): void {
                $captured = $context['kirby'];
            }
        );

        App::destroy();
        $kirby = new App(['roots' => ['index' => __DIR__]]);
        $handler();

        $this->assertSame($kirby, $captured);
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
