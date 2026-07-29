<?php

require __DIR__ . '/bootstrap.php';

$date0 = new DateTime('+1 day');

// Получаем актуальный маршрут
$routes = json_decode($api->trainRoutes([
    'dir'        => 0,
    'tfl'        => 3,
    'checkSeats' => 1,
    'code0'      => '2004000',
    'code1'      => '2000000',
    'dt0'        => $date0->format('d.m.Y'),
]), true, 512, JSON_THROW_ON_ERROR)['list'];

if (! $routes) {
    exit('Не удалось найти маршрут' . PHP_EOL);
}

$params = [
    'dir'   => 0,
    'code0' => '2004000',
    'code1' => '2000000',
    'dt0'   => $routes[0]['date0'],
    'time0' => $routes[0]['time0'],
    'tnum0' => $routes[0]['number'],
];

show($api->trainCarriages($params));
