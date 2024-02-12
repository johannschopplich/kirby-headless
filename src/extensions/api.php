<?php

use JohannSchopplich\Headless\Api\Api;
use Kirby\Data\Json;
use Kirby\Exception\NotFoundException;
use Kirby\Http\Uri;
use Kirby\Kql\Kql;
use Kirby\Toolkit\Str;

$validateOptionalBearerToken = function (array $context, array $args) {
    /** @var \Kirby\Cms\App */
    $kirby = $context['kirby'];

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
    'routes' => function (\Kirby\Cms\App $kirby) use ($validateOptionalBearerToken) {
        $authMethod = $kirby->option('kql.auth', true);
        $auth = $authMethod !== false && $authMethod !== 'bearer';

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
                'auth' => $auth,
                'action' => Api::createHandler(
                    // Middleware to validate the bearer token
                    function (array $context, array $args) use ($kirby, $authMethod) {
                        if ($authMethod !== 'bearer') {
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
                        $isCacheable = $kirby->request()->header('X-Cacheable');

                        // Set the Kirby language in multilanguage sites
                        if ($kirby->multilang() && $languageCode) {
                            $kirby->setCurrentLanguage($languageCode);
                        }

                        if (!empty($input)) {
                            $hash = sha1(Json::encode($input));
                            $cache = $kirby->cache('pages');
                            $cacheKey = 'query-' . $hash . (!empty($languageCode) ? '-' . $languageCode : '') . '.json';

                            if ($isCacheable !== 'false') {
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
                        $sitemap = [];
                        $cache = $kirby->cache('pages');
                        $cacheKey = '_sitemap.headless.json';
                        $sitemap = $cache->get($cacheKey);
                        $withoutBase = fn (string $url) => '/' . (new Uri($url))->path();

                        if ($sitemap === null) {
                            $isIndexable = option('headless.sitemap.isIndexable');
                            $excludeTemplates = option('headless.sitemap.exclude.templates', []);
                            $excludePages = option('headless.sitemap.exclude.pages', []);

                            if (is_callable($excludePages)) {
                                $excludePages = $excludePages();
                            }

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

                            $cache?->set($cacheKey, $sitemap);
                        }

                        return Api::createResponse(201, $sitemap);
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

                        if (!$templateName) {
                            throw new NotFoundException([
                                'key' => 'template.default.notFound'
                            ]);
                        }

                        $data = $kirby->cache('pages')->getOrSet(
                            $templateName . '.headless.json',
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

                        return Api::createResponse(201, $data);
                    }
                )
            ]
        ];
    }
];
