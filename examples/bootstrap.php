<?php

declare(strict_types=1);

/**
 * Общая часть примеров
 *
 * Запуск из корня проекта:
 *   php examples/search_trains.php
 *
 * Сайт принимает запросы только с российских адресов. Вне РФ нужен прокси,
 * он берется из переменной окружения:
 *   RZD_PROXY=socks5://127.0.0.1:1080 php examples/search_trains.php
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Rzd\Client;
use Rzd\Config;
use Rzd\Exception\RzdException;

/**
 * Примеры выводят простой текст, поэтому в браузере он отдается как есть
 *
 * Через встроенный сервер примеры открываются по ссылкам из index.php:
 *   RZD_PROXY=socks5://127.0.0.1:1080 php -S localhost:8000 -t examples
 */
if (PHP_SAPI !== 'cli' && ! headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

/**
 * Готовый клиент с настроенным транспортом
 *
 * Таймаут и прокси задаются в HTTP-клиенте, а не в настройках библиотеки:
 * этим занимается тот клиент, который приложение уже использует
 */
function rzd(): Client
{
    $options = ['timeout' => 30];

    if ($proxy = getenv('RZD_PROXY')) {
        $options['proxy'] = $proxy;
    }

    $factory = new Psr17Factory();

    return new Client(new Config(), new GuzzleClient($options), $factory, $factory);
}

/**
 * Выполняет запрос, печатая понятную ошибку вместо стектрейса
 *
 * @template T
 *
 * @param callable(): T $request
 *
 * @return T
 */
function attempt(callable $request): mixed
{
    try {
        return $request();
    } catch (RzdException $e) {
        fwrite(STDERR, sprintf("Ошибка: %s\n", $e->getMessage()));

        if (! getenv('RZD_PROXY')) {
            fwrite(STDERR, "Возможно нужен прокси: RZD_PROXY=socks5://127.0.0.1:1080\n");
        }

        exit(1);
    }
}

/**
 * Заголовок раздела вывода
 */
function heading(string $title): void
{
    printf("\n%s\n%s\n", $title, str_repeat('-', mb_strlen($title)));
}

/**
 * Время в пути из минут в часы и минуты
 */
function duration(?int $minutes): string
{
    if ($minutes === null) {
        return '-';
    }

    return sprintf('%dч %02dм', intdiv($minutes, 60), $minutes % 60);
}

/**
 * Цена с разделителями разрядов
 */
function price(?float $value): string
{
    return $value === null ? '-' : number_format($value, 2, ',', ' ');
}

/**
 * Дополняет строку пробелами до нужной ширины
 *
 * Функции printf считают байты, поэтому на русских названиях столбцы
 * разъезжаются
 */
function pad(?string $value, int $width): string
{
    $value = mb_strimwidth((string) $value, 0, $width);

    return $value . str_repeat(' ', max(0, $width - mb_strlen($value)));
}
