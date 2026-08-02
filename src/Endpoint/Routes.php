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
    public function search(RouteSearch $request): Route
    {
        $response = $this->fetch($request);
        $routes = $this->routes($response);

        if ($routes === []) {
            throw new MalformedResponseException(
                'Сайт не вернул ни одного маршрута поезда',
                json_encode($response, JSON_UNESCAPED_UNICODE) ?: '',
            );
        }

        return $routes[0];
    }

    /**
     * Основной маршрут поезда
     *
     * @deprecated 6.1 Имя совпадало с фабрикой запроса, отчего вызов выглядел
     *             как routes->forTrain(RouteSearch::forTrain($train)).
     *             Используйте search(), метод будет удален в 7.0
     */
    public function forTrain(RouteSearch $request): Route
    {
        return $this->search($request);
    }

    /**
     * Все варианты маршрута поезда
     *
     * @return list<Route>
     */
    public function all(RouteSearch $request): array
    {
        return $this->routes($this->fetch($request));
    }

    /**
     * Ответ сайта целиком, он же попадает в исключение при пустом маршруте
     *
     * @return array<mixed>
     */
    private function fetch(RouteSearch $request): array
    {
        // Поставщик услуги здесь в другом написании, чем в остальных запросах
        return $this->transport->get(
            self::PATH,
            $request->toQuery() + ['serviceProvider' => $this->config->serviceProvider->value],
        );
    }

    /**
     * @param array<mixed> $response
     *
     * @return list<Route>
     */
    private function routes(array $response): array
    {
        return $this->models($response['Routes'] ?? [], Route::class);
    }
}
