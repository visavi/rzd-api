<?php

declare(strict_types=1);

namespace Rzd\Exception;

use RuntimeException;

/**
 * Сайт недоступен: таймаут, обрыв соединения, ошибка прокси
 *
 * Сайт принимает запросы только с российских адресов и молча закрывает
 * соединение с остальных, поэтому вне РФ это самая частая ошибка
 */
final class TransportException extends RuntimeException implements RzdException
{
}
