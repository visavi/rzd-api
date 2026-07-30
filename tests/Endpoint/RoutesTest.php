<?php

declare(strict_types=1);

namespace Rzd\Tests\Endpoint;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Rzd\Exception\MalformedResponseException;
use Rzd\Request\RouteSearch;
use Rzd\Request\TrainSearch;
use Rzd\Tests\Concerns\AssertsExceptions;
use Rzd\Tests\TestCase;

final class RoutesTest extends TestCase
{
    use AssertsExceptions;

    private function search(): RouteSearch
    {
        return new RouteSearch(
            trainNumber: '197Щ',
            origin: '2000005',
            destination: '2064150',
            departure: new DateTimeImmutable('2026-08-05 00:55'),
            originName: 'Москва Павелецкая',
            destinationName: 'Адлер',
        );
    }

    #[Test]
    public function returnsRouteWithStops(): void
    {
        $route = $this->clientWith('train-route')->routes->forTrain($this->search());

        self::assertSame('ОСНОВНОЙ МАРШРУТ', $route->name);
        self::assertSame('197Щ', $route->trainNumber);
        self::assertSame('МОСКВА ПАВ', $route->originName);
        self::assertSame('АДЛЕР', $route->destinationName);
        self::assertCount(5, $route);
    }

    #[Test]
    public function parsesRouteStop(): void
    {
        $route = $this->clientWith('train-route')->routes->forTrain($this->search());

        $first = $route->stops[0];

        self::assertSame('Москва Павелецкая (Павелецкий вокзал)', $first->stationName);
        self::assertSame('2000005', $first->stationCode);
        self::assertSame('2026-08-05 00:55', $first->departure?->format('Y-m-d H:i'));

        // У начальной станции прибытия нет
        self::assertNull($first->arrival);

        $second = $route->stops[1];

        self::assertSame('2026-08-05 06:15', $second->arrival?->format('Y-m-d H:i'));
        self::assertSame('2026-08-05 06:22', $second->departure?->format('Y-m-d H:i'));
    }

    #[Test]
    public function iteratesAsStopList(): void
    {
        $route = $this->clientWith('train-route')->routes->forTrain($this->search());

        $names = [];

        foreach ($route as $stop) {
            $names[] = $stop->stationName;
        }

        self::assertCount(5, $names);
        self::assertSame('Павелец-Тульский', $names[1]);
    }

    #[Test]
    public function returnsAllRouteVariants(): void
    {
        $routes = $this->clientWith('train-route')->routes->all($this->search());

        self::assertCount(1, $routes);
    }

    #[Test]
    public function reportsMissingRoute(): void
    {
        $this->assertThrows(
            MalformedResponseException::class,
            'Сайт не вернул ни одного маршрута поезда',
            fn() => $this->client(['{"Routes":[]}'])->routes->forTrain($this->search()),
        );
    }

    #[Test]
    public function sendsRouteParameters(): void
    {
        $this->clientWith('train-route')->routes->forTrain($this->search());

        $query = $this->query();

        self::assertSame('/apib2b/p/Railway/V1/Search/TrainRoute', $this->request()->getUri()->getPath());
        self::assertSame('197Щ', $query['TrainNumber']);
        self::assertSame('2000005', $query['Origin']);
        self::assertSame('Адлер', $query['DestinationName']);
        self::assertSame('2026-08-05T00:55:00', $query['DepartureDate']);
        self::assertSame('true', $query['GetNewRoute']);
        self::assertSame('B2B_RZD', $query['serviceProvider']);
    }

    #[Test]
    public function omitsEmptyStationNames(): void
    {
        $this->clientWith('train-route')->routes->forTrain(new RouteSearch(
            trainNumber: '197Щ',
            origin: '2000005',
            destination: '2064150',
            departure: new DateTimeImmutable('2026-08-05 00:55'),
        ));

        self::assertArrayNotHasKey('OriginName', $this->query());
        self::assertArrayNotHasKey('DestinationName', $this->query());
    }

    #[Test]
    public function buildsRouteRequestFromFoundTrain(): void
    {
        $client = $this->clientWith('train-pricing', 'train-route');

        $train = $client->trains->search(
            new TrainSearch('2000000', '2060500', new DateTimeImmutable('2026-08-01')),
        )->trains[0];

        $client->routes->forTrain(RouteSearch::forTrain($train));

        $query = $this->query(1);

        self::assertSame('130Х', $query['TrainNumber']);
        self::assertSame('2000003', $query['Origin']);
        self::assertSame('Москва Казанская', $query['OriginName']);
        self::assertSame('2026-08-01T00:20:00', $query['DepartureDate']);
    }
}
