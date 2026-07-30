<?php

declare(strict_types=1);

namespace Rzd\Request;

use DateTimeInterface;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\Train;

/**
 * Параметры запроса маршрута поезда
 */
final readonly class RouteSearch
{
    /**
     * @param string            $trainNumber     Номер поезда
     * @param string            $origin          Код станции отправления
     * @param string            $destination     Код станции прибытия
     * @param DateTimeInterface $departure       Отправление с точностью до минуты
     * @param string|null       $originName      Название станции отправления, сайт передает его вместе с кодом
     * @param string|null       $destinationName Название станции прибытия
     * @param string            $provider        Система бронирования из данных поезда
     */
    public function __construct(
        public string $trainNumber,
        public string $origin,
        public string $destination,
        public DateTimeInterface $departure,
        public ?string $originName = null,
        public ?string $destinationName = null,
        public string $provider = Defaults::PROVIDER,
    ) {
        if ($this->trainNumber === '') {
            throw new InvalidArgumentException('Номер поезда обязателен');
        }

        if ($this->origin === '' || $this->destination === '') {
            throw new InvalidArgumentException('Коды станций отправления и прибытия обязательны');
        }
    }

    /**
     * Собирает параметры из найденного поезда
     */
    public static function forTrain(Train $train): self
    {
        if ($train->originStationCode === null || $train->destinationStationCode === null) {
            throw new InvalidArgumentException('У поезда нет кодов станций, запрос маршрута невозможен');
        }

        if ($train->number === null || $train->departure === null) {
            throw new InvalidArgumentException('У поезда нет номера или времени отправления');
        }

        return new self(
            $train->number,
            $train->originStationCode,
            $train->destinationStationCode,
            $train->departure,
            $train->originName,
            $train->destinationName,
            $train->provider ?? Defaults::PROVIDER,
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toQuery(): array
    {
        return [
            'TrainNumber'     => $this->trainNumber,
            'Origin'          => $this->origin,
            'Destination'     => $this->destination,
            'OriginName'      => $this->originName,
            'DestinationName' => $this->destinationName,
            'DepartureDate'   => $this->departure->format('Y-m-d\TH:i:s'),
            'Provider'        => $this->provider,
            'GetNewRoute'     => true,
        ];
    }
}
