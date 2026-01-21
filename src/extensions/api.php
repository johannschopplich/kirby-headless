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

        return [
            /**
             * KQL endpoint with bearer token authentication and caching support
             *
             * Supports multilingual queries via X-Language header
             * and cache control via X-Cacheable header
             */
            [
                'pattern' => 'kql',
                'method' => 'GET|POST',
                'auth' => !in_array($kqlAuthMethod, [false, 'bearer'], true),
                'action' => Api::createHandler(
                    // Validate the bearer token if required
                    function (array $context, array $args) use ($kqlAuthMethod): mixed {
                        if ($kqlAuthMethod !== 'bearer') {
                            return null;
                        }

                        return Middlewares::validateBearerToken();
                    },
                    // Run KQL queries and cache their results
                    function (array $context, array $args) use ($kirby): mixed {
                        // Check if KQL is installed
                        if (!class_exists('Kirby\\Kql\\Kql')) {
                            throw new Exception('KQL is not installed. Please run: composer require getkirby/kql');
                        }

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
                            $data = \Kirby\Kql\Kql::run($input);
                            $cache?->set($cacheKey, $data);
                        }

                        return Api::createResponse(200, $data);
                    }
                )
            ],

            /**
             * Sitemap endpoint for headless frontend usage
             *
             * Generates a JSON sitemap with support for multilingual sites
             * and configurable page exclusions
             */
            [
                'pattern' => '__sitemap__',
                'method' => 'GET',
                'auth' => false,
                'action' => Api::createHandler(
                    Middlewares::hasBearerToken(),
                    function (array $context, array $args) use ($kirby): mixed {
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
             * Template rendering endpoint for standalone template usage
             *
             * Renders any Kirby template as JSON without page context
             */
            [
                'pattern' => '__template__/(:any)',
                'method' => 'GET|POST',
                'auth' => false,
                'action' => Api::createHandler(
                    Middlewares::hasBearerToken(),
                    function (array $context, array $args) use ($kirby): mixed {
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
