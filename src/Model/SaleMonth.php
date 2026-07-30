<?php

declare(strict_types=1);

namespace Rzd\Model;

use DateTimeImmutable;

/**
 * Месяц календаря продажи
 *
 * Показывает, до какой даты вообще открыта продажа по направлению,
 * в отличие от Rzd\Endpoint\Prices::availability, отвечающего на вопрос
 * о наличии мест на конкретные даты
 */
final readonly class SaleMonth extends Model
{
    private function __construct(
        array $raw,
        public ?int $year,
        public ?int $month,
        /**
         * Числа месяца, на которые есть поезда
         *
         * @var list<int>
         */
        public array $availableDays,
        /**
         * Числа месяца, на которые открыта продажа
         *
         * @var list<int>
         */
        public array $saleDays,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::int($data, 'year'),
            self::int($data, 'month'),
            self::days($data, 'availableDays'),
            self::days($data, 'saleDays'),
        );
    }

    /**
     * Даты месяца, на которые открыта продажа
     *
     * @return list<DateTimeImmutable>
     */
    public function dates(): array
    {
        if ($this->year === null || $this->month === null) {
            return [];
        }

        $dates = [];

        foreach ($this->saleDays as $day) {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-n-j',
                sprintf('%d-%d-%d', $this->year, $this->month, $day),
            );

            if ($date instanceof DateTimeImmutable) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * Открыта ли продажа на указанное число месяца
     */
    public function isOnSale(int $day): bool
    {
        return in_array($day, $this->saleDays, true);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<int>
     */
    private static function days(array $data, string $key): array
    {
        $days = $data[$key] ?? [];

        if (! is_array($days)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($days, 'is_numeric')));
    }
}
