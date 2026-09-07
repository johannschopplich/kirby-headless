<a href="https://kirby.tools/headless"><img src="./.github/favicon.svg" alt="Kirby Headless" width="120"></a>

# Kirby Headless

Kirby Headless is a plugin for [Kirby CMS](https://getkirby.com) that adds bearer-authenticated KQL, UUID resolution in blocks and layouts, JSON templates, and an Express-style API builder – keep editing in Kirby and serve the result to whatever frontend you prefer.

> [!NOTE]
> Want a ready-to-use headless-only project? Start from the [Kirby Headless Starter](https://github.com/johannschopplich/kirby-headless-starter).

## When to Use

| I want to…                                              | Use                                             |
| ------------------------------------------------------- | ----------------------------------------------- |
| Query content from a frontend over HTTP                 | KQL endpoint at `/api/kql`                      |
| Lock the API behind a token instead of basic auth       | `kql.auth => 'bearer'` + `headless.token`       |
| Resolve UUIDs in blocks and layouts to real objects     | `$field->toResolvedBlocks()`                    |
| Resolve permalinks in writer and text fields            | `$field->resolvePermalinks()`                   |
| Return JSON straight from a template                    | JSON templates / `__template__` endpoint        |
| Compose custom, authenticated API routes                | `Api::createHandler()` + middlewares            |
| Build navigation and language switchers in the frontend | page methods (`frontendUrl()`, `i18nMeta()`, …) |

## Features

- 🔑 **Bearer Token Authentication**: Protect `/api/kql` and your own API routes with a bearer token, or fall back to Kirby's native API authentication – see [authentication](https://kirby.tools/docs/headless/configuration/authentication).
- 🧱 **Block & Layout Resolution**: UUIDs in blocks and layouts resolved to file and page objects server-side, with configurable fields and custom resolvers – see [field methods](https://kirby.tools/docs/headless/usage/field-methods).
- ⚡️ **Enhanced KQL**: A drop-in `/api/kql` endpoint with bearer authentication, response caching, and multi-language support via a request header; needs `getkirby/kql` installed – see [KQL](https://kirby.tools/docs/headless/usage/kql).
- 🗂 **JSON Templates**: Return JSON from templates instead of HTML, with built-in `__template__` and `__sitemap__` endpoints, or serve every page as JSON through one catch-all route – see [JSON templates](https://kirby.tools/docs/headless/usage/json-templates).
- 🍢 **API Builder**: Compose routes from middleware chains, Express-style, and reuse bearer auth, file and page resolution, or your own validators – see [API builder](https://kirby.tools/docs/headless/advanced/api-builder).
- 🧭 **Page Methods**: Frontend URLs, breadcrumb data, and multi-language metadata for navigation and language switchers – see [page methods](https://kirby.tools/docs/headless/usage/page-methods).

## Requirements

- Kirby 5

> [!NOTE]
> Using Kirby 4? Install the [`v4` release](https://github.com/johannschopplich/kirby-headless/releases/tag/v4.0.2).

## Installation

### Composer (Recommended)

```bash
composer require johannschopplich/kirby-headless
```

### Manual Installation

Download and copy this repository to `/site/plugins/kirby-headless`.

## Documentation

For installation, configuration, and usage, see the [Kirby Headless documentation](https://kirby.tools/docs/headless).

## License

[MIT](./LICENSE) License © 2022-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
