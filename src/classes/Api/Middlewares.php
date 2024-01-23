<?php

namespace JohannSchopplich\Headless\Api;

use Kirby\Cms\App;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Http\Uri;
use Kirby\Panel\Panel;
use Kirby\Toolkit\Str;

class Middlewares
{
    /**
     * Try to resolve page and site files
     */
    public static function tryResolveFiles(array $context, array $args)
    {
        // The `$args` array contains the route parameters
        [$path] = $args;
        $kirby = App::instance();

        if (empty($path)) {
            return;
        }

        $extension = F::extension($path);

        if (empty($extension)) {
            return;
        }

        $id = dirname($path);
        $filename = basename($path);

        // Try to resolve image urls for pages and drafts
        if ($page = $kirby->site()->findPageOrDraft($id)) {
            return $page->file($filename);
        }

        // Try to resolve site files at last
        if ($file = $kirby->site()->file($filename)) {
            return $file;
        }
    }

    /**
     * Try to resolve global site data
     */
    public static function tryResolveSite(array $context, array $args)
    {
        $kirby = App::instance();

        // The `$args` array contains the route parameters
        if ($kirby->multilang()) {
            [$languageCode, $path] = $args;
        } else {
            [$path] = $args;
        }

        if ($path !== '_site') {
            return;
        }

        $data = $kirby->cache('pages')->getOrSet(
            '_site.headless.json',
            function () use ($kirby) {
                $template = $kirby->template('_site');

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

        return Response::json($data);
    }

    /**
     * Try to resolve the page id
     */
    public static function tryResolvePage(array $context, array $args)
    {
        $kirby = App::instance();
        $cache = $cacheKey = $data = null;

        // The `$args` array contains the route parameters
        if ($kirby->multilang()) {
            [$languageCode, $path] = $args;
        } else {
            [$path] = $args;
        }

        // Fall back to homepage id
        if (empty($path)) {
            $page = $kirby->site()->homePage();
        } else {
            $path = Str::rtrim($path, '.json');
            $page = $kirby->site()->find($path);

            if (!$page) {
                $page = $kirby->site()->errorPage();
            }
        }

        // Try to get the page from cache
        if ($page->isCacheable()) {
            $cache = $kirby->cache('pages');
            $cacheKey = $page->id() . '.headless.json';
            $data = $cache->get($cacheKey);
        }

        // Fetch the page regularly
        if ($data === null) {
            $template = $page->template();

            if (!$template->exists()) {
                throw new NotFoundException([
                    'key' => 'template.default.notFound'
                ]);
            }

            $kirby->data = $page->controller();
            $data = $template->render($kirby->data);

            // Cache the result
            $cache?->set($cacheKey, $data);
        }

        return Response::json($data);
    }

    /**
     * Try to resolve the sitemap tree
     *
     * @return \Kirby\Http\Response|void
     */
    public static function tryResolveSitemap(array $context, array $args)
    {
        $kirby = App::instance();

        // The `$args` array contains the route parameters
        if ($kirby->multilang()) {
            [$languageCode, $path] = $args;
        } else {
            [$path] = $args;
        }

        if ($path !== '_sitemap') {
            return;
        }

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

        return Response::json($sitemap);
    }

    /**
     * Checks if a bearer token was sent with the request and
     * if it matches the one configured in `.env`
     */
    public static function hasBearerToken()
    {
        $kirby = App::instance();
        $token = $kirby->option('headless.token');
        $authorization = $kirby->request()->header('Authorization');

        if ($kirby->option('headless.panel.redirect', false) && empty($authorization)) {
            go(Panel::url('site'));
        }

        if (
            !empty($token) &&
            (empty($authorization) || $authorization !== 'Bearer ' . $token)
        ) {
            return Api::createResponse(401);
        }
    }

    /**
     * Checks if a body was sent with the request
     */
    public static function hasBody(array $context)
    {
        $request = App::instance()->request();

        if (empty($request->body()->data())) {
            return Api::createResponse(400, [
                'error' => 'No body was sent with the request'
            ]);
        }

        $context['body'] = $request->body();
        $context['query'] = $request->query();

        return $context;
    }
}
