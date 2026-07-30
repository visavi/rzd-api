<?php

declare(strict_types=1);

namespace Rzd\Tests\Concerns;

use Throwable;

/**
 * Проверка исключений без устаревших методов PHPUnit
 *
 * `expectExceptionMessage()` помечен устаревшим в PHPUnit 13, а его замена
 * `expectExceptionMessageIsOrContains()` появилась только там же. Поскольку
 * библиотека собирается и на PHPUnit 11, использовать нельзя ни то, ни другое
 */
trait AssertsExceptions
{
    /**
     * Проверяет класс исключения и текст сообщения
     *
     * @param class-string<Throwable> $exception
     * @param callable(): mixed       $action
     */
    protected function assertThrows(string $exception, string $message, callable $action): void
    {
        try {
            $action();
        } catch (Throwable $thrown) {
            self::assertInstanceOf($exception, $thrown, sprintf(
                'Ожидалось %s, получено %s: %s',
                $exception,
                $thrown::class,
                $thrown->getMessage(),
            ));

            self::assertStringContainsString($message, $thrown->getMessage());

            return;
        }

        self::fail('Ожидалось исключение ' . $exception . ', но его не было');
    }
}
