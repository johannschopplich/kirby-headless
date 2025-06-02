<?php

namespace JohannSchopplich\Headless\BlocksResolver;

use Kirby\Cms\Block;

final readonly class BlockHelper
{
    /**
     * Creates a new block with updated content
     */
    public static function createBlockWithContent(Block $block, array $newContent): Block
    {
        return new Block([
            'content' => $newContent,
            'id' => $block->id(),
            'isHidden' => $block->isHidden(),
            'type' => $block->type(),
            'parent' => $block->parent(),
            'siblings' => $block->siblings()
        ]);
    }

    /**
     * Merges resolved field values into content array
     */
    public static function mergeResolvedValue(
        array &$content,
        Block $block,
        string $key,
        mixed $value,
        string|null $resolvedKey = null
    ): void {
        if (!empty($resolvedKey)) {
            $resolvedData = $block->content()->get($resolvedKey)->or([])->value();
            $content[$resolvedKey] = array_merge($resolvedData, [
                strtolower($key) => $value
            ]);
        } else {
            $content[$key] = $value;
        }
    }
}
