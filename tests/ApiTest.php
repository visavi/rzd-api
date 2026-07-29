<?php

namespace Rzd\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Rzd\Api;
use Rzd\Config;

class ApiTest extends TestCase
{
    /**
     * Конфиг не обязателен, по умолчанию создается свой
     */
    public function testWorksWithoutConfig(): void
    {
        $this->assertInstanceOf(Api::class, new Api());
    }

    /**
     * Язык из конфига должен попадать в путь запроса
     */
    public function testLanguageGoesIntoPath(): void
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler([$this->fixture('routes')]));
        $stack->push(Middleware::history($this->history));

        $config = new Config();
        $config->setLanguage('en');
        $config->setHandler($stack);

        (new Api($config))->trainRoutes(['code0' => '2000000']);

        $this->assertStringEndsWith('/timetable/public/en', (string) $this->request(0)->getUri());
    }

    /**
     * По умолчанию используется русский язык
     */
    public function testDefaultLanguageIsRussian(): void
    {
        $api = $this->api([$this->fixture('routes')]);

        $api->trainRoutes(['code0' => '2000000']);

        $this->assertStringEndsWith('/timetable/public/ru', (string) $this->request(0)->getUri());
    }

    /**
     * Тест получения маршрутов
     */
    public function testTrainRoutes(): void
    {
        $api = $this->api([$this->rid(), $this->fixture('routes')]);

        $routes = $this->decode($api->trainRoutes([
            'code0' => '2000000',
            'code1' => '2004000',
            'dt0'   => '05.08.2026',
        ]));

        $this->assertSame('МОСКВА', $routes['from']);
        $this->assertSame('САНКТ-ПЕТЕРБУРГ', $routes['where']);
        $this->assertFalse($routes['noSeats']);
        $this->assertCount(2, $routes['list']);
        $this->assertSame('022А', $routes['list'][0]['number']);
        $this->assertSame('МОСКВА ОКТ', $routes['list'][0]['route0']);

        $params = $this->params(0);

        $this->assertSame((string) Api::ROUTES_LAYER, $params['layer_id']);
        $this->assertSame('2000000', $params['code0']);
    }

    /**
     * Тест получения маршрутов туда-обратно
     */
    public function testTrainRoutesReturn(): void
    {
        $routes = json_decode(file_get_contents(__DIR__ . '/fixtures/routes.json'), true);
        $routes['tp'][] = $routes['tp'][0];

        $api = $this->api([$this->rid(), $this->json(json_encode($routes))]);

        $result = $this->decode($api->trainRoutesReturn([
            'dir'   => 1,
            'code0' => '2000000',
            'code1' => '2004000',
        ]));

        $this->assertArrayHasKey('forward', $result);
        $this->assertArrayHasKey('back', $result);
        $this->assertSame('022А', $result['forward']['list'][0]['number']);
        $this->assertSame('022А', $result['back']['list'][0]['number']);
        $this->assertSame('МОСКВА', $result['forward']['from']);
    }

    /**
     * Тест получения вагонов
     */
    public function testTrainCarriages(): void
    {
        $api = $this->api([$this->rid(), $this->fixture('carriages')]);

        $carriages = $this->decode($api->trainCarriages([
            'code0' => '2000000',
            'code1' => '2004000',
            'tnum0' => '022А',
        ]));

        $this->assertArrayHasKey('cars', $carriages);
        $this->assertArrayHasKey('schemes', $carriages);
        $this->assertArrayHasKey('companies', $carriages);
        $this->assertSame('02', $carriages['cars'][0]['cnumber']);
        $this->assertSame((string) Api::CARRIAGES_LAYER, $this->params(0)['layer_id']);
    }

    /**
     * Тест дополнительных данных о поезде, список вагонов в них не дублируется
     */
    public function testTrainCarriagesReturnsTrainInfo(): void
    {
        $api = $this->api([$this->rid(), $this->fixture('carriages')]);

        $carriages = $this->decode($api->trainCarriages(['tnum0' => '022А']));

        $this->assertSame('022А', $carriages['train']['number']);
        $this->assertArrayHasKey('route0', $carriages['train']);
        $this->assertArrayNotHasKey('cars', $carriages['train']);
        $this->assertArrayNotHasKey('functionBlocks', $carriages['train']);
        $this->assertSame(10, $carriages['childrenAge']);
        $this->assertNotEmpty($carriages['companyTypes']);
        $this->assertArrayHasKey('insuranceTariffs', $carriages['companyTypes'][0]);
        $this->assertArrayHasKey('motherAndChildAge', $carriages);
        $this->assertArrayHasKey('partialPayment', $carriages);
    }

    /**
     * Тест получения вагонов при пустом ответе
     */
    public function testTrainCarriagesEmpty(): void
    {
        $api = $this->api([$this->json('{"result":"OK"}')]);

        $carriages = $this->decode($api->trainCarriages(['tnum0' => '022А']));

        $this->assertNull($carriages['cars']);
        $this->assertNull($carriages['schemes']);
    }

    /**
     * Тест просмотра станций
     */
    public function testTrainStationList(): void
    {
        $api = $this->api([$this->rid(), $this->fixture('stations')]);

        $stations = $this->decode($api->trainStationList([
            'trainNumber' => '022А',
            'depDate'     => '05.08.2026',
        ]));

        $this->assertArrayHasKey('train', $stations);
        $this->assertArrayHasKey('routes', $stations);
        $this->assertSame('022А', $stations['train']['number']);
        $this->assertSame((string) Api::STATIONS_STRUCTURE_ID, $this->params(0)['STRUCTURE_ID']);
    }

    /**
     * Тест кодов станций
     */
    public function testStationCode(): void
    {
        $api = $this->api([$this->fixture('suggests')]);

        $stations = $this->decode($api->stationCode(['stationNamePart' => 'ЧЕБ']));

        $this->assertNotEmpty($stations);
        $this->assertSame('Чебоксары', $stations[0]['station']);
        $this->assertSame('2060620', $stations[0]['code']);
        $this->assertSame('Город', $stations[0]['type']);
        $this->assertSame('Europe/Moscow', $stations[0]['timezone']);

        // Код пригородных перевозок, которого не было в старом эндпоинте
        $this->assertSame('5389', $stations[0]['codes']['Cbdpr']);
        $this->assertContains('2060899', $stations[0]['stations']);

        $params = $this->params(0);

        $this->assertSame('ЧЕБ', $params['Query']);
        $this->assertSame('ru', $params['Language']);
        $this->assertSame('GET', $this->request(0)->getMethod());
    }

    /**
     * Тест кодов станций при пустой выдаче
     */
    public function testStationCodeEmpty(): void
    {
        $api = $this->api([$this->json('{"total_count":0,"transport_node_suggests":[]}')]);

        $this->assertSame([], $this->decode($api->stationCode(['stationNamePart' => 'ЙЦУ'])));
    }

    /**
     * Тест алиаса Query
     */
    public function testStationCodeAcceptsQueryAlias(): void
    {
        $api = $this->api([$this->fixture('suggests')]);

        $api->stationCode(['Query' => 'ЧЕБ']);

        $this->assertSame('ЧЕБ', $this->params(0)['Query']);
    }

    /**
     * Город и станция приходят разными узлами с одним кодом, дублей быть не должно
     */
    public function testStationCodeDeduplicates(): void
    {
        $api = $this->api([$this->json(json_encode([
            'transport_node_suggests' => [
                ['Name' => 'Чебоксары', 'SubType' => 'Город', 'Codes' => ['Railway' => '2060620']],
                ['Name' => 'Чебоксары', 'SubType' => 'Станция', 'Codes' => ['Railway' => '2060620']],
                ['Name' => 'Чебаркуль', 'SubType' => 'Город', 'Codes' => ['Railway' => '2040425']],
            ],
        ]))]);

        $stations = $this->decode($api->stationCode(['stationNamePart' => 'ЧЕБ']));

        $this->assertCount(2, $stations);
        $this->assertSame(['2060620', '2040425'], array_column($stations, 'code'));
        $this->assertSame('Город', $stations[0]['type']);
    }

}
