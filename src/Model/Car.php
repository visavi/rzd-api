<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Конкретный вагон поезда с номерами свободных мест
 */
final readonly class Car extends Model
{
    private function __construct(
        array $raw,
        public ?string $number,
        /** Тип вагона в терминах сайта: Compartment, Luxury, Soft, Reserved */
        public ?string $type,
        /** Тип вагона по-русски: КУПЕ, СВ, ЛЮКС, ПЛАЦ */
        public ?string $typeName,
        /** Подтип, определяющий схему: 64К, 20К */
        public ?string $subType,
        public ?string $serviceClass,
        public ?string $serviceClassName,
        /** Свободные места как их отдает сайт: 2, 4, 12М */
        public ?string $freePlaces,
        /** @var list<Compartment> Свободные места, разложенные по купе */
        public array $compartments,
        /**
         * Свободных мест в вагоне
         *
         * У багажных и служебных вагонов бывает заполнено при пустом
         * freePlaces: такие места пассажирам не продаются
         */
        public ?int $places,
        public ?float $minPrice,
        public ?float $maxPrice,
        /** Стоимость сервисных услуг, входит в цену */
        public ?float $serviceCost,
        public ?string $carrier,
        /** Идентификатор схемы, по нему запрашивается изображение вагона */
        public ?int $schemeId,
        /** Название схемы, например 64К с указанием мест */
        public ?string $schemeName,
        /** Нумерация с головы или с хвоста: FromHead, FromTail, Unknown */
        public ?string $numeration,
        /** @var list<string> Услуги вагона */
        public array $services,
        public ?string $description,
        /** Наличие мест: Available, LastPlaces, NotAvailable */
        public ?string $availability,
        public bool $twoStorey,
        public bool $hasImages,
        public bool $hasGenderCabins,
        public bool $hasPlaceNumeration,
        public bool $hasDynamicPricing,
        public bool $hasElectronicRegistration,
        public bool $branded,
        public bool $buffet,
        public bool $saleForbidden,
        public bool $forDisabledPersons,
        public bool $beddingSelectionPossible,
        public bool $mealPossible,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::str($data, 'CarNumber'),
            self::str($data, 'CarType'),
            self::str($data, 'CarTypeName'),
            self::str($data, 'CarSubType'),
            self::str($data, 'ServiceClass'),
            self::str($data, 'ServiceClassNameRu') ?? self::str($data, 'ServiceClassName'),
            self::str($data, 'FreePlaces'),
            self::each($data, 'FreePlacesByCompartments', Compartment::class),
            self::int($data, 'PlaceQuantity'),
            self::float($data, 'MinPrice'),
            self::float($data, 'MaxPrice'),
            self::float($data, 'ServiceCost'),
            self::str($data, 'Carrier'),
            self::int($data, 'RailwayCarSchemeId'),
            self::str($data, 'CarSchemeName'),
            self::str($data, 'CarNumeration'),
            self::strings($data, 'Services'),
            self::str($data, 'CarDescription'),
            self::str($data, 'AvailabilityIndication'),
            self::bool($data, 'IsTwoStorey'),
            self::bool($data, 'HasImages'),
            self::bool($data, 'HasGenderCabins'),
            self::bool($data, 'HasPlaceNumeration'),
            self::bool($data, 'HasDynamicPricing'),
            self::bool($data, 'HasElectronicRegistration'),
            self::bool($data, 'IsBranded'),
            self::bool($data, 'IsBuffet'),
            self::bool($data, 'IsSaleForbidden'),
            self::bool($data, 'IsForDisabledPersons'),
            self::bool($data, 'IsBeddingSelectionPossible'),
            self::bool($data, 'IsMealOptionPossible'),
        );
    }

    /**
     * Номера свободных мест списком
     *
     * @return list<int>
     */
    public function placeNumbers(): array
    {
        return self::parsePlaces($this->freePlaces);
    }

    /**
     * Разбирает строку мест вида "2, 4, 12М"
     *
     * Буква после номера означает пол пассажиров в купе и к номеру места
     * отношения не имеет, поэтому отбрасывается
     *
     * @return list<int>
     */
    public static function parsePlaces(?string $places): array
    {
        if ($places === null || trim($places) === '') {
            return [];
        }

        preg_match_all('/\d+/', $places, $matches);

        return array_values(array_map('intval', $matches[0]));
    }
}
