<?php

declare(strict_types=1);

namespace Rzd\Model;

use Countable;
use DateTimeImmutable;
use IteratorAggregate;
use Traversable;

/**
 * Вариант поездки с пересадками
 *
 * Перебирается напрямую как список плеч
 *
 * @implements IteratorAggregate<int, RouteLeg>
 */
final readonly class TransferRoute extends Model implements IteratorAggregate, Countable
{
    private function __construct(
        array $raw,
        /** @var list<RouteLeg> Плечи поездки по порядку */
        public array $legs,
        /**
         * Переезды между вокзалами
         *
         * Пустой список означает, что все пересадки происходят на одном
         * вокзале, а не что пересадок нет
         *
         * @var list<Transfer>
         */
        public array $transfers,
        /** Стоимость всей поездки по самым дешевым местам, в рублях */
        public ?float $minPrice,
        public ?float $maxPrice,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::each($data, 'routes', RouteLeg::class),
            self::each($data, 'transfers', Transfer::class),
            self::money($data, 'min_price'),
            self::money($data, 'max_price'),
        );
    }

    /**
     * @return Traversable<int, RouteLeg>
     */
    public function getIterator(): Traversable
    {
        yield from $this->legs;
    }

    public function count(): int
    {
        return count($this->legs);
    }

    /**
     * Число пересадок
     */
    public function changes(): int
    {
        return max(count($this->legs) - 1, 0);
    }

    public function origin(): ?Place
    {
        return ($this->legs[0] ?? null)?->origin();
    }

    public function destination(): ?Place
    {
        $last = $this->legs === [] ? null : $this->legs[count($this->legs) - 1];

        return $last?->destination();
    }

    public function departure(): ?DateTimeImmutable
    {
        return $this->firstTrip()?->departure;
    }

    public function arrival(): ?DateTimeImmutable
    {
        return $this->lastTrip()?->arrival;
    }

    /**
     * Время всей поездки в минутах, включая ожидание на пересадках
     */
    public function duration(): ?int
    {
        $departure = $this->departure();
        $arrival = $this->arrival();

        if ($departure === null || $arrival === null) {
            return null;
        }

        return intdiv($arrival->getTimestamp() - $departure->getTimestamp(), 60);
    }

    /**
     * Все рейсы поездки подряд
     *
     * @return list<Trip>
     */
    public function trips(): array
    {
        return array_merge(...array_map(static fn(RouteLeg $leg): array => $leg->trips, $this->legs));
    }

    /**
     * Есть ли места на всех плечах
     */
    public function hasSeats(): bool
    {
        if ($this->legs === []) {
            return false;
        }

        foreach ($this->legs as $leg) {
            if ($leg->freePlaces < 1) {
                return false;
            }
        }

        return true;
    }

    private function firstTrip(): ?Trip
    {
        $trips = $this->trips();

        return $trips[0] ?? null;
    }

    private function lastTrip(): ?Trip
    {
        $trips = $this->trips();

        return $trips === [] ? null : $trips[count($trips) - 1];
    }
}
