<?php

namespace JohannSchopplich\Headless\BlocksResolver;

use Kirby\Cms\Block;
use Kirby\Cms\Page;

/**
 * Resolver for page fields in blocks
 */
final readonly class PagesFieldResolver
{
    public function __invoke(Block $block): Block
    {
        $kirby = $block->kirby();
        $blocks = $kirby->option('blocksResolver.pages', []);

        // If the block type isn't one to be resolved, return early
        if (!isset($blocks[$block->type()])) {
            return $block;
        }

        // Get the resolver method
        $resolvers = $kirby->option('blocksResolver.resolvers', []);
        $defaultResolver = $kirby->option('blocksResolver.defaultResolvers.pages', fn (Page $page) => [
            'uri' => $page->uri(),
            'title' => $page->title()->value()
        ]);

        $fieldKeys = $blocks[$block->type()];
        $fieldKeys = is_array($fieldKeys) ? $fieldKeys : [$fieldKeys];
        $currentContent = $block->content()->data();
        $hasChanges = false;
        $resolvedKey = $kirby->option('blocksResolver.resolvedKey');

        foreach ($fieldKeys as $key) {
            /** @var \Kirby\Cms\Pages */
            $pages = $block->content()->get($key)->toPages();

            if ($pages->count() === 0) {
                continue;
            }

            // If part of custom resolver, skip
            if (isset($resolvers[$block->type() . ':' . $key])) {
                continue;
            }

            $resolvedValue = $pages->map($defaultResolver)->values();

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
