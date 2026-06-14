<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\BlocksResolver\FilesFieldResolver;
use Kirby\Cms\App;
use Kirby\Cms\Block;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FilesFieldResolverTest extends TestCase
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
                        'template' => 'default',
                        'files' => [
                            ['filename' => 'hero.jpg', 'content' => ['alt' => 'Hero']],
                            ['filename' => 'cover.jpg', 'content' => ['alt' => 'Cover']]
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
    public function resolves_default_image_block_field_to_url_dimensions_and_alt(): void
    {
        $kirby = $this->app();
        $block = new Block([
            'type' => 'image',
            'parent' => $kirby->page('about'),
            'content' => ['image' => 'hero.jpg']
        ]);

        $resolved = (new FilesFieldResolver())($block);
        $image = $resolved->content()->get('image')->value()[0];

        $this->assertSame(['url', 'width', 'height', 'srcset', 'alt'], array_keys($image));
        $this->assertStringContainsString('hero.jpg', $image['url']);
        $this->assertSame('Hero', $image['alt']);
    }

    #[Test]
    public function returns_same_block_when_referenced_files_collection_is_empty(): void
    {
        $kirby = $this->app();
        $block = new Block([
            'type' => 'image',
            'parent' => $kirby->page('about'),
            'content' => ['image' => '']
        ]);

        $this->assertSame($block, (new FilesFieldResolver())($block));
    }

    #[Test]
    public function skips_field_when_custom_resolver_registered_for_block_and_key(): void
    {
        $kirby = $this->app([
            'blocksResolver.resolvers' => ['image:image' => fn () => 'custom']
        ]);
        $block = new Block([
            'type' => 'image',
            'parent' => $kirby->page('about'),
            'content' => ['image' => 'hero.jpg']
        ]);

        $resolved = (new FilesFieldResolver())($block);

        $this->assertSame('hero.jpg', $resolved->content()->get('image')->value());
    }

    #[Test]
    public function accumulates_multiple_file_fields_under_one_resolved_key(): void
    {
        // Regression: a block resolving two file fields into one bucket must
        // keep both, not let the second overwrite the first
        $kirby = $this->app([
            'blocksResolver.files' => ['gallery' => ['image', 'cover']],
            'blocksResolver.resolvedKey' => 'resolved'
        ]);
        $block = new Block([
            'type' => 'gallery',
            'parent' => $kirby->page('about'),
            'content' => ['image' => 'hero.jpg', 'cover' => 'cover.jpg']
        ]);

        $resolved = (new FilesFieldResolver())($block)->content()->get('resolved')->value();

        $this->assertArrayHasKey('image', $resolved);
        $this->assertArrayHasKey('cover', $resolved);
        $this->assertStringContainsString('hero.jpg', $resolved['image'][0]['url']);
        $this->assertStringContainsString('cover.jpg', $resolved['cover'][0]['url']);
    }
}
