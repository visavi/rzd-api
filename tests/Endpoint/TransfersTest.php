<?php

declare(strict_types=1);

namespace Rzd\Tests\Endpoint;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Rzd\Enum\TransportProvider;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\TransferResult;
use Rzd\Request\TransferSearch;
use Rzd\Tests\Concerns\AssertsExceptions;
use Rzd\Tests\TestCase;

final class TransfersTest extends TestCase
{
    use AssertsExceptions;

    #[Test]
    public function returnsRoutes(): void
    {
        $result = $this->search();

        self::assertCount(2, $result);
        self::assertSame('prod:6d:20260730173315:3423006', $result->requestId);

        $route = $result->routes[0];

        self::assertSame(1, $route->changes());
        self::assertCount(2, $route->legs);
        self::assertSame(5534.1, $route->minPrice);
        self::assertSame(18562.3, $route->maxPrice);
    }

    #[Test]
    public function walksResultAsListOfRoutes(): void
    {
        $routes = iterator_to_array($this->search());

        self::assertCount(2, $routes);
        self::assertSame(1, $routes[0]->changes());
    }

    #[Test]
    public function walksRouteAsListOfLegs(): void
    {
        $legs = iterator_to_array($this->search()->routes[0]);

        self::assertCount(2, $legs);
        self::assertSame(TransportProvider::Rails, $legs[0]->provider);
        self::assertSame('Express3', $legs[0]->bookingSystem);
        self::assertSame(['Train'], $legs[0]->transportTypes);
    }

    #[Test]
    public function describesEndsOfRoute(): void
    {
        $route = $this->search()->routes[0];

        $origin = $route->origin();

        self::assertNotNull($origin);
        self::assertSame('Москва Ярославская (Ярославский вокзал)', $origin->name);
        self::assertSame('Москва', $origin->cityName);
        self::assertSame('Moskva Yaroslavskaya (Yaroslavskiy vokzal)', $origin->nameEn);
        self::assertSame('Исакогорка', $route->destination()?->name);
    }

    #[Test]
    public function countsWholeTripDuration(): void
    {
        $route = $this->search()->routes[0];

        self::assertSame('2026-09-11T01:00:00+03:00', $route->departure()?->format('c'));
        self::assertSame('2026-09-11T18:58:00+03:00', $route->arrival()?->format('c'));
        // Время в пути считается вместе с ожиданием на пересадке
        self::assertSame(1078, $route->duration());
    }

    #[Test]
    public function countsWaitsBetweenTrips(): void
    {
        $route = $this->search()->routes[0];

        // Одна пересадка: прибытие в 04:36, отправление в 05:29
        self::assertSame([53], $route->waits());
        self::assertSame(53, $route->waitTotal());
    }

    #[Test]
    public function describesTrips(): void
    {
        $trips = $this->search()->routes[0]->trips();

        self::assertCount(2, $trips);

        $trip = $trips[0];

        self::assertSame('002Э', $trip->number);
        self::assertSame('Train', $trip->transportType);
        self::assertSame(280, $trip->distance);
        self::assertSame(216, $trip->duration());
        self::assertSame(1976.4, $trip->minPrice);
        self::assertSame(353, $trip->freePlaces);
        self::assertSame('Ярославль', $trip->destination?->cityName);
    }

    #[Test]
    public function describesProducts(): void
    {
        $product = $this->search()->routes[0]->trips()[0]->products[0];

        self::assertSame(2682.9, $product->price);
        self::assertSame(77, $product->freePlaces);
        self::assertSame('Compartment', $product->type);
        self::assertSame(['2Э:ФПК', '2Э:ФПК', '2Ф:ФПК'], $product->serviceClasses);
        self::assertSame(['ФПК'], $product->carriers);
    }

    #[Test]
    public function readsTrainOfTrip(): void
    {
        $train = $this->search()->routes[0]->trips()[0]->train();

        self::assertNotNull($train);
        self::assertSame('002Э', $train->number);
        self::assertSame('Россия', $train->name);
        self::assertSame('2000002', $train->originStationCode);
        self::assertNotSame([], $train->carGroups);
    }

    #[Test]
    public function describesTransferBetweenStations(): void
    {
        $transfers = $this->search()->routes[0]->transfers;

        self::assertCount(1, $transfers);

        $transfer = $transfers[0];

        self::assertSame('Ярославль (Московский вокзал)', $transfer->origin?->name);
        self::assertSame('Ярославль-Главный', $transfer->destination?->name);
        self::assertSame(500.0, $transfer->price);
        self::assertSame(13, $transfer->duration);
        // Сайт считает переезд посекундно, точность сохранена рядом
        self::assertSame(807, $transfer->seconds);
    }

