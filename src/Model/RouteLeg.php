<?php

declare(strict_types=1);

namespace Rzd\Model;

use Rzd\Enum\TransportProvider;

/**
 * Плечо поездки: часть маршрута, оформляемая одним билетом
 *
 * Сайт делит плечо на участки, а участок на рейсы, но участок нужен только
 * при оформлении, поэтому рейсы здесь сведены в один список
 */
final readonly class RouteLeg extends Model
{
    private function __construct(
        array $raw,
        /** Поставщик перевозки, null для незнакомого библиотеке */
        public ?TransportProvider $provider,
        /** Система бронирования, например Express3 */
        public ?string $bookingSystem,
        /** Цена в рублях за самое дешевое место */
        public ?float $minPrice,
        public ?float $maxPrice,
        public int $freePlaces,
        /** @var list<string> Виды транспорта: Train, Bus, Airplane */
        public array $transportTypes,
        /** @var list<Trip> Рейсы плеча по порядку */
        public array $trips,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $provider = $data['provider'] ?? [];
        $provider = is_array($provider) ? self::str($provider, 'key') : null;

        $segments = $data['segments'] ?? [];
        $segments = is_array($segments) ? array_filter($segments, 'is_array') : [];

        $trips = [];

        foreach ($segments as $segment) {
            $items = $segment['trips'] ?? [];

            foreach (is_array($items) ? array_filter($items, 'is_array') : [] as $trip) {
                $trips[] = Trip::fromArray($trip);
            }
        }

        return new self(
            $data,
            $provider === null ? null : TransportProvider::tryFrom($provider),
            self::str($data, 'booking_system'),
            self::money($data, 'min_price'),
            self::money($data, 'max_price'),
            self::int($data, 'free_places') ?? 0,
            self::codes($data, 'transport_types'),
            $trips,
        );
    }

    public function origin(): ?Place
    {
        return ($this->trips[0] ?? null)?->origin;
    }

    public function destination(): ?Place
    {
        $last = $this->trips === [] ? null : $this->trips[count($this->trips) - 1];

        return $last?->destination;
    }
}
