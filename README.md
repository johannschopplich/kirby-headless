![Kirby Headless Preview](./.github/kirby-headless.png)

# Kirby Headless

This plugin is designed for developers who want to use Kirby's backend to serve content to a frontend application, static site generator, or mobile application. You can either add headless functionality to your existing Kirby site, or use this plugin to build a headless-first CMS from scratch.

> [!NOTE]
> Check out to the [Kirby Headless Starter](https://github.com/johannschopplich/kirby-headless-starter) repository for a ready-to-use headless-only setup!

## Key Features

- 🧩 Optional bearer token authentication for [KQL](https://kirby.tools/docs/headless/usage/kql) and custom API endpoints
- 🧱 Resolve fields in blocks: [UUIDs to file and page objects](https://kirby.tools/docs/headless/usage/field-methods) or [any other field](https://kirby.tools/docs/headless/usage/field-methods)
- ⚡️ Cached KQL queries
- 🌐 Multi-language support for KQL queries
- 🍢 Express-esque [API builder](https://kirby.tools/docs/headless/advanced/api-builder) with middleware support
- 🗂 Return [JSON from templates](https://kirby.tools/docs/headless/usage/json-templates) instead of HTML

## Compatibility

This plugin is compatible with Kirby 5 and later. If you are using Kirby 4, please use the [`v4` release](https://github.com/johannschopplich/kirby-headless/releases/tag/v4.0.2).

## Installation

> [!TIP]
> [📖 Read the documentation](https://kirby.tools/docs/headless/getting-started/installation)

### Composer

The recommended way to install the plugin is via Composer. To install the plugin, run the following command in your terminal:

```bash
composer require johannschopplich/kirby-headless
```

### Download

Head over to the [releases page](https://github.com/johannschopplich/kirby-headless/releases) and download the latest version of the plugin as a ZIP file. Extract the contents of this ZIP file to your `site/plugins` folder. It should look like this:

```
site/plugins/
├─ kirby-headless/
│  └─ … Plugin files
```

## Usage

> [!TIP]
> [📖 Read the documentation](https://kirby.tools/docs/headless/usage/kql)

## License

[MIT](./LICENSE) License © 2022-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
