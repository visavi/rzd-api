<?php

declare(strict_types=1);

namespace Rzd\Model;

use DateTimeImmutable;

/**
 * Минимальная цена на дату отправления
 *
 * Разбивка по перевозчикам, поездам и типам вагонов приходит тем же ответом
 * и доступна через raw: четыре уровня вложенности в модели не нужны,
 * а для календаря цен достаточно минимума по дню
 */
final readonly class PriceDay extends Model
{
    private function __construct(
        array $raw,
        public ?DateTimeImmutable $date,
        public ?float $minPrice,
        /** Минимальная цена места для пассажиров с ограниченной подвижностью */
        public ?float $disabledPlaceMinPrice,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            // Опечатка в названии поля со стороны сайта, второе написание на случай исправления
            self::date($data, 'DepatureDate') ?? self::date($data, 'DepartureDate'),
            self::float($data, 'MinPrice'),
            self::float($data, 'DisabledPlaceMinPrice'),
        );
    }

    /**
     * Минимальные цены по типам вагонов
     *
     * Ключ - тип вагона в терминах сайта: Compartment, Luxury, Sedentary
     *
     * @return array<string, float>
     */
    public function byCarType(): array
    {
        $prices = [];

        foreach ($this->raw['Carriers'] ?? [] as $carrier) {
            foreach ($carrier['Trains'] ?? [] as $train) {
                foreach ($train['CarTypes'] ?? [] as $carType) {
                    $name = $carType['CarTypeName'] ?? null;
                    $price = $carType['MinPrice'] ?? null;

                    if (! is_string($name) || ! is_numeric($price) || $price <= 0) {
                        continue;
                    }

                    $prices[$name] = min($prices[$name] ?? INF, (float) $price);
                }
            }
        }

        return $prices;
    }

    /**
     * Перевозчики, у которых есть места на эту дату
     *
     * @return list<string>
     */
    public function carriers(): array
    {
        return array_values(array_filter(array_map(
            static fn(mixed $carrier): ?string => is_array($carrier) && is_string($carrier['CarrierName'] ?? null)
                ? $carrier['CarrierName']
                : null,
            $this->raw['Carriers'] ?? [],
        )));
    }
}
