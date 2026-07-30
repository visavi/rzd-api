<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Группа вагонов одного типа в поезде
 *
 * Приходит в поиске поездов: сколько мест какого типа есть и по какой цене.
 * Номера конкретных вагонов и мест отдает отдельный запрос вагонов
 */
final readonly class CarGroup extends Model
{
    private function __construct(
        array $raw,
        /** Тип вагона в терминах сайта: Compartment, Luxury, Soft, Reserved */
        public ?string $type,
        /** Тип вагона по-русски: КУПЕ, СВ, ЛЮКС, ПЛАЦ */
        public ?string $typeName,
        /** @var list<string> Классы обслуживания, например 1Ф */
        public array $serviceClasses,
        public ?float $minPrice,
        public ?float $maxPrice,
        /**
         * Свободных мест в группе
         *
         * Берется из TotalPlaceQuantity: поле PlaceQuantity в группах
         * приходит нулем и свободные места не отражает, в отличие от
         * одноименного поля у вагона
         */
        public ?int $places,
        /** Нижних мест из числа свободных */
        public ?int $lowerPlaces,
        public ?int $upperPlaces,
        /** Нижних боковых, у плацкартных вагонов */
        public ?int $lowerSidePlaces,
        public ?int $upperSidePlaces,
        /** @var list<string> Перевозчики группы */
        public array $carriers,
        /** @var list<string> Пометки вагонов, например МЖ У0 */
        public array $descriptions,
        /** Наличие мест: Available, LastPlaces, NotAvailable */
        public ?string $availability,
        public bool $saleForbidden,
        public bool $hasPlacesForDisabledPersons,
        public bool $hasPlacesNearPets,
        public bool $hasPlacesNearBabies,
        public bool $hasPlacesNearPlayground,
        public bool $hasGenderCabins,
        public bool $hasNonRefundableTariff,
        public bool $hasElectronicRegistration,
        public bool $mealPossible,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::str($data, 'CarType'),
            self::str($data, 'CarTypeName'),
            self::strings($data, 'ServiceClasses'),
            self::float($data, 'MinPrice'),
            self::float($data, 'MaxPrice'),
            self::int($data, 'TotalPlaceQuantity'),
            self::int($data, 'LowerPlaceQuantity'),
            self::int($data, 'UpperPlaceQuantity'),
            self::int($data, 'LowerSidePlaceQuantity'),
            self::int($data, 'UpperSidePlaceQuantity'),
            self::strings($data, 'Carriers'),
            self::strings($data, 'CarDescriptions'),
            self::str($data, 'AvailabilityIndication'),
            self::bool($data, 'IsSaleForbidden'),
            self::bool($data, 'HasPlacesForDisabledPersons'),
            self::bool($data, 'HasPlacesNearPets'),
            self::bool($data, 'HasPlacesNearBabies'),
            self::bool($data, 'HasPlacesNearPlayground'),
            self::bool($data, 'HasGenderCabins'),
            self::bool($data, 'HasNonRefundableTariff'),
            self::bool($data, 'HasElectronicRegistration'),
            self::bool($data, 'IsMealOptionPossible'),
        );
    }
}
