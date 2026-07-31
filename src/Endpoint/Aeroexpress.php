<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use DateTimeInterface;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\AeroexpressTariff;

/**
 * Аэроэкспресс
 *
 * Отдельный перевозчик со своими тарифами: место в них обычно не фиксировано,
 * а билет действует несколько месяцев, поэтому поиска поездов здесь нет
 */
final readonly class Aeroexpress extends Endpoint
{
    private const TARIFFS_PATH = '/apib2b/p/Aeroexpress/V1/Search/TariffPricing';

    /**
     * Тарифы на дату поездки
     *
     * Коды станций необязательны: без них сайт отдает тарифы, действующие
     * на любом направлении от аэропортов и к ним
     *
     * @return list<AeroexpressTariff>
     */
    public function tariffs(
        DateTimeInterface $date,
        ?string $origin = null,
        ?string $destination = null,
    ): array {
        if ($origin === '' || $destination === '') {
            throw new InvalidArgumentException('Коды станций должны быть либо заданы, либо опущены');
        }

        $body = array_filter([
            'DepartureDate'          => $date->format('Y-m-d\T00:00:00'),
            'OriginCode'             => $origin,
            'DestinationCode'        => $destination,
        ], static fn(mixed $value): bool => $value !== null);

        $response = $this->transport->post(self::TARIFFS_PATH, $body);

        return $this->models($response['Tariffs'] ?? [], AeroexpressTariff::class);
    }
}
