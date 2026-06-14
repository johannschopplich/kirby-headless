<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\Api\Middlewares;
use Kirby\Cms\App;
use Kirby\Cms\File;
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

    private function siteWithFile(array $config = []): array
    {
        return array_merge([
            'roots' => ['index' => __DIR__],
            'site' => [
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

    #[Test]
    public function resolves_page_file_from_path_in_single_language_mode(): void
    {
        new App($this->siteWithFile());

        $file = Middlewares::tryResolveFiles([], ['about/hero.jpg']);

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('about/hero.jpg', $file->id());
    }

    #[Test]
    public function resolves_page_file_from_the_path_argument_in_multilang_mode(): void
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

        $this->assertInstanceOf(File::class, $file);
        $this->assertSame('about/hero.jpg', $file->id());
    }

    #[Test]
    public function returns_null_when_path_has_no_file_extension(): void
    {
        new App($this->siteWithFile());

        $this->assertNull(Middlewares::tryResolveFiles([], ['about']));
    }
}
