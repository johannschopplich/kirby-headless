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
    'toResolvedBlocks' => function (Field $field) {
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
     * Enhances the `toLayouts()` method to resolve files, pages, and other fields
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (Field $field) {
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
