<?php

use Kirby\Cms\App;
use Kirby\Filesystem\F;

F::loadClasses([
    'JohannSchopplich\\Headless\\Api\\Api' => 'src/classes/Api/Api.php',
    'JohannSchopplich\\Headless\\Api\\Middlewares' => 'src/classes/Api/Middlewares.php'
], __DIR__);

// Add backwards compatibility for Kirby 3
if (!class_exists('Kirby\Content\Field')) {
    class_alias('Kirby\Cms\Field', 'Kirby\Content\Field');
}

App::plugin('johannschopplich/headless', [
    'hooks' => [
        // Explicitly register catch-all routes only when Kirby and all plugins
        // have been loaded to ensure no other routes are overwritten
        'system.loadPlugins:after' => function () {
            $kirby = App::instance();

            $extensions = [
                'api' => require __DIR__ . '/src/extensions/api.php'
            ];

            if ($kirby->option('headless.routes', true)) {
                $extensions['routes'] = require __DIR__ . '/src/extensions/routes.php';
            }

            $kirby->extend($extensions, $kirby->plugin('johannschopplich/headless'));
        }
    ],
    'fieldMethods' => require __DIR__ . '/src/extensions/fieldMethods.php',
    'pageMethods' => require __DIR__ . '/src/extensions/pageMethods.php',
    'siteMethods' => require __DIR__ . '/src/extensions/siteMethods.php'
]);
