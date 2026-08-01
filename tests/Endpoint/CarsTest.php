<?php

declare(strict_types=1);

namespace Rzd\Tests\Endpoint;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Rzd\Enum\SchemeView;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Request\CarSchemeSearch;
use Rzd\Request\CarSearch;
use Rzd\Request\TrainSearch;
use Rzd\Tests\Concerns\AssertsExceptions;
use Rzd\Tests\TestCase;

final class CarsTest extends TestCase
{
    use AssertsExceptions;

    private function search(): CarSearch
    {
        return new CarSearch(
            origin: '2000003',
            destination: '2060500',
            trainNumber: '130Х',
            departure: new DateTimeImmutable('2026-08-01 00:20'),
        );
    }

    private function schemeSearch(): CarSchemeSearch
    {
        return new CarSchemeSearch(
            carrier: 'ФПК',
            carSubType: '64К',
            carNumber: '07',
            trainNumber: '060М',
            departure: new DateTimeImmutable('2026-08-01 16:38'),
            serviceClass: '2Э',
        );
    }

    #[Test]
    public function picksCheapestCar(): void
    {
        $cars = $this->clientWith('car-pricing')->cars->search($this->search());

        $cheapest = $cars->cheapest();

        self::assertNotNull($cheapest);
        self::assertContains($cheapest, $cars->withSeats());

        foreach ($cars->withSeats() as $car) {
            self::assertGreaterThanOrEqual($cheapest->minPrice, $car->minPrice);
        }
    }

    #[Test]
    public function returnsCarsWithPlaceNumbers(): void
    {
        $cars = $this->clientWith('car-pricing')->cars->search($this->search());

        self::assertCount(3, $cars);

        $car = $cars->cars[1];

        self::assertSame('09', $car->number);
        self::assertSame('СВ', $car->typeName);
        self::assertSame('Luxury', $car->type);
        self::assertSame('1Х', $car->serviceClass);
        self::assertSame('2, 4', $car->freePlaces);
        self::assertSame([2, 4], $car->placeNumbers());
        self::assertSame(2, $car->places);
        self::assertSame('ФПК', $car->carrier);
        self::assertSame(307, $car->schemeId);
        self::assertSame('FromHead', $car->numeration);
    }

    #[Test]
    public function stripsGenderMarkFromPlaceNumber(): void
    {
        $cars = $this->clientWith('car-pricing')->cars->search($this->search());

        // Место 4М означает мужское купе, к номеру места буква не относится
        self::assertSame('4М', $cars->cars[0]->freePlaces);
        self::assertSame([4], $cars->cars[0]->placeNumbers());
    }

    #[Test]
    public function splitsPlacesByCompartment(): void
    {
        $car = $this->clientWith('car-pricing')->cars->search($this->search())->cars[1];

        self::assertCount(2, $car->compartments);
        self::assertSame('1', $car->compartments[0]->number);
        self::assertSame('2', $car->compartments[0]->places);
        self::assertSame([2], $car->compartments[0]->placeNumbers());
        self::assertSame([4], $car->compartments[1]->placeNumbers());
    }

    #[Test]
    public function filtersCarsWithSeats(): void
    {
        $cars = $this->clientWith('car-pricing')->cars->search($this->search());

        // Багажный вагон приходит тем же списком, мест к продаже в нём нет
        self::assertCount(2, $cars->withSeats());
        self::assertSame('БАГАЖ', $cars->cars[2]->typeName);
        self::assertSame([], $cars->cars[2]->placeNumbers());
    }

    #[Test]
    public function returnsTrainDataInSameResponse(): void
    {
        $cars = $this->clientWith('car-pricing')->cars->search($this->search());

        self::assertNotNull($cars->train);
        self::assertSame('130Х', $cars->train->number);
        self::assertSame(787, $cars->train->duration);
        self::assertSame('2000003', $cars->originCode);
        self::assertNotSame([], $cars->allowedDocumentTypes);
    }

    #[Test]
    public function sendsCarRequestBody(): void
    {
        $this->clientWith('car-pricing')->cars->search($this->search());

        $request = $this->request();
        $body = $this->body();

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/apib2b/p/Railway/V1/Search/CarPricing', $request->getUri()->getPath());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('B2B_RZD', $this->query()['service_provider']);
        self::assertSame('2000003', $body['OriginCode']);
        self::assertSame('130Х', $body['TrainNumber']);
        self::assertSame('2026-08-01T00:20:00', $body['DepartureDate']);
        self::assertSame('P1', $body['Provider']);
        self::assertFalse($body['OnlyFpkBranded']);
    }

