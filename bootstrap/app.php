<?php

require_once __DIR__ . '/../vendor/autoload.php';

try {
    (new Dotenv\Dotenv(__DIR__ . '/../'))->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    //
}

$app = new Slim\App([
    'settings' => [
        'displayErrorDetails' => getenv('APP_DEBUG') === 'true',
        'app' => [
            'name' => getenv('APP_NAME')
        ],
        'views' => [
            'cache' => __DIR__ . '/../storage/views'
        ],
        'database' => [
            'redis' => [
                'host' => getenv('REDIS_HOST'),
                'port' => getenv('REDIS_PORT'),
                'password' => getenv('REDIS_PASSWORD') ?: null,
            ]
        ]
    ],
]);

$container = $app->getContainer();

$container['redis'] = function ($container) {
    $config = $container->settings['database']['redis'];

    return new Predis\Client([
        'scheme' => 'tcp',
        'host' => $config['host'],
        'port' => $config['port'],
        'password' => $config['password'],
    ]);
};

$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig(__DIR__ . '/../resources/views', [
        'cache' => $container->settings['views']['cache']
    ]);

    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));

    return $view;
};

require_once __DIR__ . '/../routes/web.php';
require_once __DIR__ . '/../routes/api.php';
