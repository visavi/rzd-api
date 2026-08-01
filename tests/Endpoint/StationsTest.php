<?php

declare(strict_types=1);

namespace Rzd\Tests\Endpoint;

use PHPUnit\Framework\Attributes\Test;
use Rzd\Config;
use Rzd\Enum\Language;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Tests\Concerns\AssertsExceptions;
use Rzd\Tests\TestCase;

final class StationsTest extends TestCase
{
    use AssertsExceptions;

    #[Test]
    public function returnsStationsByNamePart(): void
    {
        $stations = $this->clientWith('suggests')->stations->suggest('ЧЕБ');

        self::assertNotSame([], $stations);

        $names = array_map(static fn(object $station): ?string => $station->name, $stations);

        self::assertContains('Чебоксары', $names);
    }

    #[Test]
    public function parsesStation(): void
    {
        $stations = $this->clientWith('suggests')->stations->suggest('ЧЕБ');

        $station = $stations[0];

        self::assertSame('Чебоксары', $station->name);
        self::assertSame('2060620', $station->code);
        self::assertSame('Российская Федерация', $station->region);
        self::assertSame('Город', $station->type);
        self::assertSame('Europe/Moscow', $station->timezone);
        self::assertArrayHasKey('Cbdpr', $station->codes);
        self::assertNotSame([], $station->stationCodes);
    }

    #[Test]
    public function dropsDuplicateCodes(): void
    {
        $stations = $this->clientWith('suggests')->stations->suggest('ЧЕБ');

        // Город и его вокзалы приходят разными узлами с одним кодом
        $codes = array_map(static fn(object $station): ?string => $station->code, $stations);

        self::assertSame($codes, array_values(array_unique($codes)));
    }

    #[Test]
    public function sendsSuggestParameters(): void
    {
        $this->clientWith('suggests')->stations->suggest('ЧЕБ');

        $query = $this->query();

        self::assertSame('/isdk/suggests', $this->request()->getUri()->getPath());
        self::assertSame('ЧЕБ', $query['Query']);
        self::assertSame('rail', $query['TransportType']);
        self::assertSame('true', $query['GroupResults']);
        self::assertSame('ru', $query['Language']);
    }

    #[Test]
    public function appliesLanguageFromConfig(): void
    {
        $config = new Config(language: Language::English);

        $this->client([$this->fixture('suggests')], $config)->stations->suggest('CHE');

        self::assertSame('en', $this->query()['Language']);
        self::assertSame('en', $this->request()->getHeaderLine('Accept-Language'));
    }

    #[Test]
    public function rejectsEmptySuggestQuery(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Часть названия станции обязательна',
            fn() => $this->client()->stations->suggest('   '),
        );
    }

    #[Test]
    public function skipsNodesWithoutCode(): void
    {
        $response = '{"transport_node_suggests":[{"Name":"Без кода"},{"Name":"Москва","Codes":{"Railway":"2000000"}}]}';

        $stations = $this->client([$response])->stations->suggest('Мос');

        self::assertCount(1, $stations);
        self::assertSame('2000000', $stations[0]->code);
    }

    #[Test]
    public function skipsNodesOfWrongType(): void
    {
        $response = '{"transport_node_suggests":["строка",42,null,{"Name":"Москва","Codes":{"Railway":"2000000"}}]}';

        $stations = $this->client([$response])->stations->suggest('Мос');

        self::assertCount(1, $stations);
        self::assertSame('Москва', $stations[0]->name);
    }

    #[Test]
    public function returnsPopularCities(): void
    {
        $response = '[{"nodeId":"5a323c29340c7441a0a556bb","expressCode":"2000000","nodeType":"city","name":"Москва"}]';

        $cities = $this->client([$response])->stations->popular();

        self::assertCount(1, $cities);
        self::assertSame('2000000', $cities[0]->code);
        self::assertSame('5a323c29340c7441a0a556bb', $cities[0]->nodeId);
        // Тут сайт называет поля со строчной буквы, в отличие от подсказок
        self::assertSame('Москва', $cities[0]->name);
        self::assertSame('city', $cities[0]->type);
        self::assertSame('/api/v1/popular_cities/ru', $this->request()->getUri()->getPath());
    }

    #[Test]
    public function returnsPopularDirections(): void
    {
        $response = '[{"from":"Москва","to":"Казань","type":"train",'
            . '"departure":{"nodeId":"5a323c29340c7441a0a556bb","expressCode":"2000000","nodeType":"city","name":"Москва"},'
            . '"arrival":{"nodeId":"5a13bd41340c745ca1e88b55","expressCode":"2060615","nodeType":"city","name":"Казань"}}]';

        $directions = $this->client([$response])->stations->directions();

        self::assertCount(1, $directions);
        self::assertSame('Москва', $directions[0]->origin?->name);
        self::assertSame('2060615', $directions[0]->destination?->code);
        self::assertSame('train', $directions[0]->type);
        self::assertSame('/api/v1/directions', $this->request()->getUri()->getPath());
    }

    #[Test]
    public function returnsDirectionWithoutStations(): void
    {
        $directions = $this->client(['[{"from":"Москва","to":"Казань"}]'])->stations->directions();

        self::assertNull($directions[0]->origin);
        self::assertNull($directions[0]->destination);
        self::assertNull($directions[0]->type);
    }

    #[Test]
    public function tellsCityFromStation(): void
    {
        $response = '{"transport_node_suggests":['
            . '{"Name":"Новый Уренгой","NodeId":"5a13bdc3","CityId":"5a13bdc3","Codes":{"Railway":"2030319"}},'
            . '{"Name":"Новый Уренгой","NodeId":"5a8ac376","CityId":"5a13bdc3","Codes":{"Railway":"2030317"}}]}';

        $stations = $this->client([$response])->stations->suggest('Новый Уренгой');

        // Город ссылается сам на себя, вокзал - на город
        self::assertTrue($stations[0]->isCity());
        self::assertFalse($stations[1]->isCity());
    }

    #[Test]
    public function treatsPopularCitiesAsCities(): void
    {
        // В популярных городах CityId не приходит вовсе
        $response = '[{"nodeId":"5a323c29340c7441a0a556bb","expressCode":"2000000","nodeType":"city","name":"Москва"}]';

        self::assertTrue($this->client([$response])->stations->popular()[0]->isCity());
    }

    #[Test]
    public function returnsCityByNodeId(): void
    {
        $response = '{"nodeId":"5a323c29340c7441a0a556bb","expressCode":"2000000","name":"Москва"}';

        $station = $this->client([$response])->stations->byNodeId('5a323c29340c7441a0a556bb');

        self::assertSame('2000000', $station->code);
        self::assertSame('Москва', $station->name);
        self::assertSame('/api/v1/getobject', $this->request()->getUri()->getPath());
        self::assertSame('5a323c29340c7441a0a556bb', $this->query()['id']);
    }

    #[Test]
    public function rejectsEmptyNodeId(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            'Идентификатор узла обязателен',
            fn() => $this->client()->stations->byNodeId(' '),
        );
    }
}
