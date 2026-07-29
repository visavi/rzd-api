<?php

namespace Rzd\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Rzd\Api;
use Rzd\Config;

abstract class TestCase extends BaseTestCase
{
    /**
     * Перехваченные запросы
     *
     * @var array
     */
    protected array $history = [];

    /**
     * Создает Api с подменённым транспортом
     *
     * @param array $responses Очередь ответов
     *
     * @return Api
     */
    protected function api(array $responses): Api
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $config = new Config();
        $config->setHandler($stack);

        return new Api($config);
    }

    /**
     * Ответ с телом из файла фикстуры
     *
     * @param string $name
     *
     * @return Response
     */
    protected function fixture(string $name): Response
    {
        return $this->json(file_get_contents(__DIR__ . '/fixtures/' . $name . '.json'));
    }

    /**
     * Ответ с произвольным json
     *
     * @param string $body
     * @param int    $status
     *
     * @return Response
     */
    protected function json(string $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }

    /**
     * Ответ первого шага, отдающий идентификатор запроса
     *
     * @param string $key RID или REQUEST_ID
     * @param int    $rid
     *
     * @return Response
     */
    protected function rid(string $key = 'RID', int $rid = 12345): Response
    {
        $result = $key === 'RID' ? 'RID' : 'REQUEST_ID';

        return $this->json(json_encode([$result => $rid, 'result' => $result]));
    }

    /**
     * Запрос из истории
     *
     * @param int $index
     *
     * @return Request
     */
    protected function request(int $index): Request
    {
        return $this->history[$index]['request'];
    }

    /**
     * Параметры запроса из истории
     *
     * @param int $index
     *
     * @return array
     */
    protected function params(int $index): array
    {
        $request = $this->request($index);
        $source = $request->getMethod() === 'GET'
            ? $request->getUri()->getQuery()
            : (string) $request->getBody();

        parse_str($source, $params);

        return $params;
    }

    /**
     * Декодирует ответ Api
     *
     * @param string $response
     *
     * @return array
     */
    protected function decode(string $response): array
    {
        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }
}
