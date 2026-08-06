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
        unset($_SERVER['HTTP_X_LANGUAGE']);
        App::destroy();
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

    private function multilangApp(array $children): App
    {
        return new App([
            'roots' => [
                'index' => __DIR__,
                'templates' => __DIR__ . '/fixtures/templates'
            ],
            'options' => [
                'content' => ['fileRedirects' => true],
                'headless' => ['globalRoutes' => true]
            ],
            'languages' => [
                ['code' => 'en', 'default' => true, 'url' => '/'],
                ['code' => 'de', 'url' => '/de']
            ],
            'site' => ['children' => $children]
        ]);
    }

    /**
     * Kirby hands the catch-all to every language's router, and the default
     * language's pattern matches any path – so the route has to step aside
     * for a path that belongs to a language further down the cascade.
     */
    #[Test]
    public function serves_pages_in_a_non_default_language(): void
    {
        $result = $this
            ->multilangApp([['slug' => 'about', 'template' => 'language']])
            ->router()
            ->call('de/about', 'GET');

        $this->assertSame('{"id":"about","lang":"de"}', $result->body());
    }

    #[Test]
    public function serves_a_default_language_page_below_another_languages_path(): void
    {
        $result = $this
            ->multilangApp([
                [
                    'slug' => 'de',
                    'template' => 'language',
                    'children' => [
                        ['slug' => 'berlin', 'template' => 'language']
                    ]
                ]
            ])
            ->router()
            ->call('de/berlin', 'GET');

        $this->assertSame('{"id":"de\/berlin","lang":"en"}', $result->body());
    }

    #[Test]
    public function serves_an_unprefixed_path_in_the_language_named_by_x_language(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'de';

        $result = $this
            ->multilangApp([['slug' => 'about', 'template' => 'language']])
            ->router()
            ->call('about', 'GET');

        $this->assertSame('{"id":"about","lang":"de"}', $result->body());
    }

    #[Test]
    public function ignores_x_language_for_a_path_that_carries_a_language_prefix(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'en';

        $result = $this
            ->multilangApp([['slug' => 'about', 'template' => 'language']])
            ->router()
            ->call('de/about', 'GET');

        $this->assertSame('{"id":"about","lang":"de"}', $result->body());
    }

    /**
     * `setCurrentLanguage()` falls back to the default language for a code it
     * does not know, which would answer in a language nobody asked for.
     */
    #[Test]
    public function ignores_an_x_language_code_no_language_declares(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'fr';

        $result = $this
            ->multilangApp([['slug' => 'about', 'template' => 'language']])
            ->router()
            ->call('about', 'GET');

        $this->assertSame('{"id":"about","lang":"en"}', $result->body());
    }

    /**
     * Kirby's language router sets the translation and the language together,
     * so a template that mixes `t()` into its content does not answer half in
     * one language and half in the other.
     */
    #[Test]
    public function switches_the_translation_alongside_the_language_named_by_x_language(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'de';

        $result = $this
            ->multilangApp([['slug' => 'about', 'template' => 'translation']])
            ->router()
            ->call('about', 'GET');

        $this->assertSame('{"id":"about","lang":"de","translation":"de"}', $result->body());
    }

    /**
     * `App::language()` resolves `default` and `current` as if they named a
     * language, so the guard reads the collection, which knows only real codes.
     */
    #[Test]
    public function ignores_default_as_an_x_language_code(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'default';

        $result = $this
            ->multilangApp([['slug' => 'about', 'template' => 'translation']])
            ->router()
            ->call('about', 'GET');

        $this->assertSame('{"id":"about","lang":"en","translation":"en"}', $result->body());
    }

    #[Test]
    public function serves_a_single_language_site_regardless_of_x_language(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'de';

        $result = $this->app(null)->router()->call('about', 'GET');

        $this->assertSame(200, $result->code());
        $this->assertSame('{"id":"about"}', $result->body());
    }

    /**
     * Kirby looks a page up by the current language's slugs, and the header
     * runs before files resolve – so a file keeps resolving through the parent
     * page whose ID is the same in either language.
     */
    #[Test]
    public function resolves_a_file_for_a_path_carrying_x_language(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'de';

        $result = $this
            ->multilangApp([[
                'slug' => 'about',
                'template' => 'language',
                'files' => [['filename' => 'hero.jpg']]
            ]])
            ->router()
            ->call('about/hero.jpg', 'GET');

        $this->assertSame('about/hero.jpg', $result->id());
    }
}
