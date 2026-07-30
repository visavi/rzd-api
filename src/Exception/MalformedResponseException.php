<?php

declare(strict_types=1);

namespace Rzd\Exception;

use RuntimeException;

/**
 * Сайт ответил успешно, но тело не разобрать
 *
 * Признак того, что запрос ушел не туда: вместо API отвечает балансировщик
 * или страница с ошибкой
 */
final class MalformedResponseException extends RuntimeException implements RzdException
{
    public function __construct(string $message, private readonly string $body)
    {
        parent::__construct($message);
    }

    /**
     * Тело ответа без изменений
     */
    public function body(): string
    {
        return $this->body;
    }
}
