<?php

load([
    'JohannSchopplich\\Headless\\Api\\Api' => 'src/classes/Api/Api.php',
    'JohannSchopplich\\Headless\\Api\\Middlewares' => 'src/classes/Api/Middlewares.php'
], __DIR__);

\Kirby\Cms\App::plugin('johannschopplich/headless', [
    'hooks' => [
        // Explicitly register catch-all routes only when Kirby and all plugins
        // have been loaded to ensure no other routes are overwritten
        'system.loadPlugins:after' => function () {
            kirby()->extend(
                [
                    'api' => require __DIR__ . '/src/extensions/api.php',
                    'routes' => require __DIR__ . '/src/extensions/routes.php'
                ],
                kirby()->plugin('johannschopplich/headless')
            );
        }
    ],
    'fieldMethods' => require __DIR__ . '/src/extensions/fieldMethods.php',
    'pageMethods' => require __DIR__ . '/src/extensions/pageMethods.php',
    'siteMethods' => require __DIR__ . '/src/extensions/siteMethods.php'
]);
