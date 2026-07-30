<?php

declare(strict_types=1);

namespace Rzd\Tests\Endpoint;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Rzd\Model\Train;
use Rzd\Request\TrainSearch;
use Rzd\Tests\TestCase;

final class TrainsTest extends TestCase
{
    private function search(): TrainSearch
    {
        return new TrainSearch('2000000', '2060500', new DateTimeImmutable('2026-08-01'));
    }

    #[Test]
    public function returnsTrainsWithDirectionData(): void
    {
        $result = $this->clientWith('train-pricing')->trains->search($this->search());

        self::assertCount(2, $result);
        self::assertSame('Москва', $result->originName);
        self::assertSame('Казань', $result->destinationName);
        self::assertSame('2000000', $result->originCode);
        self::assertFalse($result->partial);
        self::assertContainsOnlyInstancesOf(Train::class, $result->trains);
    }

    #[Test]
    public function iteratesAsTrainList(): void
    {
        $result = $this->clientWith('train-pricing')->trains->search($this->search());

        $numbers = [];

        foreach ($result as $train) {
            $numbers[] = $train->number;
        }

        self::assertSame(['130Х', '056М'], $numbers);
    }

    #[Test]
    public function parsesTrainFields(): void
    {
        $train = $this->clientWith('train-pricing')->trains->search($this->search())->trains[0];

        self::assertSame('130Х', $train->number);
        self::assertSame('СК', $train->description);
        self::assertSame('P1', $train->provider);
        self::assertSame('2000003', $train->originStationCode);
        self::assertSame('2060500', $train->destinationStationCode);
        self::assertSame(787, $train->duration);
        self::assertSame(793, $train->distance);
        self::assertSame('2026-08-01 00:20', $train->departure?->format('Y-m-d H:i'));
        self::assertSame('2026-08-01 13:27', $train->arrival?->format('Y-m-d H:i'));
        self::assertSame(['ФПК'], $train->carriers);
        self::assertSame(['Bedclothes'], $train->services);
        self::assertTrue($train->hasElectronicRegistration);
        self::assertFalse($train->branded);
    }

    #[Test]
    public function nameIsNullForRegularTrain(): void
    {
        $train = $this->clientWith('train-pricing')->trains->search($this->search())->trains[0];

        // Сайт присылает пустую строку, модель приводит её к null
        self::assertNull($train->name);
    }

    #[Test]
    public function countsSeatsAcrossCarGroups(): void
    {
        $train = $this->clientWith('train-pricing')->trains->search($this->search())->trains[0];

        self::assertCount(2, $train->carGroups);
        self::assertSame(3, $train->freeSeats());
        self::assertSame(16224.3, $train->minPrice());

        $group = $train->carGroups[0];

        self::assertSame('Luxury', $group->type);
        self::assertSame('СВ', $group->typeName);
        self::assertSame(['1Ф'], $group->serviceClasses);
        self::assertSame(1, $group->places);
        self::assertSame(1, $group->lowerPlaces);
        self::assertSame(0, $group->upperPlaces);
        self::assertSame('Available', $group->availability);
    }

    #[Test]
    public function filtersTrainsWithSeats(): void
    {
        $result = $this->clientWith('train-pricing')->trains->search($this->search());

        self::assertCount(2, $result->withSeats());
    }

    #[Test]
    public function sendsSearchParameters(): void
    {
        $this->clientWith('train-pricing')->trains->search(new TrainSearch(
            origin: '2000000',
            destination: '2060500',
            date: new DateTimeImmutable('2026-08-01 15:30'),
            adults: 2,
            children: 1,
        ));

        $query = $this->query();

        self::assertSame('GET', $this->request()->getMethod());
        self::assertSame('/api/v1/railway-service/prices/train-pricing', $this->request()->getUri()->getPath());
        self::assertSame('2000000', $query['origin']);
        self::assertSame('2060500', $query['destination']);
        self::assertSame('2', $query['adultPassengersQuantity']);
        self::assertSame('1', $query['childrenPassengersQuantity']);
        self::assertSame('B2B_RZD', $query['service_provider']);
        self::assertSame('DontGroup', $query['carGrouping']);

        // Время в дате поиска не учитывается, сайт ждёт начало суток
        self::assertSame('2026-08-01T00:00:00', $query['departureDate']);
    }

    #[Test]
    public function searchesRoundTripWithTwoRequests(): void
    {
        $trip = $this->clientWith('train-pricing', 'train-pricing')->trains->searchReturn(
            new TrainSearch('2000000', '2060500', new DateTimeImmutable('2026-08-01'), adults: 2),
            new DateTimeImmutable('2026-08-05'),
        );

        // Сайт не умеет искать пару маршрутов одним запросом
        self::assertCount(2, $this->http->getRequests());
        self::assertCount(2, $trip->forward);
        self::assertCount(2, $trip->back);
    }

    #[Test]
    public function swapsStationsAndKeepsParametersOnReturnLeg(): void
    {
        $this->clientWith('train-pricing', 'train-pricing')->trains->searchReturn(
            new TrainSearch(
                origin: '2000000',
                destination: '2060500',
                date: new DateTimeImmutable('2026-08-01'),
                adults: 2,
                children: 1,
                groupCars: true,
            ),
            new DateTimeImmutable('2026-08-05'),
        );

        $forward = $this->query(0);
        $back = $this->query(1);

        self::assertSame('2000000', $forward['origin']);
        self::assertSame('2026-08-01T00:00:00', $forward['departureDate']);

        self::assertSame('2060500', $back['origin']);
        self::assertSame('2000000', $back['destination']);
        self::assertSame('2026-08-05T00:00:00', $back['departureDate']);

        // Остальные параметры повторяют первый запрос
        self::assertSame('2', $back['adultPassengersQuantity']);
        self::assertSame('1', $back['childrenPassengersQuantity']);
        self::assertSame('Group', $back['carGrouping']);
    }

    #[Test]
    public function roundTripSumsCheapestPrices(): void
    {
        $trip = $this->clientWith('train-pricing', 'train-pricing')->trains->searchReturn(
            new TrainSearch('2000000', '2060500', new DateTimeImmutable('2026-08-01')),
            new DateTimeImmutable('2026-08-05'),
        );

        self::assertTrue($trip->hasSeats());

        // Самый дешёвый поезд фикстуры стоит 16224.3 в каждую сторону
        self::assertSame(16224.3 * 2, $trip->minPrice());
    }

    #[Test]
    public function roundTripWithoutSeatsHasNoPrice(): void
    {
        $empty = '{"Trains":[]}';

        $trip = $this->client([$this->fixture('train-pricing'), $empty])->trains->searchReturn(
            new TrainSearch('2000000', '2060500', new DateTimeImmutable('2026-08-01')),
            new DateTimeImmutable('2026-08-05'),
        );

        self::assertFalse($trip->hasSeats());
        self::assertNull($trip->minPrice());
    }

    #[Test]
    public function groupsCarsOnDemand(): void
    {
        $this->clientWith('train-pricing')->trains->search(new TrainSearch(
            origin: '2000000',
            destination: '2060500',
            date: new DateTimeImmutable('2026-08-01'),
            groupCars: true,
        ));

        self::assertSame('Group', $this->query()['carGrouping']);
    }
}
