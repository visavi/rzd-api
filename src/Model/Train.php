<?php

declare(strict_types=1);

namespace Rzd\Model;

use DateTimeImmutable;

/**
 * Поезд в результатах поиска
 */
final readonly class Train extends Model
{
    private function __construct(
        array $raw,
        /** Номер поезда, например 130Х */
        public ?string $number,
        /** Номер для показа пользователю, у части поездов отличается от служебного */
        public ?string $displayNumber,
        /** Название фирменного поезда, у обычных отсутствует */
        public ?string $name,
        /** Категория: СК, ПАСС, СКОР */
        public ?string $description,
        /** Система бронирования, нужна при запросе вагонов этого поезда */
        public ?string $provider,
        /** Код станции отправления, нужен при запросе вагонов вместо кода города */
        public ?string $originStationCode,
        public ?string $destinationStationCode,
        public ?string $originName,
        public ?string $destinationName,
        /** Отправление по местному времени станции */
        public ?DateTimeImmutable $departure,
        /** Прибытие по местному времени станции */
        public ?DateTimeImmutable $arrival,
        /** Отправление по московскому времени */
        public ?DateTimeImmutable $moscowDeparture,
        public ?DateTimeImmutable $moscowArrival,
        /** Время в пути в минутах */
        public ?int $duration,
        /** Расстояние в километрах */
        public ?int $distance,
        /** @var list<CarGroup> Группы вагонов с ценами и количеством мест */
        public array $carGroups,
        /** @var list<string> Перевозчики */
        public array $carriers,
        /** @var list<string> Услуги в вагонах, например Bedclothes */
        public array $services,
        public bool $branded,
        public bool $suburban,
        public bool $saleForbidden,
        public bool $hasElectronicRegistration,
        public bool $hasTwoStoreyCars,
        public bool $hasDynamicPricingCars,
        /** Поезд взят из расписания, мест в нем еще нет */
        public bool $fromSchedule,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::str($data, 'TrainNumber'),
            self::str($data, 'DisplayTrainNumber'),
            self::str($data, 'TrainName'),
            self::str($data, 'TrainDescription'),
            self::str($data, 'Provider'),
            self::str($data, 'OriginStationCode'),
            self::str($data, 'DestinationStationCode'),
            self::str($data, 'OriginName'),
            self::str($data, 'DestinationName'),
            self::date($data, 'LocalDepartureDateTime'),
            self::date($data, 'LocalArrivalDateTime'),
            self::date($data, 'DepartureDateTime'),
            self::date($data, 'ArrivalDateTime'),
            self::int($data, 'TripDuration'),
            self::int($data, 'TripDistance'),
            self::each($data, 'CarGroups', CarGroup::class),
            self::strings($data, 'Carriers'),
            self::strings($data, 'CarServices'),
            self::bool($data, 'IsBranded'),
            self::bool($data, 'IsSuburban'),
            self::bool($data, 'IsSaleForbidden'),
            self::bool($data, 'HasElectronicRegistration'),
            self::bool($data, 'HasTwoStoreyCars'),
            self::bool($data, 'HasDynamicPricingCars'),
            self::bool($data, 'IsFromSchedule'),
        );
    }

    /**
     * Минимальная цена по всем группам вагонов
     */
    public function minPrice(): ?float
    {
        // Цену сравниваем с null, а не приводим к bool: бесплатное место
        // теоретически возможно, и терять его нельзя
        $prices = array_filter(
            array_map(static fn(CarGroup $group): ?float => $group->minPrice, $this->carGroups),
            static fn(?float $price): bool => $price !== null,
        );

        return $prices === [] ? null : min($prices);
    }

    /**
     * Свободных мест по всем группам вагонов
     */
    public function freeSeats(): int
    {
        return array_sum(array_map(
            static fn(CarGroup $group): int => $group->places ?? 0,
            $this->carGroups,
        ));
    }
}
