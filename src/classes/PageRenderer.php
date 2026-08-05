<?php

declare(strict_types = 1);

namespace JohannSchopplich\Headless;

use JohannSchopplich\Headless\Api\Api;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Responder;
use Kirby\Content\VersionId;
use Kirby\Exception\NotFoundException;
use Kirby\Template\Template;

final readonly class PageRenderer
{
    /**
     * Renders the page and hands back the response Kirby would send.
     *
     * Mirrors `Page::render()`: the response configuration travels with the
     * body, so headers and status codes a template sets apply to every visitor
     * and not just the one whose request filled the cache.
     *
     * @param string $extension The extension the request asked for, empty for none
     * @throws \Kirby\Exception\NotFoundException If the template does not exist
     */
    public static function respond(Page $page, string $extension): Responder
    {
        $kirby = App::instance();
        $response = $kirby->response();
        $representation = $extension === '' ? null : self::representation($page, $extension);
        $template = $representation ?? $page->template();
        $contentType = $representation !== null ? $extension : 'html';
        $versionId = self::renderVersion($page);
        $cache = $cacheKey = $body = null;

        if (Api::clientAllowsCache() && $page->isCacheable($versionId)) {
            $cache = $kirby->cache('pages');
            $cacheKey = self::cacheKey($page, $contentType, $versionId);
            $body = self::replayCachedResponse($cache->get($cacheKey), $response);
        }

        if ($body === null) {
            // The body travels inside the response, so it has to be in place
            // before the configuration is handed to the cache.
            $response->body(VersionId::render(
                $versionId,
                fn () => self::render($page, $template, $contentType)
            ));

            if ($cache !== null && $response->cache() === true) {
                $cache->set($cacheKey, [
                    'response' => $response->toCacheArray(),
                    'usesAuth' => $response->usesAuth(),
                    'usesCookies' => $response->usesCookies()
                ], $response->expires() ?? 0);
            }
        }

        // A representation keeps the MIME type its extension implies –
        // everything else is page JSON.
        if ($response->type() === null) {
            $response->type($contentType === 'html' ? 'json' : $contentType);
        }

        if ($response->code() === null) {
            $response->code($page->isErrorPage() ? 404 : 200);
        }

        return $response;
    }

    /**
     * Finds the template that renders a page as the given content type.
     */
    public static function representation(Page $page, string $contentType): Template|null
    {
        $template = App::instance()->template($page->template()->name(), $contentType);

        return $template->exists() ? $template : null;
    }

    /**
     * Restores a cached response and returns its body.
     */
    private static function replayCachedResponse(mixed $cachedEntry, Responder $response): string|null
    {
        if (!is_array($cachedEntry)) {
            return null;
        }

        // Read the body before touching the response, so an entry that turns
        // out to be unusable cannot color the one we render in its place.
        $body = $cachedEntry['response']['body'] ?? null;

        if ($body === null) {
            return null;
        }

        if (Responder::isPrivate($cachedEntry['usesAuth'] ?? false, $cachedEntry['usesCookies'] ?? [])) {
            return null;
        }

        $response->fromArray($cachedEntry['response']);

        return $body;
    }

    /**
     * Determines which version of the page the request may render.
     */
    private static function renderVersion(Page $page): VersionId
    {
        return VersionId::from(
            VersionId::$render ?? $page->renderVersionFromRequest() ?? 'latest'
        );
    }

    /**
     * Fires `page.render:before` and `page.render:after`, so plugins that hook
     * Kirby's own rendering keep working for headless responses.
     *
     * @throws \Kirby\Exception\NotFoundException If the template does not exist
     */
    private static function render(Page $page, Template $template, string $contentType): string
    {
        $kirby = App::instance();

        if (!$template->exists()) {
            throw new NotFoundException([
                'key' => 'template.default.notFound'
            ]);
        }

        $kirby->data = $kirby->apply('page.render:before', [
            'contentType' => $contentType,
            'data' => $page->controller([], $contentType),
            'page' => $page
        ], 'data');

        $renderedContent = $template->render($kirby->data);

        return $kirby->apply('page.render:after', [
            'contentType' => $contentType,
            'data' => $kirby->data,
            'html' => $renderedContent,
            'page' => $page
        ], 'html');
    }

    private static function cacheKey(Page $page, string $contentType, VersionId $versionId): string
    {
        // Filtering on `null` rather than truthiness keeps a page whose ID is `0`.
        return implode('.', array_filter([
            $page->id(),
            App::instance()->language()?->code(),
            $versionId->value(),
            $contentType,
            'headless',
            'json'
        ], fn ($part) => $part !== null));
    }
}
