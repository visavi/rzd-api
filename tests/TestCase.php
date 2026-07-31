<?php

declare(strict_types=1);

namespace Rzd\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;
use Rzd\Client;
use Rzd\Config;

/**
 * Основа тестов на подставном HTTP-клиенте
 *
 * Ни один тест не обращается к сети: ответы задаются заранее, а ушедшие
 * запросы проверяются по журналу подставного клиента
 */
abstract class TestCase extends BaseTestCase
{
    protected MockClient $http;

    protected function setUp(): void
    {
        $this->http = new MockClient();
    }

    /**
     * Клиент, отвечающий заготовленными телами ответов
     *
     * @param list<string> $responses
     */
    protected function client(array $responses = [], ?Config $config = null): Client
    {
        foreach ($responses as $body) {
            $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));
        }

        $factory = new HttpFactory();

        return new Client($config ?? new Config(), $this->http, $factory, $factory);
    }

    /**
     * Клиент, отвечающий содержимым файлов фикстур
     */
    protected function clientWith(string ...$fixtures): Client
    {
        return $this->client(array_values(array_map($this->fixture(...), $fixtures)));
    }

    /**
     * Клиент, отвечающий заданным кодом и телом
     */
    protected function clientFailing(int $status, string $body = '', string $contentType = 'application/json'): Client
    {
        $this->http->addResponse(new Response($status, ['Content-Type' => $contentType], $body));

        $factory = new HttpFactory();

        return new Client(new Config(), $this->http, $factory, $factory);
    }

    /**
     * Содержимое файла фикстуры
     */
    protected function fixture(string $name): string
    {
        $path = __DIR__ . '/fixtures/' . $name . '.json';

        if (! is_file($path)) {
            self::fail('Нет файла фикстуры: ' . $path);
        }

        return (string) file_get_contents($path);
    }

    /**
     * Запрос по порядку отправки
     */
    protected function request(int $index = 0): RequestInterface
    {
        $requests = $this->http->getRequests();

        self::assertArrayHasKey($index, $requests, 'Запрос с таким номером не отправлялся');

        return $requests[$index];
    }

    /**
     * Параметры строки запроса
     *
     * @return array<string, string>
     */
    protected function query(int $index = 0): array
    {
        parse_str($this->request($index)->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * Тело запроса, разобранное из JSON
     *
     * @return array<string, mixed>
     */
    protected function body(int $index = 0): array
    {
        return (array) json_decode((string) $this->request($index)->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
