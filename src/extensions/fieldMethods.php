<?php

use JohannSchopplich\Headless\BlocksResolver\CustomFieldResolver;
use JohannSchopplich\Headless\BlocksResolver\FilesFieldResolver;
use JohannSchopplich\Headless\BlocksResolver\PagesFieldResolver;
use Kirby\Cms\Layout;
use Kirby\Cms\LayoutColumn;
use Kirby\Content\Field;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Dom;
use Kirby\Uuid\Uuid;

return [
    /**
     * Resolves UUID-based permalinks to actual URLs in anchor and image tags
     *
     * Converts `page://xxx` and `file://xxx` permalinks to their respective URLs
     * Supports custom URL parser via `permalinksResolver.urlParser` option
     *
     * @kql-allowed
     */
    'resolvePermalinks' => function (Field $field): Field {
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
     * Converts field to blocks and resolves all nested content
     *
     * Resolves page references, file references, and custom fields within blocks
     *
     * @kql-allowed
     */
    'toResolvedBlocks' => function (Field $field): mixed {
        /** @var \Kirby\Cms\Blocks */
        $blocks = $field->toBlocks();

        $pagesResolver = new PagesFieldResolver();
        $filesResolver = new FilesFieldResolver();
        $customResolver = new CustomFieldResolver();

        return $blocks
            ->map($pagesResolver)
            ->map($filesResolver)
            ->map($customResolver);
    },

    /**
     * Converts field to layouts and resolves all nested content
     *
     * Resolves page references, file references, and custom fields within layout blocks
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (Field $field): mixed {
        /** @var \Kirby\Cms\Layouts */
        $layouts = $field->toLayouts();

        $pagesResolver = new PagesFieldResolver();
        $filesResolver = new FilesFieldResolver();
        $customResolver = new CustomFieldResolver();

        return $layouts
            ->map(function (Layout $layout) use ($pagesResolver, $filesResolver, $customResolver) {
                $columns = $layout
                    ->columns()
                    ->map(function (LayoutColumn $column) use ($pagesResolver, $filesResolver, $customResolver) {
                        $blocks = $column
                            ->blocks()
                            ->map($pagesResolver)
                            ->map($filesResolver)
                            ->map($customResolver);

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
