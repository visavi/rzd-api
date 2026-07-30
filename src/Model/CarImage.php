<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Фотография вагона
 */
final readonly class CarImage extends Model
{
    private function __construct(
        array $raw,
        public ?int $id,
        public ?string $title,
        /** Путь к уменьшенному изображению */
        public ?string $preview,
        /** Путь к полному изображению */
        public ?string $content,
        /** Порядок показа на сайте */
        public ?int $position,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::int($data, 'RailwayCarImageId'),
            self::str($data, 'TitleRu'),
            self::str($data, 'Preview'),
            self::str($data, 'Content'),
            self::int($data, 'SequenceNumber'),
        );
    }
}
