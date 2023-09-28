<?php

$filesFieldResolver = function (\Kirby\Cms\Block $block) {
    $kirby = $block->kirby();
    $blocks = $kirby->option('blocksResolver.files', ['image' => 'image']);

    // If the block type isn't one to be resolved, return early
    if (!isset($blocks[$block->type()])) {
        return $block;
    }

    // Get the resolvers config
    $resolvers = $kirby->option('blocksResolver.resolvers', []);
    $defaultResolver = $kirby->option('blocksResolver.defaultResolvers.files', fn (\Kirby\Cms\File $image) => [
        'url' => $image->url(),
        'width' => $image->width(),
        'height' => $image->height(),
        'srcset' => $image->srcset(),
        'alt' => $image->alt()->value()
    ]);

    $fieldKeys = $blocks[$block->type()];
    $fieldKeys = is_array($fieldKeys) ? $fieldKeys : [$fieldKeys];

    foreach ($fieldKeys as $key) {
        /** @var \Kirby\Cms\Files $images */
        $images = $block->content()->get($key)->toFiles();

        if ($images->count() === 0) {
            continue;
        }

        // If part of custom resolver, skip
        if (isset($resolvers[$block->type() . ':' . $key])) {
            continue;
        }

        // Get already resolved images
        $resolved = $block->content()->get('resolved')->or([])->value();

        $block->content()->update([
            'resolved' => array_merge($resolved, [
                strtolower($key) => $images->map($defaultResolver)->values()
            ])
        ]);
    }

    return $block;
};

$pagesFieldResolver = function (\Kirby\Cms\Block $block) {
    $kirby = $block->kirby();
    $blocks = $kirby->option('blocksResolver.pages', []);

    // If the block type isn't one to be resolved, return early
    if (!isset($blocks[$block->type()])) {
        return $block;
    }

    // Get the resolver method
    $resolvers = $kirby->option('blocksResolver.resolvers', []);
    $defaultResolver = $kirby->option('blocksResolver.defaultResolvers.pages', fn (\Kirby\Cms\Page $page) => [
        'uri' => $page->uri(),
        'title' => $page->title()->value()
    ]);

    $fieldKeys = $blocks[$block->type()];
    $fieldKeys = is_array($fieldKeys) ? $fieldKeys : [$fieldKeys];

    foreach ($fieldKeys as $key) {
        /** @var \Kirby\Cms\Pages $pages */
        $pages = $block->content()->get($key)->toPages();

        if ($pages->count() === 0) {
            continue;
        }

        // If part of custom resolver, skip
        if (isset($resolvers[$block->type() . ':' . $key])) {
            continue;
        }

        // Get already resolved images
        $resolved = $block->content()->get('resolved')->or([])->value();

        $block->content()->update([
            'resolved' => array_merge($resolved, [
                strtolower($key) => $pages->map($defaultResolver)->values()
            ])
        ]);
    }

    return $block;
};

// Custom Resolvers
$customResolvers = function (\Kirby\Cms\Block $block) {
    $kirby = $block->kirby();
    $resolvers = $kirby->option('blocksResolver.resolvers', []);

    foreach ($resolvers as $identifier => $resolver) {
        [$blockType, $key] = explode(':', $identifier);

        if ($block->type() !== $blockType) {
            continue;
        }

        $resolved = $block->content()->get('resolved')->or([])->value();
        $field = $block->content()->get($key);

        $block->content()->update([
            'resolved' => array_merge($resolved, [
                strtolower($key) => $resolver($field)
            ])
        ]);
    }

    return $block;
};

$nestedBlocksFieldResolver = function (\Kirby\Cms\Block $block) use ($filesFieldResolver) {
    /** @var \Kirby\Cms\Block $block */
    $kirby = $block->kirby();
    $nestedBlocks = $kirby->option('blocksResolver.nested', ['prose']);
    $blocksKeys = array_intersect($block->content()->keys(), $nestedBlocks);

    foreach ($blocksKeys as $key) {
        /** @var \Kirby\Cms\Field $ktField */
        $field = $block->content()->get($key);

        $block->content()->update([
            $key => $field->toBlocks()->map($filesFieldResolver)->toArray()
        ]);
    }

    return $block;
};

return [
    /**
     * Enhances the `toBlocks()` method to resolve files and pages
     *
     * @kql-allowed
     */
    'toResolvedBlocks' => function (\Kirby\Cms\Field $field) use ($pagesFieldResolver, $filesFieldResolver, $customResolvers, $nestedBlocksFieldResolver) {
        return $field
            ->toBlocks()
            ->map($nestedBlocksFieldResolver)
            ->map($pagesFieldResolver)
            ->map($filesFieldResolver)
            ->map($customResolvers);
    },

    /**
     * Enhances the `toLayouts()` method to resolve files and pages
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (\Kirby\Cms\Field $field) use ($filesFieldResolver, $pagesFieldResolver, $customResolvers) {
        return $field
            ->toLayouts()
            ->map(function (\Kirby\Cms\Layout $layout) use ($filesFieldResolver, $pagesFieldResolver, $customResolvers) {
                foreach ($layout->columns() as $column) {
                    $column
                        ->blocks()
                        ->map($filesFieldResolver)
                        ->map($pagesFieldResolver)
                        ->map($customResolvers);
                }

                return $layout;
            });
    }
];
