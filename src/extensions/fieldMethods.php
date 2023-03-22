<?php

$filesResolver = function (\Kirby\Cms\Block $item) {
    $keys = array_values(option('blocksResolver.files', ['image' => 'image']));

    // Flatten keys, since the option values can be arrays
    $keys = array_reduce(
        $keys,
        fn ($acc, $i) => array_merge($acc, is_array($i) ? $i : [$i]),
        []
    );

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
                $key => $images->map(fn ($image) => [
                    'url'    => $image->url(),
                    'width'  => $image->width(),
                    'height' => $image->height(),
                    'srcset' => $image->srcset(),
                    'alt'    => $image->alt()->value()
                ])->values()
            ])
        ]);
    }

    return $item;
};

$filesFieldResolver = function (\Kirby\Cms\Block $block) use ($filesResolver) {
    if (in_array(
        $block->type(),
        array_keys(option('blocksResolver.files', ['image' => 'image'])),
        true
    )) {
        return $filesResolver($block);
    }

    return $block;
};

$nestedBlocksFieldResolver = function (\Kirby\Cms\Block $block) use ($filesFieldResolver) {
    /** @var \Kirby\Cms\Block $block */
    $nestedBlocks = option('blocksResolver.nested', ['prose']);
    $blocksKeys = array_filter(
        $block->content()->keys(),
        fn ($i) => in_array($i, $nestedBlocks, true)
    );

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
                $columns = $layout->columns();

                foreach ($columns as $column) {
                    $column->blocks()->map($filesFieldResolver);
                }

                return $layout;
            });
    }
];
