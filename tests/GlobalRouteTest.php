<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class GlobalRouteTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function requires_a_bearer_token_before_resolving_files(): void
    {
        $result = $this->app()->router()->call('about/hero.jpg', 'GET');

        $this->assertSame(401, $result->code());
    }

    /**
     * Kirby's own catch-all accepts every method, so a route that matches only
     * `GET` lets every other method walk past the token.
     */
    #[Test]
    public function guards_pages_against_methods_other_than_get(): void
    {
        $result = $this->app()->router()->call('about', 'POST');

        $this->assertSame(401, $result->code());
    }

    /**
     * Serving every page publicly is a supported setup, not a misconfiguration.
     */
    #[Test]
    public function serves_pages_when_no_token_is_configured(): void
    {
        $result = $this->app(null)->router()->call('about', 'GET');

        $this->assertSame(200, $result->code());
        $this->assertSame('{"id":"about"}', $result->body());
    }

    /**
     * Kirby hands the catch-all to every language's router, and the default
     * language's pattern matches any path – so the route has to step aside
     * for a path that belongs to a language further down the cascade.
     */
    #[Test]
    public function serves_pages_in_a_non_default_language(): void
    {
        $kirby = new App([
            'roots' => [
                'index' => __DIR__,
                'templates' => __DIR__ . '/fixtures/templates'
            ],
            'options' => ['headless' => ['globalRoutes' => true]],
            'languages' => [
                ['code' => 'en', 'default' => true, 'url' => '/'],
                ['code' => 'de', 'url' => '/de']
            ],
            'site' => [
                'children' => [['slug' => 'about', 'template' => 'language']]
            ]
        ]);

        $result = $kirby->router()->call('de/about', 'GET');

        $this->assertSame('{"id":"about","lang":"de"}', $result->body());
    }

    /**
     * Dispatches through Kirby's router instead of calling the route action,
     * so that registration and method matching are covered as well.
     */
    private function app(string|null $token = 'secret'): App
    {
        return new App([
            'roots' => [
                'index' => __DIR__,
                'templates' => __DIR__ . '/fixtures/templates'
            ],
            'options' => [
                'content' => ['fileRedirects' => true],
                'headless' => ['globalRoutes' => true, 'token' => $token]
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'about',
                        'template' => 'default',
                        'files' => [['filename' => 'hero.jpg']]
                    ]
                ]
            ]
        ]);
    }
}
