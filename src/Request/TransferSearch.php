<?php

declare(strict_types=1);

namespace Rzd\Request;

use DateTimeInterface;
use Rzd\Enum\TransportProvider;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\PopularDirection;
use Rzd\Model\Station;

/**
 * Параметры поиска с пересадками
 *
 * Города задаются идентификаторами узлов сайта, а не кодами станций: их
 * отдает подсказка станций в поле nodeId
 */
final readonly class TransferSearch
{
    /**
     * @param string                 $origin      Идентификатор узла города отправления
     * @param string                 $destination Идентификатор узла города прибытия
     * @param DateTimeInterface      $date        Дата отправления, время не учитывается
     * @param int                    $minTrips    Наименьшее число рейсов в цепочке, 1 добавит прямые
     * @param int                    $maxTrips    Наибольшее число рейсов, то есть пересадок плюс один
     * @param int                    $maxResults  Предел числа вариантов в ответе
     * @param list<TransportProvider> $providers  Виды транспорта, участвующие в поиске
     */
    public function __construct(
        public string $origin,
        public string $destination,
        public DateTimeInterface $date,
        public int $minTrips = 2,
        public int $maxTrips = 4,
        public int $maxResults = 200,
        public array $providers = [TransportProvider::Rails],
    ) {
        if ($this->origin === '' || $this->destination === '') {
            throw new InvalidArgumentException('Идентификаторы городов отправления и прибытия обязательны');
        }

        if ($this->minTrips < 1) {
            throw new InvalidArgumentException('Рейсов в цепочке должно быть не меньше одного');
        }

        if ($this->maxTrips < $this->minTrips) {
            throw new InvalidArgumentException('Наибольшее число рейсов меньше наименьшего');
        }

        if ($this->maxResults < 1) {
            throw new InvalidArgumentException('Число вариантов должно быть положительным');
        }

        if ($this->providers === []) {
            throw new InvalidArgumentException('Нужен хотя бы один вид транспорта');
        }
    }

    /**
     * Собирает параметры из подсказок станций
     *
     * Города, а не станции: поиск с пересадками работает с узлами сайта.
     * Станции принимаются пустыми, чтобы результат Stations::find подходил
     * без проверок на стороне вызова
     */
    public static function forStations(?Station $origin, ?Station $destination, DateTimeInterface $date): self
    {
        if ($origin?->nodeId === null || $destination?->nodeId === null) {
            throw new InvalidArgumentException('Станция не найдена или у нее нет узла, поиск с пересадками невозможен');
        }

        return new self($origin->nodeId, $destination->nodeId, $date);
    }

    /**
     * Собирает параметры из популярного направления
     *
     * Направление уже содержит обе станции, поэтому подсказки не нужны
     */
    public static function forDirection(PopularDirection $direction, DateTimeInterface $date): self
    {
        if ($direction->origin === null || $direction->destination === null) {
            throw new InvalidArgumentException('У направления нет пары станций, поиск с пересадками невозможен');
        }

        return self::forStations($direction->origin, $direction->destination, $date);
    }

    /**
     * @return array<string, mixed>
     */
    public function toBody(): array
    {
        return [
            'max_results'       => $this->maxResults,
            'min_trips_in_leg'  => $this->minTrips,
            'max_trips_in_leg'  => $this->maxTrips,
            'system_params'     => [
                'search_via_ar'    => 'SVA_DONT_SEARCH',
                'search_via_graph' => 'SVG_SEARCH_WITH_DETAIL',
                // Без подробностей ответ приходит без названий и координат станций
                'detailed_location' => true,
                'debug'             => false,
            ],
            'start_location'  => ['city' => ['key' => $this->origin], 'type' => 'city'],
            'finish_location' => ['city' => ['key' => $this->destination], 'type' => 'city'],
            // Сайт ищет в пределах суток отправления
            'start_datetime_range' => [
                'from' => $this->date->format('Y-m-d\T00:00:00'),
                'to'   => $this->date->format('Y-m-d\T23:59:59'),
            ],
            'filters' => [[
                'param_name'   => 'route.provider.key',
                'exact_filter' => ['param_values' => TransportProvider::values($this->providers)],
            ]],
        ];
    }
}
