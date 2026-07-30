<?php

declare(strict_types=1);

namespace Rzd;

use Rzd\Enum\Language;
use Rzd\Enum\ServiceProvider;

/**
 * Настройки клиента
 *
 * Таймаут, прокси и повторы здесь отсутствуют намеренно: это забота
 * HTTP-клиента, который передается в Rzd\Client. Настраивать их следует
 * в самом клиенте, тогда библиотека не дублирует его возможности
 */
final readonly class Config
{
    /**
     * Запросы без User-Agent отбиваются с 403, поэтому задан браузерный
     */
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'
        . ' AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    public const DEFAULT_BASE_URI = 'https://ticket.rzd.ru';

    public function __construct(
        public Language $language = Language::Russian,
        public string $userAgent = self::DEFAULT_USER_AGENT,
        public ServiceProvider $serviceProvider = ServiceProvider::Rzd,
        public string $baseUri = self::DEFAULT_BASE_URI,
        /**
         * Дополнительные заголовки, уходящие с каждым запросом
         *
         * @var array<string, string>
         */
        public array $headers = [],
    ) {
    }

    /**
     * Копия настроек с другим языком
     */
    public function withLanguage(Language $language): self
    {
        return new self($language, $this->userAgent, $this->serviceProvider, $this->baseUri, $this->headers);
    }

    /**
     * Копия настроек с другим User-Agent
     */
    public function withUserAgent(string $userAgent): self
    {
        return new self($this->language, $userAgent, $this->serviceProvider, $this->baseUri, $this->headers);
    }

    /**
     * Копия настроек с добавленными заголовками
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            $this->language,
            $this->userAgent,
            $this->serviceProvider,
            $this->baseUri,
            [...$this->headers, ...$headers],
        );
    }
}
