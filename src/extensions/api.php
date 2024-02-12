<?php

use JohannSchopplich\Headless\Api\Api;
use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Exception\NotFoundException;
use Kirby\Http\Url;
use Kirby\Kql\Kql;
use Kirby\Toolkit\Str;

$validateOptionalBearerToken = function (array $context, array $args) {
    $kirby = App::instance();
    $token = $kirby->option('headless.token');
    $authorization = $kirby->request()->header('Authorization');

    if (
        !empty($token) &&
        (empty($authorization) || $authorization !== 'Bearer ' . $token)
    ) {
        return Api::createResponse(401);
    }
};

return [
    'routes' => function (App $kirby) use ($validateOptionalBearerToken) {
        $kqlAuthMethod = $kirby->option('kql.auth', true);

        return [
            /**
             * Allow preflight requests, mainly for `fetch` requests
             */
            [
                'pattern' => '(:all)',
                'method' => 'OPTIONS',
                'auth' => false,
                'action' => fn () => Api::createPreflightResponse()
            ],

            /**
             * KQL with bearer token authentication and caching
             */
            [
                'pattern' => 'kql',
                'method' => 'GET|POST',
                'auth' => !in_array($kqlAuthMethod, [false, 'bearer'], true),
                'action' => Api::createHandler(
                    // Middleware to validate the bearer token
                    function (array $context, array $args) use ($kirby, $kqlAuthMethod) {
                        if ($kqlAuthMethod !== 'bearer') {
                            return;
                        }

                        $token = $kirby->option('headless.token');
                        $authorization = $kirby->request()->header('Authorization');

                        if ($authorization !== 'Bearer ' . $token) {
                            return Api::createResponse(401);
                        }
                    },
                    // Middleware to run queries and cache their results
                    function (array $context, array $args) use ($kirby) {
                        $input = $kirby->request()->get();
                        $cache = $cacheKey = $data = null;
                        $languageCode = $kirby->request()->header('X-Language');
                        $isCacheable = $kirby->request()->header('X-Cacheable') !== 'false';

                        // Set the Kirby language in multilanguage sites
                        if ($kirby->multilang() && !empty($languageCode)) {
                            $kirby->setCurrentLanguage($languageCode);
                        }

                        if (!empty($input)) {
                            $hash = sha1(Json::encode($input));
                            $cache = $kirby->cache('pages');
                            $cacheKey = 'query-' . $hash . (!empty($languageCode) ? '-' . $languageCode : '') . '.json';

                            if ($isCacheable) {
                                $data = $cache->get($cacheKey);
                            }
                        }

                        if ($data === null) {
                            $data = Kql::run($input);
                            $cache?->set($cacheKey, $data);
                        }

                        return Api::createResponse(200, $data);
                    }
                )
            ],

            /**
             * Generate a sitemap for headless usage
             */
            [
                'pattern' => '__sitemap__',
                'method' => 'GET',
                'auth' => false,
                'action' => Api::createHandler(
                    $validateOptionalBearerToken,
                    function (array $context, array $args) use ($kirby) {
                        $data = $kirby->cache('pages')->getOrSet(
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

                                foreach ($kirby->site()->index() as $item) {
                                    /** @var \Kirby\Cms\Page $item */
                                    if (in_array($item->intendedTemplate()->name(), $excludeTemplates, true)) {
                                        continue;
                                    }

                                    if (preg_match('!^(?:' . implode('|', $excludePages) . ')$!i', $item->id())) {
                                        continue;
                                    }

                                    $options = $item->blueprint()->options();
                                    if (isset($options['sitemap']) && $options['sitemap'] === false) {
                                        continue;
                                    }

                                    if (is_callable($isIndexable) && $isIndexable($item) === false) {
                                        continue;
                                    }

                                    $url = [
                                        'url' => $withoutBase($item->url()),
                                        'modified' => $item->modified('Y-m-d', 'date')
                                    ];

                                    if ($kirby->multilang()) {
                                        $url['links'] = $kirby->languages()->map(fn ($lang) => [
                                            // Support ISO 3166-1 Alpha 2 and ISO 639-1
                                            'lang' => Str::slug(preg_replace(
                                                '/\.utf-?8$/i',
                                                '',
                                                $lang->locale(LC_ALL) ?? $lang->code()
                                            )),
                                            'url' => $withoutBase($item->url($lang->code()))
                                        ])->values();

                                        $url['links'][] = [
                                            'lang' => 'x-default',
                                            'url' => $withoutBase($item->url())
                                        ];
                                    }

                                    $sitemap[] = $url;
                                }

                                return $sitemap;
                            }
                        );

                        return Api::createResponse(201, $data);
                    }
                )
            ],

            /**
             * Render a page template as JSON
             */
            [
                'pattern' => '__template__/(:any)',
                'method' => 'GET|POST',
                'auth' => false,
                'action' => Api::createHandler(
                    $validateOptionalBearerToken,
                    function (array $context, array $args) use ($kirby) {
                        $templateName = $args[0] ?? null;

                        if (empty($templateName)) {
                            throw new NotFoundException([
                                'key' => 'template.default.notFound'
                            ]);
                        }

                        $data = $kirby->cache('pages')->getOrSet(
                            'template-' . $templateName . '.headless.json',
                            function () use ($args, $kirby) {
                                $template = $kirby->template($args[0]);

                                if (!$template->exists()) {
                                    throw new NotFoundException([
                                        'key' => 'template.default.notFound'
                                    ]);
                                }

                                return $template->render([
                                    'kirby' => $kirby,
                                    'site'  => $kirby->site()
                                ]);
                            }
                        );

                        return Api::createResponse(
                            201,
                            Json::decode($data)
                        );
                    }
                )
            ]
        ];
    }
];
