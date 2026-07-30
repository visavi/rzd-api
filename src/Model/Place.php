<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Точка маршрута в поиске с пересадками
 *
 * Сайт вкладывает станцию в объект по виду точки, а название дает сразу на
 * двух языках: отдельного запроса на перевод не требуется
 */
final readonly class Place extends Model
{
    private function __construct(
        array $raw,
        /** Идентификатор узла станции, тот же, что в подсказках */
        public ?string $key,
        public ?string $name,
        public ?string $nameEn,
        /** Название города станции, приходит не во всех местах ответа */
        public ?string $cityName,
        public ?string $cityKey,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $station = $data['station'] ?? [];
        $station = is_array($station) ? $station : [];

        $city = $data['parent_city'] ?? [];
        $city = is_array($city) ? $city : [];

        return new self(
            $data,
            self::str($station, 'key'),
            self::str($station, 'name_ru'),
            self::str($station, 'name_en'),
            self::str($city, 'name_ru'),
            self::str($city, 'key'),
        );
    }
}
