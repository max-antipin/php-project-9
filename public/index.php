<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
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

$app->get('/urls/{id}', function (Request $request, Response $response, $args): Response {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'url.html.twig', [...HREFS, 'url_id' => $args['id']]);
});

$app->get('/urls/{id}/checks', function (Request $request, Response $response, $args): Response {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'checks.html.twig', HREFS);
});

$app->post('/urls', function (Request $request, Response $response, $args): Response {
    $params = $request->getParsedBody();
    if (!empty($params['url']['name'])) {
        $url = $params['url']['name'];
        $isUrl = static fn (string $u): bool => ($u = parse_url($u)) && !empty($u['host']) && isset($u['scheme']) && isset(['http' => 1, 'https' => 1][$u['scheme']]);
        // filter_var($params['url']['name'], FILTER_VALIDATE_URL) // не работает с национальными URL (https://дом.рф)
        if ($isUrl($url)) {
            $client = new Client();
            try {
                $r = $client->get($url);
                $text = $url . ' ' . $r->getStatusCode();
            } catch (RequestException $e) {
                $text = $e->getMessage();
            }
        } else {
            $text = 'Invalid URL';
        }
        $response->getBody()->write($text);
    }
    return $response;
});

$app->post('/urls/{id}/checks', function (Request $request, Response $response, $args): Response {
    $response->getBody()->write($args['id']);
    return $response;
});

$app->run();
