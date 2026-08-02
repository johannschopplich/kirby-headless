<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Middlewares;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ResolveFilesMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function resolves_a_page_file_from_the_request_path(): void
    {
        new App($this->siteWithFile());

        $file = Middlewares::tryResolveFiles([], ['about/hero.jpg']);

        $this->assertSame('about/hero.jpg', $file->id());
    }

    #[Test]
    public function resolves_a_page_file_behind_a_language_argument(): void
    {
        $kirby = new App($this->siteWithFile([
            'languages' => [
                ['code' => 'en', 'default' => true],
                ['code' => 'de']
            ]
        ]));

        // In multilang mode the language object is the first route argument
        // and the captured path is the second
        $file = Middlewares::tryResolveFiles([], [$kirby->language('en'), 'about/hero.jpg']);

        $this->assertSame('about/hero.jpg', $file->id());
    }

    #[Test]
    public function ignores_a_path_without_a_file_extension(): void
    {
        new App($this->siteWithFile());

        $this->assertNull(Middlewares::tryResolveFiles([], ['about']));
    }

    #[Test]
    public function serves_no_files_while_file_redirects_are_disabled(): void
    {
        new App($this->siteWithFile(['options' => []]));

        $this->assertNull(Middlewares::tryResolveFiles([], ['about/hero.jpg']));
    }

    #[Test]
    public function resolves_site_files_only_at_the_root_level(): void
    {
        new App($this->siteWithFile());

        $this->assertSame('logo.png', Middlewares::tryResolveFiles([], ['logo.png'])?->id());
        $this->assertNull(Middlewares::tryResolveFiles([], ['any/made/up/path/logo.png']));
    }

    #[Test]
    public function lets_a_page_keep_a_path_a_file_could_also_claim(): void
    {
        new App($this->siteWithFile([
            'site' => [
                'children' => [
                    [
                        'slug' => 'blog',
                        'template' => 'default',
                        'files' => [['filename' => 'post.json']],
                        'children' => [['slug' => 'post', 'template' => 'default']]
                    ]
                ]
            ]
        ]));

        $this->assertNull(Middlewares::tryResolveFiles([], ['blog/post.json']));
    }

    #[Test]
    public function resolves_a_file_that_belongs_to_a_draft(): void
    {
        new App($this->siteWithFile([
            'site' => [
                'drafts' => [
                    [
                        'slug' => 'unpublished',
                        'template' => 'default',
                        'files' => [['filename' => 'attachment.pdf']]
                    ]
                ]
            ]
        ]));

        $file = Middlewares::tryResolveFiles([], ['unpublished/attachment.pdf']);

        $this->assertSame('unpublished/attachment.pdf', $file->id());
    }

    private function siteWithFile(array $config = []): array
    {
        return array_merge([
            'roots' => ['index' => __DIR__],
            // Clean file URLs are opt-in in Kirby and stay opt-in here
            'options' => ['content' => ['fileRedirects' => true]],
            'site' => [
                'files' => [['filename' => 'logo.png']],
                'children' => [
                    [
                        'slug' => 'about',
                        'template' => 'default',
                        'files' => [['filename' => 'hero.jpg']]
                    ]
                ]
            ]
        ], $config);
    }
}
