<?php

declare(strict_types=1);

namespace Rzd\Request;

use DateTimeInterface;
use Rzd\Exception\InvalidArgumentException;

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
