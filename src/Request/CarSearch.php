<?php

declare(strict_types=1);

namespace Rzd\Request;

use DateTimeInterface;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\Train;

/**
 * Параметры запроса вагонов поезда
 *
 * Коды станций здесь нужны конкретные, а не города: у поезда это
 * OriginStationCode и DestinationStationCode. Проще всего собрать
 * параметры из найденного поезда через forTrain
 */
final readonly class CarSearch
{
    /**
     * @param string            $origin      Код станции отправления поезда
     * @param string            $destination Код станции прибытия поезда
     * @param string            $trainNumber Номер поезда, например 130Х
     * @param DateTimeInterface $departure   Отправление с точностью до минуты
     * @param string            $provider    Система бронирования из данных поезда
     * @param bool              $onlyBranded Только фирменные вагоны
     * @param bool              $largeFamily Места для многодетных
     */
    public function __construct(
        public string $origin,
        public string $destination,
        public string $trainNumber,
        public DateTimeInterface $departure,
        public string $provider = Defaults::PROVIDER,
        public bool $onlyBranded = false,
        public bool $largeFamily = false,
    ) {
        if ($this->origin === '' || $this->destination === '') {
            throw new InvalidArgumentException('Коды станций отправления и прибытия обязательны');
        }

        if ($this->trainNumber === '') {
            throw new InvalidArgumentException('Номер поезда обязателен');
        }
    }

    /**
     * Собирает параметры из найденного поезда
     *
     * Берет коды станций самого поезда и его систему бронирования, поэтому
     * переносить их вручную из результатов поиска не требуется
     */
    public static function forTrain(Train $train): self
    {
        if ($train->originStationCode === null || $train->destinationStationCode === null) {
            throw new InvalidArgumentException('У поезда нет кодов станций, запрос вагонов невозможен');
        }

        if ($train->number === null || $train->departure === null) {
            throw new InvalidArgumentException('У поезда нет номера или времени отправления');
        }

        return new self(
            $train->originStationCode,
            $train->destinationStationCode,
            $train->number,
            $train->departure,
            $train->provider ?? Defaults::PROVIDER,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toBody(): array
    {
        return [
            'OriginCode'              => $this->origin,
            'DestinationCode'         => $this->destination,
            'TrainNumber'             => $this->trainNumber,
            'DepartureDate'           => $this->departure->format('Y-m-d\TH:i:s'),
            'Provider'                => $this->provider,
            'SpecialPlacesDemand'     => Defaults::SPECIAL_PLACES_DEMAND,
            'CarIssuingType'          => Defaults::CAR_ISSUING_TYPE,
            'OnlyFpkBranded'          => $this->onlyBranded,
            'HasPlacesForLargeFamily' => $this->largeFamily,
        ];
    }
}
