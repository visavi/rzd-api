<?php

declare(strict_types=1);

/**
 * Список примеров
 *
 * Из консоли:
 *   php examples/index.php                  список
 *   php examples/index.php search_trains    запустить пример
 *
 * В браузере, со ссылками на примеры:
 *   php -S localhost:8000 -t examples
 *
 * Вне РФ нужен прокси:
 *   RZD_PROXY=socks5://127.0.0.1:1080 php examples/index.php search_trains
 */

$examples = [
    'search_trains'  => 'Поиск поездов Москва — Санкт-Петербург: расписание, цены, типы вагонов',
    'round_trip'     => 'Поиск туда и обратно, стоимость поездки целиком',
    'car_places'     => 'Вагоны поезда и номера свободных мест по купе',
    'car_scheme'     => 'Схема вагона в SVG и фотографии салона',
    'train_route'    => 'Маршрут поезда по станциям с местным временем и часовыми поясами',
    'stations'       => 'Коды станций по части названия и популярные города',
    'price_calendar' => 'Календари: горизонт продажи, наличие мест, минимальные цены',
    'cards'          => 'Карты и абонементы перевозчиков со скидками',
    'aeroexpress'    => 'Тарифы аэроэкспресса и условия их применения',
    'tariffs'        => 'Справочник тарифов и конфигурация сайта',
];

$proxy = getenv('RZD_PROXY');

if (PHP_SAPI !== 'cli') {
    require __DIR__ . '/index_web.php';

    return;
}

$name = $argv[1] ?? null;

if ($name === null) {
    printf("Примеры работы с API РЖД\n\n");

    foreach ($examples as $example => $description) {
        printf("  %-16s %s\n", $example, $description);
    }

    printf("\nЗапуск: php examples/index.php <имя>\n");
    printf("Или напрямую: php examples/<имя>.php\n");

    if (! $proxy) {
        printf("\nСайт принимает запросы только с российских адресов.\n");
        printf("Вне РФ нужен прокси: RZD_PROXY=socks5://127.0.0.1:1080\n");
    }

    exit(0);
}

// Имя приходит из аргументов, поэтому сверяется со списком, а не подставляется в путь
if (! isset($examples[$name])) {
    fwrite(STDERR, sprintf("Нет примера «%s». Список: php examples/index.php\n", $name));

    exit(1);
}

require __DIR__ . '/' . $name . '.php';
