<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\I18n;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EndpointCacheTest extends TestCase
{
    private string $root = __DIR__ . '/fixtures/endpoints';

    protected function tearDown(): void
    {
        $_GET = [];
        unset($_SERVER['HTTP_X_CACHEABLE'], $_SERVER['HTTP_X_LANGUAGE']);
        App::destroy();
        Dir::remove($this->root);
    }

    /**
     * Seeds the cache with a marker no template could produce, so a response
     * carrying it can only have come from the cache.
     */
    private function app(string $cacheKey, mixed $stale): App
    {
        $kirby = new App([
            'roots' => [
                'index' => $this->root,
                'templates' => __DIR__ . '/fixtures/templates',
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

    /**
     * Each request builds a fresh app because the language sticks to the
     * instance – only the file cache root is shared between them.
     */
    private function appWithLanguages(): App
    {
        return new App([
            // Without an index URL the sitemap paths collapse to an empty string.
            'urls' => [
                'index' => 'https://example.com'
            ],
            'roots' => [
                'index' => $this->root,
                'templates' => __DIR__ . '/fixtures/templates',
                'cache' => $this->root . '/cache',
                'plugins' => dirname(__DIR__) . '/vendor/kirby-plugins'
            ],
            'options' => [
                'cache' => ['pages' => ['active' => true]],
                'kql' => ['auth' => false]
            ],
            'languages' => [
                ['code' => 'en', 'default' => true, 'url' => '/'],
                ['code' => 'de', 'url' => '/de']
            ],
            'site' => [
                'translations' => [
                    ['code' => 'en', 'content' => ['title' => 'Headless']],
                    ['code' => 'de', 'content' => ['title' => 'Kopflos']]
                ],
                'children' => [['slug' => 'about']]
            ]
        ]);
    }

    #[Test]
    public function serves_a_rendered_template_from_the_cache(): void
    {
        $kirby = $this->app('template-probe.headless.json', '{"value":"stale"}');

        $this->assertStringContainsString('stale', $kirby->router()->call('api/__template__/probe', 'GET')->body());
    }

    #[Test]
    public function serves_the_sitemap_from_the_cache(): void
    {
        $kirby = $this->app('sitemap.headless.json', [['url' => '/stale']]);

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
        $kirby = $this->app('template-probe.headless.json', '{"value":"stale"}');

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
        $kirby = $this->app('sitemap.headless.json', [['url' => '/stale']]);

        $this->assertStringNotContainsString('stale', $kirby->router()->call('api/__sitemap__', 'GET')->body());
    }

    #[Test]
    public function keeps_the_sitemap_of_one_language_from_answering_another(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'de';
        $this->appWithLanguages()->router()->call('api/__sitemap__', 'GET');

        unset($_SERVER['HTTP_X_LANGUAGE']);
        $body = $this->appWithLanguages()->router()->call('api/__sitemap__', 'GET')->body();

        $this->assertSame('/about', Json::decode($body)['result'][0]['url']);
    }

    #[Test]
    public function caches_a_rendered_template_per_language(): void
    {
        $this->appWithLanguages()->router()->call('api/__template__/probe', 'GET');

        $_SERVER['HTTP_X_LANGUAGE'] = 'de';
        $kirby = $this->appWithLanguages();
        $kirby->router()->call('api/__template__/probe', 'GET');

        $cache = $kirby->cache('pages');
        $this->assertNotNull($cache->get('template-probe-en.headless.json'));
        $this->assertNotNull($cache->get('template-probe-de.headless.json'));
    }

    #[Test]
    public function switches_the_translation_alongside_the_language_of_a_rendered_template(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'de';

        $body = $this->appWithLanguages()->router()->call('api/__template__/probe', 'GET')->body();

        $this->assertSame('de', Json::decode($body)['result']['translation']);
    }

    /**
     * The English request seeds the cache first, so a key that did not carry
     * the language would answer the German one with the English title.
     */
    #[Test]
    public function caches_a_kql_answer_per_language(): void
    {
        $_GET = ['query' => 'site.title'];
        $english = $this->appWithLanguages()->router()->call('api/kql', 'GET')->body();

        $_SERVER['HTTP_X_LANGUAGE'] = 'de';
        $german = $this->appWithLanguages()->router()->call('api/kql', 'GET')->body();

        $this->assertSame('Headless', Json::decode($english)['result']);
        $this->assertSame('Kopflos', Json::decode($german)['result']);
    }

    #[Test]
    public function switches_the_translation_alongside_the_language_of_a_kql_answer(): void
    {
        $_GET = ['query' => 'site.title'];
        $_SERVER['HTTP_X_LANGUAGE'] = 'de';
        $this->appWithLanguages()->router()->call('api/kql', 'GET');

        $this->assertSame('de', I18n::locale());
    }

    /**
     * Both sources resolve to German content either way, so only the suffix
     * tells a header-derived key from a resolved one.
     */
    #[Test]
    public function keys_a_kql_answer_on_language_ahead_of_x_language(): void
    {
        $_GET = ['query' => 'site.title', 'language' => 'de'];
        $_SERVER['HTTP_X_LANGUAGE'] = 'en';
        $kirby = $this->appWithLanguages();
        $kirby->router()->call('api/kql', 'GET');

        $hash = sha1(Json::encode(['query' => 'site.title', 'language' => 'de']));
        $this->assertNotNull($kirby->cache('pages')->get('query-' . $hash . '-de.json'));
    }
}
