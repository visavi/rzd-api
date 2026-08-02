<?php

declare(strict_types=1);

namespace Rzd\Request;

use DateTimeInterface;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\PopularDirection;
use Rzd\Model\Station;

/**
 * Параметры поиска поездов
 */
final readonly class TrainSearch
{
    /**
     * @param string            $origin      Код станции или города отправления, например 2000000
     * @param string            $destination Код станции или города прибытия
     * @param DateTimeInterface $date        Дата отправления, время не учитывается
     * @param int               $adults      Взрослых пассажиров
     * @param int               $children    Детей без места
     * @param bool              $fromSchedule Добавлять поезда из расписания, у которых мест еще нет
     * @param bool              $largeFamily Искать места для многодетных
     * @param bool              $groupCars   Группировать вагоны одного типа
     */
    public function __construct(
        public string $origin,
        public string $destination,
        public DateTimeInterface $date,
        public int $adults = 1,
        public int $children = 0,
        public bool $fromSchedule = true,
        public bool $largeFamily = false,
        public bool $groupCars = false,
    ) {
        if ($this->origin === '' || $this->destination === '') {
            throw new InvalidArgumentException('Коды станций отправления и прибытия обязательны');
        }

        if ($this->adults < 1) {
            throw new InvalidArgumentException('Пассажиров должно быть не меньше одного');
        }

        if ($this->children < 0) {
            throw new InvalidArgumentException('Число детей не может быть отрицательным');
        }
    }

    /**
     * Собирает параметры из подсказок станций
     *
     * Прочие условия поиска остаются по умолчанию, менять их удобнее через
     * обычный конструктор. Станции принимаются пустыми, чтобы результат
     * Stations::find подходил без проверок на стороне вызова
     */
    public static function forStations(
        ?Station $origin,
        ?Station $destination,
        DateTimeInterface $date,
        int $adults = 1,
        int $children = 0,
    ): self {
        if ($origin?->code === null || $destination?->code === null) {
            throw new InvalidArgumentException('Станция не найдена или у нее нет кода, поиск поездов невозможен');
        }

        return new self($origin->code, $destination->code, $date, $adults, $children);
    }

    /**
     * Собирает параметры из популярного направления
     *
     * Направление уже содержит обе станции, поэтому подсказки не нужны
     */
    public static function forDirection(
        PopularDirection $direction,
        DateTimeInterface $date,
        int $adults = 1,
        int $children = 0,
    ): self {
        if ($direction->origin === null || $direction->destination === null) {
            throw new InvalidArgumentException('У направления нет пары станций, поиск поездов невозможен');
        }

        return self::forStations($direction->origin, $direction->destination, $date, $adults, $children);
    }

    /**
     * @return array<string, scalar>
     */
    public function toQuery(): array
    {
        return [
            'origin'                     => $this->origin,
            'destination'                => $this->destination,
            'departureDate'              => $this->date->format('Y-m-d\T00:00:00'),
            'adultPassengersQuantity'    => $this->adults,
            'childrenPassengersQuantity' => $this->children,
            'getTrainsFromSchedule'      => $this->fromSchedule,
            'hasPlacesForLargeFamily'    => $this->largeFamily,
            'carGrouping'                => $this->groupCars ? 'Group' : 'DontGroup',
            'getByLocalTime'             => true,
            'specialPlacesDemand'        => Defaults::SPECIAL_PLACES_DEMAND,
            'carIssuingType'             => Defaults::CAR_ISSUING_TYPE,
        ];
    }
}
