<?php

require __DIR__ . '/bootstrap.php';

// Пример переопределения настроек, bootstrap.php задает их по умолчанию
$config = new Rzd\Config();

// Устанавливаем язык
$config->setLanguage('en');

// Изменяем userAgent
$config->setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 12_1_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 Mobile/15E148 Safari/604.2');

// Изменяем referer
$config->setReferer('https://ticket.rzd.ru/');

// Включаем режим отладки
//$config->setDebugMode(true);

if ($proxy = getenv('RZD_PROXY')) {
    $config->setProxy($proxy);
}

$api = new Rzd\Api($config);

$date0 = new DateTime('+1 day');

$params = [
    'dir'        => 0,
    'tfl'        => 3,
    'checkSeats' => 1,
    'code0'      => '2004000',
    'code1'      => '2000000',
    'dt0'        => $date0->format('d.m.Y'),
];

show($api->trainRoutes($params));
