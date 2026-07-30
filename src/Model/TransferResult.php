<?php

declare(strict_types=1);

namespace Rzd\Model;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Результат поиска с пересадками
 *
 * Перебирается напрямую как список вариантов поездки
 *
 * @implements IteratorAggregate<int, TransferRoute>
 */
final readonly class TransferResult extends Model implements IteratorAggregate, Countable
{
    private function __construct(
        array $raw,
        /** @var list<TransferRoute> Варианты поездки, сайт отдает их по возрастанию времени в пути */
        public array $routes,
        /** Идентификатор поиска, сайт просит указывать его в обращениях в поддержку */
        public ?string $requestId,
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            $data,
            self::each($data, 'multi_modal_routes', TransferRoute::class),
            self::str($data, 'request_id'),
        );
    }

    /**
     * @return Traversable<int, TransferRoute>
     */
    public function getIterator(): Traversable
    {
        yield from $this->routes;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Варианты, в которых есть места на всех плечах
     *
     * @return list<TransferRoute>
     */
    public function withSeats(): array
    {
        return array_values(array_filter(
            $this->routes,
            static fn(TransferRoute $route): bool => $route->hasSeats(),
        ));
    }

    /**
     * Самый быстрый вариант
     */
    public function fastest(): ?TransferRoute
    {
        return $this->best(static fn(TransferRoute $route): ?int => $route->duration());
    }

    /**
     * Самый дешевый вариант
     */
    public function cheapest(): ?TransferRoute
    {
        return $this->best(static fn(TransferRoute $route): ?float => $route->minPrice);
    }

    /**
     * @param callable(TransferRoute): (int|float|null) $value
     */
    private function best(callable $value): ?TransferRoute
    {
        $best = null;
        $bestValue = null;

        foreach ($this->routes as $route) {
            $current = $value($route);

            if ($current === null) {
                continue;
            }

            if ($bestValue === null || $current < $bestValue) {
                $best = $route;
                $bestValue = $current;
            }
        }

        return $best;
    }
}
