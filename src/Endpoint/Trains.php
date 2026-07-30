<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use DateTimeInterface;
use Rzd\Model\RoundTrip;
use Rzd\Model\SearchResult;
use Rzd\Request\TrainSearch;

/**
 * Поиск поездов
 */
final readonly class Trains extends Endpoint
{
    private const PATH = '/api/v1/railway-service/prices/train-pricing';

    /**
     * Ищет поезда на дату
     *
     * Возвращает поезда с группами вагонов, ценами и количеством мест.
     * Номера конкретных вагонов и мест отдает Rzd\Endpoint\Cars
     */
    public function search(TrainSearch $request): SearchResult
    {
        return SearchResult::fromArray(
            $this->transport->get(self::PATH, $request->toQuery() + $this->serviceProvider()),
        );
    }

    /**
     * Ищет поезда туда и обратно
     *
     * Выполняет два запроса: сайт не умеет искать пару маршрутов одним, его
     * собственная страница поиска туда-обратно делает то же самое. Второй
     * запрос идет с переставленными станциями и остальными параметрами
     * первого - числом пассажиров, поездами из расписания и прочими
     */
    public function searchReturn(TrainSearch $request, DateTimeInterface $returnDate): RoundTrip
    {
        $back = new TrainSearch(
            origin: $request->destination,
            destination: $request->origin,
            date: $returnDate,
            adults: $request->adults,
            children: $request->children,
            fromSchedule: $request->fromSchedule,
            largeFamily: $request->largeFamily,
            groupCars: $request->groupCars,
        );

        return new RoundTrip($this->search($request), $this->search($back));
    }
}
