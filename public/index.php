<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Hexlet\Code\Controller;
use MaxieSystems\DBAL\DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Symfony\Component\DomCrawler\Crawler;

$dbUrl = parse_url($_ENV['DATABASE_URL']);
DB::Add(
    'PostgreSQL',
    ['dbname' => ltrim($dbUrl['path'], '/'), 'host' => $dbUrl['host'], 'port' => $dbUrl['port']],
    $dbUrl['user'],
    $dbUrl['pass']
);

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
$app = AppFactory::create();
$app->add(
    function ($request, $next) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->get('flash')->__construct($_SESSION);
        return $next->handle($request);
    }
);
$app->addErrorMiddleware(true, true, true);
$twig = Twig::create(__DIR__ . '/../templates', [/*'cache' => __DIR__ . '/../var/cache'*/]);
$app->add(TwigMiddleware::create($app, $twig));
$controller = new Controller($container);
const HREFS = ['href_urls' => '/urls'];

$app->get('/', [$controller, 'showHomepage'])->setName('home');

$app->get('/urls', [$controller, 'showUrls'])->setName('urls');

$app->get('/urls/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    $data = [...HREFS, 'url_id' => $args['id']];
    $flash = $this->get('flash');
    if ($flash->hasMessage('alert')) {
        $data['alert'] = implode(PHP_EOL, $flash->getMessage('alert'));
    }
    $view = Twig::fromRequest($request);
    $res = DB::select('urls', '*, TO_CHAR(created_at, \'YYYY-MM-DD HH24:MI:SS\') AS created', 'id = ?', [$args['id']]);
    if (!count($res)) {
        $data['status'] = '404';
        return $view->render($response, 'error.html.twig', $data)->withStatus(404);
    }
    $data['url'] = $res->fetch();
    $data['url_checks'] = DB::select('url_checks', '*, TO_CHAR(created_at, \'YYYY-MM-DD HH24:MI:SS\') AS created', order_by:'created_at DESC');
    $data['href_checks'] = RouteContext::fromRequest($request)->getRouteParser()->urlFor('checks', ['id' => $data['url']->id]);
    return $view->render($response, 'url.html.twig', $data);
})->setName('url');

$app->post('/urls', function (Request $request, Response $response, array $args): Response {
    $params = $request->getParsedBody();
    $routeParser = RouteContext::fromRequest($request)->getRouteParser();
    $url = '';
    $flash = $this->get('flash');
    if (!empty($params['url']['name'])) {
        $url = $params['url']['name'];
        $isUrl = static fn (string $u): bool => ($u = parse_url($u)) && !empty($u['host']) && isset($u['scheme']) && isset(['http' => 1, 'https' => 1][$u['scheme']]);
        // filter_var($params['url']['name'], FILTER_VALIDATE_URL) // не работает с национальными URL (https://дом.рф)
        if ($isUrl($url)) {
            $res = DB::select('urls', '*', 'name = ?', [$url]);
            if (count($res)) {
                $urlId = $res->fetchField('id');
                $msg = 'Страница уже существует';
            } else {
                $urlId = DB::insert('urls', ['name' => $url]);
                $msg = 'Страница успешно добавлена';
            }
            $flash->addMessage('alert', $msg);
            return $response->withStatus(302)->withHeader('Location', $routeParser->urlFor('url', ['id' => $urlId]));
        }
    }
    $flash->addMessage('invalid_url', $url);
    return $response->withStatus(302)->withHeader('Location', $routeParser->urlFor('home'));
})->setName('add_url');

$app->post('/urls/{id:[0-9]+}/checks', function (Request $request, Response $response, array $args): Response {
    $flash = $this->get('flash');
    $routeParser = RouteContext::fromRequest($request)->getRouteParser();
    $res = DB::select('urls', '*', 'id = ?', [$args['id']]);
    if (!count($res)) {
        $flash->addMessage('alert', 'URL не найден');
        return $response->withStatus(302)->withHeader('Location', $routeParser->urlFor('urls'));
    }
    $url = $res->fetch();
    $client = new Client(['http_errors' => false]);
    try {
        $r = $client->get($url->name);
        if (false !== strpos($r->getHeaderLine('content-type'), 'text/html')) {
            $crawler = new Crawler($r->getBody()->getContents());
            $row2insert = ['url_id' => $args['id'], 'status_code' => $r->getStatusCode()];
            foreach (['h1' => 'h1', 'title' => 'title', 'description' => 'meta[name="description"][content]'] as $field => $selector) {
                foreach ($crawler->filter($selector) as $node) {
                    $value = trim($node->nodeName === 'meta' ? $node->getAttribute('content') : $node->nodeValue);    
                    $row2insert[$field] = $value;
                    if ($value !== '') {
                        break;
                    }
                }
            }
            DB::insert('url_checks', $row2insert);
            $msg = 'Страница успешно проверена';
        } else {
            $msg = 'Это не HTML-документ';
        }
    } catch (RequestException | ConnectException $e) {
        $msg = $e->getMessage();
    }
    $flash->addMessage('alert', $msg);
    return $response->withStatus(302)->withHeader('Location', $routeParser->urlFor('url', ['id' => $args['id']]));
})->setName('checks');

$app->run();
