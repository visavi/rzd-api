<?php

declare(strict_types=1);

namespace Rzd;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rzd\Endpoint\Aeroexpress;
use Rzd\Endpoint\Cars;
use Rzd\Endpoint\Prices;
use Rzd\Endpoint\References;
use Rzd\Endpoint\Routes;
use Rzd\Endpoint\Stations;
use Rzd\Endpoint\Trains;
use Rzd\Http\Transport;

/**
 * Клиент API РЖД
 *
 * Точка входа: разбит по ресурсам, каждый отвечает за свою часть API.
 *
 * HTTP-клиент передается снаружи, поэтому таймаут, прокси, повторы и логи
 * настраиваются там же, где для остальных запросов приложения. Если клиент
 * не передан, он определяется автоматически среди установленных реализаций
 * PSR-18.
 *
 * Сайт принимает запросы только с российских адресов, вне РФ нужен прокси:
 *
 * ```php
 * $client = new Client(httpClient: new GuzzleHttp\Client([
 *     'proxy'   => 'socks5://127.0.0.1:1080',
 *     'timeout' => 30,
 * ]));
 * ```
 */
final readonly class Client
{
    public Trains $trains;
    public Cars $cars;
    public Routes $routes;
    public Stations $stations;
    public Prices $prices;
    public References $references;
    public Aeroexpress $aeroexpress;

    public function __construct(
        public Config $config = new Config(),
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        // Транспорт общий для всех ресурсов и наружу не отдается
        $transport = new Transport(
            $httpClient ?? Psr18ClientDiscovery::find(),
            $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            $this->config,
        );

        $this->trains = new Trains($transport, $this->config);
        $this->cars = new Cars($transport, $this->config);
        $this->routes = new Routes($transport, $this->config);
        $this->stations = new Stations($transport, $this->config);
        $this->prices = new Prices($transport, $this->config);
        $this->references = new References($transport, $this->config);
        $this->aeroexpress = new Aeroexpress($transport, $this->config);
    }
}
