<?php

use App\Controllers\SomeController;

$limiter = new App\Middleware\LimitsRequests($container['redis']);

$limiter->setRateLimit(10, 30)
    ->setStorageKey('api:limit:%s')
    ->setIdentifier(100)
    ->setLimitExeededHandler(function ($request, $response, $next) {
        return $response->withJson([
            'errors' => [
                [
                    'status' => 429,
                    'title' => 'Too many requests. Slow down!'
                ]
            ]
        ], 429);
    });

dump($limiter);

$app->group('/api', function () {
    $this->get('', SomeController::class . ':index');
})->add($limiter);
