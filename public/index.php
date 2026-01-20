<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Hexlet\Code\Controller;
use MaxieSystems\DBAL\DB;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Exception\HttpNotFoundException;

if (!($dbUrl = getenv('DATABASE_URL')) || ($dbUrl = parse_url($dbUrl)) === false) {
    die('Invalid DATABASE_URL');
}
$params = ['dbname' => ltrim($dbUrl['path'], '/')];
foreach (['host', 'port'] as $k) {
    if (!empty($dbUrl[$k])) {
        $params[$k] = $dbUrl[$k];
    }
}
DB::Add('PostgreSQL', $params, $dbUrl['user'], $dbUrl['pass']);

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(
    [
        'flash' => static function (): Messages {
            $storage = [];
            return new Messages($storage);
        }
    ]
);
$container = $containerBuilder->build();
AppFactory::setContainer($container);

/** @var App<ContainerInterface> $app */
$app = AppFactory::create();
$app->add(
    function ($request, $next) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->get('flash')->__construct($_SESSION);// @phpstan-ignore variable.undefined
        return $next->handle($request);
    }
);
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$twig = Twig::create(__DIR__ . '/../templates');
$app->add(TwigMiddleware::create($app, $twig));
$controller = new Controller($app);

$app->get('/', [$controller, 'showHomepage'])->setName('home');
$app->get('/urls', [$controller, 'showUrls'])->setName('urls');
$app->get('/urls/{id:[0-9]+}', [$controller, 'showUrl'])->setName('url');
$app->post('/urls', [$controller, 'addUrl'])->setName('add_url');
$app->post('/urls/{id:[0-9]+}/checks', [$controller, 'checkUrl'])->setName('checks');
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, [$controller, 'showErrorPage']);

$app->run();
