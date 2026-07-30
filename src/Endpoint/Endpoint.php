<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use Rzd\Config;
use Rzd\Http\Transport;

/**
 * Основа ресурсов API
 */
abstract readonly class Endpoint
{
    public function __construct(
        protected Transport $transport,
        protected Config $config,
    ) {
    }

    /**
     * Обязательный для всех запросов параметр поставщика услуги
     *
     * @return array<string, string>
     */
    protected function serviceProvider(): array
    {
        return ['service_provider' => $this->config->serviceProvider->value];
    }
}
