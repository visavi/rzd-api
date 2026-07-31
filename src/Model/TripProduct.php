<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Предложение на рейсе: класс обслуживания со своей ценой
 *
 * Для поезда это тип вагона, для самолета тариф, поэтому поля названы
 * нейтрально
 */
final readonly class TripProduct extends Model
{
    private function __construct(
        array $raw,
        /** Цена в рублях */
        public ?float $price,
        public int $freePlaces,
        /** Тип места: Compartment, ReservedSeat, Sedentary */
        public ?string $type,
        /**
         * Классы обслуживания перевозчика, например 2Э:ФПК
         *
         * Их несколько, потому что предложение объединяет вагоны с разным
         * набором услуг и одинаковой ценой
         *
         * @var list<string>
         */
        public array $serviceClasses,
        /** @var list<string> Перевозчики, например ФПК */
        public array $carriers,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::money($data, 'price'),
            self::int($data, 'free_places') ?? 0,
            self::str(self::nested($data, 'train_car_type'), 'key'),
            // Одиночное common_service_class дублирует тип вагона, а не класс
            self::codes($data, 'common_service_classes'),
            self::codes($data, 'carriers'),
        );
    }
}
