<div align="center">
  <a href="https://kirby.tools/headless"><img src="./.github/favicon.svg" alt="Kirby Headless logo" width="120"></a>

# Kirby Headless

Kirby Headless is a plugin for [Kirby CMS](https://getkirby.com): JSON pages and KQL queries, served behind one bearer token.

[Authentication](https://kirby.tools/docs/headless/configuration/authentication) •
[KQL](https://kirby.tools/docs/headless/usage/kql) •
[JSON Templates](https://kirby.tools/docs/headless/usage/json-templates) •
[Field Methods](https://kirby.tools/docs/headless/usage/field-methods) •
[API Builder](https://kirby.tools/docs/headless/advanced/api-builder) •
[Page Methods](https://kirby.tools/docs/headless/usage/page-methods)

</div>

> [!NOTE]
> Want a ready-to-use headless-only project? Start from the [Kirby Headless Starter](https://github.com/johannschopplich/kirby-headless-starter).

## When to Use

| I want to…                                              | Use                                             |
| ------------------------------------------------------- | ----------------------------------------------- |
| Query content from a frontend over HTTP                 | KQL endpoint at `/api/kql`                      |
| Lock the API behind a token instead of basic auth       | `kql.auth => 'bearer'` + `headless.token`       |
| Resolve UUIDs in blocks and layouts to real objects     | `$field->toResolvedBlocks()`                    |
| Return JSON straight from a template                    | JSON templates / `__template__` endpoint        |
| Compose custom, authenticated API routes                | `Api::createHandler()` + middlewares            |

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
