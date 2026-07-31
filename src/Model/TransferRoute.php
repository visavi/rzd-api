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
        return self::last($this->legs)?->destination();
    }

    public function departure(): ?DateTimeImmutable
    {
        return ($this->trips()[0] ?? null)?->departure;
    }

    public function arrival(): ?DateTimeImmutable
    {
        return self::last($this->trips())?->arrival;
    }

    /**
     * Время всей поездки в минутах, включая ожидание на пересадках
     */
    public function duration(): ?int
    {
        return self::minutesBetween($this->departure(), $this->arrival());
    }

    /**
     * Ожидание между рейсами в минутах, по одному числу на пересадку
     *
     * Пересадки, у которых сайт не дал время рейса, пропускаются, поэтому
     * список бывает короче числа пересадок
     *
     * @return list<int>
     */
    public function waits(): array
    {
        $trips = $this->trips();
        $waits = [];

        foreach (array_slice($trips, 1) as $index => $trip) {
            $wait = self::minutesBetween($trips[$index]->arrival, $trip->departure);

            if ($wait !== null) {
                $waits[] = $wait;
            }
        }

        return $waits;
    }

    /**
     * Сколько всего ждать на пересадках, в минутах
     */
    public function waitTotal(): int
    {
        return array_sum($this->waits());
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
}
