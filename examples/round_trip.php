<?php

declare(strict_types=1);

/**
 * Поиск поездов туда и обратно
 *
 * Сайт не умеет искать пару маршрутов одним запросом, метод делает два
 */

require __DIR__ . '/bootstrap.php';

use Rzd\Model\SearchResult;
use Rzd\Request\TrainSearch;

$client = rzd();

$trip = attempt(fn() => $client->trains->searchReturn(
    new TrainSearch(
        origin: '2000000',      // Москва
        destination: '2004000',  // Санкт-Петербург
        date: new DateTimeImmutable('+7 days'),
        adults: 2,
    ),
    new DateTimeImmutable('+14 days'),
));

/**
 * Печатает самые дешёвые поезда одного направления
 */
function leg(string $title, SearchResult $result): void
{
    heading(sprintf('%s: %s → %s, поездов %d', $title, $result->originName, $result->destinationName, count($result)));

    $trains = $result->withSeats();

    usort($trains, static fn(object $a, object $b): int => ($a->minPrice() ?? INF) <=> ($b->minPrice() ?? INF));

    foreach (array_slice($trains, 0, 5) as $train) {
        printf(
            "%-6s %s %s → %s  %3d мест  от %10s\n",
            $train->number,
            pad($train->name ?? $train->description, 22),
            $train->departure?->format('d.m H:i'),
            $train->arrival?->format('d.m H:i'),
            $train->freeSeats(),
            price($train->minPrice()),
        );
    }
}

leg('Туда', $trip->forward);
leg('Обратно', $trip->back);

heading('Итого');

printf("места в обе стороны  %s\n", $trip->hasSeats() ? 'есть' : 'нет');
printf("минимум за поездку   %s\n", price($trip->minPrice()));
