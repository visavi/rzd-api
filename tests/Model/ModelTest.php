<?php

declare(strict_types=1);

namespace Rzd\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rzd\Model\Car;
use Rzd\Model\CarList;
use Rzd\Model\PriceDay;
use Rzd\Model\SearchResult;
use Rzd\Model\Train;

/**
 * Разбор ответов сайта в моделях, включая неполные и битые данные
 */
final class ModelTest extends TestCase
{
    #[Test]
    public function exposesFieldsOutsideModel(): void
    {
        $train = Train::fromArray([
            'TrainNumber'  => '130Х',
            'TrainBrandCode' => '3033',
            'PlacesStorageType' => 'Russia',
        ]);

        // Полей у поезда больше семидесяти, в модели описаны нужные
        self::assertSame('3033', $train->get('TrainBrandCode'));
        self::assertSame('Russia', $train->get('PlacesStorageType'));
        self::assertNull($train->get('НетТакогоПоля'));
        self::assertSame('по умолчанию', $train->get('НетТакогоПоля', 'по умолчанию'));
        self::assertSame('130Х', $train->raw['TrainNumber']);
    }

    #[Test]
    public function parsesEmptyResponseWithoutErrors(): void
    {
        $train = Train::fromArray([]);

        self::assertNull($train->number);
        self::assertNull($train->departure);
        self::assertNull($train->duration);
        self::assertSame([], $train->carGroups);
        self::assertSame([], $train->carriers);
        self::assertFalse($train->branded);
        self::assertSame(0, $train->freeSeats());
        self::assertNull($train->minPrice());
    }

    #[Test]
    #[DataProvider('malformedDates')]
    public function skipsUnparsableDate(mixed $value): void
    {
        $train = Train::fromArray(['LocalDepartureDateTime' => $value]);

        self::assertNull($train->departure);
    }

    public static function malformedDates(): array
    {
        return [
            'чепуха'      => ['совсем не дата'],
            'пустая строка' => [''],
            'число'       => [12345],
            'null'        => [null],
            'массив'      => [['2026-08-01']],
        ];
    }

    #[Test]
    public function skipsNestedItemsThatAreNotArrays(): void
    {
        $train = Train::fromArray(['CarGroups' => ['строка', 42, ['CarType' => 'Luxury']]]);

        self::assertCount(1, $train->carGroups);
        self::assertSame('Luxury', $train->carGroups[0]->type);
    }

    #[Test]
    public function ignoresNestedFieldThatIsNotArray(): void
    {
        $train = Train::fromArray(['CarGroups' => 'вовсе не список']);

        self::assertSame([], $train->carGroups);
    }

    #[Test]
    public function dropsNonStringListItems(): void
    {
        $train = Train::fromArray(['Carriers' => ['ФПК', 42, null, 'ДОСС']]);

        self::assertSame(['ФПК', 'ДОСС'], $train->carriers);
    }

    #[Test]
    public function castsNumbersFromStrings(): void
    {
        $train = Train::fromArray(['TripDuration' => '787', 'TripDistance' => '793']);

        self::assertSame(787, $train->duration);
        self::assertSame(793, $train->distance);
    }

    #[Test]
    public function doesNotCastNonNumericValues(): void
    {
        $train = Train::fromArray(['TripDuration' => 'много']);

        self::assertNull($train->duration);
    }

    #[Test]
    public function carListIsIterableAndCountable(): void
    {
        $list = CarList::fromArray([
            'Cars' => [['CarNumber' => '07'], ['CarNumber' => '08']],
        ]);

        $numbers = [];

        foreach ($list as $car) {
            $numbers[] = $car->number;
        }

        self::assertSame(['07', '08'], $numbers);
        self::assertCount(2, $list);
    }

    #[Test]
    public function carListWithoutTrainData(): void
    {
        $list = CarList::fromArray(['Cars' => [], 'TrainInfo' => 'не объект']);

        self::assertNull($list->train);
        self::assertCount(0, $list);
        self::assertSame([], $list->withSeats());
    }

    #[Test]
    public function searchResultWithoutTrains(): void
    {
        $result = SearchResult::fromArray([]);

        self::assertCount(0, $result);
        self::assertSame([], $result->withSeats());
        self::assertNull($result->originName);
    }

    #[Test]
    #[DataProvider('malformedPrices')]
    public function calendarSkipsUnusablePrices(array $carriers): void
    {
        $day = PriceDay::fromArray(['Carriers' => $carriers]);

        self::assertSame([], $day->byCarType());
    }

    public static function malformedPrices(): array
    {
        return [
            'нулевая цена'    => [[['Trains' => [['CarTypes' => [['CarTypeName' => 'Compartment', 'MinPrice' => 0]]]]]]],
            'цена не число'   => [[['Trains' => [['CarTypes' => [['CarTypeName' => 'Compartment', 'MinPrice' => 'дорого']]]]]]],
            'нет названия'    => [[['Trains' => [['CarTypes' => [['MinPrice' => 100]]]]]]],
            'название не строка' => [[['Trains' => [['CarTypes' => [['CarTypeName' => 42, 'MinPrice' => 100]]]]]]],
            'нет вагонов'     => [[['Trains' => [[]]]]],
            'нет поездов'     => [[[]]],
        ];
    }

    #[Test]
    public function calendarSkipsCarriersWithoutName(): void
    {
        $day = PriceDay::fromArray(['Carriers' => [
            ['CarrierName' => 'ФПК'],
            ['CarrierName' => 42],
            [],
            'не массив',
        ]]);

        self::assertSame(['ФПК'], $day->carriers());
    }

    #[Test]
    public function readsCalendarDateFromCorrectedField(): void
    {
        // Сайт пишет DepatureDate с опечаткой, но может её исправить
        $day = PriceDay::fromArray(['DepartureDate' => '2026-08-03T00:00:00']);

        self::assertSame('2026-08-03', $day->date?->format('Y-m-d'));
    }

    #[Test]
    #[DataProvider('placesStrings')]
    public function parsesPlacesString(?string $places, array $expected): void
    {
        self::assertSame($expected, Car::parsePlaces($places));
    }

    public static function placesStrings(): array
    {
        return [
            'обычные'        => ['2, 4', [2, 4]],
            'с пометкой пола' => ['4М', [4]],
            'смешанные'      => ['1, 12Ж, 25М', [1, 12, 25]],
            'без пробелов'   => ['2,4,6', [2, 4, 6]],
            'одно место'     => ['7', [7]],
            'пустая строка'  => ['', []],
            'пробелы'        => ['   ', []],
            'null'           => [null, []],
            'без цифр'       => ['нет мест', []],
        ];
    }

    #[Test]
    public function serviceClassFallsBackToAlternateField(): void
    {
        // У части вагонов сайт заполняет ServiceClassName вместо ServiceClassNameRu
        $car = Car::fromArray(['ServiceClassName' => 'Купе']);

        self::assertSame('Купе', $car->serviceClassName);
    }
}
