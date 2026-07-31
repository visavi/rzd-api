<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use Rzd\Exception\MalformedResponseException;
use Rzd\Model\Route;
use Rzd\Request\RouteSearch;

/**
 * Маршрут поезда по станциям
 */
final readonly class Routes extends Endpoint
{
    private const PATH = '/apib2b/p/Railway/V1/Search/TrainRoute';

    /**
     * Основной маршрут поезда
     *
     * У части поездов сайт отдает несколько вариантов маршрута, например
     * с прицепными вагонами. Здесь возвращается первый, остальные доступны
     * через all
     */
    public function forTrain(RouteSearch $request): Route
    {
        $routes = $this->all($request);

        if ($routes === []) {
            throw new MalformedResponseException(
                'Сайт не вернул ни одного маршрута поезда',
                json_encode($request->toQuery(), JSON_UNESCAPED_UNICODE) ?: '',
            );
        }

        return $routes[0];
    }

    /**
     * Все варианты маршрута поезда
     *
     * @return list<Route>
     */
    public function all(RouteSearch $request): array
    {
        $response = $this->transport->get(
            self::PATH,
            $request->toQuery() + ['serviceProvider' => $this->config->serviceProvider->value],
        );

        return $this->models($response['Routes'] ?? [], Route::class);
    }
}
