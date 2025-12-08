<?php

use Kirby\Toolkit\Str;

return [
    /**
     * Returns the frontend URL for this page based on configuration
     *
     * Replaces the Kirby site URL with the configured frontend URL
     *
     * @kql-allowed
     */
    'frontendUrl' => function (): string|null {
        /** @var \Kirby\Cms\Page $this */
        $url = $this->kirby()->option('headless.panel.frontendUrl');

        if (empty($url)) {
            return null;
        }

        return Str::replace(
            $this->url(),
            $this->kirby()->url(),
            $url
        );
    },

    /**
     * Returns breadcrumb navigation metadata
     *
     * Generates an array of page titles and URIs from current page to site root
     *
     * @kql-allowed
     */
    'breadcrumbMeta' => function (): array {
        /** @var \Kirby\Cms\Page $this */
        $breadcrumb = [];
        $breadcrumb[] = [
            'title' => $this->title()->value(),
            'uri' => $this->uri()
        ];
        $parent = $this->parent();

        while ($parent) {
            $breadcrumb[] = [
                'title' => $parent->title()->value(),
                'uri' => $parent->uri()
            ];

            $parent = $parent->parent();
        }

        return array_reverse($breadcrumb);
    },

    /**
     * Returns internationalization metadata for all languages
     *
     * Provides translated titles and URIs for each configured language
     *
     * @kql-allowed
     */
    'i18nMeta' => function (): array {
        /** @var \Kirby\Cms\Page $this */
        $locales = $this->kirby()->languages()->codes();
        $meta = [];

        foreach ($locales as $locale) {
            $meta[$locale] = [
                'title' => $this->content($locale)->get('title')->value(),
                'uri' => $this->uri($locale)
            ];
        }

        return $meta;
    }
];
