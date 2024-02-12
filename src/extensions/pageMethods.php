<?php

use Kirby\Toolkit\Str;

return [
    'frontendUrl' => function () {
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
    'i18nMeta' => function () {
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
