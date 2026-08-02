<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless\BlocksResolver;

use Closure;
use Kirby\Cms\Collection;
use Kirby\Cms\Page;
use Kirby\Content\Field;

/**
 * Resolver for page fields in blocks.
 */
final readonly class PagesFieldResolver extends ReferenceFieldResolver
{
    protected function optionNamespace(): string
    {
        return 'pages';
    }

    protected function defaultBlocks(): array
    {
        return [];
    }

    protected function defaultResolver(): Closure
    {
        return fn (Page $page) => [
            'uri' => $page->uri(),
            'title' => $page->title()->value()
        ];
    }

    protected function toCollection(Field $field): Collection
    {
        return $field->toPages();
    }
}
