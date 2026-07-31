<?php

declare(strict_types=1);

namespace Rzd\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Сайт ответил ошибкой
 *
 * Хранит код ответа и тело целиком: текст ошибки приходит в разных полях
 * и не всегда в формате JSON, а для разбора причины полезно видеть исходное
 */
class ApiException extends RuntimeException implements RzdException
{
    /**
     * Конструктор финальный: fromResponse() создает наследников через
     * new static, а это безопасно только при неизменной сигнатуре
     */
    final public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $body,
        private readonly ?string $errorCode = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Собирает исключение из ответа, вытаскивая текст ошибки, если он есть
     *
     * Возвращает static, иначе наследники вроде ForbiddenException теряются
     */
    public static function fromResponse(ResponseInterface $response): static
    {
        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return new static(
                sprintf('Сайт ответил кодом %d', $status),
                $status,
                $body,
            );
        }

        // Текст лежит в Message или Error, регистр первой буквы у сайта плавает
        $message = $decoded['Message']
            ?? $decoded['message']
            ?? $decoded['Error']
            ?? $decoded['error']
            ?? sprintf('Сайт ответил кодом %d', $status);

        return new static(
            is_string($message) ? $message : sprintf('Сайт ответил кодом %d', $status),
            $status,
            $body,
            $decoded['code'] ?? $decoded['Code'] ?? null,
        );
    }

    /**
     * Код ответа HTTP
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Внутренний код ошибки сайта, например INTERNAL_ERROR
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Тело ответа без изменений
     */
    public function body(): string
    {
        return $this->body;
    }
}
