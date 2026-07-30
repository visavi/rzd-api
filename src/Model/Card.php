<?php

declare(strict_types=1);

namespace Rzd\Model;

use DateTimeImmutable;

/**
 * Карта или абонемент перевозчика
 *
 * Дают скидку на билеты определенных типов вагонов и классов обслуживания
 * либо фиксированное число поездок
 */
final readonly class Card extends Model
{
    private function __construct(
        array $raw,
        /** Код карты, например П40 */
        public ?string $code,
        public ?string $name,
        /** Вид карты: UniversalRzhdCard, BusinessTravel */
        public ?string $type,
        public ?string $carrier,
        public ?string $carrierName,
        /** Стоимость самой карты */
        public ?float $price,
        /** Скидка на билеты в процентах, у абонементов на поездки равна нулю */
        public ?int $discount,
        /** Срок действия в днях */
        public ?int $activeDays,
        /** Число поездок у абонемента */
        public ?int $tripQuantity,
        /** @var list<string> Типы вагонов, на которые действует */
        public array $carTypes,
        /** @var list<string> Классы обслуживания */
        public array $serviceClasses,
        /** Начало продажи карты */
        public ?DateTimeImmutable $saleStart,
        public ?DateTimeImmutable $saleEnd,
        /** Первая дата поездки, к которой применима */
        public ?DateTimeImmutable $departureStart,
        public ?DateTimeImmutable $departureEnd,
        public ?int $minAge,
        public ?int $maxAge,
        public bool $refundable,
        public bool $exchangeable,
        public bool $giftCard,
        /** Только нижние места */
        public bool $lowerSeatsOnly,
        /** Только верхние места */
        public bool $upperSeatsOnly,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::str($data, 'Code'),
            self::str($data, 'Name'),
            self::str($data, 'Type'),
            self::str($data, 'Carrier'),
            self::str($data, 'CarrierName'),
            self::float($data, 'Price'),
            self::int($data, 'DiscountInPercents'),
            self::int($data, 'ActiveDays'),
            self::int($data, 'TripQuantity'),
            self::strings($data, 'CarTypes'),
            self::strings($data, 'ServiceClasses'),
            self::date($data, 'TicketsSaleStartsDate'),
            self::date($data, 'TicketsSaleEndsDate'),
            self::date($data, 'DepartureStartsDateTime'),
            self::date($data, 'DepartureEndsDateTime'),
            self::int($data, 'MinAge'),
            self::int($data, 'MaxAge'),
            self::bool($data, 'IsReturnAllowed'),
            self::bool($data, 'IsExchangeAllowed'),
            self::bool($data, 'IsGiftCard'),
            self::bool($data, 'IsOnlyForLowerSeats'),
            self::bool($data, 'IsOnlyForUpperSeats'),
        );
    }

    /**
     * Абонемент на поездки, а не скидочная карта
     */
    public function isPass(): bool
    {
        return ($this->tripQuantity ?? 0) > 0;
    }

    /**
     * Действует ли карта на такой тип вагона
     *
     * Тип задается в терминах сайта: Compartment, Luxury, ReservedSeat
     */
    public function fitsCarType(string $carType): bool
    {
        return $this->carTypes === [] || in_array($carType, $this->carTypes, true);
    }
}
