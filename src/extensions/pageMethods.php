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
    }
];
