<?php

declare(strict_types=1);

namespace Rzd\Endpoint;

use Rzd\Config;
use Rzd\Http\Transport;
use Rzd\Model\Model;

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

    /**
     * Разбирает список моделей, пропуская элементы неверного вида
     *
     * @template T of Model
     *
     * @param array<mixed>    $items
     * @param class-string<T> $model
     *
     * @return list<T>
     */
    protected function models(array $items, string $model): array
    {
        return array_values(array_map(
            static fn(array $item): Model => $model::fromArray($item),
            array_filter($items, 'is_array'),
        ));
    }
}
