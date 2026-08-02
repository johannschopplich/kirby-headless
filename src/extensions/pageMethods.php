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
     * Returns breadcrumb navigation metadata, from the site root down to this page.
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
     * Returns the translated title and URI per configured language.
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
