<?php

declare(strict_types=1);

namespace Rzd\Model;

use Rzd\Enum\SchemeView;

/**
 * Схема вагона
 *
 * Сам чертеж отдается отдельным запросом изображения по идентификатору схемы
 */
final readonly class CarScheme extends Model
{
    private function __construct(
        array $raw,
        public ?int $schemeId,
        /** Подтип вагона, к которому относится схема: 64К, 20К */
        public ?string $subType,
        public ?string $carrier,
        public ?string $carNumber,
        public ?string $serviceClass,
        /** Направление вагона: FromHead, FromTail, Unknown */
        public ?string $direction,
        /**
         * Пути к вариантам схемы, ключ - значение SchemeView
         *
         * Второй этаж заполнен только у двухэтажных вагонов
         *
         * @var array<string, string>
         */
        public array $views,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $views = [];

        foreach (SchemeView::cases() as $view) {
            $path = $data[$view->field()] ?? null;

            if (is_string($path) && $path !== '') {
                $views[$view->value] = $path;
            }
        }

        return new self(
            $data,
            self::int($data, 'SchemeId'),
            self::str($data, 'CarSubType'),
            self::str($data, 'Carrier'),
            self::str($data, 'CarNumber'),
            self::str($data, 'ServiceClass'),
            self::str($data, 'Direction'),
            $views,
        );
    }

    /**
     * Есть ли у схемы такой вариант
     */
    public function has(SchemeView $view): bool
    {
        return isset($this->views[$view->value]);
    }

    /**
     * Двухэтажный ли вагон
     */
    public function isTwoStorey(): bool
    {
        return $this->has(SchemeView::DesktopSecondStorey);
    }
}
