<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

$app = AppFactory::create();

$twig = Twig::create(__DIR__ . '/../templates', [/*'cache' => __DIR__ . '/../var/cache'*/]);

$app->add(TwigMiddleware::create($app, $twig));

const HREFS = ['href_urls' => '/urls'];

$app->get('/', function (Request $request, Response $response, $args): Response {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'main.html.twig', HREFS);
});

$app->get('/urls', function (Request $request, Response $response, $args): Response {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'urls.html.twig', HREFS);
});

$app->post('/urls', function (Request $request, Response $response, $args): Response {
    $params = $request->getParsedBody();
    if (!empty($params['url']['name'])) {
        $response->getBody()->write(var_export(parse_url($params['url']['name']), true));
    }
    return $response;
});

$app->run();
