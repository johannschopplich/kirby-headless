<?php

use JohannSchopplich\Headless\FrontendUrl;
use JohannSchopplich\Headless\PageLanguages;
use Kirby\Cms\Page;

return [
    /**
     * Returns the page's URL rebased onto `headless.panel.frontendUrl`,
     * or `null` when that option is unset.
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
     * Returns the title and URI in each language the page is translated
     * into; a page without any content lists every language.
     *
     * @kql-allowed
     */
    'i18nMeta' => function (): array {
        /** @var \Kirby\Cms\Page $this */
        $languageCodes = PageLanguages::of($this)->codes();
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
