<?php

declare(strict_types=1);

/**
 * Маршрут поезда по станциям
 */

require __DIR__ . '/bootstrap.php';

use Rzd\Request\RouteSearch;
use Rzd\Request\TrainSearch;

$client = rzd();

$result = attempt(fn() => $client->trains->search(new TrainSearch(
    origin: '2000000',
    destination: '2064001', // Адлер, маршрут длинный и с переводом времени
    date: new DateTimeImmutable('+7 days'),
)));

if (count($result) === 0) {
    fwrite(STDERR, "Поездов не найдено\n");
    exit(1);
}

$train = $result->trains[0];

$route = attempt(fn() => $client->routes->search(RouteSearch::forTrain($train)));

heading(sprintf('%s, поезд %s: остановок %d', $route->name, $route->trainNumber, count($route)));

foreach ($route as $stop) {
    printf(
        "%s приб %-11s отпр %-11s стоянка %3s мин  %s сутки  МСК %+d\n",
        pad($stop->stationName, 32),
        $stop->arrival?->format('d.m H:i') ?? '',
        $stop->departure?->format('d.m H:i') ?? '',
        $stop->stopDuration ?? '',
        $stop->dayFromStart ?? '',
        $stop->timeZoneDifference ?? 0,
    );
}
