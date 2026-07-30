<?php

declare(strict_types=1);

namespace Rzd\Enum;

/**
 * Поставщик перевозки в поиске с пересадками
 *
 * Поиск мультимодальный: части поездки могут обслуживаться разными
 * перевозчиками, поэтому список задается явно
 */
enum TransportProvider: string
{
    /** Поезда дальнего следования */
    case Rails = 'b2brails';

    /** Пригородные поезда */
    case Suburban = 'cbdpr';

    case Bus = 'b2bbus';

    case Avia = 'b2bavia';

    /** Водный транспорт, например паром в Крым */
    case Boat = 'b2bboat';

    case Aeroexpress = 'aeroexpress';

    /**
     * Все виды транспорта, которые сайт учитывает в поиске
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @param list<self> $providers
     *
     * @return list<string>
     */
    public static function values(array $providers): array
    {
        return array_values(array_map(static fn(self $provider): string => $provider->value, $providers));
    }
}
