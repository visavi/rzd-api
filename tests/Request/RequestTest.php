<?php

declare(strict_types=1);

namespace Rzd\Tests\Request;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rzd\Enum\TransportProvider;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\Car;
use Rzd\Model\Station;
use Rzd\Model\Train;
use Rzd\Request\CarSchemeSearch;
use Rzd\Request\CarSearch;
use Rzd\Request\RouteSearch;
use Rzd\Request\TrainSearch;
use Rzd\Request\TransferSearch;
use Rzd\Tests\Concerns\AssertsExceptions;

final class RequestTest extends TestCase
{
    use AssertsExceptions;

    private function date(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-01 00:20');
    }

    /**
     * Поезд с полным набором полей для сборки запросов
     *
     * @param array<string, mixed> $override
     */
    private function train(array $override = []): Train
    {
        return Train::fromArray([
            'TrainNumber'             => '130Х',
            'Provider'                => 'P1',
            'OriginStationCode'       => '2000003',
            'DestinationStationCode'  => '2060500',
            'OriginName'              => 'Москва Казанская',
            'DestinationName'         => 'Казань Пасс',
            'LocalDepartureDateTime'  => '2026-08-01T00:20:00',
            ...$override,
        ]);
    }

    #[Test]
    public function trainSearchRejectsEmptyCodes(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Коды станций отправления и прибытия обязательны',
            fn() => new TrainSearch('', '2060500', $this->date()),
        );
    }

    #[Test]
    #[DataProvider('invalidPassengerCounts')]
    public function trainSearchValidatesPassengerCount(int $adults, int $children, string $message): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            $message,
            fn() => new TrainSearch('2000000', '2060500', $this->date(), $adults, $children),
        );
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function invalidPassengerCounts(): array
    {
        return [
            'нет взрослых'       => [0, 0, 'Пассажиров должно быть не меньше одного'],
            'взрослых меньше нуля' => [-1, 0, 'Пассажиров должно быть не меньше одного'],
            'детей меньше нуля'  => [1, -1, 'Число детей не может быть отрицательным'],
        ];
    }

    #[Test]
    public function carSearchIsBuiltFromTrain(): void
    {
        $request = CarSearch::forTrain($this->train());

        self::assertSame('2000003', $request->origin);
        self::assertSame('2060500', $request->destination);
        self::assertSame('130Х', $request->trainNumber);
        self::assertSame('P1', $request->provider);
        self::assertSame('2026-08-01 00:20', $request->departure->format('Y-m-d H:i'));
    }

    #[Test]
    public function carSearchFallsBackToDefaultProvider(): void
    {
        $request = CarSearch::forTrain($this->train(['Provider' => null]));

        self::assertSame('P1', $request->provider);
    }

    #[Test]
    public function carSearchRequiresTrainStationCodes(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'У поезда нет кодов станций',
            fn() => CarSearch::forTrain($this->train(['OriginStationCode' => null])),
        );
    }

    #[Test]
    public function carSearchRequiresNumberAndDeparture(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'У поезда нет номера или времени отправления',
            fn() => CarSearch::forTrain($this->train(['LocalDepartureDateTime' => null])),
        );
    }

    #[Test]
    public function carSearchRejectsEmptyTrainNumber(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Номер поезда обязателен',
            fn() => new CarSearch('2000003', '2060500', '', $this->date()),
        );
    }

    #[Test]
    public function carSearchRejectsEmptyCodes(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Коды станций отправления и прибытия обязательны',
            fn() => new CarSearch('2000003', '', '130Х', $this->date()),
        );
    }

    #[Test]
    public function routeSearchIsBuiltFromTrain(): void
    {
        $request = RouteSearch::forTrain($this->train());

        self::assertSame('130Х', $request->trainNumber);
        self::assertSame('Москва Казанская', $request->originName);
        self::assertSame('Казань Пасс', $request->destinationName);
    }

    #[Test]
    public function routeSearchRequiresStationCodes(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'У поезда нет кодов станций',
            fn() => RouteSearch::forTrain($this->train(['DestinationStationCode' => null])),
        );
    }

    #[Test]
    public function routeSearchRequiresNumberAndDeparture(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'У поезда нет номера или времени отправления',
            fn() => RouteSearch::forTrain($this->train(['TrainNumber' => null])),
        );
    }

    #[Test]
    public function routeSearchRejectsEmptyNumber(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Номер поезда обязателен',
            fn() => new RouteSearch('', '2000003', '2060500', $this->date()),
        );
    }

    #[Test]
    public function routeSearchRejectsEmptyCodes(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Коды станций отправления и прибытия обязательны',
            fn() => new RouteSearch('130Х', '', '2060500', $this->date()),
        );
    }

    #[Test]
    public function schemeSearchIsBuiltFromCarAndTrain(): void
    {
        $car = Car::fromArray([
            'CarNumber'     => '07',
            'CarSubType'    => '64К',
            'Carrier'       => 'ФПК',
            'ServiceClass'  => '2Э',
            'CarNumeration' => 'FromTail',
        ]);

        $request = CarSchemeSearch::forCar($car, $this->train());

        self::assertSame('ФПК', $request->carrier);
        self::assertSame('64К', $request->carSubType);
        self::assertSame('07', $request->carNumber);
        self::assertSame('2Э', $request->serviceClass);
        self::assertSame('FromTail', $request->numeration);
        self::assertSame('130Х', $request->trainNumber);
    }

    #[Test]
    public function schemeSearchFallsBackToDefaultNumeration(): void
    {
        $car = Car::fromArray(['CarNumber' => '07', 'CarSubType' => '64К', 'Carrier' => 'ФПК']);

        self::assertSame('FromHead', CarSchemeSearch::forCar($car, $this->train())->numeration);
    }

    #[Test]
    public function schemeSearchRequiresCarData(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'У вагона нет перевозчика, подтипа или номера',
            fn() => CarSchemeSearch::forCar(Car::fromArray(['CarNumber' => '07']), $this->train()),
        );
    }

    #[Test]
    public function schemeSearchRequiresTrainData(): void
    {
        $car = Car::fromArray(['CarNumber' => '07', 'CarSubType' => '64К', 'Carrier' => 'ФПК']);

        $this->assertThrows(
            InvalidArgumentException::class,
            'У поезда нет номера или времени отправления',
            fn() => CarSchemeSearch::forCar($car, $this->train(['LocalDepartureDateTime' => null])),
        );
    }

    #[Test]
    public function schemeSearchRejectsEmptyCarrier(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Перевозчик и подтип вагона обязательны',
            fn() => new CarSchemeSearch('', '64К', '07', '130Х', $this->date()),
        );
    }

    #[Test]
    public function schemeSearchRejectsEmptyCarNumber(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Номер вагона и номер поезда обязательны',
            fn() => new CarSchemeSearch('ФПК', '64К', '', '130Х', $this->date()),
        );
    }

    #[Test]
    public function trainSearchIsBuiltFromStations(): void
    {
        $request = TrainSearch::forStations(
            Station::fromArray(['Codes' => ['Railway' => '2000000']]),
            Station::fromArray(['Codes' => ['Railway' => '2004000']]),
            $this->date(),
            adults: 2,
            children: 1,
        );

        self::assertSame('2000000', $request->origin);
        self::assertSame('2004000', $request->destination);
        self::assertSame(2, $request->adults);
        self::assertSame(1, $request->children);
        self::assertSame('2026-08-01T00:00:00', $request->toQuery()['departureDate']);
    }

    #[Test]
    public function trainSearchRequiresStationCodes(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'У станции нет кода, поиск поездов невозможен',
            fn() => TrainSearch::forStations(
                Station::fromArray(['Name' => 'Москва']),
                Station::fromArray(['Codes' => ['Railway' => '2004000']]),
                $this->date(),
            ),
        );
    }

    #[Test]
    public function transferSearchIsBuiltFromStations(): void
    {
        $request = TransferSearch::forStations(
            Station::fromArray(['nodeId' => '5a323c29340c7441a0a556bb']),
            Station::fromArray(['nodeId' => '5a13baf9340c745ca1e80436']),
            $this->date(),
        );

        self::assertSame('5a323c29340c7441a0a556bb', $request->origin);
        self::assertSame('5a13baf9340c745ca1e80436', $request->destination);
        // Время отправления не учитывается, сайт ищет в пределах суток
        self::assertSame('2026-08-01T00:00:00', $request->toBody()['start_datetime_range']['from']);
    }

    #[Test]
    public function transferSearchRequiresNodeIds(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'У станции нет идентификатора узла',
            fn() => TransferSearch::forStations(
                Station::fromArray(['Name' => 'Москва']),
                Station::fromArray(['nodeId' => '5a13baf9340c745ca1e80436']),
                $this->date(),
            ),
        );
    }

    #[Test]
    public function transferSearchListsAllProviders(): void
    {
        $request = new TransferSearch('a', 'b', $this->date(), providers: TransportProvider::all());

        self::assertSame(
            ['b2brails', 'cbdpr', 'b2bbus', 'b2bavia', 'b2bboat', 'aeroexpress'],
            $request->toBody()['filters'][0]['exact_filter']['param_values'],
        );
    }
}
