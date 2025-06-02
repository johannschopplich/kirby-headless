<?php

namespace JohannSchopplich\Headless\BlocksResolver;

use Kirby\Cms\Block;

/**
 * Resolver for custom fields in blocks
 */
final readonly class CustomFieldResolver
{
    public function __invoke(Block $block): Block
    {
        $kirby = $block->kirby();
        $resolvers = $kirby->option('blocksResolver.resolvers', []);
        $currentContent = $block->content()->data();
        $hasChanges = false;

        foreach ($resolvers as $identifier => $resolver) {
            [$blockType, $key] = explode(':', $identifier);

            if ($block->type() !== $blockType) {
                continue;
            }

            $field = $block->content()->get($key);
            $resolvedKey = $kirby->option('blocksResolver.resolvedKey');
            $resolvedValue = $resolver($field, $block);

            // Merge resolved value into content
            BlockHelper::mergeResolvedValue($currentContent, $block, $key, $resolvedValue, $resolvedKey);
            $hasChanges = true;
        }

        // Only create a new block if there were changes
        return $hasChanges
            ? BlockHelper::createBlockWithContent($block, $currentContent)
            : $block;
    }
}
