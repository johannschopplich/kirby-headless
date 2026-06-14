<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ResolvePermalinksFieldMethodTest extends TestCase
{
    private function app(array $options = []): App
    {
        return new App([
            'roots' => ['index' => __DIR__],
            'options' => $options,
            'site' => [
                'children' => [
                    [
                        'slug' => 'about',
                        'content' => ['uuid' => 'about-uuid']
                    ],
                    [
                        'slug' => 'home',
                        'content' => [
                            'uuid' => 'home-uuid',
                            'text' => '<p><a href="page://about-uuid">About</a></p>'
                        ]
                    ]
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function rewrites_page_permalinks_in_href_attributes(): void
    {
        $kirby = $this->app();

        $resolved = $kirby->page('home')->text()->resolvePermalinks()->value();

        $this->assertStringNotContainsString('page://about-uuid', $resolved);
        $this->assertStringContainsString($kirby->page('about')->url(), $resolved);
    }

    #[Test]
    public function applies_custom_url_parser_when_configured(): void
    {
        $kirby = $this->app([
            'permalinksResolver.urlParser' => fn (string $url) => '/prefix' . $url
        ]);

        $resolved = $kirby->page('home')->text()->resolvePermalinks()->value();

        $this->assertStringContainsString('href="/prefix', $resolved);
    }

    #[Test]
    public function leaves_non_permalink_urls_and_empty_fields_untouched(): void
    {
        $kirby = new App([
            'roots' => ['index' => __DIR__],
            'site' => [
                'children' => [
                    [
                        'slug' => 'page',
                        'content' => [
                            'text' => '<a href="https://example.com">External</a>',
                            'empty' => ''
                        ]
                    ]
                ]
            ]
        ]);
        $page = $kirby->page('page');

        $this->assertStringContainsString(
            'href="https://example.com"',
            $page->text()->resolvePermalinks()->value()
        );
        $this->assertSame('', $page->empty()->resolvePermalinks()->value());
    }
}
