<?php

use Kirby\Toolkit\Str;

return [
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
     * @kql-allowed
     */
    'breadcrumbMeta' => function (): array {
        /** @var \Kirby\Cms\Page $this */
        $breadcrumb = [];
        $parent = $this->parent();

        while ($parent) {
            $breadcrumb[] = [
                'title' => $parent->title()->value(),
                'uri' => $parent->uri()
            ];

            $parent = $parent->parent();
        }

        $breadcrumb[] = [
            'title' => $this->title()->value(),
            'uri' => $this->uri()
        ];

        return array_reverse($breadcrumb);
    },

    /**
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
