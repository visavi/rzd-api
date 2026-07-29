<?php

namespace Rzd\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use JsonException;
use Rzd\Config;
use Rzd\Query;
use RuntimeException;

class QueryTest extends TestCase
{
    /**
     * Создает Query с подменённым транспортом
     *
     * @param array       $responses
     * @param Config|null $config
     *
     * @return Query
     */
    private function query(array $responses, ?Config $config = null): Query
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $config ??= new Config();
        $config->setHandler($stack);

        return new Query($config);
    }

    /**
     * Тест повторного запроса с полученным идентификатором
     */
    public function testResendsRequestWithRid(): void
    {
        $query = $this->query([$this->rid('RID', 777), $this->json('{"result":"OK","tp":[]}')]);

        $content = $query->get('https://pass.rzd.ru/timetable/public/ru', ['layer_id' => 5827]);

        $this->assertSame('OK', $content->result);
        $this->assertCount(2, $this->history);
        $this->assertArrayNotHasKey('rid', $this->params(0));
        $this->assertSame('777', $this->params(1)['rid']);
        $this->assertSame('5827', $this->params(1)['layer_id']);
    }

    /**
     * Тест поддержки ключа REQUEST_ID
     */
    public function testAcceptsRequestIdKey(): void
    {
        $query = $this->query([$this->rid('REQUEST_ID', 555), $this->json('{"result":"OK"}')]);

        $query->get('https://pass.rzd.ru/timetable/public/ru');

        $this->assertSame('555', $this->params(1)['rid']);
    }

    /**
     * Идентификатор должен обновляться, а не залипать на первом
     */
    public function testUpdatesRidOnEveryIteration(): void
    {
        $query = $this->query([
            $this->rid('RID', 111),
            $this->rid('RID', 222),
            $this->json('{"result":"OK"}'),
        ]);

        $query->get('https://pass.rzd.ru/timetable/public/ru');

        $this->assertCount(3, $this->history);
        $this->assertSame('111', $this->params(1)['rid']);
        $this->assertSame('222', $this->params(2)['rid']);
    }

    /**
     * Тест поддержки ключа rid строчными буквами
     */
    public function testAcceptsLowercaseRidKey(): void
    {
        $query = $this->query([
            $this->json('{"result":"RID","rid":333}'),
            $this->json('{"result":"OK"}'),
        ]);

        $query->get('https://pass.rzd.ru/timetable/public/ru');

        $this->assertSame('333', $this->params(1)['rid']);
    }

    /**
     * Сайт может так и не отдать данные, цикл не должен крутиться бесконечно
     */
    public function testStopsAfterTenIterations(): void
    {
        $config = new Config();
        $config->setRetryDelay(0);

        // Тело ответа вычитывается один раз, поэтому каждый ответ должен быть своим
        $responses = array_map(fn() => $this->rid(), range(1, 10));

        $query = $this->query($responses, $config);

        $content = $query->get('https://pass.rzd.ru/timetable/public/ru');

        $this->assertCount(10, $this->history);
        $this->assertSame('RID', $content->result);
    }

    /**
     * Тест паузы перед повторным запросом
     */
    public function testRetryDelayIsConfigurable(): void
    {
        $config = new Config();
        $config->setRetryDelay(0);

        $query = $this->query([$this->rid(), $this->json('{"result":"OK"}')], $config);

        $started = microtime(true);
        $query->get('https://pass.rzd.ru/timetable/public/ru');

        $this->assertLessThan(1, microtime(true) - $started);
        $this->assertSame(1, (new Config())->getRetryDelay());
    }

    /**
     * Тест передачи заголовков из конфига
     */
    public function testSendsConfiguredHeaders(): void
    {
        $config = new Config();
        $config->setUserAgent('TestAgent/1.0');
        $config->setReferer('https://example.com/');

        $query = $this->query([$this->json('{"result":"OK"}')], $config);
        $query->get('https://pass.rzd.ru/timetable/public/ru');

        $request = $this->request(0);

        $this->assertSame('TestAgent/1.0', $request->getHeaderLine('User-Agent'));
        $this->assertSame('https://example.com/', $request->getHeaderLine('Referer'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    /**
     * По умолчанию должен уходить браузерный User-Agent, иначе сайт отвечает 403
     */
    public function testSendsDefaultUserAgent(): void
    {
        $query = $this->query([$this->json('{"result":"OK"}')]);
        $query->get('https://pass.rzd.ru/timetable/public/ru');

        $this->assertSame(Config::DEFAULT_USER_AGENT, $this->request(0)->getHeaderLine('User-Agent'));
    }

    /**
     * Тест возврата кук, полученных на первом шаге
     */
    public function testReturnsCookiesFromFirstResponse(): void
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler([
            $this->rid()->withHeader('Set-Cookie', 'JSESSIONID=abc123; path=/; domain=pass.rzd.ru'),
            $this->json('{"result":"OK"}'),
        ]));
        $stack->push(Middleware::history($this->history));

        $config = new Config();
        $config->setHandler($stack);

        (new Query($config))->get('https://pass.rzd.ru/timetable/public/ru');

        $this->assertSame('', $this->request(0)->getHeaderLine('Cookie'));
        $this->assertStringContainsString('JSESSIONID=abc123', $this->request(1)->getHeaderLine('Cookie'));
    }

    /**
     * Тест GET-запроса, параметры должны уходить в строку запроса
     */
    public function testSendsParamsAsQueryStringForGet(): void
    {
        $query = $this->query([$this->json('{}')]);

        $query->get('https://ticket.rzd.ru/api/v1/suggests', ['Query' => 'ЧЕБ'], 'GET');

        $request = $this->request(0);

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('', (string) $request->getBody());
        $this->assertSame('ЧЕБ', $this->params(0)['Query']);
    }

    /**
     * Тест выброса сообщения об ошибке от сайта
     */
    public function testThrowsMessageFromResponse(): void
    {
        $query = $this->query([
            $this->json('{"result":"OK","tp":[{"msgList":[{"message":"Поезд не найден"}]}]}'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Поезд не найден');

        $query->get('https://pass.rzd.ru/timetable/public/ru');
    }

    /**
     * Тест неизвестного статуса
     */
    public function testThrowsOnUnknownResult(): void
    {
        $query = $this->query([$this->json('{"result":"FAIL"}')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get request data!');

        $query->get('https://pass.rzd.ru/timetable/public/ru');
    }

    /**
     * Тест сообщения об ошибке, пришедшего вместе со статусом
     */
    public function testThrowsMessageFromUnknownResult(): void
    {
        $query = $this->query([$this->json('{"result":"ERROR","message":"Сервис недоступен"}')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Сервис недоступен');

        $query->get('https://pass.rzd.ru/timetable/public/ru');
    }

    /**
     * Тест ответа со статусом RID, но без идентификатора
     */
    public function testThrowsWhenRidMissing(): void
    {
        $query = $this->query([$this->json('{"result":"RID"}')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rid not found!');

        $query->get('https://pass.rzd.ru/timetable/public/ru');
    }

    /**
     * Тест ответа, не являющегося json
     */
    public function testThrowsOnInvalidJson(): void
    {
        $query = $this->query([$this->json('<html>Forbidden</html>')]);

        $this->expectException(JsonException::class);

        $query->get('https://pass.rzd.ru/timetable/public/ru');
    }
}
