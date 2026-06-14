<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PageMethodsTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function breadcrumb_meta_builds_from_root_down_to_current_page(): void
    {
        $kirby = new App([
            'roots' => ['index' => __DIR__],
            'site' => [
                'children' => [
                    [
                        'slug' => 'blog',
                        'content' => ['title' => 'Blog'],
                        'children' => [
                            ['slug' => 'post', 'content' => ['title' => 'Post']]
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertSame([
            ['title' => 'Blog', 'uri' => 'blog'],
            ['title' => 'Post', 'uri' => 'blog/post']
        ], $kirby->page('blog/post')->breadcrumbMeta());
    }

    #[Test]
    public function i18n_meta_returns_title_and_uri_per_configured_language(): void
    {
        $kirby = new App([
            'roots' => ['index' => __DIR__],
            'languages' => [
                ['code' => 'en', 'default' => true],
                ['code' => 'de']
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'about',
                        'translations' => [
                            ['code' => 'en', 'content' => ['title' => 'About']],
                            ['code' => 'de', 'content' => ['title' => 'Über uns']]
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertSame([
            'en' => ['title' => 'About', 'uri' => 'about'],
            'de' => ['title' => 'Über uns', 'uri' => 'about']
        ], $kirby->page('about')->i18nMeta());
    }

    #[Test]
    public function frontend_url_replaces_the_kirby_base_url_with_the_configured_one(): void
    {
        $kirby = new App([
            'roots' => ['index' => __DIR__],
            'url' => 'https://example.com',
            'options' => ['headless.panel.frontendUrl' => 'https://frontend.test'],
            'site' => [
                'children' => [['slug' => 'about']]
            ]
        ]);

        $frontendUrl = $kirby->page('about')->frontendUrl();

        $this->assertStringStartsWith('https://frontend.test', $frontendUrl);
        $this->assertStringContainsString('about', $frontendUrl);
        $this->assertStringNotContainsString('example.com', $frontendUrl);
    }

    #[Test]
    public function frontend_url_returns_null_when_not_configured(): void
    {
        $kirby = new App([
            'roots' => ['index' => __DIR__],
            'site' => [
                'children' => [['slug' => 'about']]
            ]
        ]);

        $this->assertNull($kirby->page('about')->frontendUrl());
    }
}
