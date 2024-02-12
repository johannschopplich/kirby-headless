<?php

namespace JohannSchopplich\Headless\Api;

use Kirby\Cms\App;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
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
     * Try to resolve the page ID
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

        // Fetch the page data
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
     * Validates the bearer token sent with the request
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
                'error' => 'Missing request body'
            ]);
        }

        $context['body'] = $request->body();

        return $context;
    }
}
