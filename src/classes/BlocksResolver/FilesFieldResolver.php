<?php

namespace JohannSchopplich\Headless\BlocksResolver;

use Kirby\Cms\Block;
use Kirby\Cms\File;

/**
 * Resolver for file fields in blocks
 */
final readonly class FilesFieldResolver
{
    public function __invoke(Block $block): Block
    {
        $kirby = $block->kirby();
        $blocks = $kirby->option('blocksResolver.files', ['image' => 'image']);

        // If the block type isn't one to be resolved, return early
        if (!isset($blocks[$block->type()])) {
            return $block;
        }

        // Get the resolvers config
        $resolvers = $kirby->option('blocksResolver.resolvers', []);
        $defaultResolver = $kirby->option('blocksResolver.defaultResolvers.files', fn (File $image) => [
            'url' => $image->url(),
            'width' => $image->width(),
            'height' => $image->height(),
            'srcset' => $image->srcset(),
            'alt' => $image->alt()->value()
        ]);

        $fieldKeys = $blocks[$block->type()];
        $fieldKeys = is_array($fieldKeys) ? $fieldKeys : [$fieldKeys];
        $currentContent = $block->content()->data();
        $hasChanges = false;
        $resolvedKey = $kirby->option('blocksResolver.resolvedKey');

        foreach ($fieldKeys as $key) {
            /** @var \Kirby\Cms\Files */
            $images = $block->content()->get($key)->toFiles();

            if ($images->count() === 0) {
                continue;
            }

            // If part of custom resolver, skip
            if (isset($resolvers[$block->type() . ':' . $key])) {
                continue;
            }

            $resolvedValue = $images->map($defaultResolver)->values();

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
