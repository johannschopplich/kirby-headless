<?php

$imageResolver = function (\Kirby\Cms\Block $item) {
    foreach (array_values(option('blocksResolver.files', ['image' => 'image'])) as $key) {
        /** @var \Kirby\Cms\File|null $image */
        $image = $item->content()->get($key)->toFile();

        if (!$image) {
            continue;
        }

        // Replace the image field with the resolved image
        $item->content()->update([
            'resolved' => [
                $key => [
                    'url'    => $image->url(),
                    'width'  => $image->width(),
                    'height' => $image->height(),
                    'srcset' => $image->srcset(),
                    'alt'    => $image->alt()->value()
                ]
            ]
        ]);
    }

    return $item;
};

$imageFieldResolver = function (\Kirby\Cms\Block $block) use ($imageResolver) {
    if (in_array(
        $block->type(),
        array_keys(option('blocksResolver.files', ['image' => 'image'])),
        true
    )) {
        return $imageResolver($block);
    }

    return $block;
};

$nestedBlocksResolver = function (\Kirby\Cms\Block $block) use ($imageFieldResolver) {
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
            $key => $field->toBlocks()->map($imageFieldResolver)->toArray()
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
    'toResolvedBlocks' => function (\Kirby\Cms\Field $field) use ($imageFieldResolver, $nestedBlocksResolver) {
        return $field
            ->toBlocks()
            ->map($nestedBlocksResolver)
            ->map($imageFieldResolver);
    },

    /**
     * Enhances the `toLayouts()` method to resolve images
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (\Kirby\Cms\Field $field) use ($imageFieldResolver) {
        return $field
            ->toLayouts()
            ->map(function (\Kirby\Cms\Layout $layout) use ($imageFieldResolver) {
                $columns = $layout->columns();

                foreach ($columns as $column) {
                    $column->blocks()->map($imageFieldResolver);
                }

                return $layout;
            });
    }
];
