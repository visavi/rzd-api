<?php

require __DIR__ . '/bootstrap.php';

$params = [
    'stationNamePart' => 'ЧЕБ',
];

show($api->stationCode($params));
