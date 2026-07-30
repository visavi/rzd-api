<?php

declare(strict_types=1);

/**
 * Вагоны выбранного поезда с номерами свободных мест
 *
 * Запрос вагонов собирается из найденного поезда: коды станций и систему
 * бронирования переносить вручную не нужно
 */

require __DIR__ . '/bootstrap.php';

use Rzd\Request\CarSearch;
use Rzd\Request\TrainSearch;

$client = rzd();

$result = attempt(fn() => $client->trains->search(new TrainSearch(
    origin: '2000000',
    destination: '2004000',
    date: new DateTimeImmutable('+7 days'),
)));

$trains = $result->withSeats();

if ($trains === []) {
    fwrite(STDERR, "На эту дату нет поездов со свободными местами\n");
    exit(1);
}

$train = $trains[0];

$cars = attempt(fn() => $client->cars->search(CarSearch::forTrain($train)));

heading(sprintf('Поезд %s, отправление %s', $train->number, $train->departure?->format('d.m.Y H:i')));

foreach ($cars as $car) {
    printf(
        "вагон %-3s %s %-4s мест %2d  %10s — %-10s  места: %s\n",
        $car->number,
        pad($car->typeName, 8),
        $car->serviceClass ?? '',
        $car->places ?? 0,
        price($car->minPrice),
        price($car->maxPrice),
        $car->freePlaces ?? 'нет',
    );
}

heading('Свободные места по купе в первом вагоне');

$first = $cars->withSeats()[0] ?? null;

if ($first === null) {
    exit(0);
}

foreach ($first->compartments as $compartment) {
    printf("купе %-3s места %s\n", $compartment->number, implode(', ', $compartment->placeNumbers()));
}
