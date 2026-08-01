<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Станция или город из подсказок
 */
final readonly class Station extends Model
{
    private function __construct(
        array $raw,
        public ?string $name,
        /** Код станции, он же нужен для поиска поездов */
        public ?string $code,
        /** Идентификатор узла нового сайта, участвует в адресах страниц */
        public ?string $nodeId,
        /**
         * Узел города, которому принадлежит станция
         *
         * У самого города совпадает с nodeId. Поиску с пересадками годится
         * любой из двух, но выдача отличается: город объединяет вокзалы,
         * отдельный вокзал дает больше вариантов от него самого
         */
        public ?string $cityId,
        /** Регион или страна */
        public ?string $region,
        /** Тип узла: Город, Станция. У популярных городов машинное значение, city */
        public ?string $type,
        /** Часовой пояс станции, нужен для расчета местного времени */
        public ?string $timezone,
        /**
         * Коды смежных видов транспорта: Railway, Cbdpr, Bus, Avia
         *
         * @var array<string, string>
         */
        public array $codes,
        /**
         * Коды вокзалов города, если узел является городом
         *
         * @var list<string>
         */
        public array $stationCodes,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $codes = array_filter(self::nested($data, 'Codes'), 'is_string');
        $stations = self::nested($data, 'Stations');

        // Популярные города приходят с теми же полями, но со строчной буквы
        return new self(
            $data,
            self::str($data, 'Name') ?? self::str($data, 'name'),
            isset($codes['Railway']) ? (string) $codes['Railway'] : self::str($data, 'expressCode'),
            self::str($data, 'NodeId') ?? self::str($data, 'nodeId'),
            self::str($data, 'CityId') ?? self::str($data, 'cityId'),
            self::str($data, 'Description'),
            self::str($data, 'SubType') ?? self::str($data, 'nodeType'),
            self::str($data, 'Timezone'),
            $codes,
            array_values(array_filter(array_map(
                static fn(mixed $station): ?string => is_array($station)
                    ? (isset($station['Codes']['Railway']) ? (string) $station['Codes']['Railway'] : null)
                    : null,
                $stations,
            ))),
        );
    }

    /**
     * Узел города, а не отдельного вокзала
     *
     * Определяется по совпадению идентификаторов: у города cityId указывает
     * на него самого. Поле type для этого не годится, оно переводится вместе
     * с языком ответа, а у популярных городов приходит машинным значением
     */
    public function isCity(): bool
    {
        return $this->cityId === null || $this->cityId === $this->nodeId;
    }
}
