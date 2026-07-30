<?php

declare(strict_types=1);

namespace Rzd\Tests\Http;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Rzd\Client;
use Rzd\Config;
use Rzd\Exception\ApiException;
use Rzd\Exception\ForbiddenException;
use Rzd\Exception\MalformedResponseException;
use Rzd\Exception\RzdException;
use Rzd\Exception\TransportException;
use Rzd\Http\Transport;
use Rzd\Tests\Concerns\AssertsExceptions;
use Rzd\Tests\TestCase;

final class TransportTest extends TestCase
{
    use AssertsExceptions;

    #[Test]
    public function sendsBrowserUserAgentByDefault(): void
    {
        $this->client(['{}'])->references->appConfig();

        self::assertSame(Config::DEFAULT_USER_AGENT, $this->request()->getHeaderLine('User-Agent'));
        self::assertStringContainsString('application/json', $this->request()->getHeaderLine('Accept'));
    }

    #[Test]
    public function allowsCustomUserAgent(): void
    {
        $config = new Config(userAgent: 'MyApp/1.0');

        $this->client(['{}'], $config)->references->appConfig();

        self::assertSame('MyApp/1.0', $this->request()->getHeaderLine('User-Agent'));
    }

    #[Test]
    public function omitsEmptyUserAgent(): void
    {
        $config = new Config(userAgent: '');

        $this->client(['{}'], $config)->references->appConfig();

        self::assertFalse($this->request()->hasHeader('User-Agent'));
    }

    #[Test]
    public function addsHeadersFromConfig(): void
    {
        $config = (new Config())->withHeaders(['X-Client-ID' => '22900']);

        $this->client(['{}'], $config)->references->appConfig();

        self::assertSame('22900', $this->request()->getHeaderLine('X-Client-ID'));
    }

    #[Test]
    public function encodesCyrillicInQuery(): void
    {
        $this->client(['{}'])->stations->suggest('Чебоксары');

        $uri = (string) $this->request()->getUri();

        // Сайт отвечает 400 на некодированную кириллицу в строке запроса
        self::assertStringNotContainsString('Чебоксары', $uri);
        self::assertStringContainsString('%D0%A7%D0%B5%D0%B1', $uri);
    }

    #[Test]
    public function omitsQueryStringWhenNoParameters(): void
    {
        $this->client(['{}'])->references->appConfig();

        self::assertSame('', $this->request()->getUri()->getQuery());
    }

    #[Test]
    public function wrapsClientErrorIntoTransportException(): void
    {
        $failure = new class ('Соединение разорвано') extends RuntimeException implements ClientExceptionInterface {
        };

        $this->http->addException($failure);

        $factory = Psr17FactoryDiscovery::findRequestFactory();

        $client = new Client(new Config(), $this->http, $factory, Psr17FactoryDiscovery::findStreamFactory());

        $this->assertThrows(
            TransportException::class,
            'Соединение разорвано',
            fn() => $client->references->appConfig(),
        );
    }

    #[Test]
    public function reportsForbiddenSeparately(): void
    {
        $body = '<h1>Forbidden</h1><pre>Request ID: 1|2026-07-30-10-07-51</pre>';

        try {
            $this->clientFailing(403, $body, 'text/html')->references->appConfig();
            self::fail('Ожидалось исключение о блокировке');
        } catch (ForbiddenException $e) {
            self::assertSame(403, $e->statusCode());
            self::assertSame($body, $e->body());
            self::assertSame('Сайт ответил кодом 403', $e->getMessage());
        }
    }

    #[Test]
    public function extractsErrorMessageFromResponse(): void
    {
        $body = '{"Code":"i1","code":"INTERNAL_ERROR","Message":"Внутренняя ошибка сервиса"}';

        try {
            $this->clientFailing(500, $body)->references->appConfig();
            self::fail('Ожидалось исключение об ошибке сайта');
        } catch (ApiException $e) {
            self::assertSame('Внутренняя ошибка сервиса', $e->getMessage());
            self::assertSame('INTERNAL_ERROR', $e->errorCode());
            self::assertSame(500, $e->statusCode());
            self::assertSame(500, $e->getCode());
        }
    }

