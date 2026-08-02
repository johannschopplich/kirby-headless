<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless\BlocksResolver;

use Closure;
use Kirby\Cms\Block;
use Kirby\Cms\Collection;
use Kirby\Content\Field;

/**
 * Turns the references a block holds into ready-made data for the frontend.
 */
abstract readonly class ReferenceFieldResolver
{
    final public function __invoke(Block $block): Block
    {
        $kirby = $block->kirby();
        $blocks = $kirby->option('blocksResolver.' . $this->optionNamespace(), $this->defaultBlocks());

        if (!isset($blocks[$block->type()])) {
            return $block;
        }

        $resolvers = $kirby->option('blocksResolver.resolvers', []);
        $resolvedKey = $kirby->option('blocksResolver.resolvedKey');
        $defaultResolver = $kirby->option(
            'blocksResolver.defaultResolvers.' . $this->optionNamespace(),
            $this->defaultResolver()
        );

        $fieldKeys = $blocks[$block->type()];
        $fieldKeys = is_array($fieldKeys) ? $fieldKeys : [$fieldKeys];
        $content = $block->content()->data();
        $hasChanges = false;

        foreach ($fieldKeys as $key) {
            // A resolver registered for this very field takes precedence
            if (isset($resolvers[$block->type() . ':' . $key])) {
                continue;
            }

            $references = $this->toCollection($block->content()->get($key));

            if ($references->count() === 0) {
                continue;
            }

            BlockHelper::mergeResolvedValue(
                $content,
                $block,
                $key,
                $references->map($defaultResolver)->values(),
                $resolvedKey
            );

            $hasChanges = true;
        }

        return $hasChanges
            ? BlockHelper::createBlockWithContent($block, $content)
            : $block;
    }

    /**
     * Returns the `blocksResolver` namespace this resolver reads its options from.
     */
    abstract protected function optionNamespace(): string;

    /**
     * Returns the block types and fields resolved without any configuration.
     */
    abstract protected function defaultBlocks(): array;

    /**
     * Returns the shape a reference takes when no custom resolver is configured.
     */
    abstract protected function defaultResolver(): Closure;

    /**
     * Returns the references a field holds.
     */
    abstract protected function toCollection(Field $field): Collection;
}
