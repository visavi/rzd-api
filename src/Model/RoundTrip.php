<?php

declare(strict_types=1);

namespace Rzd\Model;

/**
 * Поездка туда и обратно
 *
 * Сайт не умеет искать пару маршрутов одним запросом: страница поиска
 * туда-обратно отправляет два независимых запроса, второй с переставленными
 * станциями. Здесь то же самое, но одним вызовом
 */
final readonly class RoundTrip
{
    public function __construct(
        public SearchResult $forward,
        public SearchResult $back,
    ) {
    }

    /**
     * Есть ли места в обе стороны
     */
    public function hasSeats(): bool
    {
        return $this->forward->withSeats() !== [] && $this->back->withSeats() !== [];
    }

    /**
     * Минимальная стоимость поездки в обе стороны
     *
     * Возвращает null, если хотя бы в одну сторону мест нет
     */
    public function minPrice(): ?float
    {
        $forward = $this->forward->cheapest()?->minPrice();
        $back = $this->back->cheapest()?->minPrice();

        if ($forward === null || $back === null) {
            return null;
        }

        return $forward + $back;
    }
}
