<?php

declare(strict_types=1);

namespace Hexlet\Code;

use DI\Container;
use MaxieSystems\DBAL\DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class Controller
{
    public function __construct(
        readonly private Container $container,
    ) {}

    private Messages $flash {
        get => $this->container->get('flash');
    }
    private function getRouteParser(Request $request): RouteParserInterface
    {
        static $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        return $routeParser;
    }

    public function showUrls(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $urls = DB::select('urls', '*, TO_CHAR(created_at, \'YYYY-MM-DD HH24:MI:SS\') AS created', order_by:'created_at DESC');
        $routeParser = $this->getRouteParser($request);
        return $view->render($response, 'urls.html.twig', ['urls' => $urls->setCallback(static function (object $row) use ($routeParser): void {
            $row->href = $routeParser->urlFor('url', ['id' => $row->id]);
        })]);
    }
 
    public function showHomepage(Request $request, Response $response): Response
    {
        $data = [];
        if ($this->flash->hasMessage('invalid_url')) {
            $data['invalid_url'] = true;
            $data['i_url_value'] = $this->flash->getFirstMessage('invalid_url');
        }
        return $this->render($request, $response, 'main', $data);
    }

    public function render(Request $request, Response $response, string $template, array $data): Response
    {
        $data['href_urls'] = $this->getRouteParser($request)->urlFor('urls');
        return Twig::fromRequest($request)->render($response, "$template.html.twig", $data);
    }
}
