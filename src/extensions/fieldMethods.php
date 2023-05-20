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
    $resolver = $kirby->option('resolvers.files', fn ($image) => [
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
     * Enhances the `toBlocks()` method to resolve images
     *
     * @kql-allowed
     */
    'toResolvedBlocks' => function (\Kirby\Cms\Field $field) use ($filesFieldResolver, $nestedBlocksFieldResolver) {
        return $field
            ->toBlocks()
            ->map($nestedBlocksFieldResolver)
            ->map($filesFieldResolver);
    },

    /**
     * Enhances the `toLayouts()` method to resolve images
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (\Kirby\Cms\Field $field) use ($filesFieldResolver) {
        return $field
            ->toLayouts()
            ->map(function (\Kirby\Cms\Layout $layout) use ($filesFieldResolver) {
                foreach ($layout->columns() as $column) {
                    $column->blocks()->map($filesFieldResolver);
                }

                return $layout;
            });
    }
];
