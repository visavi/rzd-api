<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Переезд между вокзалами внутри пересадки
 *
 * Появляется, когда цепочка приходит на один вокзал города, а уезжает с
 * другого: сайт считает время и стоимость такого переезда отдельно
 */
final readonly class Transfer extends Model
{
    private function __construct(
        array $raw,
        public ?Place $origin,
        public ?Place $destination,
        /** Стоимость переезда в рублях */
        public ?float $price,
        /** Наименьшее время переезда в минутах, как у рейса и всей поездки */
        public ?int $duration,
        /** То же время без округления: сайт считает переезд посекундно */
        public ?int $seconds,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $seconds = self::seconds($data, 'min_duration');

        return new self(
            $data,
            self::one($data, 'start_location', Place::class),
            self::one($data, 'finish_location', Place::class),
            self::money($data, 'min_price'),
            $seconds === null ? null : intdiv($seconds, 60),
            $seconds,
        );
    }
}
