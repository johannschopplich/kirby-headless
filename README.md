# Kirby Headless

> 👉 Head over to the [Kirby Headless Starter](https://github.com/johannschopplich/kirby-headless-starter) repository for a complete setup ready to be used in production!

This plugin converts your Kirby site into a truly headless-first CMS. No visual data shall be presented. You will only be able to fetch JSON-encoded data – either by using Kirby's default template system or use KQL to fetch data in your consuming application.

Kirby's global routing will be overwritten by the plugin's [global routes](./src/extensions/routes.php) and [API routes](./src/extensions/api.php) for KQL.

## Key Features

- 🦭 Optional bearer token for authentication
- 🔒 **public** or **private** API
- 🧩 [KQL](https://github.com/getkirby/kql) with bearer token support via new `/api/kql` route
- ⚡️ Cached KQL queries
- 🌐 Multi-lang support for KQL queries
- 🗂 [Templates](#templates) present JSON instead of HTML
- 😵‍💫 No CORS issues!
- 🍢 Build your own [API chain](./src/extensions/routes.php)
- 🦾 Express-esque [API builder](#api-builder) with middleware support

## Use Cases

If you intend to fetch data from a headless Kirby instance, you have two options with this plugin:

- 1️⃣ use [Kirby's default template system](#templates)
- 2️⃣ use [KQL](#kirbyql)

Head over to the [usage](#usage) section for instructions.

## Requirements

- Kirby 3 or higher

> [!NOTE]
> Yes, that includes Kirby 4!

Kirby is not a free software. You can try it for free on your local machine but in order to run Kirby on a public server you must purchase a [valid license](https://getkirby.com/buy).

## Installation

### Composer

```bash
composer require johannschopplich/kirby-headless
```

### Download

Download and copy this repository to `/site/plugins/kirby-headless`.

## Setup

If you're not using the [Kirby Headless Starter](https://github.com/johannschopplich/kirby-headless-starter) but adding the plugin to an existing Kirby project, you have to make sure that the following configuration options are set in your `config.php`:

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

## Usage

### Private vs. Public API

It's recommended to secure your API with a token. To do so, set the `headless.token` Kirby configuration option:

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

> [!NOTE]
> The internal `/api/kql` route will always enforce bearer authentication, unless you explicitly disable it in your config (see below).

### Templates

Create templates just like you normally would in any Kirby project. Instead of writing HTML, we build arrays and encode them to JSON. The internal route handler will add the correct content type and also handles caching (if enabled).

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
const API_TOKEN = "test";

const response = await fetch("<website-url>/about", {
  headers: {
    Authentication: `Bearer ${API_TOKEN}`,
  },
});

const data = await response.json();
console.log(data);
```

</details>

### KirbyQL

A new KQL endpoint supporting caching and bearer token authentication is implemented under `/api/kql`.

Fetch KQL query results like you normally would, but provide an `Authentication` header with your request:

<details>
<summary>👉 Fetch example</summary>

```js
const API_TOKEN = "test";

const response = await fetch("<website-url>/api/kql", {
  method: "POST",
  body: {
    query: "page('notes').children",
    select: {
      title: true,
      text: "page.text.toBlocks",
      slug: true,
      date: "page.date.toDate('d.m.Y')",
    },
  },
  headers: {
    Authentication: `Bearer ${API_TOKEN}`,
  },
});

const data = await response.json();
console.log(data);
```

</details>

#### Basic Authentication for KQL

To **disable** the bearer token authentication for your Kirby instance and instead use the **basic authentication** method, set the following option in your `config.php`:

```php
'kql' => [
    'auth' => true
]
```

> [!NOTE]
> The KQL default endpoint `/api/query` remains using basic authentication and also infers the `kql.auth` config option.

### Panel Settings

#### Preview URL to the Frontend

With the headless approach, the default preview link from the Kirby Panel won't make much sense, since it will point to the backend API itself. Thus, we have to overwrite it utilizing a custom page method in your site/page blueprints:

```yaml
options:
  # Or `site.frontendUrl` for the `site.yml`
  preview: "{{ page.frontendUrl }}"
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
            'allowHeaders' => '*',
            'maxAge' => '86400',
        ]
    ]
];
```

## Field Methods

### `toResolvedWriter()`

This field method resolves page and file permalinks to their respective URLs. It's primarily intended for usage with KQL queries, because the value of `writer` fields contain permalink URLs like `/@/page/nDvVIAwDBph4uOpm`.

In multilanguage setups, you may want to add a prefix to the URL, e.g. `/en` or `/de`. You can do so by defining a custom `writerResolver.pathPrefix` option in your `config.php`:

```php
# /site/config/config.php
return [
    'writerResolver' => [
        // Add the language code as a prefix to the URL
        'pathPrefix' => fn (\Kirby\Cms\App $kirby) => '/' . $kirby->language()->code()
    ]
];
```

### `toResolvedBlocks()`

The `toResolvedBlocks()` method is a wrapper around the `toBlocks()` method. It's primarily intended for usage with KQL queries, because the `toBlocks()` method returns only UUIDs for the `files` and `pages` fields.

This field method will resolve the UUIDs to the actual file or page objects, so you can access their properties directly in your frontend.

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
$defaultResolver = fn (\Kirby\Cms\File $image) => [
    'url' => $image->url(),
    'width' => $image->width(),
    'height' => $image->height(),
    'srcset' => $image->srcset(),
    'alt' => $image->alt()->value()
];
```

If you just need one custom resolver for all files fields, you can use the `blocksResolver.defaultResolvers.files` options key. Respectively, you can use the `blocksResolver.defaultResolvers.pages` options key for all pages fields.

Both options accept a closure that receives the file/page object as its first argument and returns an array of properties, just like the default resolver:

```php
# /site/config/config.php
return [
    'blocksResolver' => [
        // Default Resolvers
        'defaultResolvers' => [
            'files' => fn (\Kirby\Cms\File $image) => [
                'url' => $image->url(),
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

If you need a custom resolver for images, links, or any other field in a specific block, you can use the `blocksResolver.resolvers` options key. It accepts an array of resolvers, where the key is the block name and the value is a closure that receives the field object as its first argument and returns an array of properties:

```php
# /site/config/config.php
return [
    'blocksResolver' => [
        'resolvers' => [
            'intro:link' => fn (\Kirby\Content\Field $field) => [
                'value' => $field->value()
            ]
        ]
    ]
];
```

## Page Methods

### `i18nMeta()`

The `i18nMeta()` method returns an array including the title and URI for the current page in all available languages. This is useful for the frontend to build a language switcher.

## Advanced

### API Builder

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

## Why Not Use Content Representations?

[Content representations](https://getkirby.com/docs/guide/templates/content-representations) are great. But they require a non-representing template. Otherwise, the content representation template just would not be read by Kirby. This means, you would have to create the following template structure:

- `default.php`
- `default.json.php`
- `home.php`
- `home.json.php`
- … and so on

To simplify this approach, we use the standard template structure, but encode each template's content as JSON via the internal [route middleware](./src/extensions/routes.php).

## License

[MIT](./LICENSE) License © 2022-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
