<?php

declare(strict_types=1);

namespace Hexlet\Code;

use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use MaxieSystems\DBAL\DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Symfony\Component\DomCrawler\Crawler;

final class Controller
{
    public function __construct(
        readonly private Container $container,
    ) {
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function checkUrl(Request $request, Response $response, array $args): Response
    {
        $res = DB::select('urls', '*', 'id = ?', [$args['id']]);
        if (!\count($res)) {
            $this->flash->addMessage('alert', 'URL не найден');
            return $response->withStatus(302)->withHeader('Location', $this->getRouteParser($request)->urlFor('urls'));
        }
        /** @var object $url */
        $url = $res->fetch();
        $client = new Client(['http_errors' => false]);
        try {
            $r = $client->get($url->name);// @phpstan-ignore property.notFound
            if (false !== strpos($r->getHeaderLine('content-type'), 'text/html')) {
                $crawler = new Crawler($r->getBody()->getContents());
                $row2insert = ['url_id' => $args['id'], 'status_code' => $r->getStatusCode()];
                foreach (
                    [
                        'h1' => 'h1', 'title' => 'title', 'description' => 'meta[name="description"][content]'
                    ] as $field => $selector
                ) {
                    /** @var \DOMElement $node */
                    foreach ($crawler->filter($selector) as $node) {
                        $value = trim($node->nodeName === 'meta' ? $node->getAttribute('content') : $node->textContent);
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
        $this->flash->addMessage('alert', $msg);
        return $response->withStatus(302)->withHeader(
            'Location',
            $this->getRouteParser($request)->urlFor('url', ['id' => $args['id']])
        );
    }

    public function addUrl(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $url = '';
        if (!empty($params['url']['name'])) {// @phpstan-ignore offsetAccess.nonOffsetAccessible
            $url = $params['url']['name'];
            $isUrl = static fn (string $u): bool => ($u = parse_url($u))
                && !empty($u['host'])
                && isset($u['scheme'])
                && isset(['http' => 1, 'https' => 1][$u['scheme']]);
            //filter_var($params['url']['name'], FILTER_VALIDATE_URL)# не работает с национальными URL (https://дом.рф)
            if ($isUrl($url)) {
                $res = DB::select('urls', '*', 'name = ?', [$url]);
                if (\count($res)) {
                    $urlId = $res->fetchField('id');
                    $msg = 'Страница уже существует';
                } else {
                    $urlId = DB::insert('urls', ['name' => $url]);
                    $msg = 'Страница успешно добавлена';
                }
                $this->flash->addMessage('alert', $msg);
                return $response->withStatus(302)->withHeader(
                    'Location',
                    $this->getRouteParser($request)->urlFor('url', ['id' => $urlId])
                );
            }
        }
        return $this->render(
            $request,
            $response,
            'main',
            ['invalid_url' => true, 'i_url_value' => $url]
        )->withStatus(422);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function showUrl(Request $request, Response $response, array $args): Response
    {
        $data = ['url_id' => $args['id']];
        if ($this->flash->hasMessage('alert')) {
            $data['alert'] = implode(PHP_EOL, $this->flash->getMessage('alert'));// @phpstan-ignore argument.type
        }
        $res = DB::select(
            'urls',
            '*, TO_CHAR(created_at, \'YYYY-MM-DD HH24:MI:SS\') AS created',
            'id = ?',
            [$args['id']]
        );
        if (!\count($res)) {
            $data['status'] = '404';
            return $this->render($request, $response, 'error', $data)->withStatus(404);
        }
        $data['url'] = $res->fetch();
        $data['url_checks'] = DB::select(
            'url_checks',
            '*, TO_CHAR(created_at, \'YYYY-MM-DD HH24:MI:SS\') AS created',
            'url_id = ?',
            [$args['id']],
            order_by:'created_at DESC'
        );
        $data['href_checks'] = $this->getRouteParser($request)->urlFor('checks', $args);
        return $this->render($request, $response, 'url', $data);
    }

    public function showUrls(Request $request, Response $response): Response
    {
        $urls = DB::query(<<<QUERY
SELECT u.id, u.name, c.status_code, TO_CHAR(c.created_at, 'YYYY-MM-DD HH24:MI:SS') AS last FROM urls AS u
LEFT JOIN (
        SELECT
            url_id, status_code, created_at,
            ROW_NUMBER() OVER (PARTITION BY url_id ORDER BY created_at DESC) AS rn
        FROM url_checks
    ) AS c ON u.id = c.url_id
WHERE rn = 1
ORDER BY u.created_at DESC
QUERY   );
        $routeParser = $this->getRouteParser($request);
        return $this->render(
            $request,
            $response,
            'urls',
            ['urls' => $urls->setCallback(static function (object $row) use ($routeParser): void {
                $row->href = $routeParser->urlFor(// @phpstan-ignore property.notFound
                    'url',
                    ['id' => $row->id]// @phpstan-ignore property.notFound
                );
            })]
        );
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

    private Messages $flash {// phpcs:ignore
        /** @phpstan-ignore return.type */
        get => $this->container->get('flash');// phpcs:ignore
    }

    private function getRouteParser(Request $request): RouteParserInterface
    {
        static $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        return $routeParser;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param string $template
     * @param array<string, mixed> $data
     * @return Response
     */
    private function render(Request $request, Response $response, string $template, array $data): Response
    {
        $data['href_urls'] = $this->getRouteParser($request)->urlFor('urls');
        return Twig::fromRequest($request)->render($response, "$template.html.twig", $data);
    }
}
