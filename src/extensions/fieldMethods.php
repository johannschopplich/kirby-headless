<?php

use Kirby\Cms\Block;
use Kirby\Cms\File;
use Kirby\Cms\Layout;
use Kirby\Cms\LayoutColumn;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Dom;
use Kirby\Uuid\Uuid;

// Define helper functions in a closure to avoid polluting the global namespace
$helpers = (function () {
    /**
     * Helper function to create a new block with updated content
     */
    $createBlockWithContent = function (Block $block, array $newContent): Block {
        return new Block([
            'content' => $newContent,
            'id' => $block->id(),
            'isHidden' => $block->isHidden(),
            'type' => $block->type(),
            'parent' => $block->parent(),
            'siblings' => $block->siblings()
        ]);
    };

    /**
     * Helper function to update block content with resolved field values
     */
    $updateBlockContent = function (Block $block, string $key, mixed $value, string|null $resolvedKey = null): array {
        $existingContent = $block->content()->data();
        $newContent = $existingContent;

        if (!empty($resolvedKey)) {
            $resolvedData = $block->content()->get($resolvedKey)->or([])->value();
            $newContent[$resolvedKey] = array_merge($resolvedData, [
                strtolower($key) => $value
            ]);
        } else {
            $newContent[$key] = $value;
        }

        return $newContent;
    };

    return compact(
        'createBlockWithContent',
        'updateBlockContent'
    );
})();

$filesFieldResolver = function (Block $block) use ($helpers) {
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

        $resolvedKey = $kirby->option('blocksResolver.resolvedKey');

        // Update content with resolved images and create a new block
        $newContent = $helpers['updateBlockContent'](
            $block,
            $key,
            $images->map($defaultResolver)->values(),
            $resolvedKey
        );

        // Create and return new block with updated content
        return $helpers['createBlockWithContent']($block, $newContent);
    }

    return $block;
};

$pagesFieldResolver = function (Block $block) use ($helpers) {
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

        $resolvedKey = $kirby->option('blocksResolver.resolvedKey');

        // Update content with resolved pages and create a new block
        $newContent = $helpers['updateBlockContent'](
            $block,
            $key,
            $pages->map($defaultResolver)->values(),
            $resolvedKey
        );

        // Create and return new block with updated content
        return $helpers['createBlockWithContent']($block, $newContent);
    }

    return $block;
};

// Support any field type
$customFieldResolver = function (Block $block) use ($helpers) {
    $kirby = $block->kirby();
    $resolvers = $kirby->option('blocksResolver.resolvers', []);

    foreach ($resolvers as $identifier => $resolver) {
        [$blockType, $key] = explode(':', $identifier);

        if ($block->type() !== $blockType) {
            continue;
        }

        $field = $block->content()->get($key);

        $resolvedKey = $kirby->option('blocksResolver.resolvedKey');

        // Update content with resolved field value and create a new block
        $newContent = $helpers['updateBlockContent'](
            $block,
            $key,
            $resolver($field, $block),
            $resolvedKey
        );

        // Create and return new block with updated content
        return $helpers['createBlockWithContent']($block, $newContent);
    }

    return $block;
};

return [
    /**
     * Resolves page and file permalinks in anchor tags
     *
     * @kql-allowed
     */
    'resolvePermalinks' => function (Field $field) {
        $kirby = $field->parent()->kirby();
        $urlParser = $kirby->option('permalinksResolver.urlParser');

        if ($field->isNotEmpty()) {
            $dom = new Dom($field->value);
            $attributes = ['href', 'src'];
            $elements = $dom->query('//*[' . implode(' | ', A::map($attributes, fn ($attribute) => '@' . $attribute)) . ']');

            foreach ($elements as $element) {
                foreach ($attributes as $attribute) {
                    if ($element->hasAttribute($attribute) && $uuid = $element->getAttribute($attribute)) {
                        try {
                            if ($url = Uuid::for($uuid)?->model()?->url()) {
                                if (is_callable($urlParser)) {
                                    $url = $urlParser($url, $kirby);
                                }
                                $element->setAttribute($attribute, $url);
                            }
                        } catch (InvalidArgumentException) {
                            // Ignore anything else than permalinks
                        }
                    }
                }
            }

            $field->value = $dom->toString();
        }

        return $field;
    },

    /**
     * Enhances the `toBlocks()` method to resolve files, pages, and other fields
     *
     * @kql-allowed
     */
    'toResolvedBlocks' => function (Field $field) use ($pagesFieldResolver, $filesFieldResolver, $customFieldResolver) {
        /** @var \Kirby\Cms\Blocks */
        $blocks = $field->toBlocks();
        return $blocks
            ->map($pagesFieldResolver)
            ->map($filesFieldResolver)
            ->map($customFieldResolver);
    },

    /**
     * Enhances the `toLayouts()` method to resolve files, pages, and other fields
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (Field $field) use ($filesFieldResolver, $pagesFieldResolver, $customFieldResolver) {
        /** @var \Kirby\Cms\Layouts */
        $layouts = $field->toLayouts();
        return $layouts
            ->map(function (Layout $layout) use ($filesFieldResolver, $pagesFieldResolver, $customFieldResolver) {
                $columns = $layout
                    ->columns()
                    ->map(function (LayoutColumn $column) use ($filesFieldResolver, $pagesFieldResolver, $customFieldResolver) {
                        $blocks = $column
                            ->blocks()
                            ->map($filesFieldResolver)
                            ->map($pagesFieldResolver)
                            ->map($customFieldResolver);

                        return [
                            'id' => $column->id(),
                            'blocks' => $blocks->toArray(),
                            'width' => $column->width()
                        ];
                    });

                return new Layout([
                    'id' => $layout->id(),
                    'field' => $layout->field(),
                    'parent' => $layout->parent(),
                    'siblings' => $layout->siblings(),
                    'columns' => $columns->values(),
                    'attrs' => $layout->attrs()->toArray()
                ]);
            });
    }
];
