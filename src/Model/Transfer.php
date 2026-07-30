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
        /** Наименьшее время переезда в секундах */
        public ?int $duration,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::one($data, 'start_location', Place::class),
            self::one($data, 'finish_location', Place::class),
            self::money($data, 'min_price'),
            self::seconds($data, 'min_duration'),
        );
    }

    /**
     * Время переезда в минутах
     */
    public function minutes(): ?int
    {
        return $this->duration === null ? null : intdiv($this->duration, 60);
    }
}
