<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use DateTimeImmutable;
use DateTimeInterface;
use Rzd\Exception\InvalidArgumentException;
use Rzd\Model\PriceDay;
use Rzd\Model\SaleMonth;

/**
 * Календарь цен и наличия мест
 *
 * Прежний протокол на pass.rzd.ru такого не умел: узнать, на какие даты
 * вообще есть поезда, можно было только перебором запросов по дням
 */
final readonly class Prices extends Endpoint
{
    private const AVAILABILITY_PATH = '/api/v1/railway-service/train-availability';
    private const MINIMAL_PRICING_PATH = '/api/v1/railway-service/train-minimal-pricing';
    private const SALE_CALENDAR_PATH = '/apib2b/e/scheduleDirection';

    /**
     * Даты, на которые между станциями есть поезда с местами
     *
     * @return list<DateTimeImmutable>
     */
    public function availability(
        string $origin,
        string $destination,
        DateTimeInterface $from,
        DateTimeInterface $to,
    ): array {
        if ($origin === '' || $destination === '') {
            throw new InvalidArgumentException('Коды станций отправления и прибытия обязательны');
        }

        if ($to < $from) {
            throw new InvalidArgumentException('Конец периода не может быть раньше начала');
        }

        $response = $this->transport->get(self::AVAILABILITY_PATH, [
            'originStationCode'      => $origin,
            'destinationStationCode' => $destination,
            'from'                   => $from->format('Y-m-d'),
            'to'                     => $to->format('Y-m-d'),
        ]);

        $dates = [];

        foreach ($response['AvailabilityItems'] ?? [] as $item) {
            // Сайт отдает дату в обратном порядке: 01-08-2026
            $date = is_array($item) && is_string($item['Date'] ?? null)
                ? DateTimeImmutable::createFromFormat('!d-m-Y', $item['Date'])
                : false;

            if ($date instanceof DateTimeImmutable) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * Календарь продажи по месяцам
     *
     * Отвечает на другой вопрос, чем availability: не где есть места, а до
     * какой даты вообще открыта продажа. Сайт отдает около тринадцати месяцев
     *
     * @return list<SaleMonth>
     */
    public function saleCalendar(string $origin, string $destination, ?DateTimeInterface $from = null): array
    {
        if ($origin === '' || $destination === '') {
            throw new InvalidArgumentException('Коды станций отправления и прибытия обязательны');
        }

        $response = $this->transport->post(self::SALE_CALENDAR_PATH, [
            'OriginCode'      => $origin,
            'DestinationCode' => $destination,
            'DepartureDate'   => ($from ?? new DateTimeImmutable('today'))->format('Y-m-d\T00:00:00'),
        ]);

        return array_values(array_map(
            static fn(array $month): SaleMonth => SaleMonth::fromArray($month),
            array_filter($response, 'is_array'),
        ));
    }

    /**
     * Минимальные цены по датам отправления
     *
     * Сайт отдает разбивку по перевозчикам, поездам и типам вагонов,
     * она доступна через PriceDay::byCarType и PriceDay::raw
     *
     * @return list<PriceDay>
     */
    public function calendar(string $origin, string $destination, DateTimeInterface $from): array
    {
        if ($origin === '' || $destination === '') {
            throw new InvalidArgumentException('Коды станций отправления и прибытия обязательны');
        }

        $response = $this->transport->get(self::MINIMAL_PRICING_PATH, [
            'originCode'      => $origin,
            'destinationCode' => $destination,
            'dateFrom'        => $from->format('Y-m-d'),
        ]);

        return array_values(array_map(
            static fn(array $day): PriceDay => PriceDay::fromArray($day),
            array_filter($response['PriceByDepartureDates'] ?? [], 'is_array'),
        ));
    }
}
