<?php

use JohannSchopplich\Headless\BlocksResolver\CustomFieldResolver;
use JohannSchopplich\Headless\BlocksResolver\FilesFieldResolver;
use JohannSchopplich\Headless\BlocksResolver\PagesFieldResolver;
use Kirby\Cms\Blocks;
use Kirby\Cms\Layout;
use Kirby\Cms\LayoutColumn;
use Kirby\Cms\Layouts;
use Kirby\Content\Field;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Dom;
use Kirby\Uuid\Uuid;

return [
    /**
     * Resolves `page://` and `file://` permalinks in `href` and `src` attributes.
     *
     * The `permalinksResolver.urlParser` option rewrites every resolved URL.
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
                            // An attribute that is not a permalink is left as it is
                        }
                    }
                }
            }

            $field->value = $dom->toString();
        }

        return $field;
    },

    /**
     * Converts the field to blocks and resolves the references they hold.
     *
     * @kql-allowed
     */
    'toResolvedBlocks' => function (Field $field): Blocks {
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
     * Converts the field to layouts and resolves the references their blocks hold.
     *
     * @kql-allowed
     */
    'toResolvedLayouts' => function (Field $field): Layouts {
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
