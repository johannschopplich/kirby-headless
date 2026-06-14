<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\BlocksResolver\CustomFieldResolver;
use Kirby\Cms\App;
use Kirby\Cms\Block;
use Kirby\Content\Field;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CustomFieldResolverTest extends TestCase
{
    private function app(array $options = []): App
    {
        return new App([
            'roots' => ['index' => __DIR__],
            'options' => $options
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    #[Test]
    public function applies_registered_resolver_to_matching_block_type_and_key(): void
    {
        $this->app([
            'blocksResolver.resolvers' => [
                'heading:level' => fn (Field $field) => strtoupper($field->value())
            ]
        ]);
        $block = new Block(['type' => 'heading', 'content' => ['level' => 'h2']]);

        $resolved = (new CustomFieldResolver())($block);

        $this->assertSame('H2', $resolved->content()->get('level')->value());
    }

    #[Test]
    public function ignores_resolver_whose_block_type_does_not_match(): void
    {
        $this->app([
            'blocksResolver.resolvers' => [
                'quote:text' => fn (Field $field) => strtoupper($field->value())
            ]
        ]);
        $block = new Block(['type' => 'heading', 'content' => ['level' => 'h2']]);

        $this->assertSame($block, (new CustomFieldResolver())($block));
    }
}
