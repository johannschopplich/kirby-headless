<?php

$imageBlockResolver = function (\Kirby\Cms\Block $block) {
    if ($block->type() !== 'image') {
        return $block;
    }

    /** @var \Kirby\Cms\File|null $image */
    $image = $block->content()->get('image')->toFile();

    if ($image) {
        $resolvedImage = [
            'url'    => $image->url(),
            'width'  => $image->width(),
            'height' => $image->height(),
            'srcset' => $image->srcset(),
            'alt'    => $image->alt()->value()
        ];

        // Replace the image field with the resolved image
        $block->content()->update(['resolved' => [
            'image' => $resolvedImage
        ]]);
    }

    return $block;
};

$nestedBlocksResolver = function (\Kirby\Cms\Block $block) use ($imageBlockResolver) {
    /** @var \Kirby\Cms\Block $block */
    $nestedBlocks = option('headless.blocks.nested', ['prose']);
    $blocksKeys = array_filter(
        $block->content()->keys(),
        fn ($i) => in_array($i, $nestedBlocks, true)
    );

    foreach ($blocksKeys as $key) {
        /** @var \Kirby\Cms\Field $ktField */
        $field = $block->content()->get($key);

        $block->content()->update([
            'prose' => $field->toBlocks()->map($imageBlockResolver)->toArray()
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
    'toResolvedBlocks' => function (\Kirby\Cms\Field $field) use ($imageBlockResolver, $nestedBlocksResolver) {
        return $field
            ->toBlocks()
            ->map($imageBlockResolver)
            ->map($nestedBlocksResolver);
    },

    /**
     * Enhances the `toLayouts()` method to resolve images
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (\Kirby\Cms\Field $field) use ($imageBlockResolver) {
        return $field
            ->toLayouts()
            ->map(function (\Kirby\Cms\Layout $layout) use ($imageBlockResolver) {
                $columns = $layout->columns();

                foreach ($columns as $column) {
                    $column->blocks()->map($imageBlockResolver);
                }

                return $layout;
            });
    }
];
