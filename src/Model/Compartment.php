<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Купе с номерами свободных мест
 */
final readonly class Compartment extends Model
{
    private function __construct(
        array $raw,
        public ?string $number,
        /** Номера мест как их отдает сайт: 2, 4 или 4М с пометкой пола */
        public ?string $places,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::str($data, 'CompartmentNumber'),
            self::str($data, 'Places'),
        );
    }

    /**
     * Номера мест списком, пометки пола отброшены
     *
     * @return list<int>
     */
    public function placeNumbers(): array
    {
        return Car::parsePlaces($this->places);
    }
}
