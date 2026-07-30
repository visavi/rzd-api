<?php

declare(strict_types=1);

/**
 * Поиск кодов станций
 *
 * Код станции нужен для всех остальных запросов
 */

require __DIR__ . '/bootstrap.php';

$client = rzd();

$query = $argv[1] ?? 'ЧЕБ';

$stations = attempt(fn() => $client->stations->suggest($query));

heading(sprintf('Подсказки по запросу «%s»: %d', $query, count($stations)));

foreach ($stations as $station) {
    printf(
        "%s %-9s %s %s %s\n",
        pad($station->name, 24),
        $station->code,
        pad($station->type, 16),
        pad($station->timezone, 20),
        implode(', ', $station->stationCodes),
    );
}

heading('Коды смежных видов транспорта у первой станции');

foreach ($stations[0]->codes as $type => $code) {
    printf("%-10s %s\n", $type, $code);
}

heading('Популярные города');

foreach (array_slice(attempt(fn() => $client->stations->popular()), 0, 10) as $city) {
    printf("%s %-9s %s\n", pad($city->name, 20), $city->code, $city->nodeId);
}
