<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless\BlocksResolver;

use Kirby\Cms\Block;

final readonly class CustomFieldResolver
{
    public function __invoke(Block $block): Block
    {
        $kirby = $block->kirby();
        $resolvers = $kirby->option('blocksResolver.resolvers', []);
        $resolvedKey = $kirby->option('blocksResolver.resolvedKey');
        $content = $block->content()->data();
        $hasChanges = false;

        foreach ($resolvers as $identifier => $resolver) {
            // A resolver is keyed `blockType:fieldName`.
            if (!str_contains($identifier, ':')) {
                continue;
            }

            [$blockType, $key] = explode(':', $identifier);

            if ($block->type() !== $blockType) {
                continue;
            }

            BlockHelper::mergeResolvedValue(
                $content,
                $block,
                $key,
                $resolver($block->content()->get($key), $block),
                $resolvedKey
            );

            $hasChanges = true;
        }

        return $hasChanges
            ? BlockHelper::createBlockWithContent($block, $content)
            : $block;
    }
}
