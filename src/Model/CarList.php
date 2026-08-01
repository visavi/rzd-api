<?php

declare(strict_types=1);

namespace Rzd\Model;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Вагоны выбранного поезда
 *
 * Перебирается напрямую как список вагонов
 *
 * @implements IteratorAggregate<int, Car>
 */
final readonly class CarList extends Model implements IteratorAggregate, Countable
{
    private function __construct(
        array $raw,
        /** @var list<Car> */
        public array $cars,
        /** Данные поезда, приходят тем же ответом */
        public ?Train $train,
        public ?string $originCode,
        public ?string $destinationCode,
        /** @var list<string> Типы документов, принимаемых при покупке */
        public array $allowedDocumentTypes,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $train = $data['TrainInfo'] ?? null;

        return new self(
            $data,
            self::each($data, 'Cars', Car::class),
            is_array($train) ? Train::fromArray($train) : null,
            self::str($data, 'OriginCode'),
            self::str($data, 'DestinationCode'),
            self::strings($data, 'AllowedDocumentTypes'),
        );
    }

    /**
     * @return Traversable<int, Car>
     */
    public function getIterator(): Traversable
    {
        yield from $this->cars;
    }

    public function count(): int
    {
        return count($this->cars);
    }

    /**
     * Вагоны, в которых есть свободные места
     *
     * Багажные и служебные вагоны приходят тем же списком с нулем мест
     *
     * @return list<Car>
     */
    public function withSeats(): array
    {
        return array_values(array_filter(
            $this->cars,
            static fn(Car $car): bool => $car->placeNumbers() !== [],
        ));
    }

    /**
     * Самый дешевый вагон из тех, где есть места
     */
    public function cheapest(): ?Car
    {
        return self::least($this->withSeats(), static fn(Car $car): ?float => $car->minPrice);
    }
}