    #[Test]
    public function readsErrorMessageFromErrorField(): void
    {
        $body = '{"error":"Сессия истекла"}';

        $this->assertThrows(
            ApiException::class,
            'Сессия истекла',
            fn() => $this->clientFailing(400, $body)->references->appConfig(),
        );
    }

    #[Test]
    public function reportsStatusWhenNoErrorMessage(): void
    {
        try {
            $this->clientFailing(502, 'Bad Gateway', 'text/plain')->references->appConfig();
            self::fail('Ожидалось исключение об ошибке сайта');
        } catch (ApiException $e) {
            self::assertSame('Сайт ответил кодом 502', $e->getMessage());
            self::assertNull($e->errorCode());
        }
    }

    #[Test]
    public function reportsStatusWhenErrorMessageIsNotString(): void
    {
        $this->assertThrows(
            ApiException::class,
            'Сайт ответил кодом 400',
            fn() => $this->clientFailing(400, '{"Message":{"вложенный":"объект"}}')->references->appConfig(),
        );
    }

    #[Test]
    public function reportsMalformedResponse(): void
    {
        try {
            $this->client(['<html>Страница ошибки</html>'])->references->appConfig();
            self::fail('Ожидалось исключение о формате ответа');
        } catch (MalformedResponseException $e) {
            self::assertStringContainsString('не является JSON', $e->getMessage());
            self::assertSame('<html>Страница ошибки</html>', $e->body());
        }
    }

    #[Test]
    public function reportsResponseThatIsNotObject(): void
    {
        $this->assertThrows(
            MalformedResponseException::class,
            'не является объектом или массивом',
            fn() => $this->client(['42'])->references->appConfig(),
        );
    }

    #[Test]
    public function allExceptionsShareCommonInterface(): void
    {
        $this->expectException(RzdException::class);

        $this->clientFailing(500, '{}')->references->appConfig();
    }

    #[Test]
    public function sendsEmptyBodyAsObject(): void
    {
        $this->client(['{"Tariffs":[]}'])->references->tariffs();

        // Сайт запрашивает справочники телом {}, на [] отвечает ошибкой
        self::assertSame('{}', (string) $this->request()->getBody());
    }

    #[Test]
    public function keepsNestedListsAsArrays(): void
    {
        $config = new Config();
        $factory = Psr17FactoryDiscovery::findRequestFactory();

        $transport = new Transport(
            $this->http,
            $factory,
            Psr17FactoryDiscovery::findStreamFactory(),
            $config,
        );

        $this->http->addResponse(new Response(200, [], '{}'));

        $transport->post('/test', ['Passengers' => ['Иванов', 'Петров']]);

        self::assertSame('{"Passengers":["Иванов","Петров"]}', (string) $this->request()->getBody());
    }

    #[Test]
    public function sendsBooleanParametersAsWords(): void
    {
        $this->client(['{"transport_node_suggests":[]}'])->stations->suggest('Мос');

        self::assertSame('true', $this->query()['GroupResults']);
    }

    #[Test]
    public function sendsSingleRequest(): void
    {
        $this->client(['{}'])->references->appConfig();

        // Прежний протокол требовал двух запросов с обменом идентификатором,
        // новому API достаточно одного
        self::assertCount(1, $this->http->getRequests());
    }

    #[Test]
    public function returnsRawBody(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';

        self::assertSame($svg, $this->client([$svg])->cars->schemeImage(1));
    }

    #[Test]
    public function buildsPsr7Request(): void
    {
        // Проверяем, что подставной клиент получает именно PSR-7 запрос
        $this->client(['{}'])->references->appConfig();

        self::assertInstanceOf(Request::class, $this->request());
    }
}