    #[Test]
    public function leavesTransfersEmptyWhenChangeIsAtSameStation(): void
    {
        $route = $this->search()->routes[1];

        // Пересадка есть, а переезжать между вокзалами не нужно
        self::assertSame(1, $route->changes());
        self::assertSame([], $route->transfers);
    }

    #[Test]
    public function picksCheapestAndFastest(): void
    {
        $result = $this->search();

        // Первый вариант и дешевле, и быстрее: 5534 против 5671 рубля,
        // 17:58 против 25:12 в пути
        self::assertSame($result->routes[0], $result->cheapest());
        self::assertSame($result->routes[0], $result->fastest());
        self::assertSame(1512, $result->routes[1]->duration());
    }

    #[Test]
    public function filtersRoutesWithSeats(): void
    {
        $result = $this->search();

        self::assertCount(2, $result->withSeats());
        self::assertTrue($result->routes[0]->hasSeats());
    }

    #[Test]
    public function sendsSearchBody(): void
    {
        $this->search();

        $request = $this->request();

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/apib2b/mmp/onewayRoutesStream/v2', $request->getUri()->getPath());
        // Без куки языка поиск отвечает 500
        self::assertSame('LANG_SITE=ru', $request->getHeaderLine('Cookie'));

        $body = $this->body();

        self::assertSame('5a323c29340c7441a0a556bb', $body['start_location']['city']['key']);
        self::assertSame('5a13baf9340c745ca1e80436', $body['finish_location']['city']['key']);
        self::assertSame('2026-09-11T00:00:00', $body['start_datetime_range']['from']);
        self::assertSame('2026-09-11T23:59:59', $body['start_datetime_range']['to']);
        self::assertSame(2, $body['min_trips_in_leg']);
        self::assertSame(4, $body['max_trips_in_leg']);
        self::assertSame(200, $body['max_results']);
        self::assertSame(['b2brails'], $body['filters'][0]['exact_filter']['param_values']);
    }

    #[Test]
    public function sendsChosenProviders(): void
    {
        $this->clientWith('transfer-search')->transfers->search(new TransferSearch(
            origin: '5a323c29340c7441a0a556bb',
            destination: '5a13baf9340c745ca1e80436',
            date: new DateTimeImmutable('2026-09-11'),
            minTrips: 1,
            providers: [TransportProvider::Rails, TransportProvider::Bus],
        ));

        $body = $this->body();

        self::assertSame(['b2brails', 'b2bbus'], $body['filters'][0]['exact_filter']['param_values']);
        self::assertSame(1, $body['min_trips_in_leg']);
    }

    #[Test]
    public function returnsEmptyResultWhenNothingFound(): void
    {
        $result = $this->client(['{}'])->transfers->search($this->searchRequest());

        self::assertCount(0, $result);
        self::assertNull($result->cheapest());
        self::assertNull($result->fastest());
        self::assertSame([], $result->withSeats());
    }

    #[Test]
    public function rejectsEmptyCity(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Идентификаторы городов отправления и прибытия обязательны',
            fn() => new TransferSearch('', '5a13baf9340c745ca1e80436', new DateTimeImmutable('2026-09-11')),
        );
    }

    #[Test]
    public function rejectsImpossibleTripCounts(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Рейсов в цепочке должно быть не меньше одного',
            fn() => new TransferSearch('a', 'b', new DateTimeImmutable('2026-09-11'), minTrips: 0),
        );

        $this->assertThrows(
            InvalidArgumentException::class,
            'Наибольшее число рейсов меньше наименьшего',
            fn() => new TransferSearch('a', 'b', new DateTimeImmutable('2026-09-11'), minTrips: 3, maxTrips: 2),
        );
    }

    #[Test]
    public function rejectsEmptyProvidersAndResults(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Нужен хотя бы один вид транспорта',
            fn() => new TransferSearch('a', 'b', new DateTimeImmutable('2026-09-11'), providers: []),
        );

        $this->assertThrows(
            InvalidArgumentException::class,
            'Число вариантов должно быть положительным',
            fn() => new TransferSearch('a', 'b', new DateTimeImmutable('2026-09-11'), maxResults: 0),
        );
    }

    private function search(): TransferResult
    {
        return $this->clientWith('transfer-search')->transfers->search($this->searchRequest());
    }

    private function searchRequest(): TransferSearch
    {
        return new TransferSearch(
            origin: '5a323c29340c7441a0a556bb',
            destination: '5a13baf9340c745ca1e80436',
            date: new DateTimeImmutable('2026-09-11'),
        );
    }
}
