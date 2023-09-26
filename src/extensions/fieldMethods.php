<?php

$filesFieldResolver = function (\Kirby\Cms\Block $block) {
    $kirby = $block->kirby();
    $blocks = $kirby->option('blocksResolver.files', ['image' => 'image']);

    if (!isset($blocks[$block->type()])) {
        return $block;
    }

    // Flatten keys, since the option values can be arrays
    $keys = array_reduce(
        $blocks[$block->type()],
        fn ($acc, $i) => array_merge($acc, is_array($i) ? $i : [$i]),
        []
    );

    // Get the resolvers config
    $resolvers = $kirby->option('blocksResolver.resolvers.files');
    $defaultResolver = fn (\Kirby\Cms\File $image) => [
        'url' => $image->url(),
        'width' => $image->width(),
        'height' => $image->height(),
        'srcset' => $image->srcset(),
        'alt' => $image->alt()->value()
    ];

    foreach ($keys as $key) {
        /** @var \Kirby\Cms\Files $images */
        $images = $block->content()->get($key)->toFiles();

        if ($images->count() === 0) {
            continue;
        }

        // Get already resolved images
        $resolved = $block->content()->get('resolved')->or([])->value();

        // Get specific resolver for the current block and key or fallback to default
        $resolver = is_callable($resolvers)
            ? $resolvers
            : $resolvers[$block->type()][$key] ?? $resolvers['_default'] ?? $defaultResolver;

        // Replace the image field with the resolved image
        $block->content()->update([
            'resolved' => array_merge($resolved, [
                strtolower($key) => $images->map($resolver)->values()
            ])
        ]);
    }

    return $block;
};

$pagesFieldResolver = function (\Kirby\Cms\Block $block) {
    $kirby = $block->kirby();
    $blocks = $kirby->option('blocksResolver.pages', []);

    if (!isset($blocks[$block->type()])) {
        return $block;
    }

    $keys = array_reduce(
        $blocks[$block->type()],
        fn ($acc, $i) => array_merge($acc, is_array($i) ? $i : [$i]),
        []
    );

    // Get the resolver method
    $resolver = $kirby->option('blocksResolver.resolvers.pages', fn (\Kirby\Cms\Page $page) => [
        'uri' => $page->uri(),
        'title' => $page->title()->value()
    ]);

    foreach ($keys as $key) {
        /** @var \Kirby\Cms\Pages $pages */
        $pages = $block->content()->get($key)->toPages();

        if ($pages->count() === 0) {
            continue;
        }

        $resolved = $block->content()->get('resolved')->or([])->value();

        $block->content()->update([
            'resolved' => array_merge($resolved, [
                strtolower($key) => $pages->map($resolver)->values()
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
    'toResolvedBlocks' => function (\Kirby\Cms\Field $field) use ($pagesFieldResolver, $filesFieldResolver, $nestedBlocksFieldResolver) {
        return $field
            ->toBlocks()
            ->map($nestedBlocksFieldResolver)
            ->map($pagesFieldResolver)
            ->map($filesFieldResolver);
    },

    /**
     * Enhances the `toLayouts()` method to resolve files and pages
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (\Kirby\Cms\Field $field) use ($filesFieldResolver, $pagesFieldResolver) {
        return $field
            ->toLayouts()
            ->map(function (\Kirby\Cms\Layout $layout) use ($filesFieldResolver, $pagesFieldResolver) {
                foreach ($layout->columns() as $column) {
                    $column
                        ->blocks()
                        ->map($filesFieldResolver)
                        ->map($pagesFieldResolver);
                }

                return $layout;
            });
    }
];
