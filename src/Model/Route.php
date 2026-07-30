<?php

declare(strict_types=1);

namespace Rzd\Model;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Маршрут поезда по станциям
 *
 * Перебирается напрямую как список остановок
 *
 * @implements IteratorAggregate<int, RouteStop>
 */
final readonly class Route extends Model implements IteratorAggregate, Countable
{
    private function __construct(
        array $raw,
        /** Название маршрута, например ОСНОВНОЙ МАРШРУТ */
        public ?string $name,
        public ?string $trainNumber,
        public ?string $originName,
        public ?string $destinationName,
        /** @var list<RouteStop> */
        public array $stops,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::str($data, 'Name'),
            self::str($data, 'TrainNumber'),
            self::str($data, 'OriginName'),
            self::str($data, 'DestinationName'),
            self::each($data, 'RouteStops', RouteStop::class),
        );
    }

    /**
     * @return Traversable<int, RouteStop>
     */
    public function getIterator(): Traversable
    {
        yield from $this->stops;
    }

    public function count(): int
    {
        return count($this->stops);
    }
}
