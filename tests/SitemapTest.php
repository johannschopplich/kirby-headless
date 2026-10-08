<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Kirby renders a page in a language it has no translation for, filled from
 * the default language, so the sitemap has to check the translation itself.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SitemapTest extends TestCase
{
    private string $root = __DIR__ . '/fixtures/sitemap';

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_LANGUAGE']);
        App::destroy();
        Dir::remove($this->root);
    }

    #[Test]
    public function leaves_an_untranslated_language_out_of_the_alternates(): void
    {
        $links = $this->sitemap()['/only-english']['links'];

        $this->assertSame(['en', 'x-default'], array_column($links, 'lang'));
    }

    #[Test]
    public function leaves_a_page_out_of_the_sitemap_of_a_language_it_lacks(): void
    {
        $_SERVER['HTTP_X_LANGUAGE'] = 'de';

        $this->assertSame(['/de/about'], array_keys($this->sitemap()));
    }

    /**
     * A virtual page without content props has no translation in any
     * language, not even the default one.
     */
    #[Test]
    public function keeps_a_page_without_content_in_every_language(): void
    {
        $links = $this->sitemap([['slug' => 'virtual']])['/virtual']['links'];

        $this->assertSame(['en', 'de', 'x-default'], array_column($links, 'lang'));
    }

    /**
     * @param array<int, array<string, mixed>> $children Pages added to the fixture's two
     * @return array<string, array<string, mixed>>
     */
    private function sitemap(array $children = []): array
    {
        $kirby = new App([
            // Without an index URL the default language's paths collapse to an empty string.
            'urls' => [
                'index' => 'https://example.com'
            ],
            'roots' => [
                'index' => $this->root
            ],
            'languages' => [
                ['code' => 'en', 'default' => true, 'url' => '/'],
                ['code' => 'de', 'url' => '/de']
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'about',
                        'translations' => [
                            ['code' => 'en', 'content' => ['title' => 'About']],
                            ['code' => 'de', 'content' => ['title' => 'Über uns']]
                        ]
                    ],
                    [
                        'slug' => 'only-english',
                        'translations' => [
                            ['code' => 'en', 'content' => ['title' => 'Only English']]
                        ]
                    ],
                    ...$children
                ]
            ]
        ]);

        $entries = Json::decode($kirby->router()->call('api/__sitemap__', 'GET')->body())['result'];

        return array_column($entries, null, 'url');
    }
}
