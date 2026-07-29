<?php

namespace Rzd;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;

class Api
{
    public const ROUTES_LAYER = 5827;
    public const CARRIAGES_LAYER = 5764;
    public const STATIONS_STRUCTURE_ID = 704;

    /**
     * Путь получения маршрутов
     */
    protected string $path = 'https://pass.rzd.ru/timetable/public/';

    /**
     * Путь получения кодов станций
     */
    protected string $suggestionPath = 'https://ticket.rzd.ru/isdk/suggests';

    /**
     * Путь получения станций маршрута
     */
    protected string $stationListPath = 'https://pass.rzd.ru/ticket/services/route/basicRoute';

    private Query $query;
    private string $lang;

    /**
     * Api constructor.
     *
     * @param Config|null $config
     */
    public function __construct(?Config $config = null)
    {
        if (! $config) {
            $config = new Config();
        }

        $this->lang = $config->getLanguage();
        $this->path .= $this->lang;
        $this->query = new Query($config);
    }

    /**
     * Получает маршруты в 1 точку
     *
     * Возвращает данные направления вместе со списком поездов в ключе list
     *
     * @param array $params Массив параметров
     *
     * @return string
     * @throws GuzzleException|JsonException
     */
    public function trainRoutes(array $params): string
    {
        $layer = [
            'layer_id' => static::ROUTES_LAYER,
        ];
        $routes = $this->query->get($this->path, $layer + $params);

        return json_encode($routes->tp[0], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Получает маршруты туда-обратно
     *
     * @param  array $params Массив параметров
     *
     * @return string
     * @throws GuzzleException|JsonException
     */
    public function trainRoutesReturn(array $params): string
    {
        $layer = [
            'layer_id' => static::ROUTES_LAYER,
        ];
        $routes = $this->query->get($this->path, $layer + $params);

        return json_encode([
                'forward' => $routes->tp[0],
                'back'    => $routes->tp[1],
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Получение списка вагонов
     *
     * @param  array $params Массив параметров
     *
     * @return string
     * @throws GuzzleException|JsonException
     */
    public function trainCarriages(array $params): string
    {
        $layer = [
            'layer_id' => static::CARRIAGES_LAYER,
        ];
        $carriages = $this->query->get($this->path, $layer + $params);

        // Данные поезда без списка вагонов, он отдается отдельным ключом
        $train = $carriages->lst[0] ?? null;

        if ($train) {
            $train = clone $train;
            unset($train->cars, $train->functionBlocks);
        }

        return json_encode([
                'train'             => $train,
                'cars'              => $carriages->lst[0]->cars ?? null,
                'functionBlocks'    => $carriages->lst[0]->functionBlocks ?? null,
                'schemes'           => $carriages->schemes ?? null,
                'companies'         => $carriages->insuranceCompany ?? null,
                'companyTypes'      => $carriages->insuranceCompanyTypes ?? null,
                'foodIconTips'      => $carriages->foodIconTips ?? null,
                'psaction'          => $carriages->psaction ?? null,
                'childrenAge'       => $carriages->childrenAge ?? null,
                'motherAndChildAge' => $carriages->motherAndChildAge ?? null,
                'partialPayment'    => $carriages->partialPayment ?? null,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Получение списка станций
     *
     * @param  array $params Массив параметров
     *
     * @return string
     * @throws GuzzleException|JsonException
     */
    public function trainStationList(array $params): string
    {
        $layer = [
            'STRUCTURE_ID' => static::STATIONS_STRUCTURE_ID,
        ];
        $stations = $this->query->get($this->stationListPath, $layer + $params);

        return json_encode([
                'train'  => $stations->data->trainInfo,
                'routes' => $stations->data->routes,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Получение списка кодов станций
     *
     * Вместе с кодом станции отдает коды смежных видов транспорта, включая
     * пригородные перевозки, часовой пояс станции и коды всех вокзалов города
     *
     * @param  array $params Массив параметров
     *
     * @return string
     * @throws GuzzleException|JsonException
     */
    public function stationCode(array $params): string
    {
        $suggest = [
            'GroupResults'  => 'true',
            'TransportType' => 'rail',
            'Language'      => $this->lang,
            'Query'         => $params['Query'] ?? $params['stationNamePart'] ?? '',
        ];

        $suggests = $this->query->get($this->suggestionPath, $suggest, 'GET');
        $stations = [];

        // Город и станция приходят разными узлами с одинаковым кодом
        foreach ($suggests->transport_node_suggests ?? [] as $node) {
            $code = $node->Codes->Railway ?? null;

            if ($code === null || isset($stations[$code])) {
                continue;
            }

            $stations[$code] = [
                'station'  => $node->Name,
                'code'     => $code,
                'region'   => $node->Description ?? null,
                'type'     => $node->SubType ?? null,
                'timezone' => $node->Timezone ?? null,
                'codes'    => $node->Codes ?? null,
                'stations' => array_map(
                    static fn($station) => $station->Codes->Railway ?? null,
                    $node->Stations ?? []
                ),
            ];
        }

        return json_encode(array_values($stations), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
