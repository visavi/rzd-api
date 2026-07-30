<?php

declare(strict_types=1);

namespace Rzd\Tests\Endpoint;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Tests\Concerns\AssertsExceptions;
use Rzd\Tests\TestCase;

final class PricesTest extends TestCase
{
    use AssertsExceptions;

    #[Test]
    public function returnsDatesWithSeats(): void
    {
        $dates = $this->clientWith('train-availability')->prices->availability(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-10'),
        );

        self::assertCount(10, $dates);
        self::assertSame('2026-08-01', $dates[0]->format('Y-m-d'));
        self::assertSame('2026-08-10', $dates[9]->format('Y-m-d'));

        // Время в датах календаря не приходит, оно должно быть обнулено
        self::assertSame('00:00:00', $dates[0]->format('H:i:s'));
    }

    #[Test]
    public function sendsAvailabilityPeriod(): void
    {
        $this->clientWith('train-availability')->prices->availability(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-08-01 18:00'),
            new DateTimeImmutable('2026-08-10 09:00'),
        );

        $query = $this->query();

        self::assertSame('/api/v1/railway-service/train-availability', $this->request()->getUri()->getPath());
        self::assertSame('2000000', $query['originStationCode']);
        self::assertSame('2004000', $query['destinationStationCode']);
        self::assertSame('2026-08-01', $query['from']);
        self::assertSame('2026-08-10', $query['to']);
    }

    #[Test]
    public function rejectsReversedPeriod(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Конец периода не может быть раньше начала',
            fn() => $this->client()->prices->availability( '2000000', '2004000', new DateTimeImmutable('2026-08-10'), new DateTimeImmutable('2026-08-01'), ),
        );
    }

    #[Test]
    public function rejectsEmptyStationCodes(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Коды станций отправления и прибытия обязательны',
            fn() => $this->client()->prices->availability( '', '2004000', new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-10'), ),
        );
    }

    #[Test]
    public function skipsMalformedDatesInResponse(): void
    {
        $response = '{"AvailabilityItems":[{"Date":"01-08-2026"},{"Date":"чепуха"},{},{"Date":null}]}';

        $dates = $this->client([$response])->prices->availability(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-10'),
        );

        self::assertCount(1, $dates);
    }

    #[Test]
    public function returnsSaleCalendar(): void
    {
        $months = $this->clientWith('sale-calendar')->prices->saleCalendar(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-07-30'),
        );

        self::assertCount(13, $months);

        $first = $months[0];

        self::assertSame(2026, $first->year);
        self::assertSame(7, $first->month);
        self::assertSame([30, 31], $first->saleDays);
        self::assertSame([30, 31], $first->availableDays);
        self::assertTrue($first->isOnSale(30));
        self::assertFalse($first->isOnSale(15));
    }

    #[Test]
    public function convertsSaleDaysToDates(): void
    {
        $month = $this->clientWith('sale-calendar')->prices->saleCalendar(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-07-30'),
        )[0];

        $dates = $month->dates();

        self::assertCount(2, $dates);
        self::assertSame('2026-07-30', $dates[0]->format('Y-m-d'));
        self::assertSame('00:00:00', $dates[0]->format('H:i:s'));
    }

    #[Test]
    public function saleCalendarWithoutYearHasNoDates(): void
    {
        self::assertSame([], \Rzd\Model\SaleMonth::fromArray(['saleDays' => [1, 2]])->dates());
    }

    #[Test]
    public function saleCalendarSkipsNonNumericDays(): void
    {
        $month = \Rzd\Model\SaleMonth::fromArray([
            'year'      => 2026,
            'month'     => 8,
            'saleDays'  => [1, 'чепуха', null, 3],
            'availableDays' => 'вовсе не список',
        ]);

        self::assertSame([1, 3], $month->saleDays);
        self::assertSame([], $month->availableDays);
    }

    #[Test]
    public function sendsSaleCalendarRequest(): void
    {
        $this->clientWith('sale-calendar')->prices->saleCalendar('2000000', '2004000');

        $request = $this->request();

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/apib2b/e/scheduleDirection', $request->getUri()->getPath());

        $body = $this->body();

        self::assertSame('2000000', $body['OriginCode']);
        self::assertSame('2004000', $body['DestinationCode']);

        // Без даты берётся начало текущих суток
        self::assertSame(date('Y-m-d') . 'T00:00:00', $body['DepartureDate']);
    }

    #[Test]
    public function rejectsEmptyCodesInSaleCalendar(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Коды станций отправления и прибытия обязательны',
            fn() => $this->client()->prices->saleCalendar('', '2004000'),
        );
    }

    #[Test]
    public function returnsPriceCalendar(): void
    {
        $days = $this->clientWith('train-minimal-pricing')->prices->calendar(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-08-01'),
        );

        self::assertCount(2, $days);

        $day = $days[0];

        self::assertSame('2026-08-03', $day->date?->format('Y-m-d'));
        self::assertSame(1197.9, $day->minPrice);
        self::assertSame(2362.4, $day->disabledPlaceMinPrice);
    }

    #[Test]
    public function findsMinimumByCarType(): void
    {
        $day = $this->clientWith('train-minimal-pricing')->prices->calendar(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-08-01'),
        )[0];

        $prices = $day->byCarType();

        self::assertArrayHasKey('Compartment', $prices);
        self::assertArrayHasKey('Luxury', $prices);
        self::assertSame(3397.4, $prices['Compartment']);
        self::assertSame(11355.9, $prices['Luxury']);
    }

    #[Test]
    public function returnsCarriersOfDay(): void
    {
        $day = $this->clientWith('train-minimal-pricing')->prices->calendar(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-08-01'),
        )[0];

        self::assertSame(['ФПК', 'ДОСС'], $day->carriers());
    }

    #[Test]
    public function sendsCalendarParameters(): void
    {
        $this->clientWith('train-minimal-pricing')->prices->calendar(
            '2000000',
            '2004000',
            new DateTimeImmutable('2026-08-01'),
        );

        $query = $this->query();

        self::assertSame('/api/v1/railway-service/train-minimal-pricing', $this->request()->getUri()->getPath());
        self::assertSame('2000000', $query['originCode']);
        self::assertSame('2004000', $query['destinationCode']);
        self::assertSame('2026-08-01', $query['dateFrom']);
    }

    #[Test]
    public function rejectsEmptyCodesInCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client()->prices->calendar('2000000', '', new DateTimeImmutable('2026-08-01'));
    }
}
