<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Тариф из справочника
 *
 * Полный справочник содержит около полутора сотен тарифов с условиями
 * применения, здесь описано то, что нужно для показа и отбора
 */
final readonly class Tariff extends Model
{
    private function __construct(
        array $raw,
        public ?int $id,
        /** Системное имя: Kupek, Full, Child */
        public ?string $sysName,
        /** Категория пассажира: Adult, Child, Baby */
        public ?string $category,
        public ?string $type,
        /** Название для показа, у части тарифов пустое */
        public ?string $name,
        public ?string $description,
        /** Состояние тарифа: Active, Inactive */
        public ?string $status,
        public bool $nonRefundable,
        public bool $availableForDisabledPersons,
        public bool $promocodeAvailable,
        public bool $bonusCardAvailable,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::int($data, 'Id'),
            self::str($data, 'SysName'),
            self::str($data, 'Category'),
            self::str($data, 'Type'),
            self::str($data, 'TariffNameForSiteRu'),
            self::str($data, 'TariffDescriptionForSiteRu'),
            self::str($data, 'Status'),
            self::bool($data, 'IsNonRefundable'),
            self::bool($data, 'IsAvailableForDisabledPersons'),
            self::bool($data, 'IsPromocodeAvailable'),
            self::bool($data, 'IsRzhdBonusCardAvailable'),
        );
    }

    /**
     * Действующий ли тариф
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }
}
