<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\BlocksResolver\PagesFieldResolver;
use Kirby\Cms\App;
use Kirby\Cms\Block;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PagesFieldResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

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
                        'content' => ['title' => 'About Us']
                    ]
                ]
            ]
        ]);
    }

    #[Test]
    public function resolves_configured_pages_field_to_default_uri_and_title_shape(): void
    {
        $this->app(['blocksResolver.pages' => ['link' => 'page']]);
        $block = new Block(['type' => 'link', 'content' => ['page' => 'about']]);

        $resolved = (new PagesFieldResolver())($block);

        $this->assertSame(
            [['uri' => 'about', 'title' => 'About Us']],
            $resolved->content()->get('page')->value()
        );
    }

    #[Test]
    public function leaves_a_block_of_an_unconfigured_type_untouched(): void
    {
        $this->app(['blocksResolver.pages' => ['link' => 'page']]);
        $block = new Block(['type' => 'text', 'content' => ['page' => 'about']]);

        $this->assertSame($block, (new PagesFieldResolver())($block));
    }

    #[Test]
    public function nests_the_resolved_value_under_the_resolved_key_and_keeps_the_original_field(): void
    {
        $this->app([
            'blocksResolver.pages' => ['link' => 'page'],
            'blocksResolver.resolvedKey' => 'resolved'
        ]);
        $block = new Block(['type' => 'link', 'content' => ['page' => 'about']]);

        $resolved = (new PagesFieldResolver())($block);

        $this->assertSame(
            [['uri' => 'about', 'title' => 'About Us']],
            $resolved->content()->get('resolved')->value()['page']
        );
        // Original field value is preserved alongside the resolved bucket
        $this->assertSame('about', $resolved->content()->get('page')->value());
    }

    #[Test]
    public function skips_field_when_a_custom_resolver_is_registered_for_block_and_key(): void
    {
        $this->app([
            'blocksResolver.pages' => ['link' => 'page'],
            'blocksResolver.resolvers' => ['link:page' => fn () => 'custom']
        ]);
        $block = new Block(['type' => 'link', 'content' => ['page' => 'about']]);

        $resolved = (new PagesFieldResolver())($block);

        // Default pages resolution is skipped, leaving the raw value untouched
        $this->assertSame('about', $resolved->content()->get('page')->value());
    }
}
