<?php

declare(strict_types=1);

namespace Rzd\Model;

use Countable;
use DateTimeImmutable;
use IteratorAggregate;
use Traversable;

/**
 * Результат поиска поездов
 *
 * Перебирается напрямую как список поездов, при этом хранит данные
 * направления: без них пустой список неотличим от отсутствия мест
 *
 * @implements IteratorAggregate<int, Train>
 */
final readonly class SearchResult extends Model implements IteratorAggregate, Countable
{
    private function __construct(
        array $raw,
        /** @var list<Train> */
        public array $trains,
        public ?string $originCode,
        public ?string $destinationCode,
        public ?string $originName,
        public ?string $destinationName,
        /** Текущее московское время сайта, от него считаются сроки продажи */
        public ?DateTimeImmutable $moscowTime,
        /** Сайт вернул не все поезда направления, стоит сузить период */
        public bool $partial,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::each($data, 'Trains', Train::class),
            self::str($data, 'OriginCode'),
            self::str($data, 'DestinationCode'),
            self::str($data, 'OriginStationName'),
            self::str($data, 'DestinationStationName'),
            self::date($data, 'MoscowDateTime'),
            self::bool($data, 'NotAllTrainsReturned'),
        );
    }

    /**
     * @return Traversable<int, Train>
     */
    public function getIterator(): Traversable
    {
        yield from $this->trains;
    }

    public function count(): int
    {
        return count($this->trains);
    }

    /**
     * Поезда, в которых есть места
     *
     * @return list<Train>
     */
    public function withSeats(): array
    {
        return array_values(array_filter(
            $this->trains,
            static fn(Train $train): bool => $train->freeSeats() > 0,
        ));
    }
}
