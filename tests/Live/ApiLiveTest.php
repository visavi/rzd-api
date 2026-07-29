<?php

namespace Rzd\Tests\Live;

use DateTime;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;
use Rzd\Api;
use Rzd\Config;

/**
 * Проверка реальных ответов сайта, ловит смену протокола
 *
 * Сайт доступен только с российских адресов, вне РФ нужен прокси:
 * RZD_PROXY=socks5://127.0.0.1:1080 vendor/bin/phpunit --group live
 *
 * @group live
 */
class ApiLiveTest extends TestCase
{
    private Api $api;
    private string $date0;
    private string $date1;

    protected function setUp(): void
    {
        $config = new Config();
        $config->setTimeout(30);

        if ($proxy = getenv('RZD_PROXY')) {
            $config->setProxy($proxy);
        }

        $this->api = new Api($config);

        $this->date0 = (new DateTime('+1 day'))->format('d.m.Y');
        $this->date1 = (new DateTime('+5 day'))->format('d.m.Y');
    }

    /**
     * Декодирует ответ Api, пропуская тест если сайт недоступен
     *
     * @param callable $request
     *
     * @return array
     */
    private function request(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectException $e) {
            $this->markTestSkipped('Сайт rzd.ru недоступен: ' . $e->getMessage());
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Параметры поиска маршрута
     *
     * @return array
     */
    private function routeParams(): array
    {
        return [
            'dir'        => 0,
            'tfl'        => 3,
            'checkSeats' => 1,
            'code0'      => '2004000',
            'code1'      => '2000000',
            'dt0'        => $this->date0,
        ];
    }

    /**
     * Тест получения маршрутов
     */
    public function testTrainRoutes(): void
    {
        $trainRoutes = $this->request(fn() => $this->api->trainRoutes($this->routeParams()));

        $this->assertSame('САНКТ-ПЕТЕРБУРГ', $trainRoutes['from']);
        $this->assertSame('МОСКВА', $trainRoutes['where']);
        $this->assertArrayHasKey('noSeats', $trainRoutes);
        $this->assertNotEmpty($trainRoutes['list']);
        $this->assertSame('С-ПЕТЕР-ГЛ', $trainRoutes['list'][0]['route0']);
    }

    /**
     * Тест получения маршрутов туда-обратно
     */
    public function testTrainRoutesReturn(): void
    {
        $params = $this->routeParams() + ['dt1' => $this->date1];
        $params['dir'] = 1;

        $trainRoutesReturn = $this->request(fn() => $this->api->trainRoutesReturn($params));

        $this->assertNotEmpty($trainRoutesReturn['forward']['list']);
        $this->assertNotEmpty($trainRoutesReturn['back']['list']);
        $this->assertSame('С-ПЕТЕР-ГЛ', $trainRoutesReturn['forward']['list'][0]['route0']);
    }

    /**
     * Тест получения вагонов
     */
    public function testTrainCarriages(): void
    {
        $routes = $this->request(fn() => $this->api->trainRoutes($this->routeParams()))['list'];

        $this->assertNotEmpty($routes);

        $params = [
            'dir'   => 0,
            'code0' => '2004000',
            'code1' => '2000000',
            'dt0'   => $routes[0]['date0'],
            'time0' => $routes[0]['time0'],
            'tnum0' => $routes[0]['number'],
        ];

        $trainCarriages = $this->request(fn() => $this->api->trainCarriages($params));

        $this->assertArrayHasKey('cars', $trainCarriages);
        $this->assertNotEmpty($trainCarriages['cars']);
        $this->assertArrayHasKey('cnumber', $trainCarriages['cars'][0]);
    }

    /**
     * Тест получения вагонов в поездах с большим числом свободных мест
     *
     * По issue #17 и #23 сайт отдавал по ним ошибку сессии или бесконечный RID,
     * поэтому берется дальняя дата на длинном направлении, где мест максимум
     */
    public function testTrainCarriagesWithManyFreeSeats(): void
    {
        $date = (new DateTime('+45 day'))->format('d.m.Y');

        $params = [
            'dir'        => 0,
            'tfl'        => 3,
            'checkSeats' => 1,
            'code0'      => '2000000',
            'code1'      => '2064001',
            'dt0'        => $date,
        ];

        $routes = $this->request(fn() => $this->api->trainRoutes($params))['list'];

        $this->assertNotEmpty($routes);

        // Поезда с наибольшим числом мест идут первыми
        usort($routes, static fn($a, $b) => array_sum(array_column($b['cars'] ?? [], 'freeSeats'))
            <=> array_sum(array_column($a['cars'] ?? [], 'freeSeats')));

        foreach (array_slice($routes, 0, 3) as $route) {
            $carriages = $this->request(fn() => $this->api->trainCarriages([
                'dir'   => 0,
                'code0' => '2000000',
                'code1' => '2064001',
                'dt0'   => $route['date0'],
                'time0' => $route['time0'],
                'tnum0' => $route['number'],
            ]));

            $this->assertNotEmpty($carriages['cars'], 'Нет вагонов у поезда ' . $route['number']);
            $this->assertSame($route['number'], $carriages['train']['number']);
        }
    }

    /**
     * Тест просмотра станций
     */
    public function testTrainStationList(): void
    {
        $routes = $this->request(fn() => $this->api->trainRoutes($this->routeParams()))['list'];

        $this->assertNotEmpty($routes);

        $params = [
            'trainNumber' => $routes[0]['number'],
            'depDate'     => $routes[0]['date0'],
        ];

        $trainStationList = $this->request(fn() => $this->api->trainStationList($params));

        $this->assertArrayHasKey('train', $trainStationList);
        $this->assertArrayHasKey('routes', $trainStationList);
        $this->assertSame($routes[0]['number'], $trainStationList['train']['number']);
    }

    /**
     * Тест кодов станций
     */
    public function testStationCode(): void
    {
        $stationCode = $this->request(fn() => $this->api->stationCode(['stationNamePart' => 'ЧЕБ']));

        $this->assertNotEmpty($stationCode);
        $this->assertContains('Чебоксары', array_column($stationCode, 'station'));
        $this->assertArrayHasKey('Cbdpr', $stationCode[0]['codes']);
        $this->assertNotEmpty($stationCode[0]['timezone']);

        $codes = array_column($stationCode, 'code');

        $this->assertSame($codes, array_unique($codes));
    }
}
