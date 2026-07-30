<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Тариф аэроэкспресса
 */
final readonly class AeroexpressTariff extends Model
{
    private function __construct(
        array $raw,
        public ?string $id,
        public ?string $name,
        /** Вид тарифа: Standard, Business */
        public ?string $type,
        /** Условия применения, у части тарифов длинный текст с ограничениями */
        public ?string $description,
        public ?float $price,
        public ?string $routeName,
        public ?string $originStationCode,
        public ?string $destinationStationCode,
        /** Сколько билетов можно купить одним заказом */
        public ?int $maxTickets,
        /** Место фиксируется при покупке */
        public bool $guaranteedSeat,
        /** @var list<string> Документы, принимаемые при покупке */
        public array $documentTypes,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::str($data, 'TariffId'),
            self::str($data, 'TariffName'),
            self::str($data, 'TariffType'),
            self::str($data, 'Description'),
            self::float($data, 'Price'),
            self::str($data, 'RouteName'),
            self::str($data, 'OriginStationCode'),
            self::str($data, 'DestinationStationCode'),
            self::int($data, 'MaxTicketsQuantityAllowedForBooking'),
            self::bool($data, 'IsForGuaranteedSeats'),
            self::strings($data, 'DocumentTypes'),
        );
    }
}
