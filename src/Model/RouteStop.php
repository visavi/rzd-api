<?php

declare(strict_types=1);

namespace Rzd\Model;

use DateTimeImmutable;

/**
 * Остановка на маршруте поезда
 */
final readonly class RouteStop extends Model
{
    private function __construct(
        array $raw,
        public ?string $stationName,
        public ?string $stationCode,
        public ?string $cityName,
        /** Прибытие по местному времени станции, у начальной отсутствует */
        public ?DateTimeImmutable $arrival,
        /** Отправление по местному времени станции, у конечной отсутствует */
        public ?DateTimeImmutable $departure,
        /** Прибытие по московскому времени */
        public ?DateTimeImmutable $moscowArrival,
        public ?DateTimeImmutable $moscowDeparture,
        /** Стоянка в минутах */
        public ?int $stopDuration,
        /** Сутки пути от станции формирования */
        public ?int $dayFromStart,
        /** Разница с московским временем в часах */
        public ?int $timeZoneDifference,
        /** Пояснение к остановке, например о переводе времени */
        public ?string $clarification,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::str($data, 'StationName'),
            self::str($data, 'StationCode'),
            self::str($data, 'CityName'),
            self::date($data, 'LocalArrivalDateTime'),
            self::date($data, 'LocalDepartureDateTime'),
            self::date($data, 'ArrivalDateTime'),
            self::date($data, 'DepartureDateTime'),
            self::int($data, 'StopDuration'),
            self::int($data, 'DaysFromFormingStation'),
            self::int($data, 'TimeZoneDifference'),
            self::str($data, 'Clarification'),
        );
    }
}
