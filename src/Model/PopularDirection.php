<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Популярное направление с главной страницы сайта
 *
 * Обе станции приходят готовыми узлами, поэтому направление подходит и
 * обычному поиску, и поиску с пересадками без запроса подсказок
 */
final readonly class PopularDirection extends Model
{
    private function __construct(
        array $raw,
        public ?Station $origin,
        public ?Station $destination,
        /** Вид транспорта направления, например train */
        public ?string $type,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::one($data, 'departure', Station::class),
            self::one($data, 'arrival', Station::class),
            self::str($data, 'type'),
        );
    }
}
