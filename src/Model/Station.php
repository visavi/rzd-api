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
        /** Регион или страна */
        public ?string $region,
        /** Тип узла: Город, Станция */
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
        $codes = $data['Codes'] ?? [];
        $codes = is_array($codes) ? array_filter($codes, 'is_string') : [];

        $stations = $data['Stations'] ?? [];
        $stations = is_array($stations) ? $stations : [];

        return new self(
            $data,
            self::str($data, 'Name'),
            isset($codes['Railway']) ? (string) $codes['Railway'] : self::str($data, 'expressCode'),
            self::str($data, 'NodeId') ?? self::str($data, 'nodeId'),
            self::str($data, 'Description'),
            self::str($data, 'SubType'),
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
}
