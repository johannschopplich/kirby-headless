<?php

$filesResolver = function (\Kirby\Cms\Block $item) {
    $kirby = $item->kirby();
    $keys = array_values($kirby->option('blocksResolver.files', ['image' => 'image']));

    // Flatten keys, since the option values can be arrays
    $keys = array_reduce(
        $keys,
        fn ($acc, $i) => array_merge($acc, is_array($i) ? $i : [$i]),
        []
    );

    // Get the resolver method
    $resolver = $kirby->option('blocksResolver.resolvers.files', fn (\Kirby\Cms\File $image) => [
        'url' => $image->url(),
        'width' => $image->width(),
        'height' => $image->height(),
        'srcset' => $image->srcset(),
        'alt' => $image->alt()->value()
    ]);

    foreach ($keys as $key) {
        /** @var \Kirby\Cms\Files $images */
        $images = $item->content()->get($key)->toFiles();

        if ($images->count() === 0) {
            continue;
        }

        // Get already resolved images
        $resolved = $item->content()->get('resolved')->or([])->value();

        // Replace the image field with the resolved image
        $item->content()->update([
            'resolved' => array_merge($resolved, [
                $key => $images->map($resolver)->values()
            ])
        ]);
    }

    return $item;
};

$filesFieldResolver = function (\Kirby\Cms\Block $block) use ($filesResolver) {
    $kirby = $block->kirby();
    $resolvers = $kirby->option('blocksResolver.files', ['image' => 'image']);

    if (isset($resolvers[$block->type()])) {
        return $filesResolver($block);
    }

    return $block;
};

$pagesResolver = function (\Kirby\Cms\Block $item) {
    $kirby = $item->kirby();
    $keys = array_values($kirby->option('blocksResolver.pages', []));

    $keys = array_reduce(
        $keys,
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
        $pages = $item->content()->get($key)->toPages();

        if ($pages->count() === 0) {
            continue;
        }

        $resolved = $item->content()->get('resolved')->or([])->value();

        $item->content()->update([
            'resolved' => array_merge($resolved, [
                $key => $pages->map($resolver)->values()
            ])
        ]);
    }

    return $item;
};

$pagesFieldResolver = function (\Kirby\Cms\Block $block) use ($pagesResolver) {
    $kirby = $block->kirby();
    $resolvers = $kirby->option('blocksResolver.pages', []);

    if (isset($resolvers[$block->type()])) {
        return $pagesResolver($block);
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
