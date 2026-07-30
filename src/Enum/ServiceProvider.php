<?php

declare(strict_types=1);

namespace Rzd\Enum;

/**
 * Поставщик услуги, уходит в query-параметре service_provider
 *
 * Сайт передает его во все запросы к API. Единственное встреченное
 * значение, оставлено перечислением на случай появления других
 */
enum ServiceProvider: string
{
    case Rzd = 'B2B_RZD';
}