    #[Test]
    public function buildsCarRequestFromFoundTrain(): void
    {
        $client = $this->clientWith('train-pricing', 'car-pricing');

        $train = $client->trains->search(
            new TrainSearch('2000000', '2060500', new DateTimeImmutable('2026-08-01')),
        )->trains[0];

        $client->cars->search(CarSearch::forTrain($train));

        $body = $this->body(1);

        // Код станции поезда, а не города, который задавался в поиске
        self::assertSame('2000003', $body['OriginCode']);
        self::assertSame('2060500', $body['DestinationCode']);
        self::assertSame('130Х', $body['TrainNumber']);
        self::assertSame('2026-08-01T00:20:00', $body['DepartureDate']);
        self::assertSame('P1', $body['Provider']);
    }

    #[Test]
    public function returnsCarScheme(): void
    {
        $scheme = $this->clientWith('car-scheme')->cars->scheme($this->schemeSearch());

        self::assertSame(567, $scheme->schemeId);
        self::assertSame('64К', $scheme->subType);
        self::assertSame('ФПК', $scheme->carrier);
        self::assertTrue($scheme->has(SchemeView::DesktopFirstStorey));
        self::assertFalse($scheme->has(SchemeView::DesktopSecondStorey));
        self::assertFalse($scheme->isTwoStorey());
        self::assertSame('/567/PcFirstStorey', $scheme->views[SchemeView::DesktopFirstStorey->value]);
    }

    #[Test]
    public function sendsSchemeParameters(): void
    {
        $this->clientWith('car-scheme')->cars->scheme($this->schemeSearch());

        $query = $this->query();

        self::assertSame('/api/v1/railway-service/carscheme', $this->request()->getUri()->getPath());
        self::assertSame('ФПК', $query['Carrier']);
        self::assertSame('64К', $query['CarSubType']);
        self::assertSame('2Э', $query['ServiceClass']);
        self::assertSame('FromHead', $query['CarNumeration']);
        self::assertSame('2026-08-01T16:38:00', $query['DepartureDate']);
    }

    #[Test]
    public function returnsSchemeImage(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        $client = $this->client([$svg]);

        self::assertSame($svg, $client->cars->schemeImage(567));
        self::assertSame('/api/v1/carscheme/image/567/PcFirstStorey', $this->request()->getUri()->getPath());
    }

    #[Test]
    public function returnsSecondStoreyImage(): void
    {
        $client = $this->client(['<svg></svg>']);

        $client->cars->schemeImage(567, SchemeView::MobileSecondStorey);

        self::assertSame(
            '/api/v1/carscheme/image/567/MobileSecondStoreyVert',
            $this->request()->getUri()->getPath(),
        );
    }

    #[Test]
    public function rejectsInvalidSchemeId(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Идентификатор схемы должен быть положительным',
            fn() => $this->client()->cars->schemeImage(0),
        );
    }

    #[Test]
    public function returnsCarImages(): void
    {
        $images = $this->clientWith('car-images')->cars->images($this->schemeSearch());

        self::assertNotSame([], $images);
        self::assertSame(2079, $images[2]->id);
        self::assertSame('Интерьер купе', $images[2]->title);
        self::assertSame('/2079/Preview', $images[2]->preview);
        self::assertSame('/2079/Content', $images[2]->content);
        self::assertSame(1, $images[2]->position);
    }

    #[Test]
    public function sendsImageParametersInExpectedCase(): void
    {
        $this->clientWith('car-images')->cars->images($this->schemeSearch());

        $query = $this->query();

        self::assertSame('/api/v1/railway-service/carimage/list', $this->request()->getUri()->getPath());
        self::assertSame('ФПК', $query['carrier']);
        self::assertSame('060М', $query['trainNumber']);
        self::assertSame('060М', $query['hiddenTrainNumber']);
        self::assertSame('060М', $query['displayTrainNumber']);
    }

    #[Test]
    public function returnsEmptyListWhenNoImages(): void
    {
        self::assertSame([], $this->client(['{}'])->cars->images($this->schemeSearch()));
    }
}
