<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless\BlocksResolver;

use Closure;
use Kirby\Cms\Collection;
use Kirby\Cms\File;
use Kirby\Content\Field;

final readonly class FilesFieldResolver extends ReferenceFieldResolver
{
    protected function optionNamespace(): string
    {
        return 'files';
    }

    protected function defaultBlocks(): array
    {
        return ['image' => 'image'];
    }

    protected function defaultResolver(): Closure
    {
        return fn (File $image) => [
            'url' => $image->url(),
            'width' => $image->width(),
            'height' => $image->height(),
            'srcset' => $image->srcset(),
            'alt' => $image->alt()->value()
        ];
    }

    protected function toCollection(Field $field): Collection
    {
        return $field->toFiles();
    }
}
