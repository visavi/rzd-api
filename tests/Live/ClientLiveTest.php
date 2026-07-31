<?php

declare(strict_types=1);

namespace Rzd\Tests\Live;

use DateTimeImmutable;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rzd\Client;
use Rzd\Config;
use Rzd\Enum\SchemeView;
use Rzd\Exception\TransportException;
use Rzd\Model\Station;
use Rzd\Model\Train;
use Rzd\Request\CarSchemeSearch;
use Rzd\Request\CarSearch;
use Rzd\Request\RouteSearch;
use Rzd\Request\TrainSearch;
use Rzd\Request\TransferSearch;

/**
 * Проверка реальных ответов сайта, ловит смену протокола
 *
 * Сайт принимает запросы только с российских адресов, вне РФ нужен прокси:
 * RZD_PROXY=socks5://127.0.0.1:1080 vendor/bin/phpunit --group live
 */
#[Group('live')]
final class ClientLiveTest extends TestCase
{
    /** Москва, код города */
    private const MOSCOW = '2000000';

    /** Санкт-Петербург, код города */
    private const SAINT_PETERSBURG = '2004000';

    private Client $client;

    protected function setUp(): void
    {
        $options = ['timeout' => 30];

        if ($proxy = getenv('RZD_PROXY')) {
            $options['proxy'] = $proxy;
        }

        $factory = new HttpFactory();

        $this->client = new Client(
            new Config(),
            new GuzzleClient($options),
            $factory,
            $factory,
        );
    }

    /**
     * Пропускает тест, если сайт недоступен: без российского адреса
     * соединение просто уходит в таймаут
     */
    private function guard(callable $request): mixed
    {
        try {
            return $request();
        } catch (TransportException $e) {
            self::markTestSkipped('Сайт недоступен: ' . $e->getMessage());
        }
    }

    private function date(string $shift = '+7 days'): DateTimeImmutable
    {
        return new DateTimeImmutable($shift);
    }

    /**
     * Поезд, найденный на ближайшую неделю
     */
    private function anyTrain(): Train
    {
        $result = $this->guard(fn() => $this->client->trains->search(new TrainSearch(
            self::MOSCOW,
            self::SAINT_PETERSBURG,
            $this->date(),
        )));

        self::assertGreaterThan(0, count($result), 'Между Москвой и Петербургом всегда есть поезда');

        foreach ($result as $train) {
            if ($train->freeSeats() > 0 && $train->number !== null) {
                return $train;
            }
        }

        self::markTestSkipped('На выбранную дату нет поездов со свободными местами');
    }

    #[Test]
    public function findsTrains(): void
    {
        $result = $this->guard(fn() => $this->client->trains->search(new TrainSearch(
            self::MOSCOW,
            self::SAINT_PETERSBURG,
            $this->date(),
        )));

        self::assertGreaterThan(10, count($result));
        self::assertSame(self::MOSCOW, $result->originCode);
        self::assertNotNull($result->originName);

        $train = $result->trains[0];

        self::assertNotNull($train->number);
        self::assertNotNull($train->departure);
        self::assertNotNull($train->provider);
        self::assertNotNull($train->originStationCode);
        self::assertGreaterThan(0, $train->duration);
        self::assertNotSame([], $train->carriers);
    }

    #[Test]
    public function findsTrainsThereAndBack(): void
    {
        $trip = $this->guard(fn() => $this->client->trains->searchReturn(
            new TrainSearch(self::MOSCOW, self::SAINT_PETERSBURG, $this->date()),
            $this->date('+14 days'),
        ));

        self::assertGreaterThan(10, count($trip->forward));
        self::assertGreaterThan(10, count($trip->back));

        // Станции обратного плеча переставлены
        self::assertSame(self::SAINT_PETERSBURG, $trip->back->originCode);
        self::assertSame(self::MOSCOW, $trip->back->destinationCode);

        self::assertTrue($trip->hasSeats());
        self::assertGreaterThan(0, $trip->minPrice());
    }

