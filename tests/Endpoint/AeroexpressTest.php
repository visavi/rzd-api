<?php

declare(strict_types=1);

namespace Rzd\Tests\Endpoint;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Tests\Concerns\AssertsExceptions;
use Rzd\Tests\TestCase;

final class AeroexpressTest extends TestCase
{
    use AssertsExceptions;

    #[Test]
    public function returnsTariffs(): void
    {
        $tariffs = $this->clientWith('aeroexpress-tariffs')
            ->aeroexpress
            ->tariffs(new DateTimeImmutable('2026-08-05'));

        self::assertCount(4, $tariffs);

        $tariff = $tariffs[0];

        self::assertSame('21', $tariff->id);
        self::assertSame('Стандарт 650', $tariff->name);
        self::assertSame('Standard', $tariff->type);
        self::assertSame(650.0, $tariff->price);
        self::assertSame(10, $tariff->maxTickets);
        self::assertFalse($tariff->guaranteedSeat);
        self::assertStringContainsString('Шереметьево', (string) $tariff->description);
        self::assertContains('RussianPassport', $tariff->documentTypes);
    }

    #[Test]
    public function sendsDateOnlyWhenStationsOmitted(): void
    {
        $this->clientWith('aeroexpress-tariffs')
            ->aeroexpress
            ->tariffs(new DateTimeImmutable('2026-08-05 18:30'));

        $request = $this->request();

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/apib2b/p/Aeroexpress/V1/Search/TariffPricing', $request->getUri()->getPath());

        $body = $this->body();

        // Время поездки тариф не учитывает, сайт ждёт начало суток
        self::assertSame('2026-08-05T00:00:00', $body['DepartureDate']);
        self::assertArrayNotHasKey('OriginCode', $body);
        self::assertArrayNotHasKey('DestinationCode', $body);
    }

    #[Test]
    public function sendsStationsWhenGiven(): void
    {
        $this->clientWith('aeroexpress-tariffs')
            ->aeroexpress
            ->tariffs(new DateTimeImmutable('2026-08-05'), '2000000', '2000006');

        $body = $this->body();

        self::assertSame('2000000', $body['OriginCode']);
        self::assertSame('2000006', $body['DestinationCode']);
    }

    #[Test]
    public function rejectsEmptyStationCode(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Коды станций должны быть либо заданы, либо опущены',
            fn() => $this->client()->aeroexpress->tariffs(new DateTimeImmutable('2026-08-05'), '', '2000006'),
        );
    }

    #[Test]
    public function returnsEmptyListWhenNoTariffs(): void
    {
        self::assertSame([], $this->client(['{}'])->aeroexpress->tariffs(new DateTimeImmutable('2026-08-05')));
    }
}
