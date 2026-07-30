<?php

declare(strict_types=1);

namespace Rzd\Exception;

/**
 * Запрос отбит защитой сайта
 *
 * Штатная причина одна: отсутствие заголовка User-Agent. Клиент подставляет
 * браузерный по умолчанию, так что исключение возможно только при явной
 * его замене на пустое значение либо при блокировке адреса
 */
final class ForbiddenException extends ApiException
{
}
