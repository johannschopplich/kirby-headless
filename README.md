<div align="center">
  <img src="./.github/favicon.svg" alt="Kirby Headless logo" width="120">

# Kirby Headless

Bearer-authenticated KQL, UUID resolution in blocks and layouts, JSON templates, and an Express-style API builder for Kirby – everything you need to drive any frontend from Kirby.

[KQL](https://kirby.tools/docs/headless/usage/kql) •
[Field Methods](https://kirby.tools/docs/headless/usage/field-methods) •
[JSON Templates](https://kirby.tools/docs/headless/usage/json-templates) •
[API Builder](https://kirby.tools/docs/headless/advanced/api-builder) •
[Authentication](https://kirby.tools/docs/headless/configuration/authentication)

</div>

> [!NOTE]
> Want a ready-to-use headless-only project? Start from the [Kirby Headless Starter](https://github.com/johannschopplich/kirby-headless-starter).

## When to Use

| I want to…                                              | Use                                            |
| ------------------------------------------------------- | ---------------------------------------------- |
| Query content from a frontend over HTTP                 | KQL endpoint at `/api/kql`                      |
| Lock the API behind a token instead of basic auth       | `kql.auth => 'bearer'` + `headless.token`       |
| Resolve UUIDs in blocks and layouts to real objects     | `$field->toResolvedBlocks()`                    |
| Resolve permalinks in writer and text fields            | `$field->resolvePermalinks()`                   |
| Return JSON straight from a template                    | JSON templates / `__template__` endpoint        |
| Compose custom, authenticated API routes                | `Api::createHandler()` + middlewares            |
| Build navigation and language switchers in the frontend | page methods (`frontendUrl()`, `i18nMeta()`, …) |

## Features

### 🔑 Bearer Token Authentication

Protect the `/api/kql` endpoint and your own API routes with a bearer token, or fall back to Kirby's native API authentication.

```php
// config.php
return [
    'kql' => ['auth' => 'bearer'],
    'headless' => ['token' => 'your-secret-token']
];
```

**[Read more →](https://kirby.tools/docs/headless/configuration/authentication)**

### 🧱 Block & Layout Resolution

Resolve UUIDs in blocks and layouts to complete file and page objects server-side, so your frontend consumes ready-to-use URLs and data. Configure which fields to resolve, or plug in custom resolvers.

```php
$page->blocks()->toResolvedBlocks()->toArray();
$page->layout()->toResolvedLayouts()->toArray();
```

**[Read more →](https://kirby.tools/docs/headless/usage/field-methods)**

### ⚡️ Enhanced KQL

A drop-in `/api/kql` endpoint that extends the official KQL plugin with bearer authentication, response caching, and multi-language support via a request header.

```ts
await fetch("https://example.com/api/kql", {
  method: "POST",
  headers: { Authorization: `Bearer ${token}` },
  body: JSON.stringify({ query: "page('notes').children" }),
});
```

**[Read more →](https://kirby.tools/docs/headless/usage/kql)**

### 🗂 JSON Templates

Return JSON from your templates instead of HTML for full control over the response shape, with built-in `__template__` and `__sitemap__` endpoints.

```php
// site/templates/about.php
echo \Kirby\Data\Json::encode([
    'title' => $page->title()->value(),
    'layout' => $page->layout()->toResolvedLayouts()->toArray()
]);
```

**[Read more →](https://kirby.tools/docs/headless/usage/json-templates)**

### 🍢 API Builder

Compose routes from middleware chains – Express-style. Reuse bearer auth, file and page resolution, or your own validators across routes.

```php
use JohannSchopplich\Headless\Api\Api;
use JohannSchopplich\Headless\Api\Middlewares;

Api::createHandler(
    Middlewares::hasBearerToken(),
    fn (array $context, array $args) => Api::createResponse(200, [
        'message' => 'Hello World'
    ])
);
```

**[Read more →](https://kirby.tools/docs/headless/advanced/api-builder)**

### 🧭 Page Methods

Helpers for headless frontends: frontend URLs, breadcrumb data, and multi-language metadata for navigation and language switchers.

```php
$page->frontendUrl();    // URL on your frontend app
$page->breadcrumbMeta(); // breadcrumb data for navigation
$page->i18nMeta();       // language switcher metadata
```

**[Read more →](https://kirby.tools/docs/headless/usage/page-methods)**

## Requirements

- Kirby 5
- PHP 8.3+

> [!NOTE]
> Using Kirby 4? Install the [`v4` release](https://github.com/johannschopplich/kirby-headless/releases/tag/v4.0.2).

## Installation

### Composer (Recommended)

```bash
composer require johannschopplich/kirby-headless
```

### Manual Installation

Download and copy this repository to `/site/plugins/kirby-headless`.

## License

[MIT](./LICENSE) License © 2022-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
