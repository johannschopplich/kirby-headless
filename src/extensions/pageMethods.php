<?php

use JohannSchopplich\Headless\FrontendUrl;
use Kirby\Cms\Page;

return [
    /**
     * Returns the frontend URL for this page based on configuration.
     *
     * @kql-allowed
     */
    'frontendUrl' => function (): string|null {
        /** @var \Kirby\Cms\Page $this */
        return FrontendUrl::resolve($this->url());
    },

    /**
     * Returns breadcrumb navigation metadata.
     *
     * Generates an array of page titles and URIs from current page to site root.
     *
     * @kql-allowed
     */
    'breadcrumbMeta' => function (): array {
        /** @var \Kirby\Cms\Page $this */
        return $this->parents()
            ->flip()
            ->add($this)
            ->values(fn (Page $page) => [
                'title' => $page->title()->value(),
                'uri' => $page->uri()
            ]);
    },

    /**
     * Returns internationalization metadata for all languages.
     *
     * Provides translated titles and URIs for each configured language.
     *
     * @kql-allowed
     */
    'i18nMeta' => function (): array {
        /** @var \Kirby\Cms\Page $this */
        $languageCodes = $this->kirby()->languages()->codes();
        $meta = [];

        foreach ($languageCodes as $languageCode) {
            $meta[$languageCode] = [
                'title' => $this->content($languageCode)->get('title')->value(),
                'uri' => $this->uri($languageCode)
            ];
        }

        return $meta;
    }
];
