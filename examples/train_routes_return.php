<?php

require __DIR__ . '/bootstrap.php';

$date0 = new DateTime('+1 day');
$date1 = new DateTime('+5 day');

$params = [
    'dir'        => 1,
    'tfl'        => 3,
    'checkSeats' => 1,
    'code0'      => '2004000',
    'code1'      => '2000000',
    'dt0'        => $date0->format('d.m.Y'),
    'dt1'        => $date1->format('d.m.Y'),
];

show($api->trainRoutesReturn($params));
