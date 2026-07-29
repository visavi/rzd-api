<?php

namespace Rzd;

class Config
{
    /**
     * Запросы без User-Agent отбиваются с 403, поэтому задан браузерный по умолчанию
     */
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private bool $debug = false;
    private string $language = 'ru';
    private float $timeout = 5.0;
    private int $retryDelay = 1;
    private int $retryLimit = 10;
    private ?string $proxy = null;
    private ?string $userAgent = self::DEFAULT_USER_AGENT;
    private ?string $referer = null;
    private $handler = null;

    /**
     * Set Language
     *
     * @param string $language
     */
    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    /**
     * Get Language
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Set debug mode
     *
     * @param bool $debug
     */
    public function setDebugMode(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Get debug mode
     *
     * @return bool
     */
    public function getDebugMode(): bool
    {
        return $this->debug;
    }

    /**
     * Get timeout
     *
     * @return float
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Set timeout
     *
     * @param float $timeout
     */
    public function setTimeout(float $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * Set retry delay
     *
     * Пауза в секундах перед повторным запросом с полученным идентификатором
     *
     * @param int $retryDelay
     */
    public function setRetryDelay(int $retryDelay): void
    {
        $this->retryDelay = $retryDelay;
    }

    /**
     * Get retry delay
     *
     * @return int
     */
    public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }

    /**
     * Set retry limit
     *
     * Сколько раз повторять запрос, пока сайт отдает идентификатор вместо данных
     *
     * @param int $retryLimit
     */
    public function setRetryLimit(int $retryLimit): void
    {
        $this->retryLimit = $retryLimit;
    }

    /**
     * Get retry limit
     *
     * @return int
     */
    public function getRetryLimit(): int
    {
        return $this->retryLimit;
    }

    /**
     * Set Proxy
     *
     * @param string $proxy
     */
    public function setProxy(string $proxy): void
    {
        $this->proxy = $proxy;
    }

    /**
     * Get Proxy
     *
     * @return string|null
     */
    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    /**
     * Set User Agent
     *
     * @param string $userAgent
     */
    public function setUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * Get User Agent
     *
     * @return string|null
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * Set Referer
     *
     * @param string $referer
     */
    public function setReferer(string $referer): void
    {
        $this->referer = $referer;
    }

    /**
     * Set Referer
     *
     * @return string|null
     */
    public function getReferer(): ?string
    {
        return $this->referer;
    }

    /**
     * Set Handler
     *
     * Позволяет подменить транспорт Guzzle, например на MockHandler в тестах
     *
     * @param callable|null $handler
     */
    public function setHandler(?callable $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Get Handler
     *
     * @return callable|null
     */
    public function getHandler(): ?callable
    {
        return $this->handler;
    }
}
