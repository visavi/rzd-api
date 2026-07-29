<?php

namespace Rzd;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class Query
{
    private Client $client;
    private CookieJar $cookieJar;
    private array $headers;

    /**
     * Query constructor.
     *
     * @param Config $config
     */
    public function __construct(public Config $config)
    {
        $options = [
            'timeout'         => $this->config->getTimeout(),
            'proxy'           => $this->config->getProxy(),
            'verify'          => false,
            'allow_redirects' => true,
        ];

        if ($handler = $this->config->getHandler()) {
            $options['handler'] = $handler;
        }

        $this->client = new Client($options);

        $this->headers = [
            'Accept' => 'application/json',
        ];

        if ($userAgent = $this->config->getUserAgent()) {
            $this->headers['User-Agent'] = $userAgent;
        }

        if ($referer = $this->config->getReferer()) {
            $this->headers['Referer'] = $referer;
        }

        $this->cookieJar = new CookieJar();
    }

    /**
     * Получает данные
     *
     * @param string $path   Путь к странице
     * @param array  $params Массив данных если необходимы параметры
     * @param string $method Метод отправки данных
     *
     * @return array|object
     * @throws GuzzleException
     */
    public function get(string $path, array $params = [], string $method = 'POST'): mixed
    {
        return $this->run($path, $params, $method);
    }

    /**
     * Запрашивает данные
     *
     * @param string $path Путь к странице
     * @param array $params Массив параметров
     * @param string $method Тип запроса
     *
     * @return array|object
     * @throws GuzzleException
     * @throws Exception
     */
    protected function run(string $path, array $params, string $method): mixed
    {
        $i = 0;
        do {
            if (isset($rid)) {
                $params['rid'] = $rid;
            }

            $options = [
                'debug'   => $this->config->getDebugMode(),
                'headers' => $this->headers,
                'cookies' => $this->cookieJar,
            ];

            $data = $method === 'GET' ? ['query' => $params] : ['form_params' => $params];
            $response = $this->client->request($method, $path, $data + $options);

            $content = $response->getBody()->getContents();
            $content = json_decode($content, false, 512, JSON_THROW_ON_ERROR);

            $result = $content->result ?? 'OK';

            switch ($result) {
                case 'RID':
                case 'REQUEST_ID':
                    $rid = $this->getRid($content);
                    sleep($this->config->getRetryDelay());
                    break;

                case 'OK':
                    if (isset($content->tp[0]->msgList[0]->message)) {
                        throw new RuntimeException($content->tp[0]->msgList[0]->message);
                    }
                    return $content;

                default:
                    // Ошибки сайта приходят в error, у части ответов - в message
                    throw new RuntimeException(
                        $content->error ?? $content->message ?? 'Failed to get request data!'
                    );
            }

            $i++;
        } while ($i < $this->config->getRetryLimit());

        throw new RuntimeException('Data not received, request id limit exceeded!');
    }

    /**
     * Получает уникальный ключ RID
     *
     * @param object $content
     *
     * @return string
     * @throws Exception
     */
    protected function getRid(object $content): string
    {
        foreach (['rid', 'RID', 'REQUEST_ID'] as $rid) {
            if (isset($content->$rid)) {
                return $content->$rid;
            }
        }

        throw new RuntimeException('Rid not found!');
    }
}
