<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless;

use Kirby\Cms\App;
use Kirby\Cms\Languages;
use Kirby\Cms\Page;

final readonly class PageLanguages
{
    /**
     * Returns the languages the page is translated into. Kirby renders a
     * missing translation from the default language, so a URL in any other
     * language would only duplicate the default page. A page without any
     * content, such as a virtual page without content props, exists in no
     * language yet renders in every one, so it counts as translated into all.
     */
    public static function of(Page $page): Languages
    {
        $languages = App::instance()->languages();
        $translatedLanguages = $languages->filter(
            fn ($language) => $page->translation($language->code())->exists()
        );

        return $translatedLanguages->isEmpty() ? $languages : $translatedLanguages;
    }
}
