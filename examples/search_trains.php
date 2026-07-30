<?php

declare(strict_types=1);

/**
 * Поиск поездов с ценами и количеством мест
 */

require __DIR__ . '/bootstrap.php';

use Rzd\Request\TrainSearch;

$client = rzd();

$search = new TrainSearch(
    origin: '2000000',      // Москва
    destination: '2004000',  // Санкт-Петербург
    date: new DateTimeImmutable('+7 days'),
    adults: 1,
);

$result = attempt(fn() => $client->trains->search($search));

heading(sprintf('%s → %s, найдено поездов: %d', $result->originName, $result->destinationName, count($result)));

foreach ($result as $train) {
    printf(
        "%-6s %s %s → %s  %8s  %3d мест  от %10s\n",
        $train->number,
        pad($train->name ?? $train->description, 26),
        $train->departure?->format('d.m H:i'),
        $train->arrival?->format('d.m H:i'),
        duration($train->duration),
        $train->freeSeats(),
        price($train->minPrice()),
    );
}

heading('Типы вагонов в первом поезде');

foreach ($result->trains[0]->carGroups as $group) {
    printf(
        "%s %-6s мест %3d (низ %d, верх %d)  %10s — %s\n",
        pad($group->typeName, 8),
        implode(',', $group->serviceClasses),
        $group->places ?? 0,
        $group->lowerPlaces ?? 0,
        $group->upperPlaces ?? 0,
        price($group->minPrice),
        price($group->maxPrice),
    );
}
