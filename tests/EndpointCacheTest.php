<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EndpointCacheTest extends TestCase
{
    private string $root = __DIR__ . '/fixtures/endpoints';

    protected function setUp(): void
    {
        Dir::make($this->root . '/templates');
        F::write($this->root . '/templates/probe.php', '<?php echo json_encode(["value" => "fresh"]);');
    }

    protected function tearDown(): void
    {
        $_GET = [];
        unset($_SERVER['HTTP_X_CACHEABLE']);
        App::destroy();
        Dir::remove($this->root);
    }

    #[Test]
    public function serves_a_rendered_template_from_the_cache(): void
    {
        $kirby = $this->bootKirby('template-probe.headless.json', '{"value":"stale"}');

        $this->assertStringContainsString('stale', $kirby->router()->call('api/__template__/probe', 'GET')->body());
    }

    #[Test]
    public function serves_the_sitemap_from_the_cache(): void
    {
        $kirby = $this->bootKirby('sitemap.headless.json', [['url' => '/stale']]);

        $this->assertStringContainsString('stale', $kirby->router()->call('api/__sitemap__', 'GET')->body());
    }

    /**
     * The cache key is the template name alone, so it cannot stand in for a
     * request that carries data – Kirby draws the same line in `isCacheable()`.
     */
    #[Test]
    public function renders_a_template_again_when_the_request_carries_data(): void
    {
        $_GET = ['preview' => '1'];
        $kirby = $this->bootKirby('template-probe.headless.json', '{"value":"stale"}');

        $this->assertStringContainsString('fresh', $kirby->router()->call('api/__template__/probe', 'GET')->body());
    }

    /**
     * `X-Cacheable: false` is how a client asks for a fresh answer – every
     * endpoint has to speak that language, not just KQL.
     */
    #[Test]
    public function rebuilds_the_sitemap_when_the_client_declines_the_cache(): void
    {
        $_SERVER['HTTP_X_CACHEABLE'] = 'false';
        $kirby = $this->bootKirby('sitemap.headless.json', [['url' => '/stale']]);

        $this->assertStringNotContainsString('stale', $kirby->router()->call('api/__sitemap__', 'GET')->body());
    }

    /**
     * Seeds the cache with a marker no template could produce, so a response
     * carrying it can only have come from the cache.
     */
    private function bootKirby(string $cacheKey, mixed $stale): App
    {
        $kirby = new App([
            'roots' => [
                'index' => $this->root,
                'templates' => $this->root . '/templates',
                'cache' => $this->root . '/cache'
            ],
            'options' => [
                'cache' => ['pages' => ['active' => true]]
            ],
            'site' => [
                'children' => [['slug' => 'about']]
            ]
        ]);

        $kirby->cache('pages')->set($cacheKey, $stale);

        return $kirby;
    }
}
