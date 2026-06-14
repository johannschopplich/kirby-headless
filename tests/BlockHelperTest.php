<?php

declare(strict_types = 1);

use JohannSchopplich\Headless\BlocksResolver\BlockHelper;
use Kirby\Cms\App;
use Kirby\Cms\Block;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BlockHelperTest extends TestCase
{
    private App $kirby;

    protected function setUp(): void
    {
        $this->kirby = new App([
            'roots' => ['index' => __DIR__],
            'site' => [
                'children' => [
                    ['slug' => 'test', 'template' => 'default']
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        App::destroy();
    }

    private function block(array $content = [], string $type = 'gallery'): Block
    {
        return new Block([
            'type' => $type,
            'id' => 'block-1',
            'isHidden' => false,
            'content' => $content
        ]);
    }

    #[Test]
    public function merge_resolved_value_accumulates_multiple_fields_under_one_resolved_key(): void
    {
        // A block resolving two file fields into the same resolved bucket
        $block = $this->block(['image' => 'a', 'cover' => 'b']);
        $content = [];

        BlockHelper::mergeResolvedValue($content, $block, 'image', ['resolved-image'], 'resolved');
        BlockHelper::mergeResolvedValue($content, $block, 'cover', ['resolved-cover'], 'resolved');

        // Both resolved values must survive; the second must not clobber the first
        $this->assertSame([
            'image' => ['resolved-image'],
            'cover' => ['resolved-cover']
        ], $content['resolved']);
    }

    #[Test]
    public function merge_resolved_value_sets_top_level_key_when_no_resolved_key(): void
    {
        $block = $this->block(['page' => 'a']);
        $content = [];

        BlockHelper::mergeResolvedValue($content, $block, 'page', ['resolved'], null);

        $this->assertSame(['resolved'], $content['page']);
    }

    #[Test]
    public function merge_resolved_value_lowercases_field_key_inside_resolved_bucket(): void
    {
        $block = $this->block(['backgroundImage' => 'a']);
        $content = [];

        BlockHelper::mergeResolvedValue($content, $block, 'backgroundImage', ['resolved'], 'resolved');

        $this->assertSame(['backgroundimage' => ['resolved']], $content['resolved']);
    }

    #[Test]
    public function create_block_with_content_preserves_id_type_hidden_and_parent(): void
    {
        $page = $this->kirby->page('test');
        $block = new Block([
            'type' => 'heading',
            'id' => 'block-42',
            'isHidden' => true,
            'content' => ['text' => 'Original'],
            'parent' => $page
        ]);

        $new = BlockHelper::createBlockWithContent($block, ['text' => 'Updated']);

        $this->assertSame('block-42', $new->id());
        $this->assertSame('heading', $new->type());
        $this->assertTrue($new->isHidden());
        $this->assertSame($page, $new->parent());
        $this->assertSame('Updated', $new->content()->get('text')->value());
    }
}
