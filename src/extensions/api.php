<?php

use JohannSchopplich\Headless\Api\Api;
use JohannSchopplich\Headless\Api\Middlewares;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Exception\Exception;
use Kirby\Exception\NotFoundException;
use Kirby\Http\Url;
use Kirby\Toolkit\Str;

return [
    'routes' => function (App $kirby) {
        $kqlAuthMethod = $kirby->option('kql.auth', true);

        // Bearer auth without a token would authenticate nobody, and `false`
        // already exists for opening the endpoint on purpose – so fall back
        // to Kirby's native API auth rather than silently serving everyone
        if ($kqlAuthMethod === 'bearer' && $kirby->option('headless.token') === null) {
            $kqlAuthMethod = true;
        }

        return [
            /**
             * Runs KQL queries against the site.
             *
             * `X-Language` picks the language a query runs in, `X-Cacheable: false`
             * asks for a freshly built answer.
             */
            [
                'pattern' => 'kql',
                'method' => 'GET|POST',
                'auth' => !in_array($kqlAuthMethod, [false, 'bearer'], true),
                'action' => Api::createHandler(
                    function (array $context, array $args) use ($kqlAuthMethod): mixed {
                        if ($kqlAuthMethod !== 'bearer') {
                            return null;
                        }

                        return Middlewares::validateBearerToken();
                    },
                    function (array $context, array $args) use ($kirby): mixed {
                        if (!class_exists('Kirby\\Kql\\Kql')) {
                            throw new Exception('KQL is not installed. Please run: composer require getkirby/kql');
                        }

                        $input = $kirby->request()->get();
                        $cache = $cacheKey = $data = null;
                        $languageCode = $kirby->request()->header('X-Language');
                        $hasLanguageCode = $languageCode !== null && $languageCode !== '';
                        // The cache key covers the query itself, so unlike the
                        // other endpoints this one may cache a request with data
                        $isCacheable = Api::clientAllowsCache();

                        if ($kirby->multilang() && $hasLanguageCode) {
                            $kirby->setCurrentLanguage($languageCode);
                        }

                        if ($input !== []) {
                            $hash = sha1(Json::encode($input));
                            $cache = $kirby->cache('pages');
                            $cacheKey = 'query-' . $hash . ($hasLanguageCode ? '-' . $languageCode : '') . '.json';

                            if ($isCacheable) {
                                $data = $cache->get($cacheKey);
                            }
                        }

                        if ($data === null) {
                            $data = \Kirby\Kql\Kql::run($input);

                            if ($isCacheable) {
                                $cache?->set($cacheKey, $data);
                            }
                        }

                        return Api::createResponse(200, $data);
                    }
                )
            ],

            /**
             * Answers with every indexable page of the site.
             *
             * Pages are filtered through the `headless.sitemap.exclude` options
             * and each blueprint's own `sitemap` option. A multilang site gets
             * the alternates of every language alongside each URL.
             */
            [
                'pattern' => '__sitemap__',
                'method' => 'GET',
                'auth' => false,
                'action' => Api::createHandler(
                    Middlewares::hasBearerToken(),
                    function (array $context, array $args) use ($kirby): mixed {
                        $data = Api::cached(
                            'sitemap.headless.json',
                            function () use ($kirby) {
                                $withoutBase = fn (string $url) => Url::path($url, true);
                                $isIndexable = $kirby->option('headless.sitemap.isIndexable');
                                $excludeTemplates = $kirby->option('headless.sitemap.exclude.templates', []);
                                $excludePages = $kirby->option('headless.sitemap.exclude.pages', []);

                                if (is_callable($excludePages)) {
                                    $excludePages = $excludePages();
                                }

                                $sitemap = [];

                                foreach ($kirby->site()->index() as $page) {
                                    /** @var \Kirby\Cms\Page $page */
                                    if (in_array($page->intendedTemplate()->name(), $excludeTemplates, true)) {
                                        continue;
                                    }

                                    if ($excludePages !== [] && preg_match('!^(?:' . implode('|', $excludePages) . ')$!i', $page->id())) {
                                        continue;
                                    }

                                    $options = $page->blueprint()->options();
                                    if (isset($options['sitemap']) && $options['sitemap'] === false) {
                                        continue;
                                    }

                                    if (is_callable($isIndexable) && $isIndexable($page) === false) {
                                        continue;
                                    }

                                    $url = ['url' => $withoutBase($page->url())];

                                    // Omit the field rather than emit null when a
                                    // page has no resolvable modification date
                                    if ($modified = $page->modified('Y-m-d', 'date')) {
                                        $url['modified'] = $modified;
                                    }

                                    if ($kirby->multilang()) {
                                        $url['links'] = $kirby->languages()->map(fn ($language) => [
                                            // Support ISO 3166-1 Alpha 2 and ISO 639-1
                                            'lang' => Str::slug(preg_replace(
                                                '/[.@].*$/',
                                                '',
                                                $language->locale(LC_ALL) ?? $language->code()
                                            )),
                                            'url' => $withoutBase($page->url($language->code()))
                                        ])->values();

                                        $url['links'][] = [
                                            'lang' => 'x-default',
                                            'url' => $withoutBase($page->url())
                                        ];
                                    }

                                    $sitemap[] = $url;
                                }

                                return $sitemap;
                            }
                        );

                        return Api::createResponse(200, $data);
                    }
                )
            ],

            /**
             * Renders a template that belongs to no page.
             *
             * The template receives `$kirby` and `$site` only – whatever a page
             * or its controller would contribute is out of reach here.
             */
            [
                'pattern' => '__template__/(:any)',
                'method' => 'GET|POST',
                'auth' => false,
                'action' => Api::createHandler(
                    Middlewares::hasBearerToken(),
                    function (array $context, array $args) use ($kirby): mixed {
                        $templateName = $args[0] ?? null;

                        if ($templateName === null || $templateName === '') {
                            throw new NotFoundException([
                                'key' => 'template.default.notFound'
                            ]);
                        }

                        $data = Api::cached(
                            'template-' . $templateName . '.headless.json',
                            function () use ($kirby, $templateName) {
                                $template = $kirby->template($templateName);

                                if (!$template->exists()) {
                                    throw new NotFoundException([
                                        'key' => 'template.default.notFound'
                                    ]);
                                }

                                return $template->render([
                                    'kirby' => $kirby,
                                    'site' => $kirby->site()
                                ]);
                            }
                        );

                        return Api::createResponse(
                            200,
                            Json::decode($data)
                        );
                    }
                )
            ]
        ];
    }
];
