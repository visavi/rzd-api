<?php

declare(strict_types=1);

namespace Rzd\Exception;

use Throwable;

/**
 * Общий интерфейс всех исключений библиотеки
 *
 * Позволяет отловить любую ошибку клиента одним catch, не перечисляя классы
 */
interface RzdException extends Throwable
{
}