    #[Test]
    public function returnsCarsWithPlaceNumbers(): void
    {
        $train = $this->anyTrain();

        $cars = $this->guard(fn() => $this->client->cars->search(CarSearch::forTrain($train)));

        self::assertGreaterThan(0, count($cars), 'У поезда с местами должны быть вагоны');
        self::assertSame($train->number, $cars->train?->number);

        $withSeats = $cars->withSeats();

        self::assertNotSame([], $withSeats);
        self::assertNotSame([], $withSeats[0]->placeNumbers());
        self::assertNotNull($withSeats[0]->number);
        self::assertNotNull($withSeats[0]->carrier);
    }

    /**
     * По issue вагоны не отдавались у поездов с большим числом
     * свободных мест, поэтому берется дальняя дата на длинном направлении
     */
    #[Test]
    public function returnsCarsForTrainsWithManySeats(): void
    {
        $result = $this->guard(fn() => $this->client->trains->search(new TrainSearch(
            self::MOSCOW,
            '2064001', // Адлер
            $this->date('+45 days'),
        )));

        $trains = $result->withSeats();

        self::assertNotSame([], $trains);

        usort($trains, static fn(Train $a, Train $b): int => $b->freeSeats() <=> $a->freeSeats());

        foreach (array_slice($trains, 0, 3) as $train) {
            $cars = $this->guard(fn() => $this->client->cars->search(CarSearch::forTrain($train)));

            self::assertNotSame([], $cars->withSeats(), 'Нет вагонов у поезда ' . $train->number);
        }
    }

    #[Test]
    public function returnsTrainRoute(): void
    {
        $train = $this->anyTrain();

        $route = $this->guard(fn() => $this->client->routes->forTrain(RouteSearch::forTrain($train)));

        self::assertGreaterThan(1, count($route));
        self::assertNotNull($route->name);

        $first = $route->stops[0];

        self::assertNotNull($first->stationName);
        self::assertNotNull($first->stationCode);
        self::assertNotNull($first->departure);
    }

    #[Test]
    public function returnsCarSchemeAndImages(): void
    {
        $train = $this->anyTrain();

        $cars = $this->guard(fn() => $this->client->cars->search(CarSearch::forTrain($train)));

        foreach ($cars->withSeats() as $car) {
            if ($car->subType === null || $car->carrier === null) {
                continue;
            }

            $request = CarSchemeSearch::forCar($car, $train);
            $scheme = $this->guard(fn() => $this->client->cars->scheme($request));

            if ($scheme->schemeId === null) {
                continue;
            }

            self::assertTrue($scheme->has(SchemeView::DesktopFirstStorey));

            $svg = $this->guard(fn() => $this->client->cars->schemeImage($scheme->schemeId));

            self::assertStringContainsString('<svg', $svg);

            return;
        }

        self::markTestSkipped('Не нашлось вагона со схемой');
    }

    #[Test]
    public function findsStationsByName(): void
    {
        $stations = $this->guard(fn() => $this->client->stations->suggest('ЧЕБ'));

        self::assertNotSame([], $stations);

        $names = array_map(static fn(Station $station): ?string => $station->name, $stations);

        self::assertContains('Чебоксары', $names);
        self::assertNotNull($stations[0]->code);
        self::assertNotNull($stations[0]->timezone);

        $codes = array_map(static fn(Station $station): ?string => $station->code, $stations);

        self::assertSame($codes, array_values(array_unique($codes)));
    }

    #[Test]
    public function returnsPopularCities(): void
    {
        $cities = $this->guard(fn() => $this->client->stations->popular());

        self::assertNotSame([], $cities);
        self::assertNotNull($cities[0]->nodeId);
        self::assertNotNull($cities[0]->code);
    }

    #[Test]
    public function returnsCityByNodeId(): void
    {
        $cities = $this->guard(fn() => $this->client->stations->popular());

        $nodeId = $cities[0]->nodeId;

        self::assertNotNull($nodeId);

        $station = $this->guard(fn() => $this->client->stations->byNodeId($nodeId));

        self::assertSame($cities[0]->code, $station->code);
    }

