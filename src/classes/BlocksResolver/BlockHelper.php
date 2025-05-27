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
     * Updates block content with resolved field values
     */
    public static function updateBlockContent(
        Block $block,
        string $key,
        mixed $value,
        string|null $resolvedKey = null
    ): array {
        $currentContent = $block->content()->data();
        $newContent = $currentContent;

        if (!empty($resolvedKey)) {
            $resolvedData = $block->content()->get($resolvedKey)->or([])->value();
            $newContent[$resolvedKey] = array_merge($resolvedData, [
                strtolower($key) => $value
            ]);
        } else {
            $newContent[$key] = $value;
        }

        return $newContent;
    }
}
