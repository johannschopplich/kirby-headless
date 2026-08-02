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
    public function replaces_the_field_itself_when_no_resolved_key_is_configured(): void
    {
        $block = $this->block(['page' => 'a']);
        $content = [];

        BlockHelper::mergeResolvedValue($content, $block, 'page', ['resolved'], null);

        $this->assertSame(['resolved'], $content['page']);
    }

    #[Test]
    public function lowercases_the_field_key_inside_the_resolved_bucket(): void
    {
        $block = $this->block(['backgroundImage' => 'a']);
        $content = [];

        BlockHelper::mergeResolvedValue($content, $block, 'backgroundImage', ['resolved'], 'resolved');

        $this->assertSame(['backgroundimage' => ['resolved']], $content['resolved']);
    }

    #[Test]
    public function keeps_a_blocks_identity_when_its_content_is_replaced(): void
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
