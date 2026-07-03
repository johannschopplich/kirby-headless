<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless\Api;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Panel\Panel;
use Kirby\Toolkit\Str;

final readonly class Middlewares
{
    /**
     * Attempts to resolve page and site files from the request path
     */
    public static function tryResolveFiles(array $context, array $args): File|null
    {
        $kirby = App::instance();

        // In multilang mode the language object is the first route argument,
        // so the captured path is the second
        $path = $kirby->multilang() ? ($args[1] ?? null) : ($args[0] ?? null);

        if (empty($path)) {
            return null;
        }

        $extension = F::extension($path);

        if (empty($extension)) {
            return null;
        }

        $id = dirname($path);
        $filename = basename($path);

        // Try to resolve image URLs for pages and drafts
        if ($page = $kirby->site()->findPageOrDraft($id)) {
            return $page->file($filename);
        }

        // Try to resolve site files at last
        if ($file = $kirby->site()->file($filename)) {
            return $file;
        }

        return null;
    }

    /**
     * Attempts to resolve and return the page data as JSON
     *
     * Falls back to homepage if path is empty, or error page if not found
     *
     * @throws \Kirby\Exception\NotFoundException If the page template does not exist
     */
    public static function tryResolvePage(array $context, array $args): Response
    {
        $kirby = App::instance();
        $cache = $cacheKey = $data = null;

        // The `$args` array contains the route parameters
        $path = $kirby->multilang() ? $args[1] : $args[0];

        // Fall back to homepage id
        if (empty($path)) {
            $page = $kirby->site()->homePage();
        } else {
            $path = Str::rtrim($path, '.json');
            $page = $kirby->site()->find($path) ?? $kirby->site()->errorPage();
        }

        // A site without a resolvable home or error page yields a JSON 404
        if (!$page) {
            return Api::createResponse(404);
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

            // Mirror Kirby's own `Page::render()` so the `page.render:before`
            // and `page.render:after` hooks keep firing for headless responses
            $kirby->data = $kirby->apply('page.render:before', [
                'contentType' => 'html',
                'data' => $page->controller(),
                'page' => $page
            ], 'data');

            $html = $template->render($kirby->data);

            $data = $kirby->apply('page.render:after', [
                'contentType' => 'html',
                'data' => $kirby->data,
                'html' => $html,
                'page' => $page
            ], 'html');

            // Cache the result
            $cache?->set($cacheKey, $data);
        }

        return Response::json($data, $page->isErrorPage() ? 404 : 200);
    }

    /**
     * Returns a middleware that validates the bearer token
     *
     * @param bool $redirectToPanel Whether to redirect to Panel when no auth header is present
     */
    public static function hasBearerToken(bool $redirectToPanel = false): callable
    {
        // Closure is handed to Kirby's dispatch, so `self::` would break if it
        // were ever rebound/inlined as a route or API action
        return fn (array $context, array $args) => Middlewares::validateBearerToken($redirectToPanel);
    }

    /**
     * Validates the bearer token from the Authorization header
     *
     * @param bool $redirectToPanel Whether to redirect to Panel when no auth header is present
     */
    public static function validateBearerToken(bool $redirectToPanel = false): Response|null
    {
        $kirby = App::instance();
        $token = $kirby->option('headless.token');
        $authorization = $kirby->request()->header('Authorization');

        if ($redirectToPanel && $kirby->option('headless.panel.redirect', false) && empty($authorization)) {
            return Response::redirect(Panel::url('site'), 302);
        }

        if (
            !empty($token) &&
            (empty($authorization) || $authorization !== 'Bearer ' . $token)
        ) {
            return Api::createResponse(401);
        }

        return null;
    }

    /**
     * Validates that a request body exists
     */
    public static function hasBody(array $context): Response|array
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
