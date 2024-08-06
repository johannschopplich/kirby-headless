![Kirby Headless Preview](./.github/og.png)

# Kirby Headless

This plugins enhances your Kirby site with headless capabilities. It can either add headless functionality to your existing Kirby site while keeping the traditional Kirby frontend or be used as a headless-first or headless-only CMS.

> [!NOTE]
> Check out to the [Kirby Headless Starter](https://github.com/johannschopplich/kirby-headless-starter) repository for a ready-to-use headless-only setup!

## Key Features

- 🦭 Optional bearer token for authentication
- 🔒 Choose between **public** or **private** API
- 🧩 Extends [KQL](https://github.com/getkirby/kql) with bearer token support (new `/api/kql` route)
- 🧱 Resolves fields in blocks: [UUIDs to file and page objects](#toresolvedblocks) or [any other field](#custom-resolver-for-a-specific-block-and-field)
- ⚡️ Cached KQL queries
- 🌐 Multi-language support for KQL queries
- 🗂 [Kirby templates](#templates) that output JSON instead of HTML
- 😵‍💫 Seamless experience without CORS issues
- 🍢 Express-esque [API builder](#api-builder) with middleware support

## Table of Contents

- [Use Cases](#use-cases)
- [Requirements](#requirements)
- [Installation](#installation)
- [Setup](#setup)
- [Usage](#usage)
  - [Private vs. Public API](#private-vs-public-api)
  - [Cross Origin Resource Sharing (CORS)](#cross-origin-resource-sharing-cors)
  - [Kirby Query Language (KQL)](#kirby-query-language-kql)
  - [Templates](#templates)
  - [Panel Settings](#panel-settings)
- [Field Methods](#field-methods)
  - [`toResolvedBlocks()`](#toresolvedblocks)
  - [`resolvePermalinks()`](#resolvepermalinks)
- [Page Methods](#page-methods)
  - [`i18nMeta()`](#i18nmeta)
- [API Builder](#api-builder)
- [FAQ](#faq)

## Use Cases

This plugin is designed for developers who want to leverage Kirby's backend to serve content to a frontend application, static site generator, or mobile app. You can either opt-in to headless functionality for your existing Kirby site or use this plugin to build a headless-first CMS from scratch.

Here are scenarios where the Kirby Headless plugin is particularly useful:

- 1️⃣ If you prefer to query data using the [Kirby Query Language](#kirby-query-language-kql).
- 2️⃣ If you want to use [Kirby's default template system](#templates) to output JSON instead of HTML.

Detailed instructions on how to use these features can be found in the [usage](#usage) section.

> [!TIP]
> Kirby Headless doesn't interfere with Kirby's default routing. You can install it without affecting your existing Kirby site.
> To use the [JSON templates](#templates) feature, opt-in to [override the gobal routing](#setup).

## Requirements

- Kirby 4

Kirby is not free software. However, you can try Kirby and the Starterkit on your local machine or on a test server as long as you need to make sure it is the right tool for your next project. … and when you’re convinced, [buy your license](https://getkirby.com/buy).

## Installation

### Composer

```bash
composer require johannschopplich/kirby-headless
```

### Download

Download and copy this repository to `/site/plugins/kirby-headless`.

## Setup

> [!TIP]
> If you don't intend to use the [JSON templates](#templates) feature, you can skip this section!

By default, the plugin doesn't interfere with Kirby's default routing, it just adds API routes like [for KQL](./src/extensions/api.php).

To transform Kirby from a traditional frontend to a truly headless-only CMS, you have to opt-in to custom global routing in your `config.php`:

```php
# /site/config/config.php
return [
    'headless' => [
        // Enable returning Kirby templates as JSON
        'globalRoutes' => true
    ]
];
```

This will make all page templates return JSON instead of HTML by [defining global routes](./src/extensions/routes.php).

## Usage

### Private vs. Public API

It is recommended to secure your API with a token. To do so, set the `headless.token` Kirby configuration option:

```php
# /site/config/config.php
return [
    'headless' => [
        'token' => 'test'
    ]
];
```

You will then have to provide the HTTP header `Authentication: Bearer ${token}` with each request.

> [!WARNING]
> Without a token your page content will be publicly accessible to everyone.

### Cross Origin Resource Sharing (CORS)

CORS is enabled by default. You can enhance the default CORS configuration by setting the following options in your `config.php`:

```php
# /site/config/config.php
return [
    'headless' => [
        // Default CORS configuration
        'cors' => [
            'allowOrigin' => '*',
            'allowMethods' => 'GET, POST, OPTIONS',
            'allowHeaders' => 'Accept, Content-Type, Authorization, X-Language',
            'maxAge' => '86400',
        ]
    ]
];
```

### Kirby Query Language (KQL)

It is common to authenticate API requests with a token, which is not possible with the default KQL endpoint. Thus, this plugin adds a new KQL endpoint under `/api/kql` that supports bearer token authentication and query response caching.

To enable the bearer token authentication, set the following option in your `config.php`:

```php
# /site/config/config.php
return [
    // Enable basic authentication for the Kirby API
    // Only needed, if you prefer basic auth over bearer tokens
    'api' => [
        'basicAuth' => true
    ],

    // Default to token-based authentication
    'kql' => [
        'auth' => 'bearer'
    ]
];
```

Fetch KQL query results like you normally would, but provide an `Authentication` header with your request:

<details>
<summary>👉 Fetch example</summary>

```js
const API_TOKEN = 'test'

const response = await fetch('<website-url>/api/kql', {
  method: 'POST',
  body: {
    query: "page('notes').children",
    select: {
      title: true,
      text: 'page.text.toBlocks',
      slug: true,
      date: "page.date.toDate('d.m.Y')",
    },
  },
  headers: {
    Authentication: `Bearer ${API_TOKEN}`,
  },
})

const data = await response.json()
console.log(data)
```

</details>

#### Basic Authentication for KQL

To **disable** the bearer token authentication for your Kirby instance and instead use the **basic authentication** method, set the following option in your `config.php`:

```php
'kql' => [
    'auth' => true
]
```

> [!TIP]
> The internal `/api/kql` route will always enforce bearer authentication, unless you explicitly disable it by setting the `kql.auth` option to `false`.

> [!NOTE]
> The KQL default endpoint `/api/query` remains using basic authentication and also infers the `kql.auth` config option.

### Templates

Write templates as you would in any other Kirby project. But instead of returning HTML, they return JSON. The internal route handler adds the correct content type and also handles caching (if enabled).

> [!INFO]
> Kirby Headless doesn't interfere with Kirby's default routing. To opt-in, follow the [setup instructions](#setup).

<details>
<summary>👉 Example template</summary>

```php
# /site/templates/about.php

$data = [
  'title' => $page->title()->value(),
  'layout' => $page->layout()->toLayouts()->toArray(),
  'address' => $page->address()->value(),
  'email' => $page->email()->value(),
  'phone' => $page->phone()->value(),
  'social' => $page->social()->toStructure()->toArray()
];

echo \Kirby\Data\Json::encode($data);
```

</details>

<details>
<summary>👉 Fetch that data in the frontend</summary>

```js
const API_TOKEN = 'test'

const response = await fetch('<website-url>/about', {
  headers: {
    Authentication: `Bearer ${API_TOKEN}`,
  },
})

const data = await response.json()
console.log(data)
```

</details>

### Panel Settings

#### Preview URL to the Frontend

With the headless approach, the default preview link from the Kirby Panel won't make much sense, since it will point to the backend API itself. Thus, we have to overwrite it utilizing a custom page method in your site/page blueprints:

```yaml
options:
  # Or `site.frontendUrl` for the `site.yml`
  preview: '{{ page.frontendUrl }}'
```

Set your frontend URL in your `config.php`:

```php
# /site/config/config.php
return [
    'headless' => [
        'panel' => [
            // Preview URL for the Panel preview button
            'frontendUrl' => 'https://example.com'
        ]
    ]
];
```

If left empty, the preview button will be disabled.

#### Redirect to the Panel

Editors visiting the headless Kirby site may not want to see any API response, but use the Panel solely. To let them automatically be redirected to the Panel, set the following option in your Kirby configuration:

```php
# /site/config/config.php
return [
    'headless' => [
        'panel' => [
            // Redirect to the Panel if no authorization header is sent,
            // useful for editors visiting the site directly
            'redirect' => false
        ]
    ]
];
```

A middleware checks if a `Authentication` header is set, which is not the case in the browser context.

## Field Methods

### `toResolvedBlocks()`

The `toResolvedBlocks()` method is a wrapper around the `toBlocks()` method. It is primarily intended for usage with KQL queries, because the `toBlocks()` method returns only UUIDs for the `files` and `pages` fields.

> [!TIP]
> Use [custom resolvers](#custom-resolver-for-a-specific-block-and-field) to resolve any field of any block to the desired output.

This field method will resolve the UUIDs to the actual file or page objects, so you can access their properties directly in your frontend. All resolved fields are stored in the `resolved` key of the block.

```php
# /site/config/config.php
return [
    'blocksResolver' => [
        // Define which fields of which blocks need resolving
        'files' => [
            // Resolve the `image` field in the `image` block as a file
            'image' => ['image'],
            // Resolve the `image` field in the `intro` block as a file
            'intro' => ['image']
        ],
        'pages' => [
            // Resolve the `link` field in the `customBlock` block as a page
            'customBlock' => ['link']
        ]
    ]
];
```

For an example, take a look at the 🍫 [Cacao Kit frontend](https://github.com/johannschopplich/cacao-kit-frontend).

#### Custom Files or Pages Resolver

To resolve image UUIDs to image objects, you can define a custom resolver in your `config.php`. By default, the following resolver is used:

```php
# /site/config/config.php
return [
    'blocksResolver' => [
        'defaultResolvers' => [
            'files' => fn (\Kirby\Cms\File $image) => [
              'url' => $image->url(),
              'width' => $image->width(),
              'height' => $image->height(),
              'srcset' => $image->srcset(),
              'alt' => $image->alt()->value()
            ]
        ]
    ]
];
```

If you just need one custom resolver for all files fields, you can use the `blocksResolver.defaultResolvers.files` options key. Respectively, you can use the `blocksResolver.defaultResolvers.pages` options key for all pages fields.

Both options accept a closure that receives the file/page object as its first argument and returns an array of properties, just like the default resolver:

```php
# /site/config/config.php
return [
    'blocksResolver' => [
        'defaultResolvers' => [
            'files' => fn (\Kirby\Cms\File $image) => [
                'srcset' => $image->srcset(),
                'alt' => $image->alt()->value()
            ],
            'pages' => fn (\Kirby\Cms\Page $page) => [
                // Default resolver for pages
            ]
        ]
    ]
];
```

#### Custom Resolver for a Specific Block and Field

If you need a custom resolver for links, structures, or any other field in a specific block, you can use the `blocksResolver.resolvers` option. It accepts an array of resolvers, where the key is `{blockName}:{fieldName}` and the value is a closure that receives the field object as its first argument and returns an array of properties:

```php
# /site/config/config.php
return [
    'blocksResolver' => [
        'resolvers' => [
            // Resolve the field `link` of the block `intro` to a custom output
            'intro:link' => fn (\Kirby\Content\Field $field, \Kirby\Cms\Block $block) => [
                'value' => $field->value(),
                'uri' => $field->toPage()?->uri()
            ]
        ]
    ]
];
```

#### Mutate Blocks Values

By default, resolved fields don't mutate source fields of the blocks array. Instead, the resolved content is stored in the `resolved` key of each block. If you want to overwrite the field value with the resolved content, you can set the `replaceValues` option to `true`:

```php
# /site/config/config.php
return [
    'blocksResolver' => [
        'replaceValues' => true
    ]
];
```

### `resolvePermalinks()`

> [!TIP]
> Acts the same as Kirby's built-in `permalinksToUrls()` method, but supports a custom URL parser.

This field method resolves page and file permalinks to their respective URLs. It is primarily intended for usage with KQL queries, because the value of `writer` fields contain permalink URLs like `/@/page/nDvVIAwDBph4uOpm`. But the method works with any field values that contains permalinks in `href` or `src` attributes.

For headless usage, you may want to remove the origin from the URL and just return the path. You can do so by defining a custom URL parser in your `config.php`:

```php
# /site/config/config.php
return [
    'permalinksResolver' => [
        // Strip the origin from the URL
        'urlParser' => function (string $url, \Kirby\Cms\App $kirby) {
            $path = parse_url($url, PHP_URL_PATH);
            return $path;
        }
    ]
];
```

Or in multilanguage setups, you may want to remove a language prefix like `/de` from the URL:

```php
# /site/config/config.php
return [
    'permalinksResolver' => [
        // Strip the language code prefix from German URLs
        'urlParser' => function (string $url, \Kirby\Cms\App $kirby) {
            $path = parse_url($url, PHP_URL_PATH);

            if (str_starts_with($path, '/de')) {
                return substr($path, 3);
            }

            return $path;
        }
    ]
];
```

## Page Methods

### `i18nMeta()`

The `i18nMeta()` method returns an array including the title and URI for the current page in all available languages. This is useful for the frontend to build a language switcher.

**Type Declaration:**

```ts
type I18nMeta = Record<
  string,
  {
    title: string
    uri: string
  }
>
```

## API Builder

This headless starter includes an Express-esque API builder, defined in the [`JohannSchopplich\Headless\Api\Api` class](./src/classes/Api/Api.php). You can use it to re-use logic like handling a token or verifying some other incoming data.

Take a look at the [built-in routes](./src/extensions/routes.php) to get an idea how you can use the API builder to chain complex route logic.

It is also useful to consume POST requests including JSON data:

```php
# /site/config/config.php
return [
    'routes' => [
        [
            'pattern' => 'post-example',
            'method' => 'POST',
            'action' => Api::createHandler(
                [\JohannSchopplich\Headless\Api\Middlewares::class, 'hasBearerToken'],
                function (array $context) {
                    $foo = kirby()->request()->body()->get('foo');

                    // Do something with `$foo` here

                    return Api::createResponse(201);
                }
            )
        ]
    ],

    // Or use the `api` option to define API routes
    // Accessible under `/api/post-example`
    'api' => [
        'routes' => [
            [
                'pattern' => 'post-example',
                'method' => 'POST',
                // Disable auth for this route to let the `hasBearerToken`
                // middleware handle the authentication
                'auth' => false,
                'action' => Api::createHandler(
                    [\JohannSchopplich\Headless\Api\Middlewares::class, 'hasBearerToken'],
                    function (array $context) {
                        $foo = kirby()->request()->body()->get('foo');

                        // Do something with `$foo` here

                        return Api::createResponse(201);
                    }
                )
            ]
        ]
    ]
];
```

You can use one of the [built-in middlewares](./src/classes/Middlewares.php) or write custom ones in by extending the middleware class or creating a custom class defining your custom middleware functions:

<details>
<summary>👉 Example custom middleware</summary>

```php
/**
 * Check if `foo` is sent with the request
 * and bail with an 401 error if not
 *
 * @param array $context
 * @return mixed
 */
public static function hasFooParam($context)
{
    if (empty(get('foo'))) {
        return Api::createResponse(401);
    }
}
```

</details>

## FAQ

### Why Not Use Content Representations?

[Content representations](https://getkirby.com/docs/guide/templates/content-representations) are great. But they require a non-representing template. Otherwise, the content representation template just would not be read by Kirby. This means, you would have to create the following template structure:

- `default.php`
- `default.json.php`
- `home.php`
- `home.json.php`
- … and so on

To simplify this approach, we use the standard template structure, but encode each template's content as JSON via the internal [route middleware](./src/extensions/routes.php).

## License

[MIT](./LICENSE) License © 2022-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
