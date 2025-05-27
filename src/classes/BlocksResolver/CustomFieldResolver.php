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

        foreach ($resolvers as $identifier => $resolver) {
            [$blockType, $key] = explode(':', $identifier);

            if ($block->type() !== $blockType) {
                continue;
            }

            $field = $block->content()->get($key);
            $resolvedKey = $kirby->option('blocksResolver.resolvedKey');

            // Update content with resolved field value and create a new block
            $newContent = BlockHelper::updateBlockContent(
                $block,
                $key,
                $resolver($field, $block),
                $resolvedKey
            );

            return BlockHelper::createBlockWithContent($block, $newContent);
        }

        return $block;
    }
}
