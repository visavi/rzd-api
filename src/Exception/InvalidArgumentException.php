<?php

declare(strict_types=1);

namespace Rzd\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * Некорректные параметры запроса, обнаруженные до обращения к сайту
 */
final class InvalidArgumentException extends BaseInvalidArgumentException implements RzdException
{
}
