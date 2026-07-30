<?php

declare(strict_types=1);

namespace Rzd\Model;

use DateTimeImmutable;

/**
 * Отдельный рейс в цепочке пересадок
 *
 * Для поезда сайт вкладывает сюда же полный ответ обычного поиска, поэтому
 * доступны все его данные без второго запроса
 */
final readonly class Trip extends Model
{
    /**
     * Ключ, под которым лежит ответ обычного поиска поездов
     */
    private const TRAIN_PRICING = '/Railway/V1/Search/TrainPricing';

    private function __construct(
        array $raw,
        /** Номер рейса: для поезда это номер поезда, например 002Э */
        public ?string $number,
        public ?Place $origin,
        public ?Place $destination,
        public ?DateTimeImmutable $departure,
        public ?DateTimeImmutable $arrival,
        /** Цена в рублях за самое дешевое место */
        public ?float $minPrice,
        public ?float $maxPrice,
        public int $freePlaces,
        /** Вид транспорта: Train, Bus, Airplane */
        public ?string $transportType,
        /** Расстояние в километрах */
        public ?int $distance,
        /** @var list<TripProduct> Классы обслуживания с ценами */
        public array $products,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $distance = $data['trip_distance'] ?? [];
        $meters = is_array($distance) ? self::int($distance, 'meters') : null;

        $transport = $data['transport_type'] ?? [];
        $transport = is_array($transport) ? $transport : [];

        return new self(
            $data,
            self::str($data, 'race_number'),
            self::one($data, 'start_location', Place::class),
            self::one($data, 'finish_location', Place::class),
            self::date($data, 'start_datetime'),
            self::date($data, 'finish_datetime'),
            self::money($data, 'min_price'),
            self::money($data, 'max_price'),
            self::int($data, 'free_places') ?? 0,
            self::str($transport, 'provider_code'),
            $meters === null ? null : intdiv($meters, 1000),
            self::each($data, 'products', TripProduct::class),
        );
    }

    /**
     * Время в пути в минутах
     */
    public function duration(): ?int
    {
        if ($this->departure === null || $this->arrival === null) {
            return null;
        }

        return intdiv($this->arrival->getTimestamp() - $this->departure->getTimestamp(), 60);
    }

    /**
     * Поезд этого рейса со всеми данными обычного поиска
     *
     * Возвращает null для рейсов другого транспорта и для поездов, которые
     * сайт отдал без подробностей
     */
    public function train(): ?Train
    {
        $raw = $this->raw['raw_data'] ?? [];

        if (! is_array($raw) || ! is_array($raw[self::TRAIN_PRICING] ?? null)) {
            return null;
        }

        $trains = $raw[self::TRAIN_PRICING]['Trains'] ?? [];

        if (! is_array($trains) || ! is_array($trains[0] ?? null)) {
            return null;
        }

        return Train::fromArray($trains[0]);
    }
}
