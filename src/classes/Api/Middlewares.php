<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless\Api;

use JohannSchopplich\Headless\PageRenderer;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Responder;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Panel\Panel;
use Kirby\Toolkit\Str;

final readonly class Middlewares
{
    /**
     * Attempts to resolve page and site files from the request path.
     *
     * Mirrors Kirby's own `App::resolve()`, down to `content.fileRedirects`.
     */
    public static function tryResolveFiles(array $context, array $args): File|null
    {
        $kirby = App::instance();
        $path = self::pathFromArgs($args);

        if ($path === null || F::extension($path) === '') {
            return null;
        }

        // Page resolution decides afterwards whether the extension is serveable
        if (self::findPage($path) !== null) {
            return null;
        }

        if (!str_contains($path, '/')) {
            return $kirby->resolveFile($kirby->site()->file($path));
        }

        if ($page = $kirby->site()->findPageOrDraft(dirname($path))) {
            return $kirby->resolveFile($page->file(basename($path)));
        }

        return null;
    }

    /**
     * Attempts to resolve the request path to a rendered page response.
     *
     * @throws \Kirby\Exception\NotFoundException If the page template does not exist
     */
    public static function tryResolvePage(array $context, array $args): Response|Responder|null
    {
        $kirby = App::instance();
        $path = self::pathFromArgs($args);

        // Returning null lets Kirby move on to the language this path addresses
        if ($path !== null && self::hasOtherLanguagePrefix($path)) {
            return null;
        }

        if ($path === null) {
            $page = $kirby->site()->homePage();
        } else {
            $page = self::resolvePath($path);

            if ($page instanceof Response) {
                return $page;
            }

            $page ??= $kirby->site()->errorPage();
        }

        if (!$page) {
            return Api::createResponse(404);
        }

        return PageRenderer::respond($page, $path === null ? '' : F::extension($path));
    }

    /**
     * Returns a middleware that validates the bearer token.
     *
     * @param bool $redirectToPanel Whether an unauthenticated browser navigation may be sent to the Panel
     */
    public static function hasBearerToken(bool $redirectToPanel = false): callable
    {
        // Kirby's dispatch rebinds the closure to the `Route` object, so
        // `self::` would resolve to `Route` instead of this class
        return fn (array $context, array $args) => Middlewares::validateBearerToken($redirectToPanel);
    }

    /**
     * @param bool $redirectToPanel Whether an unauthenticated browser navigation may be sent to the Panel
     */
    public static function validateBearerToken(bool $redirectToPanel = false): Response|null
    {
        $kirby = App::instance();
        $token = $kirby->option('headless.token');
        $authorization = $kirby->request()->header('Authorization');
        $accept = $kirby->request()->header('Accept');

        // Only a browser navigation is worth redirecting. `prefersJson()` tests
        // for JSON rather than against HTML, so a client that sent no `Accept`
        // header states no preference and must not be redirected either
        if (
            $redirectToPanel &&
            $kirby->option('headless.panel.redirect', false) &&
            ($authorization === null || $authorization === '') &&
            ($accept !== null && $accept !== '') &&
            !$kirby->visitor()->prefersJson()
        ) {
            return Response::redirect(Panel::url('site'), 302);
        }

        // An absent token opts out of authentication, but a blank one is a
        // failed configuration that must never open the site
        if ($token === null) {
            return null;
        }

        $token = trim((string)$token);

        if ($token === '' || $authorization !== 'Bearer ' . $token) {
            return Api::createResponse(401);
        }

        return null;
    }

    /**
     * Rejects a request without a body and hands the parsed one to the
     * middlewares that follow.
     */
    public static function hasBody(array $context): Response|array
    {
        $request = App::instance()->request();

        if ($request->body()->data() === []) {
            return Api::createResponse(400, [
                'error' => 'Missing request body'
            ]);
        }

        $context['body'] = $request->body();

        return $context;
    }

    private static function pathFromArgs(array $args): string|null
    {
        $path = $args[App::instance()->multilang() ? 1 : 0] ?? null;

        return $path === '' ? null : $path;
    }

    /**
     * Finds the page a request path points to, drafts included.
     *
     * `Site::find()` strips the extension itself, so a file never claims a
     * path a page already occupies.
     *
     * A draft needs the same permission Kirby asks for in `App::resolve()`.
     */
    private static function findPage(string $path): Page|null
    {
        $kirby = App::instance();
        $page = $kirby->site()->find($path);

        if ($page !== null) {
            return $page;
        }

        $draft = $kirby->site()->draft($path);

        if ($draft === null) {
            return null;
        }

        if (
            ($kirby->user() !== null && $draft->isAccessible()) ||
            $draft->renderVersionFromRequest() !== null
        ) {
            return $draft;
        }

        return null;
    }

    /**
     * Resolves the page a request path points to, honoring the content
     * representation Kirby would serve for the path's extension.
     *
     * JSON resolves to the page even without a `*.json.php` template, because
     * rendering page JSON from the default template is what this plugin is for.
     */
    private static function resolvePath(string $path): Page|Response|null
    {
        // Kirby refuses paths with an incomplete content representation
        if (Str::endsWith($path, '.')) {
            return null;
        }

        $page = self::findPage($path);

        if ($page === null) {
            return null;
        }

        $extension = F::extension($path);

        return match ($extension) {
            '', 'json' => $page,
            'html' => Response::redirect($page->url(), 301),
            default => PageRenderer::representation($page, $extension) !== null ? $page : null
        };
    }

    /**
     * Checks whether the path is prefixed with another language's URL path.
     *
     * Kirby scopes the catch-all into every language's router, and the default
     * language's pattern matches any path – so the default language sees a
     * prefixed URL first and would answer it before its own language is tried.
     */
    private static function hasOtherLanguagePrefix(string $path): bool
    {
        $kirby = App::instance();

        if (!$kirby->multilang()) {
            return false;
        }

        $currentLanguageCode = $kirby->language()?->code();

        foreach ($kirby->languages() as $language) {
            $prefix = $language->path();

            if ($prefix === '' || $language->code() === $currentLanguageCode) {
                continue;
            }

            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
