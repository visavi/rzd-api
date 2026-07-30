<?php

declare(strict_types=1);

namespace Rzd\Http;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rzd\Config;
use Rzd\Exception\ApiException;
use Rzd\Exception\ForbiddenException;
use Rzd\Exception\MalformedResponseException;
use Rzd\Exception\TransportException;

/**
 * Транспорт запросов к API
 *
 * Собирает запрос, отправляет его переданным PSR-18 клиентом и разбирает
 * ответ. В отличие от прежнего протокола на pass.rzd.ru обмена
 * идентификаторами и куками не требуется: данные приходят с первого запроса
 */
final readonly class Transport
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private Config $config,
    ) {
    }

    /**
     * Выполняет GET и разбирает ответ как JSON
     *
     * @param array<string, scalar|null> $query
     *
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->decode($this->send($this->request('GET', $path, $query)));
    }

    /**
     * Выполняет POST с телом JSON и разбирает ответ как JSON
     *
     * @param array<string, mixed>       $body
     * @param array<string, scalar|null> $query
     *
     * @return array<mixed>
     */
    public function post(string $path, array $body = [], array $query = []): array
    {
        $request = $this->request('POST', $path, $query)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($this->encode($body)));

        return $this->decode($this->send($request));
    }

    /**
     * Выполняет GET и возвращает тело без разбора
     *
     * Нужен для схем вагонов: они отдаются изображением SVG, а не JSON
     */
    public function getRaw(string $path): string
    {
        return (string) $this->send($this->request('GET', $path))->getBody();
    }

    /**
     * Собирает запрос с обязательными заголовками
     *
     * @param array<string, scalar|null> $query
     */
    private function request(string $method, string $path, array $query = []): RequestInterface
    {
        $request = $this->requestFactory
            ->createRequest($method, $this->uri($path, $query))
            ->withHeader('Accept', 'application/json, text/plain, */*')
            ->withHeader('Accept-Language', $this->config->language->value)
            // Поиск с пересадками отвечает 500 без куки языка. Значение сайту
            // безразлично, важно само ее наличие, поэтому берем язык настроек
            ->withHeader('Cookie', 'LANG_SITE=' . $this->config->language->value);

        // Без User-Agent защита сайта отвечает 403 на любой запрос
        if ($this->config->userAgent !== '') {
            $request = $request->withHeader('User-Agent', $this->config->userAgent);
        }

        foreach ($this->config->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * Склеивает адрес запроса
     *
     * Параметры кодируются обязательно: номера поездов содержат кириллицу,
     * с которой сайт иначе отвечает 400
     *
     * @param array<string, scalar|null> $query
     */
    private function uri(string $path, array $query = []): string
    {
        $uri = rtrim($this->config->baseUri, '/') . '/' . ltrim($path, '/');

        $query = array_filter($query, static fn(mixed $value): bool => $value !== null);

        if ($query === []) {
            return $uri;
        }

        $query = array_map(
            static fn(mixed $value): string => match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                default         => (string) $value,
            },
            $query,
        );

        return $uri . '?' . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
    }

    /**
     * Отправляет запрос и проверяет код ответа
     */
    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                sprintf('Не удалось обратиться к %s: %s', $request->getUri(), $e->getMessage()),
                previous: $e,
            );
        }

        $status = $response->getStatusCode();

        if ($status === 403) {
            throw ForbiddenException::fromResponse($response);
        }

        if ($status >= 400) {
            throw ApiException::fromResponse($response);
        }

        return $response;
    }

    /**
     * Разбирает тело ответа
     *
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MalformedResponseException('Ответ сайта не является JSON: ' . $e->getMessage(), $body);
        }

        if (! is_array($decoded)) {
            throw new MalformedResponseException('Ответ сайта не является объектом или массивом', $body);
        }

        return $decoded;
    }

    /**
     * Кодирует тело запроса
     *
     * Пустое тело отправляется объектом, а не массивом: справочники сайт
     * запрашивает как {}. Флаг JSON_FORCE_OBJECT здесь не годится, он
     * превратил бы в объекты и вложенные списки
     *
     * @param array<string, mixed> $body
     */
    private function encode(array $body): string
    {
        if ($body === []) {
            return '{}';
        }

        return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
