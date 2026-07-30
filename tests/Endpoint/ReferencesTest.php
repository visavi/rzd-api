<?php

declare(strict_types=1);

namespace Rzd\Tests\Endpoint;

use PHPUnit\Framework\Attributes\Test;
use Rzd\Tests\TestCase;

final class ReferencesTest extends TestCase
{
    #[Test]
    public function returnsTariffReference(): void
    {
        $tariffs = $this->clientWith('tariffs')->references->tariffs();

        self::assertCount(10, $tariffs);

        $tariff = $tariffs[0];

        self::assertSame(1, $tariff->id);
        self::assertSame('Kupek', $tariff->sysName);
        self::assertSame('Adult', $tariff->category);
        self::assertSame('Active', $tariff->status);
        self::assertTrue($tariff->isActive());
        self::assertTrue($tariff->nonRefundable);
        self::assertTrue($tariff->bonusCardAvailable);
        self::assertFalse($tariff->promocodeAvailable);
    }

    #[Test]
    public function sendsEmptyBodyForTariffs(): void
    {
        $this->clientWith('tariffs')->references->tariffs();

        $request = $this->request();

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/apib2b/p/Info/V1/References/Tariffs', $request->getUri()->getPath());
        self::assertSame('{}', (string) $request->getBody());
        self::assertSame('B2B_RZD', $this->query()['service_provider']);
    }

    #[Test]
    public function returnsCards(): void
    {
        $cards = $this->clientWith('cards')->references->cards();

        self::assertCount(8, $cards);

        $card = $cards[0];

        self::assertSame('П40', $card->code);
        self::assertSame('УНИВЕРСАЛЬНАЯ', $card->name);
        self::assertSame('UniversalRzhdCard', $card->type);
        self::assertSame('ДОСС', $card->carrier);
        self::assertSame(1000.0, $card->price);
        self::assertSame(10, $card->discount);
        self::assertSame(31, $card->activeDays);
        self::assertContains('Compartment', $card->carTypes);
        self::assertSame('/api/v1/railway-service/prices/cards', $this->request()->getUri()->getPath());
    }

    #[Test]
    public function distinguishesPassFromDiscountCard(): void
    {
        $cards = $this->clientWith('cards')->references->cards();

        // У скидочной карты поездки не ограничены числом
        self::assertFalse($cards[0]->isPass());
        self::assertSame(0, $cards[0]->tripQuantity);
    }

    #[Test]
    public function checksCardFitsCarType(): void
    {
        $card = $this->clientWith('cards')->references->cards()[0];

        self::assertTrue($card->fitsCarType('Compartment'));
        self::assertFalse($card->fitsCarType('НетТакогоТипа'));

        // Карта без ограничений по типу подходит любому
        self::assertTrue(\Rzd\Model\Card::fromArray([])->fitsCarType('Compartment'));
    }

    #[Test]
    public function returnsEmptyListWhenNoCards(): void
    {
        self::assertSame([], $this->client(['{}'])->references->cards());
    }

    #[Test]
    public function returnsSiteConfig(): void
    {
        $config = $this->client(['{"search":{"train":true},"stations_search_url":"/isdk/suggests"}'])
            ->references
            ->appConfig();

        self::assertSame('/isdk/suggests', $config['stations_search_url']);
        self::assertSame('/api/v1/app_config', $this->request()->getUri()->getPath());
    }

    #[Test]
    public function returnsEmptyListWhenNoTariffs(): void
    {
        self::assertSame([], $this->client(['{}'])->references->tariffs());
    }
}
