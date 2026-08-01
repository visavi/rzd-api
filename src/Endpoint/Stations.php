<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\PopularDirection;
use Rzd\Model\Station;

/**
 * Станции и города
 *
 * Подсказки идут через тот же адрес, который использует сам сайт: он указан
 * в его конфигурации как stations_search_url
 */
final readonly class Stations extends Endpoint
{
    private const SUGGEST_PATH = '/isdk/suggests';
    private const POPULAR_PATH = '/api/v1/popular_cities';
    private const OBJECT_PATH = '/api/v1/getobject';
    private const DIRECTIONS_PATH = '/api/v1/directions';

    /**
     * Ищет станции по части названия
     *
     * Город и его вокзалы приходят разными узлами с одинаковым кодом,
     * повторы отбрасываются
     *
     * @return list<Station>
     */
    public function suggest(string $query): array
    {
        if (trim($query) === '') {
            throw new InvalidArgumentException('Часть названия станции обязательна');
        }

        $response = $this->transport->get(self::SUGGEST_PATH, [
            'GroupResults'  => true,
            'TransportType' => 'rail',
            'Language'      => $this->config->language->value,
            'Query'         => $query,
        ]);

        $stations = [];

        foreach ($response['transport_node_suggests'] ?? [] as $node) {
            if (! is_array($node)) {
                continue;
            }

            $station = Station::fromArray($node);

            if ($station->code === null || isset($stations[$station->code])) {
                continue;
            }

            $stations[$station->code] = $station;
        }

        return array_values($stations);
    }

    /**
     * Популярные города с кодами станций
     *
     * @return list<Station>
     */
    public function popular(): array
    {
        $response = $this->transport->get(self::POPULAR_PATH . '/' . $this->config->language->value);

        return $this->models($response, Station::class);
    }

    /**
     * Популярные направления с главной страницы сайта
     *
     * В отличие от popular отдает готовые пары станций, а не отдельные узлы
     *
     * @return list<PopularDirection>
     */
    public function directions(): array
    {
        return $this->models($this->transport->get(self::DIRECTIONS_PATH), PopularDirection::class);
    }

    /**
     * Город или станция по идентификатору узла
     *
     * Идентификатор приходит в подсказках и в списке популярных городов,
     * он же участвует в адресах страниц сайта
     */
    public function byNodeId(string $nodeId): Station
    {
        if (trim($nodeId) === '') {
            throw new InvalidArgumentException('Идентификатор узла обязателен');
        }

        return Station::fromArray($this->transport->get(self::OBJECT_PATH, ['id' => $nodeId]));
    }
}
