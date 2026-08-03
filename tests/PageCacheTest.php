<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Middlewares;
use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PageCacheTest extends TestCase
{
    private string $root = __DIR__ . '/fixtures/cached';

    protected function setUp(): void
    {
        Dir::make($this->root . '/templates');
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_CACHEABLE']);
        App::destroy();
        Dir::remove($this->root);
    }

    /**
     * Boots with an active pages cache that survives `App::destroy()`, so a
     * second request can be told apart from a cache hit by its body alone.
     */
    private function app(): void
    {
        new App([
            'roots' => [
                'index' => $this->root,
                'templates' => $this->root . '/templates',
                'cache' => $this->root . '/cache'
            ],
            'options' => [
                'cache' => ['pages' => ['active' => true]]
            ],
            'site' => [
                'children' => [['slug' => 'about', 'template' => 'default']]
            ]
        ]);
    }

    private function writeTemplate(string $contents): void
    {
        F::write($this->root . '/templates/default.php', $contents);
    }

    private function seedCache(array $response): void
    {
        App::instance()->cache('pages')->set('about.latest.html.headless.json', [
            'response' => $response,
            'usesAuth' => false,
            'usesCookies' => []
        ]);
    }

    /**
     * Kirby stores the response configuration next to the rendered body and
     * replays it on a hit – without that, headers and status codes silently
     * apply to the first visitor only.
     */
    #[Test]
    public function replays_the_response_configuration_on_a_cache_hit(): void
    {
        $this->writeTemplate('<?php $kirby->response()->header("X-Custom", "yes")->code(201); echo "first";');
        $this->app();
        Middlewares::tryResolvePage([], ['about']);
        App::destroy();

        $this->writeTemplate('<?php echo "second";');
        $this->app();
        $response = Middlewares::tryResolvePage([], ['about']);

        $this->assertSame('first', $response->body());
        $this->assertSame(201, $response->code());
        $this->assertSame('yes', $response->header('X-Custom'));
    }

    /**
     * A template that reads the `Authorization` header varies by it, so the
     * copy filled by an anonymous visitor is that visitor's alone. Kirby marks
     * such a response through `Responder::usesAuth()`, and the entry has to
     * carry the mark.
     */
    #[Test]
    public function refuses_to_replay_an_entry_that_varies_by_credentials(): void
    {
        // Filling the cache while authenticated would be refused by Kirby
        // outright, so the entry that must not be replayed is the one an
        // anonymous visitor left behind
        $this->writeTemplate('<?php $kirby->request()->auth(); echo "first";');
        $this->app();
        Middlewares::tryResolvePage([], ['about']);
        App::destroy();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
        $this->writeTemplate('<?php echo "second";');
        $this->app();

        $this->assertSame('second', Middlewares::tryResolvePage([], ['about'])->body());
    }

    /**
     * An entry without a body is nothing to serve, and its status code and
     * headers must not end up on the response rendered in its place.
     */
    #[Test]
    public function renders_afresh_when_a_cached_entry_has_no_body(): void
    {
        $this->writeTemplate('<?php echo "fresh";');

        // The positive control pins the key the renderer builds – without it
        // the assertion below would pass by missing the entry altogether
        $this->app();
        $this->seedCache(['body' => 'from cache', 'code' => 200]);
        $this->assertSame('from cache', Middlewares::tryResolvePage([], ['about'])->body());
        App::destroy();

        $this->app();
        $this->seedCache(['code' => 418]);
        $response = Middlewares::tryResolvePage([], ['about']);

        $this->assertSame('fresh', $response->body());
        $this->assertSame(200, $response->code());
    }

    #[Test]
    public function renders_afresh_when_the_client_declines_the_cache(): void
    {
        $this->writeTemplate('<?php echo "first";');
        $this->app();
        Middlewares::tryResolvePage([], ['about']);
        App::destroy();

        $_SERVER['HTTP_X_CACHEABLE'] = 'false';
        $this->writeTemplate('<?php echo "second";');
        $this->app();

        $this->assertSame('second', Middlewares::tryResolvePage([], ['about'])->body());
        $this->assertSame(
            'first',
            App::instance()->cache('pages')->get('about.latest.html.headless.json')['response']['body']
        );
    }

    #[Test]
    public function respects_a_template_that_opts_out_of_the_cache(): void
    {
        $this->writeTemplate('<?php $kirby->response()->cache(false); echo "first";');
        $this->app();
        Middlewares::tryResolvePage([], ['about']);
        App::destroy();

        $this->writeTemplate('<?php echo "second";');
        $this->app();

        $this->assertSame('second', Middlewares::tryResolvePage([], ['about'])->body());
    }
}