    #[Test]
    public function returnsDatesWithSeats(): void
    {
        $dates = $this->guard(fn() => $this->client->prices->availability(
            self::MOSCOW,
            self::SAINT_PETERSBURG,
            $this->date('+7 days'),
            $this->date('+21 days'),
        ));

        self::assertNotSame([], $dates);
        self::assertContainsOnlyInstancesOf(DateTimeImmutable::class, $dates);
    }

    #[Test]
    public function returnsPriceCalendar(): void
    {
        $days = $this->guard(fn() => $this->client->prices->calendar(
            self::MOSCOW,
            self::SAINT_PETERSBURG,
            $this->date(),
        ));

        self::assertNotSame([], $days);
        self::assertNotNull($days[0]->date);
        self::assertGreaterThan(0, $days[0]->minPrice);
        self::assertNotSame([], $days[0]->byCarType());
    }

    #[Test]
    public function returnsTariffReference(): void
    {
        $tariffs = $this->guard(fn() => $this->client->references->tariffs());

        self::assertGreaterThan(10, count($tariffs));
        self::assertNotNull($tariffs[0]->sysName);
    }

    #[Test]
    public function returnsCardsLive(): void
    {
        $cards = $this->guard(fn() => $this->client->references->cards());

        self::assertGreaterThan(10, count($cards));
        self::assertNotNull($cards[0]->code);
        self::assertNotNull($cards[0]->name);
        self::assertGreaterThan(0, $cards[0]->price);
    }

    #[Test]
    public function returnsSaleCalendarLive(): void
    {
        $months = $this->guard(fn() => $this->client->prices->saleCalendar(
            self::MOSCOW,
            self::SAINT_PETERSBURG,
        ));

        self::assertGreaterThan(1, count($months));
        self::assertNotNull($months[0]->year);
        self::assertNotSame([], $months[0]->saleDays);
        self::assertNotSame([], $months[0]->dates());
    }

    #[Test]
    public function returnsAeroexpressTariffsLive(): void
    {
        $tariffs = $this->guard(fn() => $this->client->aeroexpress->tariffs($this->date()));

        self::assertNotSame([], $tariffs);
        self::assertNotNull($tariffs[0]->name);
        self::assertGreaterThan(0, $tariffs[0]->price);
    }

    #[Test]
    public function findsRoutesWithTransfers(): void
    {
        // Прямых поездов между этими городами нет, только с пересадкой
        $result = $this->guard(fn() => $this->client->transfers->search(new TransferSearch(
            origin: '5a13bdc3340c745ca1e8aa54',      // Новый Уренгой
            destination: '5a13baab340c745ca1e7f31c', // Абакан
            date: $this->date('+21 days'),
        )));

        self::assertNotSame([], $result->routes);

        $route = $result->cheapest();

        self::assertNotNull($route);
        self::assertGreaterThanOrEqual(1, $route->changes());
        self::assertGreaterThan(0, $route->minPrice);
        self::assertGreaterThan($route->trips()[0]->duration(), $route->duration());

        // Ожидание на пересадке считается по времени соседних рейсов
        self::assertCount($route->changes(), $route->waits());
        self::assertGreaterThan(0, $route->waitTotal());

        $trip = $route->trips()[0];

        self::assertNotNull($trip->number);
        self::assertSame('Train', $trip->transportType);
        self::assertNotNull($trip->train()?->number);
    }

    #[Test]
    public function findsRoutesFromStationSuggests(): void
    {
        $stations = $this->guard(fn() => $this->client->stations->suggest('Новый Уренгой'));
        $target = $this->guard(fn() => $this->client->stations->suggest('Абакан'));

        // Подсказка отдаёт и город, и его вокзалы, у станции есть ссылка на город
        self::assertSame($stations[0]->nodeId, $stations[0]->cityId, 'Первым идёт узел города');

        $result = $this->guard(fn() => $this->client->transfers->search(
            TransferSearch::forStations($stations[0], $target[0], $this->date('+21 days')),
        ));

        self::assertNotSame([], $result->routes);
    }

    #[Test]
    public function returnsSiteConfig(): void
    {
        $config = $this->guard(fn() => $this->client->references->appConfig());

        // Библиотека берет подсказки станций по этому же адресу
        self::assertSame('/isdk/suggests', $config['stations_search_url'] ?? null);
    }
}
