<?php

require __DIR__ . '/bootstrap.php';

$date0 = new DateTime('+1 day');

$params = [
    'dir'        => 0,
    'tfl'        => 3,
    'checkSeats' => 1,
    'code0'      => '2030319',
    'code1'      => '2038230',
    'dt0'        => $date0->format('d.m.Y'),
    'md'         => 1,
];

show($api->trainRoutes($params));
