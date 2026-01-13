<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

$app = AppFactory::create();

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => __DIR__ . '/../var/cache']);

$app->add(TwigMiddleware::create($app, $twig));

$app->get('/', function (Request $request, Response $response, $args) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'main.html.twig', [
        'text' => 'Hello world!'
    ]);
});

$app->run();
