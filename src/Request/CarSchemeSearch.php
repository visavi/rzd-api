<?php

declare(strict_types=1);

namespace Rzd\Request;

use DateTimeInterface;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\Car;
use Rzd\Model\Train;

/**
 * Параметры запроса схемы или фотографий вагона
 */
final readonly class CarSchemeSearch
{
    /**
     * @param string            $carrier      Перевозчик, например ФПК
     * @param string            $carSubType   Подтип вагона, определяющий схему: 64К
     * @param string            $carNumber    Номер вагона в составе
     * @param string            $trainNumber  Номер поезда
     * @param DateTimeInterface $departure    Отправление с точностью до минуты
     * @param string|null       $serviceClass Класс обслуживания, например 2Э
     * @param string            $numeration   Нумерация вагонов: FromHead, FromTail
     */
    public function __construct(
        public string $carrier,
        public string $carSubType,
        public string $carNumber,
        public string $trainNumber,
        public DateTimeInterface $departure,
        public ?string $serviceClass = null,
        public string $numeration = 'FromHead',
    ) {
        if ($this->carrier === '' || $this->carSubType === '') {
            throw new InvalidArgumentException('Перевозчик и подтип вагона обязательны');
        }

        if ($this->carNumber === '' || $this->trainNumber === '') {
            throw new InvalidArgumentException('Номер вагона и номер поезда обязательны');
        }
    }

    /**
     * Собирает параметры из вагона и его поезда
     */
    public static function forCar(Car $car, Train $train): self
    {
        if ($car->carrier === null || $car->subType === null || $car->number === null) {
            throw new InvalidArgumentException('У вагона нет перевозчика, подтипа или номера');
        }

        if ($train->number === null || $train->departure === null) {
            throw new InvalidArgumentException('У поезда нет номера или времени отправления');
        }

        return new self(
            $car->carrier,
            $car->subType,
            $car->number,
            $train->number,
            $train->departure,
            $car->serviceClass,
            $car->numeration ?? 'FromHead',
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toQuery(): array
    {
        return [
            'Carrier'       => $this->carrier,
            'CarSubType'    => $this->carSubType,
            'CarNumber'     => $this->carNumber,
            'ServiceClass'  => $this->serviceClass,
            'TrainNumber'   => $this->trainNumber,
            'DepartureDate' => $this->departure->format('Y-m-d\TH:i:s'),
            'CarNumeration' => $this->numeration,
        ];
    }

    /**
     * Тот же запрос в написании, которое требует список фотографий
     *
     * Эндпоинт изображений принимает те же параметры, но с малой буквы
     * и с дублированием номера поезда в трех полях
     *
     * @return array<string, scalar|null>
     */
    public function toImageQuery(): array
    {
        return [
            'carrier'           => $this->carrier,
            'carSubType'        => $this->carSubType,
            'carNumber'         => $this->carNumber,
            'serviceClass'      => $this->serviceClass,
            'carNumeration'     => $this->numeration,
            'departureDate'     => $this->departure->format('Y-m-d\TH:i:s'),
            'trainNumber'       => $this->trainNumber,
            'hiddenTrainNumber' => $this->trainNumber,
            'displayTrainNumber' => $this->trainNumber,
        ];
    }
}
